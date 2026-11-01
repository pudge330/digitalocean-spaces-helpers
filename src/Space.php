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

use Generator;

/**
 * DigitalOcean Spaces helper for specified space.
 */
class Space {
	public const string ACL_PRIVATE = 'private';
	public const string ACL_PUBLIC = 'public-read';

	/**
	 * @var string Name of space
	 */
	protected string $space;

	/**
	 * @var Client Spaces client instance
	 */
	protected Client $client;

	/**
	 * @param string $space    Name of space
	 * @param string $key      Spaces api key
	 * @param string $secret   Spaces api secret
	 * @param string $endpoint Spaces region
	 * @param bool $create     Optional. Create space on instantiation
	 */
	public function __construct(string $space, string $key, string $secret, string $region, bool $create = false) {
		$this->space = $space;
		$this->client = new Client($key, $secret, $region);
		if ($create) {
			$this->client->createSpace($space);
		}
	}

	/**
	 * Get list of objects.
	 * 
	 * @param bool $keys       Return keys only
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false
	 */
	public function list(bool $keys = false, array $arguments = []): array|false {
		return $this->client->list($this->space, $keys, $arguments);
	}

	/**
	 * Get one cursor-addressable page of objects.
	 *
	 * @return array{items: array, isTruncated: bool, nextCursor: string|null}|false
	 */
	public function listPage(
		bool $keys = false,
		array $arguments = [],
		?string $cursor = null
	): array|false {
		return $this->client->listPage($this->space, $keys, $arguments, $cursor);
	}

	/**
	 * Lazily iterate through objects using bounded pagination.
	 *
	 * @return Generator<int, array|string>
	 */
	public function iterate(
		bool $keys = false,
		array $arguments = [],
		?int $maxItems = null,
		int $maxPages = 1000
	): Generator {
		return $this->client->iterate($this->space, $keys, $arguments, $maxItems, $maxPages);
	}

	/**
	 * Get list of folders.
	 * 
	 * @param string|null $prefix Optional. Object key
	 * @param array $arguments    Optional. Additional api arguments
	 * 
	 * @return array|false
	 */
	public function listFolders(?string $prefix = null, array $arguments = []): array|false {
		return $this->client->listFolders($this->space, $prefix, $arguments);
	}

	/**
	 * Get one cursor-addressable page of folders.
	 *
	 * @return array{items: array, isTruncated: bool, nextCursor: string|null}|false
	 */
	public function listFoldersPage(
		?string $prefix = null,
		array $arguments = [],
		?string $cursor = null
	): array|false {
		return $this->client->listFoldersPage($this->space, $prefix, $arguments, $cursor);
	}

	/**
	 * Lazily iterate through folders using bounded pagination.
	 *
	 * @return Generator<int, string>
	 */
	public function iterateFolders(
		?string $prefix = null,
		array $arguments = [],
		?int $maxItems = null,
		int $maxPages = 1000
	): Generator {
		return $this->client->iterateFolders(
			$this->space,
			$prefix,
			$arguments,
			$maxItems,
			$maxPages
		);
	}

	/**
	 * Get object.
	 * 
	 * @param string $key      Object key
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false|null
	 */
	public function get(string $key, array $arguments = []): array|false|null {
		return $this->client->get($this->space, $key, $arguments);
	}

	/**
	 * Check if object exists.
	 * 
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function exists(string $key): bool {
		return $this->client->exists($this->space, $key);
	}

	/**
	 * Get an objects ACL.
	 * 
	 * @param string $key Object key
	 * 
	 * @return string|bool
	 */
	public function getAcl(string $key): string|false {
		return $this->client->getAcl($this->space, $key);
	}

	/**
	 * Set an objects ACL.
	 * 
	 * @param string $key Object key
	 * @param string $acl Acl value
	 * 
	 * @return bool
	 */
	public function setAcl(string $key, string $acl): bool {
		return $this->client->setAcl($this->space, $key, $acl);
	}

	/**
	 * Upload content.
	 * 
	 * @param string $key      Object key
	 * @param string|resource|Psr\Http\Message\StreamInterface $content
	 * 						   Content to add
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function upload(string $key, mixed $content, bool $public = false, array $arguments = []): string|false {
		return $this->client->upload($this->space, $key, $content, $public, $arguments);
	}

	/**
	 * Upload file.
	 * 
	 * @param string $key      Object key
	 * @param string $file     File path on disk
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function uploadFile(string $key, string $file, bool $public = false, array $arguments = []): string|false {
		return $this->client->uploadFile($this->space, $key, $file, $public, $arguments);
	}

	/**
	 * Multipart file upload.
	 * 
	 * @param string $key      Object key
	 * @param string $file     File path on disk
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function multipartUploadFile(string $key, string $file, bool $public = false, array $arguments = []): string|false {
		return $this->client->multipartUploadFile($this->space, $key, $file, $public, $arguments);
	}

	/**
	 * Downloads a file locally.
	 * 
	 * @param string $key         Object key
	 * @param string $destination File destination
	 * 
	 * @return bool
	 */
	public function download(string $key, string $destination): bool {
		return $this->client->download($this->space, $key, $destination);
	}

	/**
	 * Get object public URL.
	 * 
	 * @param string $key Object key
	 * 
	 * @return string
	 */
	public function publicUrl(string $key): string {
		return $this->client->publicUrl($this->space, $key);
	}

	/**
	 * Generate a presigned download URL.
	 * 
	 * @param string $key      Object key
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string|false
	 */
	public function presignedDownload(string $key, string $duration = '+5 minutes'): string|false {
		return $this->client->presignedDownload($this->space, $key, $duration);
	}

	/**
	 * Generate a presigned upload URL.
	 * 
	 * @param string $key      Object key
	 * @param string $type     File format type
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string|false
	 */
	public function presignedUpload(string $key, string $type, string $duration = '+5 minutes'): string|false {
		return $this->client->presignedUpload($this->space, $key, $duration, $type);
	}

	/**
	 * Delete object.
	 * 
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function delete(string $key): bool {
		return $this->client->delete($this->space, $key);
	}

	/**
	 * Get base key. Similar to PHP dirname().
	 * 
	 * @param string $key Object key.
	 * 
	 * @return string
	 */
	public function baseKey(string $key): string {
		return $this->client->baseKey($key);
	}

	/**
	 * Get Client instance.
	 * 
	 * @return Client
	 */
	public function getClient(): Client {
		return $this->client;
	}
}
