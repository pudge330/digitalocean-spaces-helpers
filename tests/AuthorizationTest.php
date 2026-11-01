<?php
declare(strict_types=1);

namespace BAG\Spaces\Tests;

use BAG\Spaces\Authorization;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    public function testCanonicalQuerySortingAndEqualsSigns(): void
    {
        $authorization = new TestableAuthorization('key', 'secret');

        self::assertSame(
            '0=zero&A=first&A=upper&a=lower&empty=&token=one=two&z=last',
            $authorization->query('z=last&A=upper&a=lower&0=zero&A=first&empty&token=one=two')
        );
    }

    public function testUrlProcessingAddsSchemeAndDefaults(): void
    {
        $authorization = new TestableAuthorization('key', 'secret');

        self::assertSame(
            [
                'host' => 'nyc3.digitaloceanspaces.com',
                'path' => '/',
                'query' => '',
            ],
            $authorization->url('nyc3.digitaloceanspaces.com')
        );
    }

    public function testInvalidUrlIsRejected(): void
    {
        $authorization = new TestableAuthorization('key', 'secret');

        $this->expectException(InvalidArgumentException::class);
        $authorization->url('http://');
    }

    public function testAuthorizationHeadersAreCreated(): void
    {
        $authorization = new Authorization('key', 'secret');

        [$headers, $headerLines] = $authorization->create([
            'method' => 'GET',
            'region' => 'nyc3',
            'url' => 'https://example.nyc3.digitaloceanspaces.com/file.txt',
        ]);

        self::assertSame('example.nyc3.digitaloceanspaces.com', $headers['Host']);
        self::assertSame(hash('sha256', ''), $headers['x-amz-content-sha256']);
        self::assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $headers['x-amz-date']);
        self::assertStringStartsWith('AWS4-HMAC-SHA256 Credential=key/', $headers['Authorization']);
        self::assertStringContainsString('Authorization: AWS4-HMAC-SHA256', $headerLines);
    }
}

final class TestableAuthorization extends Authorization
{
    public function query(string $query): string
    {
        return $this->processQuery($query);
    }

    public function url(string $url): array
    {
        return $this->processUrl($url);
    }
}
