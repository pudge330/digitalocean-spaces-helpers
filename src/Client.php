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

use Exception;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\S3\ObjectUploader;
use Aws\S3\MultipartUploader;
use Aws\S3\Exception\S3MultipartUploadException;

/**
 * DigitalOcean Spaces helper.
 * Utilizes Amazon S3 PHP SDK.
 * 
 * Documentation: https://docs.digitalocean.com/reference/api/spaces-api/
 * Documentation: https://docs.aws.amazon.com/aws-sdk-php/v3/api/
 */
class Client {
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC = 'public-read';

	/**
	 * @var string Spaces api key
	 */
	protected $key;

	/**
	 * @var string Spaces api secret
	 */
	protected $secret;

	/**
	 * @var string Spaces endpoint url
	 */
	protected $endpoint;

	/**
	 * @var S3Client S3 client class
	 */
	protected $client;

	/**
	 * @param string $key      Spaces api key
	 * @param string $secret   Spaces api secret
	 * @param string $endpoint Spaces endpoint url
	 */
	public function __construct(string $key, string $secret, string $endpoint) {
		$this->key = $key;
		$this->secret = $secret;
		$this->endpoint = $endpoint;
		$this->client = self::createClient($key, $secret, $endpoint);
	}

	/**
	 * Create space.
	 * 
	 * @param string $name Name of space
	 * 
	 * @return bool
	 */
	public function createSpace(string $name) {
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
	public function deleteSpace(string $space) {
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
	public function listSpaces(bool $names = false) {
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
	public function spaceExists(string $space) {
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
	public function list(string $space, bool $keys = false, array $arguments = []) {
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
	 * Get object.
	 * 
	 * @param string $space    Name of space
	 * @param string $key      Object key
	 * @param array $arguments Optional. Additional api arguments
	 * 
	 * @return array|false|null
	 */
	public function get(string $space, string $key, array $arguments = []) {
		$baseKey = $this->baseKey($key);
		$arguments = array_merge($arguments, [
			'Prefix' => $baseKey
		]);
		$object = null;
		$startAfter = null;
		do {
			$maxKeys = 1000;
			$objects = $this->list($space, false, array_merge($arguments, [
				'MaxKeys' => $maxKeys,
				'StartAfter' => $startAfter
			]));
			if ($objects === false) {
				return false;
			}
			foreach ($objects as $o) {
				$startAfter = $o['Key'];
				if ($o['Key'] == $key) {
					$object = $o;
				}
			}
		} while (sizeof($objects) && $object === null);
		return $object;
	}

	/**
	 * Check if object exists.
	 * 
	 * @param string $space Name of space
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function exists(string $space, string $key) {
		return (bool)$this->get($space, $key);
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
	public function upload(string $space, string $key, $content, bool $public = false, array $arguments = []) {
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
	public function uploadFile(string $space, string $key, string $file, bool $public = false, array $arguments = []) {
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
	public function multipartUploadFile(string $space, string $key, string $file, bool $public = false, array $arguments = []) {
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
	public function download(string $space, string $key, string $destination) {
		try {
			$result = $this->client->getObject([
				'Bucket' => $space,
				'Key' => $key
			]);
			return file_put_contents($destination, $result['Body']);
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
	public function publicUrl(string $space, string $key) {
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
	 * @return string|bool
	 */
	public function presignedDownload(string $space, string $key, string $duration = '+5 minutes') {
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
	 * @param string $type     File format type
	 * @param string $duration Length of time URL is valid
	 * 
	 * @return string
	 */
	public function presignedUpload(string $space, string $key, string $type, string $duration = '+5 minutes') {
		try {
			$cmd = $this->client->getCommand('PutObject', [
				'Bucket' => $space,
				'Key'    => $file,
				'ContentType' => $type
			]);
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
	public function delete(string $space, string $key) {
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
	public function baseKey(string $key) {
		$key = preg_replace('/^\/|\/$/', '', $key);
		$segments = explode('/', $key);
		array_pop($segments);
		return implode('/', $segments);
	}

	/**
	 * Verify HTTP result has particular property.
	 * 
	 * @param object $request Api result
	 * @param string $key     Value key
	 * @param mixed $default  Default value
 	 */
	public function verifyRequest(&$request, string $key, $default = []) {
		if (!isset($request[$key])) {
			$request[$key] = $default;
		}
	}

	/**
	 * Create S3 Client.
	 * 
	 * @param string $key      The api key
	 * @param string $secret   The api secret
	 * @param string $endpoint The api url endpoint
	 * 
	 * @return S3Client
	 */
	public static function createClient(string $key, string $secret, string $endpoint) {
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
	 * @param string $key      The api key
	 * @param string $secret   The api secret
	 * @param string $endpoint The api url endpoint
	 * 
	 * @return Client
	 */
	public static function create($key, $secret, $endpoint) {
		return new self($key, $secret, $endpoint);
	}
}