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

/**
 * DigitalOcean Spaces helper.
 * Utilizes Amazon S3 PHP SDK.
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
	 * @param string Spaces api key
	 * @param string Spaces api secret
	 * @param string Spaces endpoint url
	 */
	public function __construct($key, $secret, $endpoint) {
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
		$names = $this->listSpaces(true);
		if (!in_array($name, $names)) {
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
	 * List existing spaces.
	 * 
	 * @param bool $names Return names only
	 * 
	 * @param array
	 */
	public function listSpaces(bool $names = false) {
		$spaces = $this->client->listBuckets();
		if (!$names) {
			return $spaces['Buckets'];
		}
		$names = [];
		array_walk($spaces['Buckets'], function($value) use (&$names) {
			$names[] = $value['Name'];
		});
		return $names;
	}

	/**
	 * Upload content.
	 * 
	 * @param string $content Content to add
	 */
	public function upload($content, $options) {
		if (!array_key_exists('ACL', $options)) {
			$options['ACL'] = 'private';
		}
		$options['Body'] = $content;
		$this->client->putObject($options);
	}

	/**
	 * Upload file.
	 * 
	 * @param string $file File path
	 * @param string $content Content to add
	 */
	public function uploadFile($file, $options) {
		if (!array_key_exists('ACL', $options)) {
			$options['ACL'] = 'private';
		}
		$options['SourceFile'] = $file;
		$this->client->putObject($options);
	}

	public function multipartUploadFile($file, $dest, $space, $public = false) {
		$uploader = new MultipartUploader($this->client, $file, [
			'bucket' => $space,
			'key' => $dest,
			'acl' => $public ? 'public-read' : 'private'
		]);
		try {
			$result = $uploader->upload();
			return $result['ObjectURL'];
		} catch (MultipartUploadException $e) {
			return false;
		}
	}

	public function listFiles($space, $names = false) {
		$files = $this->client->listObjects([
			'Bucket' => $space,
		]);
		if (!$names) {
			return $files['Contents'];
		}
		$names = [];
		array_walk($files['Contents'], function($value, $index) use (&$names) {
			$names[] = $value['Key'];
		});
		return $names;
	}

	public function download($file, $dest, $space) {
		$result = $this->client->getObject([
			'Bucket' => $space,
			'Key' => $file
		]);
		file_put_contents($dest, $result['Body']);
	}

	public function publicUrl($file, $space) {
		return $this->client->getObjectUrl(
			$space, $file
		);
	}

	public function presignedDownload($file, $space, $duration = '+5 minutes') {
		$cmd = $this->client->getCommand('GetObject', [
			'Bucket' => $space,
			'Key'    => $file
		]);
		$request = $this->client->createPresignedRequest($cmd, $duration);
		return (string)$request->getUri();
	}

	public function presignedUpload($file, $type, $space, $duration = '+5 minutes') {
		$cmd = $this->client->getCommand('PutObject', [
			'Bucket' => $space,
			'Key'    => $file,
			'ContentType' => $type
		]);
		$request = $this->client->createPresignedRequest($cmd, $duration);
		return (string)$request->getUri();
	}

	/**
	 * Delete object.
	 * 
	 * @param string $space Name of space
	 * @param string $key Object key
	 * 
	 * @return bool
	 */
	public function deleteFile(string $space, string $key) {
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
	 * Verify key exists in api result.
	 * 
	 * @param object $request Api result
	 * @param string $key Value key
	 * @param mixed $default Default value
 	 */
	protected function verifyRequest(&$request, string $key, $default = []) {
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