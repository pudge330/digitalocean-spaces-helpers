<?php
declare(strict_types=1);

namespace BAG\Spaces\Tests;

use BAG\Spaces\Authorization;
use BAG\Spaces\Client;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = (new ReflectionClass(Client::class))->newInstanceWithoutConstructor();
    }

    public function testBaseKey(): void
    {
        self::assertSame('folder/nested', $this->client->baseKey('/folder/nested/file.txt/'));
        self::assertSame('', $this->client->baseKey('file.txt'));
    }

    public function testVerifyRequestAddsMissingDefault(): void
    {
        $request = [];

        $this->client->verifyRequest($request, 'Contents');

        self::assertSame(['Contents' => []], $request);
    }

    public function testVerifyRequestPreservesExistingValue(): void
    {
        $request = ['Contents' => ['object']];

        $this->client->verifyRequest($request, 'Contents');

        self::assertSame(['Contents' => ['object']], $request);
    }

    public function testCorsApiDeclaresDistinctGetSetAndDeleteMethods(): void
    {
        self::assertTrue(method_exists(Client::class, 'getCors'));
        self::assertTrue(method_exists(Client::class, 'setCors'));
        self::assertTrue(method_exists(Client::class, 'deleteCors'));

        $corsMethods = array_values(array_filter(
            get_class_methods(Client::class),
            static fn (string $method): bool => str_ends_with(strtolower($method), 'cors')
        ));

        sort($corsMethods, SORT_STRING);

        self::assertSame(['deleteCors', 'getCors', 'setCors'], $corsMethods);
    }

    public function testSetAclEncodesEachKeySegmentForSigningAndRequest(): void
    {
        $expectedUrl = 'https://assets.nyc3.digitaloceanspaces.com/folder/file%20name%2B%23%3F.txt?acl';
        $authorization = $this->createMock(Authorization::class);
        $authorization->expects(self::once())
            ->method('create')
            ->with([
                'method' => 'PUT',
                'url' => $expectedUrl,
                'region' => 'nyc3',
                'headers' => ['x-amz-acl' => Client::ACL_PUBLIC],
            ])
            ->willReturn([['x-amz-acl' => Client::ACL_PUBLIC], '']);

        $request = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$request): MockResponse {
                $request = compact('method', 'url', 'options');

                return new MockResponse('', ['http_code' => 204]);
            }
        );
        $client = $this->clientForAcl($authorization, $httpClient);

        self::assertTrue($client->setAcl(
            'assets',
            'folder/file name+#?.txt',
            Client::ACL_PUBLIC
        ));
        self::assertSame('PUT', $request['method']);
        self::assertSame($expectedUrl, $request['url']);
        self::assertContains('x-amz-acl: ' . Client::ACL_PUBLIC, $request['options']['headers']);
    }

    public function testSetAclThrowsUsefulExceptionForNonSuccessfulResponse(): void
    {
        $authorization = $this->createStub(Authorization::class);
        $authorization->method('create')->willReturn([['x-amz-acl' => Client::ACL_PRIVATE], '']);
        $httpClient = new MockHttpClient(new MockResponse(
            '<Error><Message>Access denied</Message></Error>',
            ['http_code' => 403]
        ));
        $client = $this->clientForAcl($authorization, $httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Spaces setAcl request failed with HTTP 403: <Error><Message>Access denied</Message></Error>'
        );

        $client->setAcl('assets', 'private.txt', 'unsupported-acl');
    }

    private function clientForAcl(
        Authorization $authorization,
        MockHttpClient $httpClient
    ): Client {
        $client = (new ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Client::class, 'region'))->setValue($client, 'nyc3');
        (new ReflectionProperty(Client::class, 'authorization'))->setValue($client, $authorization);
        (new ReflectionProperty(Client::class, 'httpClient'))->setValue($client, $httpClient);

        return $client;
    }
}
