<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$stubs = getenv('SYMCON_STUBS_PATH') ?: __DIR__ . '/stubs';
require_once $stubs . '/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary($root . '/library.json');
$moduleID = json_decode(file_get_contents($root . '/Google Cloud Account/module.json'), true, 64, JSON_THROW_ON_ERROR)['id'];
$accountID = IPS_CreateInstance($moduleID);
$anotherAccountID = IPS_CreateInstance($moduleID);
$account = IPS\InstanceManager::getInstanceInterface($accountID);
$getDevices = static fn (int $id): array => json_decode(GCAC_GetDevices($id), true, 64, JSON_THROW_ON_ERROR);
$writeCache = new ReflectionMethod(IPSModuleStrict::class, 'WriteAttributeString');
$setCache = static function (string $value) use ($writeCache, $account): void
{
    $writeCache->invoke($account, 'DeviceCache', $value);
};

$empty = ['success' => false, 'state' => 'signedOut', 'updatedAt' => 0, 'devices' => [], 'error' => 'notConnected'];
check($getDevices($accountID) === $empty, 'A new account must report the unconnected state.');
IPS_ApplyChanges($accountID);
IPS_ApplyChanges($accountID);
check(
    IPS_GetInstance($accountID)['InstanceStatus'] === IS_INACTIVE && $getDevices($accountID) === $empty,
    'Applying the initial configuration twice must leave the account inactive.'
);

$now = time();
$phone = [
    'id'          => 'synthetic-phone',
    'name'        => 'Testgerät',
    'model'       => 'Testmodell',
    'deviceClass' => 'android',
    'location'    => [
        'latitude'           => 0,
        'longitude'          => 18.75,
        'timestamp'          => $now - 30,
        'horizontalAccuracy' => 12.5,
        'isOld'              => false
    ]
];
$tag = [
    'id'          => 'synthetic-tag',
    'name'        => 'Testgerät',
    'model'       => null,
    'deviceClass' => 'bluetoothTracker',
    'location'    => [
        'latitude'           => -24.5,
        'longitude'          => 0,
        'timestamp'          => $now - 400,
        'horizontalAccuracy' => null,
        'isOld'              => true
    ]
];
$snapshot = [
    'updatedAt'   => $now,
    'devices'     => [
        array_replace_recursive($phone, ['location' => ['providerPayload' => 'fixture-private-location']]),
        $tag + ['providerDeviceID' => 'fixture-private-provider-id']
    ],
    'accessToken' => 'fixture-private-access-token'
];
$setCache(json_encode($snapshot, JSON_THROW_ON_ERROR));
$expected = ['success' => true, 'state' => 'cached', 'updatedAt' => $now, 'devices' => [$phone, $tag], 'error' => ''];
check($getDevices($accountID) === $expected, 'The export must preserve both normalized fixes and exclude source payloads.');
check($getDevices($anotherAccountID) === $empty, 'The cache must be scoped to its own account.');
IPS_ApplyChanges($accountID);
check($getDevices($accountID) === $expected, 'Reapplying configuration must preserve the saved snapshot.');

$snapshot['devices'][0]['location']['latitude'] = 91;
$snapshot['devices'][1]['location']['timestamp'] = 0;
$setCache(json_encode($snapshot, JSON_THROW_ON_ERROR));
$devices = $getDevices($accountID)['devices'];
check(
    count($devices) === 2 && $devices[0]['location'] === null && $devices[1]['location'] === null,
    'Invalid positions must be omitted without removing recognized devices.'
);

$setCache(json_encode(['updatedAt' => $now, 'devices' => []], JSON_THROW_ON_ERROR));
check(
    $getDevices($accountID) === array_replace($expected, ['devices' => []]),
    'A saved empty inventory must be distinguishable from a disconnected account.'
);

$invalid = ['success' => false, 'state' => 'unavailable', 'updatedAt' => 0, 'devices' => [], 'error' => 'invalidCache'];
foreach ([
    '{',
    '{"updatedAt":0,"devices":[]}',
    json_encode(['updatedAt' => $now, 'devices' => new stdClass()], JSON_THROW_ON_ERROR),
    json_encode(['updatedAt' => $now, 'devices' => [$phone, $phone]], JSON_THROW_ON_ERROR)
] as $badCache) {
    $setCache($badCache);
    check($getDevices($accountID) === $invalid, 'An invalid snapshot must not appear to be a successful empty inventory.');
}

echo "Google Cloud account and device cache tests passed.\n";
require __DIR__ . '/validate_structure.php';
