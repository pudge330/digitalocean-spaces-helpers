<?php
declare(strict_types=1);

namespace BAG\Spaces\Tests;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use BAG\Spaces\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class ClientPaginationTest extends TestCase
{
    public function testListPageReturnsItemsAndCursor(): void
    {
        $s3 = new FakeS3Client([
            new Result([
                'Contents' => [
                    ['Key' => 'one.txt'],
                    ['Key' => 'two.txt'],
                ],
                'IsTruncated' => true,
                'NextMarker' => 'two.txt',
            ]),
        ]);
        $client = $this->clientWithS3($s3);

        $page = $client->listPage('assets', true, ['MaxKeys' => 2]);

        self::assertSame([
            'items' => ['one.txt', 'two.txt'],
            'isTruncated' => true,
            'nextCursor' => 'two.txt',
        ], $page);
        self::assertSame('assets', $s3->listObjectRequests[0]['Bucket']);
        self::assertSame(2, $s3->listObjectRequests[0]['MaxKeys']);
        self::assertArrayNotHasKey('Marker', $s3->listObjectRequests[0]);
    }

    public function testListPageFallsBackToLastKeyWhenNextMarkerIsMissing(): void
    {
        $s3 = new FakeS3Client([
            new Result([
                'Contents' => [
                    ['Key' => '0'],
                ],
                'IsTruncated' => true,
            ]),
        ]);
        $client = $this->clientWithS3($s3);

        $page = $client->listPage('assets', true);

        self::assertSame('0', $page['nextCursor']);
    }

    public function testIteratorTraversesPagesWithLegacyMarker(): void
    {
        $s3 = new FakeS3Client([
            new Result([
                'Contents' => [
                    ['Key' => 'one.txt'],
                    ['Key' => 'two.txt'],
                ],
                'IsTruncated' => true,
                'NextMarker' => 'two.txt',
            ]),
            new Result([
                'Contents' => [
                    ['Key' => 'three.txt'],
                ],
                'IsTruncated' => false,
            ]),
        ]);
        $client = $this->clientWithS3($s3);

        $items = iterator_to_array($client->iterate('assets', true, ['MaxKeys' => 2]));

        self::assertSame(['one.txt', 'two.txt', 'three.txt'], $items);
        self::assertSame('two.txt', $s3->listObjectRequests[1]['Marker']);
    }

    public function testIteratorRejectsRepeatedCursor(): void
    {
        $s3 = new FakeS3Client([
            new Result([
                'Contents' => [['Key' => 'one.txt']],
                'IsTruncated' => true,
                'NextMarker' => 'one.txt',
            ]),
            new Result([
                'Contents' => [],
                'IsTruncated' => true,
                'NextMarker' => 'one.txt',
            ]),
        ]);
        $client = $this->clientWithS3($s3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeated continuation marker');

        iterator_to_array($client->iterate('assets', true));
    }

    public function testFolderPageHandlesZeroPrefix(): void
    {
        $s3 = new FakeS3Client([
            new Result([
                'CommonPrefixes' => [
                    ['Prefix' => '0/child/'],
                ],
                'IsTruncated' => false,
            ]),
        ]);
        $client = $this->clientWithS3($s3);

        $page = $client->listFoldersPage('assets', '0');

        self::assertSame(['child'], $page['items']);
        self::assertSame('0/', $s3->listObjectRequests[0]['Prefix']);
        self::assertSame('/', $s3->listObjectRequests[0]['Delimiter']);
    }

    public function testGetUsesHeadObjectAndPreservesCompatibilityFields(): void
    {
        $s3 = new FakeS3Client([], new Result([
            'ContentLength' => 42,
            'ContentType' => 'text/plain',
            'ETag' => 'etag',
        ]));
        $client = $this->clientWithS3($s3);

        $metadata = $client->get('assets', 'folder/file.txt');

        self::assertSame('folder/file.txt', $metadata['Key']);
        self::assertSame(42, $metadata['Size']);
        self::assertSame('text/plain', $metadata['ContentType']);
        self::assertSame([
            'Bucket' => 'assets',
            'Key' => 'folder/file.txt',
        ], $s3->headObjectRequests[0]);
        self::assertSame([], $s3->listObjectRequests);
    }

    public function testGetReturnsNullForMissingExactKey(): void
    {
        $s3 = new FakeS3Client([], new AwsException(
            'Not found',
            new Command('HeadObject'),
            ['response' => new Response(404), 'code' => 'NotFound']
        ));
        $client = $this->clientWithS3($s3);

        self::assertNull($client->get('assets', 'folder/missing.txt'));
        self::assertSame('folder/missing.txt', $s3->headObjectRequests[0]['Key']);
    }

    public function testGetReturnsFalseForNonMissingRequestFailure(): void
    {
        $s3 = new FakeS3Client([], new AwsException(
            'Forbidden',
            new Command('HeadObject'),
            ['response' => new Response(403), 'code' => 'AccessDenied']
        ));
        $client = $this->clientWithS3($s3);

        self::assertFalse($client->get('assets', 'folder/private.txt'));
    }

    private function clientWithS3(FakeS3Client $s3): Client
    {
        $client = (new ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Client::class, 'client'))->setValue($client, $s3);

        return $client;
    }
}

final class FakeS3Client extends S3Client
{
    /** @var list<Result> */
    private array $listObjectResults;

    /** @var list<array> */
    public array $listObjectRequests = [];

    /** @var list<array> */
    public array $headObjectRequests = [];

    public function __construct(
        array $listObjectResults = [],
        private Result|Throwable|null $headObjectResult = null
    ) {
        $this->listObjectResults = $listObjectResults;
    }

    public function listObjects(array $arguments = []): Result
    {
        $this->listObjectRequests[] = $arguments;
        $result = array_shift($this->listObjectResults);
        if (!$result instanceof Result) {
            throw new RuntimeException('No fake ListObjects result was queued.');
        }

        return $result;
    }

    public function headObject(array $arguments = []): Result
    {
        $this->headObjectRequests[] = $arguments;
        if ($this->headObjectResult instanceof Throwable) {
            throw $this->headObjectResult;
        }
        if (!$this->headObjectResult instanceof Result) {
            throw new RuntimeException('No fake HeadObject result was configured.');
        }

        return $this->headObjectResult;
    }
}
