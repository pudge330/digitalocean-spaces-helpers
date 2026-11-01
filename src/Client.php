<?php
declare(strict_types=1);

/**
 * This file is part of the DigitalOcean Spaces Helpers package.
 *
 * (c) Brandon Garcia <public@bgarcia.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package DigitalOcean Spaces Helpers
 * @author Brandon Garcia <public@bgarcia.dev>
 */
namespace BAG\Spaces;

use ArrayAccess;
use Exception;
use Generator;
use RuntimeException;
use SimpleXMLElement;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\S3\Exception\S3MultipartUploadException;
use Symfony\Component\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DigitalOcean Spaces helper.
 * Utilizes Amazon S3 PHP SDK.
 * 
 * Documentation: https://docs.digitalocean.com/reference/api/spaces-api/
 * Documentation: https://docs.aws.amazon.com/aws-sdk-php/v3/api/
 */
class Client {
	public const string ACL_PRIVATE = 'private';
	public const string ACL_PUBLIC = 'public-read';

	/**
	 * @var string Spaces api key
	 */
	protected string $key;

	/**
	 * @var string Spaces api secret
	 */
	protected string $secret;

	/**
	 * @var string Spaces endpoint url
	 */
	protected string $endpoint;

	/**
	 * @var string Region name
	 */
	protected string $region;

	/**
	 * @var S3Client S3 client class
	 */
	protected S3Client $client;

	/**
	 * @var HttpClientInterface Http client object
	 */
	protected HttpClientInterface $httpClient;

	/**
	 * @var Authorization Authorization header class
	 */
	protected Authorization $authorization;

	/**
	 * @param string $key      Spaces api key
	 * @param string $secret   Spaces api secret
	 * @param string $endpoint Spaces region
	 */
	public function __construct(string $key, string $secret, string $region) {
		$this->key = $key;
		$this->secret = $secret;
		$this->region = $region;
		$this->endpoint = "https://{$this->region}.digitaloceanspaces.com";
		$this->client = self::createClient($key, $secret, $this->endpoint);
		$this->httpClient = HttpClient\HttpClient::create([
			'headers' => []
		]);
		$this->authorization = new Authorization($this->key, $this->secret);
	}

	/**
	 * Create space.
	 * 
	 * @param string $name Name of space
	 * 
	 * @return bool
	 */
	public function createSpace(string $name): bool {
		$spaces = $this->listSpaces(true);
		if ($spaces === false)
			return false;
		if (!in_array($name, $spaces)) {
			try {
				$this->client->createBucket([
					'Bucket' => $name
				]);
				return true;
			}
			catch (Exception $e) {
				return false;
			}
		}
		return false;
	}

