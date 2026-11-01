<?php
declare(strict_types=1);

namespace BAG\Spaces\Tests;

use BAG\Spaces\Client;
use BAG\Spaces\Space;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class SpaceTest extends TestCase
{
    public function testListPageDelegatesSpaceAndCursor(): void
    {
        $page = [
            'items' => ['one.txt'],
            'isTruncated' => true,
            'nextCursor' => 'one.txt',
        ];
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('listPage')
            ->with('assets', true, ['MaxKeys' => 1], 'previous.txt')
            ->willReturn($page);

        $space = $this->spaceWithClient($client);

        self::assertSame($page, $space->listPage(true, ['MaxKeys' => 1], 'previous.txt'));
    }

    public function testDeleteReturnsClientResult(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('delete')
            ->with('assets', 'images/logo.png')
            ->willReturn(true);

        $space = $this->spaceWithClient($client);

        self::assertTrue($space->delete('images/logo.png'));
    }

    public function testPresignedUploadPassesDurationAndContentTypeInCorrectOrder(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('presignedUpload')
            ->with('assets', 'images/logo.png', '+30 minutes', 'image/png')
            ->willReturn('https://example.test/upload');

        $space = $this->spaceWithClient($client);

        self::assertSame(
            'https://example.test/upload',
            $space->presignedUpload('images/logo.png', 'image/png', '+30 minutes')
        );
    }

    private function spaceWithClient(Client $client): Space
    {
        $space = (new ReflectionClass(Space::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty(Space::class, 'space'))->setValue($space, 'assets');
        (new ReflectionProperty(Space::class, 'client'))->setValue($space, $client);

        return $space;
    }
}
