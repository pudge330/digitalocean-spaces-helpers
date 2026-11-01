<?php
declare(strict_types=1);

use BAG\Spaces\Client;

require dirname(__DIR__) . '/vendor/autoload.php';

const TARGET_SPACE = 'development-test';
const MAX_SPACES_TO_SAMPLE = 10;
const MAX_OBJECTS_PER_SPACE = 5;

$envPath = $argv[1] ?? dirname(__DIR__) . '/.env';
$startedAt = gmdate(DATE_ATOM);
$checks = [];
$spaceSamples = [];
$uploadedKeys = [];
$downloadPath = null;
$client = null;
$testKey = null;
$runPrefix = null;
$exitCode = 0;

$record = static function (string $name, bool $passed, array $details = []) use (&$checks): void {
    $checks[] = array_merge([
        'name' => $name,
        'passed' => $passed,
    ], $details);
};

try {
    if (!is_readable($envPath)) {
        throw new RuntimeException("Environment file is not readable: {$envPath}");
    }

    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
        throw new RuntimeException('Environment file could not be parsed.');
    }

    foreach (['DO_SPACES_KEY', 'DO_SPACES_SECRET', 'DO_SPACES_REGION'] as $requiredKey) {
        if (!isset($env[$requiredKey]) || trim((string) $env[$requiredKey]) === '') {
            throw new RuntimeException("Missing required environment value: {$requiredKey}");
        }
    }

    $configuredSpace = trim((string) ($env['DO_SPACES_BUCKET'] ?? ''));
    if ($configuredSpace !== '' && $configuredSpace !== TARGET_SPACE) {
        throw new RuntimeException(
            sprintf('Refusing write tests: DO_SPACES_BUCKET must be %s.', TARGET_SPACE)
        );
    }

    $region = trim((string) $env['DO_SPACES_REGION']);
    $prefix = trim((string) ($env['DO_SPACES_PREFIX'] ?? 'codex-integration-test'), '/');
    if ($prefix === '') {
        $prefix = 'codex-integration-test';
    }

    $runId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $runPrefix = "{$prefix}/{$runId}";
    $testKey = "{$runPrefix}/sample.txt";
    $paginationKey = "{$runPrefix}/pagination-sample.txt";
    $testObjects = [
        $testKey => "DigitalOcean Spaces integration test\nRun: {$runId}\n",
        $paginationKey => "DigitalOcean Spaces pagination test\nRun: {$runId}\n",
    ];
    $content = $testObjects[$testKey];

    $client = new Client(
        trim((string) $env['DO_SPACES_KEY']),
        trim((string) $env['DO_SPACES_SECRET']),
        $region
    );

    $spaces = $client->listSpaces(true);
    if ($spaces === false) {
        throw new RuntimeException('Listing Spaces failed.');
    }
    $record('list-spaces', true, ['spaceCount' => count($spaces)]);

    if (!in_array(TARGET_SPACE, $spaces, true)) {
        throw new RuntimeException(sprintf('Required Space %s was not found.', TARGET_SPACE));
    }

    $spacesToSample = array_slice($spaces, 0, MAX_SPACES_TO_SAMPLE);
    if (!in_array(TARGET_SPACE, $spacesToSample, true)) {
        array_pop($spacesToSample);
        $spacesToSample[] = TARGET_SPACE;
    }

    foreach ($spacesToSample as $space) {
        $objects = $client->list($space, true, ['MaxKeys' => MAX_OBJECTS_PER_SPACE]);
        $spaceSamples[] = [
            'space' => $space,
            'listingPassed' => $objects !== false,
            'sampledObjectCount' => is_array($objects) ? count($objects) : 0,
        ];
    }
    $record('sample-object-listings', !in_array(false, array_column($spaceSamples, 'listingPassed'), true), [
        'spacesSampled' => count($spaceSamples),
        'maxObjectsPerSpace' => MAX_OBJECTS_PER_SPACE,
    ]);

    foreach ($testObjects as $key => $body) {
        $uploadUrl = $client->upload(TARGET_SPACE, $key, $body, false, [
            'ContentType' => 'text/plain',
        ]);
        if ($uploadUrl === false) {
            throw new RuntimeException('Uploading a sample text object failed.');
        }
        $uploadedKeys[] = $key;
    }
    $record('upload-private-text', true, [
        'objectCount' => count($testObjects),
        'bytes' => array_sum(array_map('strlen', $testObjects)),
    ]);

    $firstPage = $client->listPage(TARGET_SPACE, true, [
        'Prefix' => "{$runPrefix}/",
        'MaxKeys' => 1,
    ]);
    $secondPage = is_array($firstPage) && is_string($firstPage['nextCursor'])
        ? $client->listPage(TARGET_SPACE, true, [
            'Prefix' => "{$runPrefix}/",
            'MaxKeys' => 1,
        ], $firstPage['nextCursor'])
        : false;
    $pagedKeys = is_array($firstPage) && is_array($secondPage)
        ? array_merge($firstPage['items'], $secondPage['items'])
        : [];
    sort($pagedKeys, SORT_STRING);
    $expectedKeys = array_keys($testObjects);
    sort($expectedKeys, SORT_STRING);
    $paginationPassed = is_array($firstPage)
        && $firstPage['isTruncated']
        && count($firstPage['items']) === 1
        && is_string($firstPage['nextCursor'])
        && $firstPage['nextCursor'] !== ''
        && is_array($secondPage)
        && !$secondPage['isTruncated']
        && $pagedKeys === $expectedKeys;
    $record('legacy-marker-pagination', $paginationPassed, [
        'pageSize' => 1,
        'pagesRead' => is_array($secondPage) ? 2 : 1,
    ]);
    if (!$paginationPassed) {
        throw new RuntimeException('Legacy marker pagination did not return both sample objects.');
    }

    $iteratedKeys = iterator_to_array($client->iterate(
        TARGET_SPACE,
        true,
        ['Prefix' => "{$runPrefix}/", 'MaxKeys' => 1],
        maxItems: 2,
        maxPages: 3
    ));
    sort($iteratedKeys, SORT_STRING);
    $iteratorPassed = $iteratedKeys === $expectedKeys;
    $record('bounded-pagination-iterator', $iteratorPassed, [
        'maxItems' => 2,
        'maxPages' => 3,
    ]);
    if (!$iteratorPassed) {
        throw new RuntimeException('Bounded pagination iterator did not return both sample objects.');
    }

    $listedKeys = $client->list(TARGET_SPACE, true, [
        'Prefix' => $runPrefix,
        'MaxKeys' => 10,
    ]);
    $listed = is_array($listedKeys) && in_array($testKey, $listedKeys, true);
    $record('list-upload-prefix', $listed, ['objectCount' => is_array($listedKeys) ? count($listedKeys) : 0]);
    if (!$listed) {
        throw new RuntimeException('Uploaded object was not returned by the prefix listing.');
    }

    $metadata = $client->get(TARGET_SPACE, $testKey);
    $metadataMatches = is_array($metadata) && ($metadata['Key'] ?? null) === $testKey;
    $record('get-object-metadata', $metadataMatches);
    if (!$metadataMatches) {
        throw new RuntimeException('Uploaded object metadata could not be retrieved.');
    }

    $downloadPath = tempnam(sys_get_temp_dir(), 'spaces-integration-');
    if ($downloadPath === false) {
        throw new RuntimeException('Could not allocate a temporary download file.');
    }
    $downloaded = $client->download(TARGET_SPACE, $testKey, $downloadPath);
    $downloadMatches = $downloaded && hash_equals(hash('sha256', $content), hash_file('sha256', $downloadPath));
    $record('download-and-compare', $downloadMatches, ['bytes' => $downloaded ? filesize($downloadPath) : 0]);
    if (!$downloadMatches) {
        throw new RuntimeException('Downloaded content did not match the uploaded text.');
    }

    $publicUrl = $client->publicUrl(TARGET_SPACE, $testKey);
    $publicUrlParts = parse_url($publicUrl);
    $publicUrlValid = is_array($publicUrlParts)
        && ($publicUrlParts['scheme'] ?? null) === 'https'
        && ($publicUrlParts['host'] ?? null) === TARGET_SPACE . ".{$region}.digitaloceanspaces.com";
    $record('build-public-url', $publicUrlValid);

    $presignedUrl = $client->presignedDownload(TARGET_SPACE, $testKey, '+5 minutes');
    $presignedValid = is_string($presignedUrl)
        && str_starts_with($presignedUrl, 'https://')
        && str_contains($presignedUrl, 'X-Amz-Signature=');
    $record('create-presigned-download-url', $presignedValid);

    $initialAcl = $client->getAcl(TARGET_SPACE, $testKey);
    $record('read-private-acl', $initialAcl === Client::ACL_PRIVATE, [
        'acl' => $initialAcl === false ? 'request-failed' : $initialAcl,
    ]);

    $aclSet = $client->setAcl(TARGET_SPACE, $testKey, Client::ACL_PRIVATE);
    $confirmedAcl = $aclSet ? $client->getAcl(TARGET_SPACE, $testKey) : false;
    $record('set-and-confirm-private-acl', $aclSet && $confirmedAcl === Client::ACL_PRIVATE);
} catch (Throwable $exception) {
    $exitCode = 1;
    $record('integration-run', false, [
        'errorType' => $exception::class,
        'message' => $exception->getMessage(),
    ]);
} finally {
    if ($uploadedKeys !== [] && $client instanceof Client) {
        $allDeleted = true;
        foreach ($uploadedKeys as $uploadedKey) {
            $allDeleted = $client->delete(TARGET_SPACE, $uploadedKey) && $allDeleted;
        }
        $record('cleanup-delete-objects', $allDeleted, ['objectCount' => count($uploadedKeys)]);
        if (!$allDeleted) {
            $exitCode = 1;
        }

        $allAbsent = true;
        foreach ($uploadedKeys as $uploadedKey) {
            $allAbsent = !$client->exists(TARGET_SPACE, $uploadedKey) && $allAbsent;
        }
        $record('cleanup-confirm-absent', $allAbsent, ['objectCount' => count($uploadedKeys)]);
        if (!$allAbsent) {
            $exitCode = 1;
        }
    }

    if (is_string($downloadPath) && is_file($downloadPath)) {
        unlink($downloadPath);
    }
}

$failedChecks = count(array_filter($checks, static fn (array $check): bool => !$check['passed']));
$summary = [
    'startedAt' => $startedAt,
    'finishedAt' => gmdate(DATE_ATOM),
    'targetSpace' => TARGET_SPACE,
    'writeScope' => $runPrefix,
    'spacesDiscovered' => isset($spaces) && is_array($spaces) ? count($spaces) : null,
    'spaceListings' => $spaceSamples,
    'checks' => $checks,
    'passedChecks' => count($checks) - $failedChecks,
    'failedChecks' => $failedChecks,
    'cleanupCompleted' => $uploadedKeys !== []
        ? !in_array(false, array_column(array_filter(
            $checks,
            static fn (array $check): bool => str_starts_with($check['name'], 'cleanup-')
        ), 'passed'), true)
        : null,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($exitCode === 0 && $failedChecks === 0 ? 0 : 1);