	/**
	 * Delete space.
	 * 
	 * @param string $space Name of space
	 * 
	 * @return bool
	 */
	public function deleteSpace(string $space): bool {
		try {
			$this->client->deleteBucket([
				'Bucket' => $space,
			]);
			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Get list of spaces.
	 * 
	 * @param bool $names Return names only
	 * 
	 * @param array|false
	 */
	public function listSpaces(bool $names = false): array|false {
		try {
			$result = $this->client->listBuckets();
		}
		catch (Exception $e) {
			return false;
		}
		$this->verifyRequest($result, 'Buckets');
		if (!$names) {
			return $result['Buckets'];
		}
		$names = [];
		array_walk($result['Buckets'], function($bucket) use (&$names) {
			$names[] = $bucket['Name'];
		});
		return $names;
	}

	/**
	 * Check if space exists.
	 * 
	 * @param string $space Name of space
	 * 
	 * @return bool
	 */
	public function spaceExists(string $space): bool {
		$spaces = $this->listSpaces(true);
		if ($spaces === false)
			return false;
		return in_array($space, $spaces);
	}

	/**
	 * Get list of objects.
	 * 
	 * @param string $space    Name of space
	 * @param bool $keys       Return keys only
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false
	 */
	public function list(string $space, bool $keys = false, array $arguments = []): array|false {
		$arguments = array_merge($arguments, [
			'Bucket' => $space
		]);
		try {
			$result = $this->client->listObjectsV2($arguments);
		}
		catch (Exception $e) {
			return false;
		}
		$this->verifyRequest($result, 'Contents');
		if (!$keys) {
			return $result['Contents'];
		}
		$keys = [];
		array_walk($result['Contents'], function($object, $index) use (&$keys) {
			$keys[] = $object['Key'];
		});
		return $keys;
	}

	/**
	 * Get one page of objects using the legacy ListObjects operation.
	 *
	 * DigitalOcean Spaces documents ListObjectsV2 pagination as unsupported,
	 * so cursor-based traversal uses the supported legacy Marker mechanism.
	 *
	 * @param string $space       Name of space
	 * @param bool $keys          Return keys only
	 * @param array $arguments    Optional. Additional API arguments
	 * @param string|null $cursor Optional marker returned by the previous page
	 *
	 * @return array{items: array, isTruncated: bool, nextCursor: string|null}|false
	 */
	public function listPage(
		string $space,
		bool $keys = false,
		array $arguments = [],
		?string $cursor = null
	): array|false {
		$page = $this->requestListPage($space, $arguments, $cursor);
		if ($page === false) {
			return false;
		}

		$items = $page['Contents'];
		if ($keys) {
			$items = array_map(
				static fn (array $object): string => $object['Key'],
				$items
			);
		}

		return [
			'items' => $items,
			'isTruncated' => $page['IsTruncated'],
			'nextCursor' => $page['NextMarker'],
		];
	}

	/**
	 * Lazily iterate through objects using bounded legacy-marker pagination.
	 *
	 * @param string $space      Name of space
	 * @param bool $keys         Return keys only
	 * @param array $arguments   Optional. Additional API arguments
	 * @param int|null $maxItems Optional maximum number of yielded items
	 * @param int $maxPages      Safety limit for remote page requests
	 *
	 * @return Generator<int, array|string>
	 */
	public function iterate(
		string $space,
		bool $keys = false,
		array $arguments = [],
		?int $maxItems = null,
		int $maxPages = 1000
	): Generator {
		$this->validateIterationLimits($maxItems, $maxPages);
		if ($maxItems === 0) {
			return;
		}

		$cursor = isset($arguments['Marker']) ? (string) $arguments['Marker'] : null;
		$seenCursors = $cursor === null ? [] : [$cursor => true];
		$yielded = 0;

		for ($pageNumber = 1; $pageNumber <= $maxPages; $pageNumber++) {
			$page = $this->listPage($space, $keys, $arguments, $cursor);
			if ($page === false) {
				throw new RuntimeException("Failed listing objects in Space '{$space}'.");
			}

			foreach ($page['items'] as $item) {
				yield $item;
				$yielded++;
				if ($maxItems !== null && $yielded >= $maxItems) {
					return;
				}
			}

			if (!$page['isTruncated']) {
				return;
			}

			$cursor = $page['nextCursor'];
			$this->assertNextCursor($cursor, $seenCursors, $space);
			$seenCursors[$cursor] = true;
		}

		throw new RuntimeException(
			"Stopped listing Space '{$space}' after reaching the {$maxPages}-page safety limit."
		);
	}

	/**
	 * Get list of folders.
	 * 
	 * @param string $space       Name of space
	 * @param string|null $prefix Optional. Object key
	 * @param array $arguments    Optional. Additional api arguments
	 * 
	 * @return array|false
	 */
	public function listFolders(string $space, ?string $prefix = null, array $arguments = []): array|false {
		$prefix = $this->normalizeFolderPrefix($prefix);
		$arguments = array_merge($arguments, [
			'Bucket' => $space,
			'Delimiter' => '/',
			'Prefix' => $prefix
		]);
		try {
			$result = $this->client->listObjectsV2($arguments);
		}
		catch (Exception $e) {
			return false;
		}
		$this->verifyRequest($result, 'CommonPrefixes');
		$keys = [];
		array_walk($result['CommonPrefixes'], function($object, $index) use (&$keys, $prefix) {
			$keys[] = preg_replace(
				$prefix ? '/^' . preg_quote($prefix, '/') . '|\/$/' : '/\/$/',
				'',
				$object['Prefix']
			);
		});
		return $keys;
	}

	/**
	 * Get one page of folders using the legacy ListObjects operation.
	 *
	 * @param string $space       Name of space
	 * @param string|null $prefix Optional object prefix
	 * @param array $arguments    Optional. Additional API arguments
	 * @param string|null $cursor Optional marker returned by the previous page
	 *
	 * @return array{items: array, isTruncated: bool, nextCursor: string|null}|false
	 */
	public function listFoldersPage(
		string $space,
		?string $prefix = null,
		array $arguments = [],
		?string $cursor = null
	): array|false {
		$prefix = $this->normalizeFolderPrefix($prefix);
		$arguments = array_merge($arguments, [
			'Delimiter' => '/',
			'Prefix' => $prefix,
		]);
		$page = $this->requestListPage($space, $arguments, $cursor);
		if ($page === false) {
			return false;
		}

		$items = array_map(
			static fn (array $object): string => preg_replace(
				$prefix !== null && $prefix !== ''
					? '/^' . preg_quote($prefix, '/') . '|\/$/'
					: '/\/$/',
				'',
				$object['Prefix']
			),
			$page['CommonPrefixes']
		);

		return [
			'items' => $items,
			'isTruncated' => $page['IsTruncated'],
			'nextCursor' => $page['NextMarker'],
		];
	}

	/**
	 * Lazily iterate through folders using bounded legacy-marker pagination.
	 *
	 * @return Generator<int, string>
	 */
	public function iterateFolders(
		string $space,
		?string $prefix = null,
		array $arguments = [],
		?int $maxItems = null,
		int $maxPages = 1000
	): Generator {
		$this->validateIterationLimits($maxItems, $maxPages);
		if ($maxItems === 0) {
			return;
		}

		$cursor = isset($arguments['Marker']) ? (string) $arguments['Marker'] : null;
		$seenCursors = $cursor === null ? [] : [$cursor => true];
		$yielded = 0;

		for ($pageNumber = 1; $pageNumber <= $maxPages; $pageNumber++) {
			$page = $this->listFoldersPage($space, $prefix, $arguments, $cursor);
			if ($page === false) {
				throw new RuntimeException("Failed listing folders in Space '{$space}'.");
			}

			foreach ($page['items'] as $item) {
				yield $item;
				$yielded++;
				if ($maxItems !== null && $yielded >= $maxItems) {
					return;
				}
			}

			if (!$page['isTruncated']) {
				return;
			}

			$cursor = $page['nextCursor'];
			$this->assertNextCursor($cursor, $seenCursors, $space);
			$seenCursors[$cursor] = true;
		}

		throw new RuntimeException(
			"Stopped listing folders in Space '{$space}' after reaching the {$maxPages}-page safety limit."
		);
	}

	/**
	 * Get object.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false|null
	 */
	public function get(string $space, string $key, array $arguments = []): array|false|null {
		$arguments = array_merge($arguments, [
			'Bucket' => $space,
			'Key' => $key,
		]);
		try {
			$result = $this->client->headObject($arguments);
		}
		catch (AwsException $e) {
			if ($e->getStatusCode() === 404 || in_array($e->getAwsErrorCode(), ['NoSuchKey', 'NotFound'], true)) {
				return null;
			}
			return false;
		}
		catch (Exception $e) {
			return false;
		}

		$metadata = $result->toArray();
		$metadata['Key'] = $key;
		$metadata['Size'] = $metadata['ContentLength'] ?? null;

		return $metadata;
	}

	/**
	 * Check if object exists.
	 * 
	 * @param string $space Name of space
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function exists(string $space, string $key): bool {
		return (bool)$this->get($space, $key);
	}

	/**
	 * Get an objects ACL.
	 * 
	 * @param string $space Name of space
	 * @param string $key   Object key
	 * 
	 * @return string|false
	 */
	public function getAcl(string $space, string $key): string|false {
		$method = 'GET';
		$url = "https://{$space}.{$this->region}.digitaloceanspaces.com/{$key}?acl";
		$headers = $this->createAuthorizationSignature([
			'method' => $method,
			'url' => $url,
			'region' => $this->region
		]);
		$response = $this->httpClient->request($method, $url, [
			'headers' => $headers[0]
		]);
		if ($response->getStatusCode() !== 200) { return false; }
		$xml = new SimpleXMLElement($response->getContent());
		$isPublic = sizeof($xml->AccessControlList->Grant) > 1;
		return $isPublic ? self::ACL_PUBLIC : self::ACL_PRIVATE;
	}

	/**
	 * Set an objects ACL.
	 * 
	 * @param string $space Name of space
	 * @param string $key   Object key
	 * @param string $acl   Acl value
	 * 
	 * @return bool
	 */
	public function setAcl(string $space, string $key, string $acl): bool {
		$acl = $acl === self::ACL_PUBLIC ? self::ACL_PUBLIC : self::ACL_PRIVATE;
		$method = 'PUT';
		$url = "https://{$space}.{$this->region}.digitaloceanspaces.com/{$key}?acl";
		$headers = $this->createAuthorizationSignature([
			'method' => $method,
			'url' => $url,
			'region' => $this->region,
			'headers' => [
				'x-amz-acl' => $acl
			]
		]);
		$response = $this->httpClient->request($method, $url, [
			'headers' => $headers[0]
		]);
		if ($response->getStatusCode() !== 200) { return false; }
		return true;
	}

	/**
	 * Get CORS settings.
	 * 
	 * @param string $space Name of space
	 * 
	 * @return string|false
	 */
	public function getCors(string $space): string|false {
		$method = 'GET';
		$url = "https://{$space}.{$this->region}.digitaloceanspaces.com/?cors";
		$headers = $this->createAuthorizationSignature([
			'method' => $method,
			'url' => $url,
			'region' => $this->region
		]);
		$response = $this->httpClient->request($method, $url, [
			'headers' => $headers[0]
		]);
		if ($response->getStatusCode() !== 200) {
			return false;
		}
		return $response->getContent();
	}

	/**
	 * Set CORS settings.
	 * 
	 * @param string $space Name of space
	 * @param string $cors CORS settings
	 * 
	 * @return bool
	 */
	public function setCors(string $space, string $cors): bool {
		$method = 'PUT';
		$url = "https://{$space}.{$this->region}.digitaloceanspaces.com/?cors";
		$headers = $this->createAuthorizationSignature([
			'method' => $method,
			'url' => $url,
			'region' => $this->region,
			'payload' => $cors
		]);
		$response = $this->httpClient->request($method, $url, [
			'headers' => $headers[0],
			'body' => $cors
		]);
		if ($response->getStatusCode() !== 200) {
			return false;
		}
		return true;
	}

	/**
	 * Delete CORS settings.
	 * 
	 * @param string $space Name of space
	 * 
	 * @return bool
	 */
	public function deleteCors(string $space): bool {
		$method = 'DELETE';
		$url = "https://{$space}.{$this->region}.digitaloceanspaces.com/?cors";
		$headers = $this->createAuthorizationSignature([
			'method' => $method,
			'url' => $url,
			'region' => $this->region
		]);
		$response = $this->httpClient->request($method, $url, [
			'headers' => $headers[0]
		]);
		if ($response->getStatusCode() !== 204) {
			return false;
		}
		return true;
	}

	/**
	 * Upload content.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param string|resource|Psr\Http\Message\StreamInterface
	 * 				  $content Content to add
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function upload(string $space, string $key, mixed $content, bool $public = false, array $arguments = []): string|false {
		$arguments = array_merge($arguments, [
			'Bucket' => $space,
			'Key' => $key,
			'ACL' => $public ? self::ACL_PUBLIC : self::ACL_PRIVATE,
			'Body' => $content
		]);
		try {
			return $this->client->putObject($arguments)['ObjectURL'];
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Upload file.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param string $file     File path on disk
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function uploadFile(string $space, string $key, string $file, bool $public = false, array $arguments = []): string|false {
		$arguments = array_merge($arguments, [
			'Bucket' => $space,
			'Key' => $key,
			'ACL' => $public ? self::ACL_PUBLIC : self::ACL_PRIVATE,
			'SourceFile' => $file
		]);
		try {
			return $this->client->putObject($arguments)['ObjectURL'];
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Multipart file upload.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param string $file     File path on disk
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function multipartUploadFile(string $space, string $key, string $file, bool $public = false, array $arguments = []): string|false {
		try {
			$uploader = new MultipartUploader($this->client, $file, [
				'bucket' => $space,
				'key' => $key,
				'acl' => $public ? self::ACL_PUBLIC : self::ACL_PRIVATE
			]);
			return $uploader->upload()['ObjectURL'];
		} catch (S3MultipartUploadException $e) {
			return false;
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Downloads a file locally.
	 * 
	 * @param string $space       Name of space
	 * @param string $key         Object key
	 * @param string $destination File destination
	 * 
	 * @return bool
	 */
	public function download(string $space, string $key, string $destination): bool {
		try {
			$result = $this->client->getObject([
				'Bucket' => $space,
				'Key' => $key
			]);
			return file_put_contents($destination, $result['Body']) !== false;
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Get object public URL.
	 * 
	 * @param string $space Name of space
	 * @param string $key   Object key
	 * 
	 * @return string
	 */
	public function publicUrl(string $space, string $key): string {
		return $this->client->getObjectUrl(
			$space, $key
		);
	}

	/**
	 * Generate a presigned download URL.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string|false
	 */
	public function presignedDownload(string $space, string $key, string $duration = '+5 minutes'): string|false {
		try {
			$cmd = $this->client->getCommand('GetObject', [
				'Bucket' => $space,
				'Key'    => $key
			]);
			$request = $this->client->createPresignedRequest($cmd, $duration);
			return (string)$request->getUri();
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Generate a presigned upload URL.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param string $duration Length of time URL is valid
	 * @param string|null $contentType Required content type for the upload
	 * 
	 * @return string|false
	 */
	public function presignedUpload(
		string $space,
		string $key,
		string $duration = '+5 minutes',
		?string $contentType = null
	): string|false {
		try {
			$arguments = [
				'Bucket' => $space,
				'Key'    => $key
			];
			if ($contentType !== null) {
				$arguments['ContentType'] = $contentType;
			}
			$cmd = $this->client->getCommand('PutObject', $arguments);
			$request = $this->client->createPresignedRequest($cmd, $duration);
			return (string)$request->getUri();
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Delete object.
	 * 
	 * @param string $space Name of space
	 * @param string $key   Object key
	 * 
	 * @return bool
	 */
	public function delete(string $space, string $key): bool {
		try {
			$this->client->deleteObject([
				'Bucket' => $space,
				'Key' => $key,
			]);
			return true;
		}
		catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Get base key. Similar to PHP dirname().
	 * 
	 * @param string $key Object key.
	 * 
	 * @return string
	 */
	public function baseKey(string $key): string {
		$key = preg_replace('/^\/|\/$/', '', $key);
		$segments = explode('/', $key);
		array_pop($segments);
		return implode('/', $segments);
	}

	/**
	 * Request one legacy ListObjects page and normalize its paging metadata.
	 *
	 * @return array{
	 *     Contents: array,
	 *     CommonPrefixes: array,
	 *     IsTruncated: bool,
	 *     NextMarker: string|null
	 * }|false
	 */
	private function requestListPage(string $space, array $arguments, ?string $cursor): array|false {
		$arguments = array_merge($arguments, [
			'Bucket' => $space,
		]);
		if ($cursor !== null) {
			$arguments['Marker'] = $cursor;
		}

		try {
			$result = $this->client->listObjects($arguments);
		}
		catch (Exception $e) {
			return false;
		}

		$contents = $result['Contents'] ?? [];
		$commonPrefixes = $result['CommonPrefixes'] ?? [];
		$isTruncated = (bool) ($result['IsTruncated'] ?? false);
		$nextMarker = isset($result['NextMarker']) ? (string) $result['NextMarker'] : null;

		if ($isTruncated && $nextMarker === null) {
			$candidates = array_merge(
				array_column($contents, 'Key'),
				array_column($commonPrefixes, 'Prefix')
			);
			if ($candidates !== []) {
				sort($candidates, SORT_STRING);
				$nextMarker = (string) $candidates[array_key_last($candidates)];
			}
		}

		return [
			'Contents' => $contents,
			'CommonPrefixes' => $commonPrefixes,
			'IsTruncated' => $isTruncated,
			'NextMarker' => $nextMarker,
		];
	}

	private function normalizeFolderPrefix(?string $prefix): ?string {
		return $prefix !== null && $prefix !== '' && !str_ends_with($prefix, '/')
			? "{$prefix}/"
			: $prefix;
	}

	private function validateIterationLimits(?int $maxItems, int $maxPages): void {
		if ($maxItems !== null && $maxItems < 0) {
			throw new \InvalidArgumentException('Maximum items cannot be negative.');
		}
		if ($maxPages < 1) {
			throw new \InvalidArgumentException('Maximum pages must be at least one.');
		}
	}

	/**
	 * @param array<string, true> $seenCursors
	 */
	private function assertNextCursor(?string $cursor, array $seenCursors, string $space): void {
		if ($cursor === null || $cursor === '') {
			throw new RuntimeException(
				"Space '{$space}' returned a truncated page without a continuation marker."
			);
		}
		if (isset($seenCursors[$cursor])) {
			throw new RuntimeException(
				"Space '{$space}' returned a repeated continuation marker."
			);
		}
	}

	/**
	 * Verify HTTP result has particular property.
	 * 
	 * @param array|ArrayAccess $request Api result
	 * @param string $key     Value key
	 * @param mixed $default  Default value
 	 */
	public function verifyRequest(array|ArrayAccess &$request, string $key, mixed $default = []): void {
		if (!isset($request[$key])) {
			$request[$key] = $default;
		}
	}

	/**
	 * Create an authorization signature header.
	 * 
	 * @param array $arguments Array of arguments
	 * 
	 * @return array
	 */
	public function createAuthorizationSignature(array $arguments): array {
		return $this->authorization->create($arguments);
	}

	/**
	 * Get S3Client instance.
	 * 
	 * @return S3Client
	 */
	public function getClient(): S3Client {
		return $this->client;
	}

	/**
	 * Create S3Client.
	 * 
	 * @param string $key      Spaces api key
	 * @param string $secret   Spaces api secret
	 * @param string $endpoint Spaces api url endpoint
	 * 
	 * @return S3Client
	 */
	public static function createClient(string $key, string $secret, string $endpoint): S3Client {
		return new S3Client([
			'version' => 'latest',
			'region' => 'us-east-1',
			'endpoint' => $endpoint,
			'credentials' => [
				'key' => $key,
				'secret' => $secret
			]
		]);
	}

	/**
	 * Create Spaces Client.
	 * 
	 * @param string $key    Spaces api key
	 * @param string $secret Spaces api secret
	 * @param string $region Spaces region
	 * 
	 * @return Client
	 */
	public static function create(string $key, string $secret, string $region): self {
		return new self($key, $secret, $region);
	}
}
