<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$json = static fn (string $path): array => json_decode(file_get_contents($root . '/' . $path), true, 64, JSON_THROW_ON_ERROR);
$library = $json('library.json');
$account = $json('Google Cloud Account/module.json');
$guid = '/^\{[0-9A-F]{8}(?:-[0-9A-F]{4}){3}-[0-9A-F]{12}\}$/D';
if (!preg_match($guid, $library['id']) || !preg_match($guid, $account['id']) || $library['id'] === $account['id']
    || $library['compatibility']['version'] !== '9.0' || $account['type'] !== 2 || $account['prefix'] !== 'GCAC') {
    throw new RuntimeException('Invalid library or account module metadata.');
}
$json('Google Cloud Account/form.json');
$json('Google Cloud Account/locale.json');
$bundle = $json('libs/helper/manifest.json')['helpers'];
$subscriptions = $json('.helper-sync.json')['helpers'];
if (array_keys($bundle) !== array_keys($subscriptions)) {
    throw new RuntimeException('Helper subscriptions do not match the bundle.');
}
foreach ($bundle as $name => $helper) {
    if ($helper['path'] !== $subscriptions[$name]['target']) {
        throw new RuntimeException('Unexpected helper target for ' . $name);
    }
    foreach ([$helper, ...($helper['dependencies'] ?? [])] as $entry) {
        if (hash_file('sha256', $root . '/' . $entry['path']) !== $entry['sha256']) {
            throw new RuntimeException('Helper integrity check failed for ' . $entry['path']);
        }
    }
}

echo "SymconGoogleCloud structure and bundled helpers passed.\n";
