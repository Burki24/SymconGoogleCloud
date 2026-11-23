<?php

declare(strict_types=1);

namespace SymconGoogleCloud;

require_once __DIR__ . '/FindHubException.php';

/** Binary messages used by the Find Hub inventory endpoint. */
final class FindHubInventoryProtocol
{
    public const MAX_RESPONSE_BYTES = 1024 * 1024;

    public static function request(): string
    {
        $id = random_bytes(16);
        $id[6] = chr((ord($id[6]) & 0x0f) | 0x40);
        $id[8] = chr((ord($id[8]) & 0x3f) | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($id), 4));

        // The wrapper and its two known fields have single-byte tags and lengths.
        return "\x0a\x28\x08\x02\x1a\x24" . $uuid;
    }

    public static function devices(string $body): array
    {
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new FindHubException('response');
        }

        $devices = [];
        foreach (self::fields($body, [2 => 2])[2] ?? [] as $entry) {
            $device = self::device($entry);
            if ($device !== null) {
                $devices[$device['id']] = $device;
            }
        }

        // An empty protobuf message is a valid empty list. A non-empty payload
        // with no supported entries could be an incompatible response.
        if ($devices === [] && $body !== '') {
            throw new FindHubException('response');
        }
        return array_values($devices);
    }

    private static function device(string $entry): ?array
    {
        $metadata = self::fields($entry, [1 => 2, 4 => 2, 5 => 2]);
        $identifier = self::fields($metadata[1][0] ?? '', [1 => 2, 2 => 0, 3 => 2]);
        $type = $identifier[2][0] ?? 0;
        if ($type !== 1 && $type !== 2) {
            return null;
        }

        $ids = $type === 1
            ? (self::fields($identifier[1][0] ?? '', [2 => 2])[2][0] ?? '')
            : ($identifier[3][0] ?? '');
        $idEntry = self::fields($ids, [1 => 2])[1][0] ?? '';
        $id = self::fields($idEntry, [1 => 2])[1][0] ?? '';
        if ($id === '') {
            return null;
        }

        $info = self::fields($metadata[4][0] ?? '', [1 => 2]);
        $registration = self::fields($info[1][0] ?? '', [34 => 2]);
        $name = self::text($metadata[5][0] ?? '');
        $model = self::text($registration[34][0] ?? '');
        return [
            'id'          => hash('sha256', $id),
            'name'        => $name,
            'model'       => $model === '' ? null : $model,
            'deviceClass' => $type === 1 ? 'android' : 'bluetoothTracker',
            'location'    => null
        ];
    }

    /** @return array<int, list<int|string>> Values for the requested field numbers and wire types. */
    private static function fields(string $message, array $requested): array
    {
        $fields = [];
        $offset = 0;
        $limit = strlen($message);
        $count = 0;
        while ($offset < $limit) {
            $tag = self::varint($message, $offset, $limit);
            if ($tag < 8 || $tag > 0xffffffff || ++$count > 16384) {
                throw new FindHubException('response');
            }

            $wire = $tag & 7;
            $field = $tag >> 3;
            $wanted = ($requested[$field] ?? null) === $wire;
            if ($wire === 0) {
                $number = self::varint($message, $offset, $limit);
                if ($wanted) {
                    $fields[$field][] = $number;
                }
                continue;
            }

            $size = match ($wire) {
                1       => 8,
                2       => self::varint($message, $offset, $limit),
                5       => 4,
                default => -1
            };
            if ($size < 0 || $size > $limit - $offset) {
                throw new FindHubException('response');
            }
            if ($wanted) {
                $fields[$field][] = substr($message, $offset, $size);
            }
            $offset += $size;
        }
        return $fields;
    }

    private static function varint(string $message, int &$offset, int $limit): int
    {
        $value = 0;
        for ($shift = 0; $shift < 64 && $offset < $limit; $shift += 7) {
            $byte = ord($message[$offset++]);
            if ($shift === 63 && $byte > 1) {
                break;
            }
            $value |= ($byte & 0x7f) << $shift;
            if ($byte < 0x80) {
                return $value;
            }
        }
        throw new FindHubException('response');
    }

    private static function text(string $value): string
    {
        return preg_match('//u', $value) === 1 ? $value : '';
    }
}
