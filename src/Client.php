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

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\S3\ObjectUploader;
use Aws\S3\MultipartUploader;

class Client {
	protected $key;
	protected $secret;
	protected $endpoint;
	protected $client;

	public function __construct($key, $secret, $endpoint) {
		$this->key = $key;
		$this->secret = $secret;
		$this->endpoint = $endpoint;
		$this->client = self::createClient($key, $secret, $endpoint);
	}

	public function createSpace($name) {
		$names = $this->listSpaces(true);
		if (!in_array($name, $names)) {
			$this->client->createBucket([
				'Bucket' => $name
			]);
		}
	}

	public function listSpaces($names = false) {
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

	public function upload($content, $options) {
		if (!array_key_exists('ACL', $options)) {
			$options['ACL'] = 'private';
		}
		$options['Body'] = $content;
		$this->client->putObject($options);
	}

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

	public function deleteFile($file, $space) {
		$this->client->deleteObject([
			'Bucket' => $space,
			'Key' => $file,
		]);
	}

	public function deleteSpace($space) {
		$this->client->deleteBucket([
			'Bucket' => $space,
		]);
	}

	public static function createClient($key, $secret, $endpoint) {
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

	public static function create($key, $secret, $endpoint) {
		return new self($key, $secret, $endpoint);
	}
}