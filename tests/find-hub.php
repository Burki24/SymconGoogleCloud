<?php

declare(strict_types=1);

use SymconGoogleCloud\FindHubClient;
use SymconGoogleCloud\FindHubException;
use SymconGoogleCloud\FindHubInventoryProtocol;

final class FindHubFixtureAccount extends GoogleCloudAccount
{
    public array $responses = [];
    public array $requests = [];

    protected function CreateFindHubClient(): FindHubClient
    {
        return new FindHubClient(function (string $url, array $headers, string $body): array
        {
            $this->requests[] = compact('url', 'headers', 'body');
            $response = array_shift($this->responses) ?? throw new RuntimeException('Unexpected Find Hub request.');
            if ($response instanceof FindHubException) {
                throw $response;
            }
            return $response;
        });
    }
}

// Each field in this fixture stays under one byte of varint length.
$bytes = static fn (int $field, string $value): string => chr(8 * $field + 2) . chr(strlen($value)) . $value;
$phoneIDs = $bytes(1, $bytes(1, 'test-phone-id')) . $bytes(1, $bytes(1, 'test-old-phone-id'));
$trackerIDs = $bytes(1, $bytes(1, 'test-tracker-id'));
$phoneRow = $bytes(1, "\x10\x01" . $bytes(1, $bytes(2, $phoneIDs))) . $bytes(5, 'Test phone');
$tagRow = $bytes(1, "\x10\x02" . $bytes(3, $trackerIDs)) . $bytes(5, 'Test tracker')
    . $bytes(4, $bytes(1, "\x92\x02\x09Tag model") . $bytes(2, 'synthetic-encrypted-location'));
$inventory = $bytes(2, $phoneRow) . $bytes(2, $tagRow);

$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $metadata->getValue();
$modules[$moduleID]['Class'] = FindHubFixtureAccount::class;
$metadata->setValue(null, $modules);
$fixtureID = IPS_CreateInstance($moduleID);
/** @var FindHubFixtureAccount $fixture */
$fixture = IPS\InstanceManager::getInstanceInterface($fixtureID);
check($fixture->requests === [], 'Creating an account must not contact Google.');

$token = 'synthetic-bearer-token';
check(
    !GCAC_RefreshDevices($fixtureID, "synthetic\r\ninjected") && $fixture->requests === [],
    'An invalid bearer token must be rejected before creating a request.'
);

$fixture->responses[] = ['status' => 200, 'body' => $inventory, 'retryAfter' => ''];
$before = time();
check(GCAC_RefreshDevices($fixtureID, $token), 'The explicit refresh must accept a valid device inventory.');
$first = $getDevices($fixtureID);
check(
    $first['success'] && $first['updatedAt'] >= $before && $first['updatedAt'] <= time(),
    'A successful response must record the retrieval time.'
);
check($first['devices'] === [
    [
        'id'          => hash('sha256', 'test-phone-id'),
        'name'        => 'Test phone',
        'model'       => null,
        'deviceClass' => 'android',
        'location'    => null
    ],
    [
        'id'          => hash('sha256', 'test-tracker-id'),
        'name'        => 'Test tracker',
        'model'       => 'Tag model',
        'deviceClass' => 'bluetoothTracker',
        'location'    => null
    ]
], 'The inventory must decode both branches and exclude provider IDs and location payloads.');
check($getDevices($anotherAccountID) === $empty, 'Refreshing one account must not change another.');
check(IPS_GetInstance($fixtureID)['InstanceStatus'] === IS_ACTIVE, 'The ready account must show its cached inventory.');
check(count($fixture->requests) === 1, 'The cached export must not trigger another request.');
$request = $fixture->requests[0];
check(
    $request['url'] === 'https://android.googleapis.com/nova/nbe_list_devices'
    && in_array('Authorization: Bearer ' . $token, $request['headers'], true)
    && preg_match('/\A\x0a\x28\x08\x02\x1a\x24[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/D', $request['body']) === 1,
    'The inventory must send a bearer-authenticated binary list request to the fixed Google endpoint.'
);
check(
    !str_contains(json_encode($fixture->GetPersistence(), JSON_THROW_ON_ERROR), $token),
    'The one-time bearer token must not be persisted.'
);

foreach ([
    [401, 'synthetic confidential error body', 201],
    [403, '', 201],
    [302, '', 202],
    [200, "\x12\x7f\x01", 202],
    [200, str_repeat('x', FindHubInventoryProtocol::MAX_RESPONSE_BYTES + 1), 202],
    [503, '', 202]
] as [$httpStatus, $body, $moduleStatus]) {
    $fixture->responses[] = ['status' => $httpStatus, 'body' => $body, 'retryAfter' => ''];
    check(!GCAC_RefreshDevices($fixtureID, $token), 'A failed request must report failure.');
    check(IPS_GetInstance($fixtureID)['InstanceStatus'] === $moduleStatus, 'The account must report the applicable failure status.');
    check($getDevices($fixtureID) === $first, 'A failed request must preserve the last good inventory.');
}

$fixture->responses[] = new FindHubException('network');
check(
    !GCAC_RefreshDevices($fixtureID, $token)
    && IPS_GetInstance($fixtureID)['InstanceStatus'] === 202
    && $getDevices($fixtureID) === $first,
    'A transport failure must preserve the last good inventory.'
);

$fixture->responses[] = ['status' => 429, 'body' => '', 'retryAfter' => '120'];
check(!GCAC_RefreshDevices($fixtureID, $token), 'Google rate limiting must report failure.');
$requestCount = count($fixture->requests);
IPS_ApplyChanges($fixtureID);
check(
    !GCAC_RefreshDevices($fixtureID, $token) && count($fixture->requests) === $requestCount,
    'Retry-After must block requests across ApplyChanges.'
);
check(
    IPS_GetInstance($fixtureID)['InstanceStatus'] === 203 && $getDevices($fixtureID) === $first,
    'A cooldown must retain the last valid cache.'
);

(new ReflectionMethod(IPSModuleStrict::class, 'WriteAttributeInteger'))->invoke($fixture, 'FindHubRetryAt', time() - 1);
$fixture->responses[] = ['status' => 200, 'body' => '', 'retryAfter' => ''];
IPS_RequestAction($fixtureID, 'RefreshDevices', $token);
check(
    $getDevices($fixtureID)['success'] && $getDevices($fixtureID)['devices'] === [],
    'The form action after the cooldown must accept a valid empty protobuf list.'
);

$retryTime = time() + 180;
$fixture->responses[] = ['status' => 503, 'body' => '', 'retryAfter' => gmdate(DATE_RFC7231, $retryTime)];
check(
    !GCAC_RefreshDevices($fixtureID, $token)
    && $fixture->GetPersistence()['attributes']['FindHubRetryAt'] === $retryTime
    && IPS_GetInstance($fixtureID)['InstanceStatus'] === 203,
    'A dated Retry-After must keep the exact UTC retry time.'
);

echo "Find Hub device request, account cache and failure handling passed.\n";
