<?php
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

/**
 * DigitalOcean Spaces helper for specified space.
 */
class Space {
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC = 'public-read';

	/**
	 * @var string Name of space
	 */
	protected $space;

	/**
	 * @var Client Spaces client instance
	 */
	protected $client;

	/**
	 * @param string $space    Name of space
	 * @param string $key      Spaces api key
	 * @param string $secret   Spaces api secret
	 * @param string $endpoint Spaces endpoint url
	 * @param bool $create     Optional. Create space on instantiation
	 */
	public function __construct($space, $key, $secret, $endpoint, $create = false) {
		$this->space = $space;
		$this->client = new Client($key, $secret, $endpoint);
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
	public function list(bool $keys = false, array $arguments = []) {
		return $this->client->list($this->space, $keys, $arguments);
	}

	/**
	 * Get object.
	 * 
	 * @param string $key      Object key
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false|null
	 */
	public function get(string $key, array $arguments = []) {
		return $this->client->get($this->space, $key, $arguments);
	}

	/**
	 * Upload content.
	 * 
	 * @param string $key      Object key
	 * @param string $content  Content to add
	 * @param bool $public     Optional. Object is public
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return string|false
	 */
	public function upload(string $key, string $content, bool $public = false, array $arguments = []) {
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
	public function uploadFile(string $key, string $file, bool $public = false, array $arguments = []) {
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
	public function multipartUploadFile(string $key, string $file, bool $public = false, array $arguments = []) {
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
	public function download(string $key, string $destination) {
		return $this->client->download($this->space, $key, $destination);
	}

	/**
	 * Get object public URL.
	 * 
	 * @param string $key Object key
	 * 
	 * @return string
	 */
	public function publicUrl(string $key) {
		return $this->client->publicUrl($this->space, $key);
	}

	/**
	 * Generate a presigned download URL.
	 * 
	 * @param string $key      Object key
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string|bool
	 */
	public function presignedDownload(string $key, string $duration = '+5 minutes') {
		return $this->client->presignedDownload($this->space, $key, $duration);
	}

	/**
	 * Generate a presigned upload URL.
	 * 
	 * @param string $key      Object key
	 * @param string $type     File format type
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string
	 */
	public function presignedUpload(string $key, string $type, string $duration = '+5 minutes') {
		return $this->client->presignedUpload($this->space, $key, $type, $duration);
	}

	/**
	 * Delete object.
	 * 
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function delete(string $key) {
		$this->client->delete($this->space, $key);
	}

	/**
	 * Get base key. Similar to PHP dirname().
	 * 
	 * @param string $key Object key.
	 * 
	 * @return string
	 */
	public function baseKey(string $key) {
		return $this->client->baseKey($key);
	}
}