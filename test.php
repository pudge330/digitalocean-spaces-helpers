<?php
include __DIR__ . '/vendor/autoload.php';

/*

BAG\Spaces
	\Client
	\Space
	\Bucket?


*/

$key = '';
$secret = '';
$space = '';
$region = 'nyc3';

$publicFile = '/_/assets/icons/vuejs.png';
$privateFile = 'test/ntfsmac15_trial.2.dmg';

// dump([
// 	'key' => $key,
// 	'secret' => $secret,
// 	'space' => $space,
// 	'endpoint' => $endpoint
// ]);

$client = new BAG\Spaces\Client($key, $secret, $region);

// dump($client->list($space));

dump(
	$client->getAcl($space, $publicFile),
	$client->getAcl($space, $privateFile)
);
