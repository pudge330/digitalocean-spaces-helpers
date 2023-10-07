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

use Datetime;
use Exception;

/**
 * DigitalOcean Spaces Athorization header helper.
 * 
 * Documentation: https://docs.digitalocean.com/reference/api/spaces-api/
 */
class Authorization {
	/**
	 * @var string Spaces api key
	 */
	protected $key;

	/**
	 * @var string Spaces api secret
	 */
	protected $secret;

	/**
	 * @param string $key    Spaces api key
	 * @param string $secret Spaces api secret
	 */
	public function __construct($key, $secret) {
		$this->key = $key;
		$this->secret = $secret;
	}

	/**
	 * Canonically sort array based on the specifications in the
	 * AWS S3 documentation. 0-9A-Za-z
	 * 
	 * @param array $array Values to sort
	 * 
	 * @return array
	 */
	protected function canonicalSort(array $array) {
		$empty = $numeric = $lower = $upper = [];
		foreach ($array as $value) {
			if ($value && preg_match('/^[0-9]/', $value)) {
				$numeric[] = $value;
			}
			else if ($value && preg_match('/^[A-Z]/', $value)) {
				$upper[] = $value;
			}
			else if ($value && preg_match('/^[a-z]/', $value)) {
				$lower[] = $value;
			}
			else {
				$empty[] = $value;
			}
		}
		sort($empty);
		sort($numeric);
		sort($lower);
		sort($upper);
		return array_merge($empty, $numeric, $upper, $lower);
	}

	/**
	 * Process url and return processed parts.
	 * 
	 * @param string $url The request url
	 * 
	 * @return array
	 */
	protected function processUrl(string $url) {
		$url = !preg_match('/^http/', $url) ? "https://{$url}" : $url;
		$parts = parse_url($url);
		if (!array_key_exists('path', $parts)) { $parts['path'] = '/'; }
		if ($parts['path'] === '') { $parts['path'] = '/'; }
		if (!array_key_exists('query', $parts)) { $parts['query'] = ''; }
		return [
			'host' => $parts['host'],
			'path' => $parts['path'],
			'query' => $this->processQuery($parts['query'])
		];
	}

	/**
	 * Process url query string.
	 * 
	 * @param string $query Url query string
	 * 
	 * @return string
	 */
	protected function processQuery(string $query) {
		$query = explode("&", $query);
		$grouped = $sortedGrouped = [];
		$canonicalQuery = '';
		foreach ($query as $segment) {
			if ($segment === '') { continue; }
			$parts = explode("=", $segment);
			if (!isset($parts[1])) { $parts[] = null; }
			if (!array_key_exists($parts[0], $grouped)) { $grouped[$parts[0]] = []; }
			$grouped[$parts[0]][] = $parts[1];
		}
		$sortedKeys = $this->canonicalSort(array_keys($grouped));
		foreach ($sortedKeys as $key) {
			$sortedGrouped[$key] = $this->canonicalSort($grouped[$key]);
		}
		foreach ($sortedGrouped as $key => $group) {
			foreach ($group as $value) {
				$value = $value !== null ? $value : '';
				$canonicalQuery .= "{$key}={$value}&";
			}
		}
		return preg_replace('/&$/', '', $canonicalQuery);
	}

	/**
	 * Process request headers.
	 * 
	 * @param array $headers Request headers
	 * 
	 * @return array
	 */
	protected function processHeaders(array $headers) {
		$sortedKeys = $this->canonicalSort(array_keys($headers));
		$sortedHeaders = [];
		$canonicalKeys = '';
		$canonicalHeaders = '';
		foreach ($sortedKeys as $key) {
			$sortedHeaders[$key] = trim($headers[$key]);
		}
		foreach ($sortedHeaders as $key => $value) {
			$key = strtolower($key);
			$canonicalKeys .= "{$key};";
			$canonicalHeaders .= "{$key}:{$value}\n";
		}
		return [$sortedHeaders, preg_replace('/;$/', '', $canonicalKeys), trim($canonicalHeaders)];
	}

	/**
	 * Get formatted UTC datetime values.
	 * 
	 * @return array
	 */
	protected function getDatetime() {
		$datetime = new Datetime('UTC');
		return [
			$datetime,
			$datetime->format('Ymd'),
			$datetime->format('Ymd') . 'T' . $datetime->format('His') . 'Z'
		];
	}

	/**
	 * Create authorization headers and return full set of headers generated.
	 * 
	 * @param array $arguments
	 * 
	 * @return array
	 */
	public function create(array $arguments) {
		$arguments = array_merge([
			'method' => 'GET',
			'region' => null,
			'url' => null,
			'headers' => [],
			'payload' => ''
		], $arguments);
		if (!$arguments['region']) {
			// attempt to get region from url
			if (preg_match('/([a-zA-Z0-9-_]+)\.digitaloceanspaces\.com/', $arguments['url'], $match)) {
				$arguments['region'] = $match[1];
			}
			if (!$arguments['region']) {
				throw new Exception('Region could not automatically be determined.');
			}
		}

		list($datetime, $datetimeYmd, $datetimeYmdHis) = $this->getDatetime();
		$region = $arguments['region'];
		$method = strtoupper($arguments['method']);
		$url = $this->processUrl($arguments['url']);
		$payloadHash = isset($arguments['payloadHash']) && $arguments['payloadHash']
			? $arguments['payloadHash'] 
			: hash('sha256', $arguments['payload']);

		// default headers
		$arguments['headers']['Host'] = $url['host'];
		$arguments['headers']['x-amz-content-sha256'] = $payloadHash;
		$arguments['headers']['x-amz-date'] = $datetimeYmdHis;

		$headers = $this->processHeaders($arguments['headers']);
		$headerKeys = array_keys($headers[0]);

		// canonical request
		$canonicalRequest = "{$method}\n" .
							"{$url['path']}\n" .
							"{$url['query']}\n" .
							"{$headers[2]}\n\n" .
							"{$headers[1]}\n" .
							"{$payloadHash}";

		$stringToSign = "AWS4-HMAC-SHA256\n" . 
						"{$datetimeYmdHis}\n" .
						"{$datetimeYmd}/{$region}/s3/aws4_request\n" .
						hash('sha256', $canonicalRequest);

		$dateKey = hash_hmac('sha256', $datetimeYmd, "AWS4{$this->secret}", true);
		$dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
		$dateRegionServiceKey = hash_hmac('sha256', "s3", $dateRegionKey, true);
		$signingKey = hash_hmac('sha256', "aws4_request", $dateRegionServiceKey, true);
		$signature = hash_hmac('sha256', $stringToSign, $signingKey);

		$authorizationHeader = "AWS4-HMAC-SHA256 " .
							   "Credential={$this->key}/{$datetimeYmd}/{$region}/s3/aws4_request," .
							   "SignedHeaders={$headers[1]}," .
							   "Signature={$signature}";

		$headers = $headers[0];
		$headers['Authorization'] = $authorizationHeader;

		$canonicalHeaders = '';
		foreach ($headers as $key => $value) {
			$canonicalHeaders .= "{$key}: {$value}\n";
		}

		return [$headers, trim($canonicalHeaders)];
	}
}