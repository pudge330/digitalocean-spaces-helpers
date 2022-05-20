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

class Space {
	protected $space;
	protected $client;

	public function __construct($space, $key, $secret, $endpoint, $createSpace = false) {
		$this->space = $space;
		$this->client = new Client($key, $secret, $endpoint);
		if ($createSpace) {
			$this->client->createSpace($space);
		}
	}

	public function upload($content, $options) {
		$options['Bucket'] = $this->space;
		$this->client->upload($content, $options);
	}

	public function uploadFile($file, $options) {
		$options['Bucket'] = $this->space;
		$this->client->uploadFile($file, $options);
	}

	public function multipartUploadFile($file, $dest) {
		return $this->client->multipartUploadFile($file, $dest, $this->space);
	}

	public function listFiles($names = false) {
		return $this->client->listFiles($this->space, $names);
	}

	public function download($file, $dest) {
		$this->download($file, $dest, $this->space);
	}

	public function publicUrl($file) {
		return $this->client->publicUrl($file, $space);
	}

	public function presignedDownload($file, $duration = '+5 minutes') {
		return $this->client->presignedDownload($file, $this->space, $duration) {
	}

	public function presignedUpload($file, $type, $duration = '+5 minutes') {
		return $this->client->presignedUpload($file, $type, $this->space, $duration);
	}

	public function deleteFile($file) {
		$this->client->deleteFile($file, $this->space);
	}
}