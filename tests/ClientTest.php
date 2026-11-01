<?php
declare(strict_types=1);

namespace BAG\Spaces\Tests;

use BAG\Spaces\Client;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
}
