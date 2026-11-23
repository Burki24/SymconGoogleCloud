<?php

declare(strict_types=1);

namespace SymconGoogleCloud;

final class DeviceInventory
{
    private const DISCONNECTED = ['success' => false, 'state' => 'signedOut', 'updatedAt' => 0, 'devices' => [], 'error' => 'notConnected'];
    private const INVALID_CACHE = ['success' => false, 'state' => 'unavailable', 'updatedAt' => 0, 'devices' => [], 'error' => 'invalidCache'];

    public static function read(string $stored, int $now): array
    {
        if ($stored === '') {
            return self::DISCONNECTED;
        }

        $snapshot = json_decode($stored, false, 32);
        $updatedAt = $snapshot->updatedAt ?? null;
        $rows = $snapshot->devices ?? null;
        if (!self::validTime($updatedAt, $now) || !is_array($rows)) {
            return self::INVALID_CACHE;
        }

        $devices = [];
        foreach ($rows as $row) {
            $id = $row->id ?? null;
            if (!is_string($id) || trim($id) === '' || isset($devices[$id])) {
                return self::INVALID_CACHE;
            }

            $class = $row->deviceClass ?? null;
            $devices[$id] = [
                'id'          => $id,
                'name'        => is_string($row->name ?? null) ? $row->name : '',
                'model'       => is_string($row->model ?? null) ? $row->model : null,
                'deviceClass' => in_array($class, ['android', 'bluetoothTracker'], true) ? $class : 'unknown',
                'location'    => self::location($row->location ?? null, $now)
            ];
        }
        return ['success' => true, 'state' => 'cached', 'updatedAt' => $updatedAt, 'devices' => array_values($devices), 'error' => ''];
    }

    private static function location(mixed $input, int $now): ?array
    {
        if (!$input instanceof \stdClass) {
            return null;
        }
        $latitude = $input->latitude ?? null;
        $longitude = $input->longitude ?? null;
        $timestamp = $input->timestamp ?? null;
        if (!self::finiteNumber($latitude) || abs($latitude) > 90 || !self::finiteNumber($longitude) || abs($longitude) > 180
            || !self::validTime($timestamp, $now)) {
            return null;
        }

        $accuracy = $input->horizontalAccuracy ?? null;
        return [
            'latitude'           => $latitude,
            'longitude'          => $longitude,
            'timestamp'          => $timestamp,
            'horizontalAccuracy' => self::finiteNumber($accuracy) && $accuracy > 0 ? $accuracy : null,
            'isOld'              => ($input->isOld ?? true) !== false
        ];
    }

    private static function validTime(mixed $timestamp, int $now): bool
    {
        return is_int($timestamp) && $timestamp > 0 && $timestamp <= $now + 300;
    }

    private static function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite($value);
    }
}
