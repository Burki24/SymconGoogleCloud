<?php

declare(strict_types=1);

namespace SymconGoogleCloud;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

require_once __DIR__ . '/FindHubInventoryProtocol.php';

final class FindHubClient
{
    private const INVENTORY_URL = 'https://android.googleapis.com/nova/nbe_list_devices';

    /** @param null|Closure(string, list<string>, string): array{status: int, body: string, retryAfter: string} $transport */
    public function __construct(private readonly ?Closure $transport = null)
    {
    }

    public static function validAccessToken(#[\SensitiveParameter] string $accessToken): bool
    {
        return strlen($accessToken) <= 8192 && preg_match('#\A[A-Za-z0-9._~+/-]+=*\z#D', $accessToken) === 1;
    }

    public function listDevices(#[\SensitiveParameter] string $accessToken, int $now): array
    {
        if (!self::validAccessToken($accessToken)) {
            throw new FindHubException('authorization');
        }

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept-Language: en-US',
            'Authorization: Bearer ' . $accessToken
        ];
        $transport = $this->transport ?? self::send(...);
        $response = $transport(self::INVENTORY_URL, $headers, FindHubInventoryProtocol::request());

        return match ($response['status']) {
            200      => FindHubInventoryProtocol::devices($response['body']),
            401, 403 => throw new FindHubException('authorization'),
            429      => throw new FindHubException('rateLimit', self::retryAt($response['retryAfter'], $now)),
            503      => $response['retryAfter'] !== ''
                ? throw new FindHubException('rateLimit', self::retryAt($response['retryAfter'], $now))
                : throw new FindHubException('http'),
            default  => throw new FindHubException('http')
        };
    }

    private static function send(string $url, #[\SensitiveParameter] array $headers, string $body): array
    {
        if (!extension_loaded('curl')) {
            throw new FindHubException('dependency');
        }
        $curl = curl_init();
        if ($curl === false) {
            throw new FindHubException('network');
        }
        $response = '';
        $retryAfter = '';
        $tooLarge = false;
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'SymconGoogleCloud/0.1',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfter): int
            {
                if (str_starts_with($line, 'HTTP/')) {
                    $retryAfter = '';
                } elseif (strncasecmp($line, 'Retry-After:', 12) === 0) {
                    $retryAfter = trim(substr($line, 12));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response, &$tooLarge): int
            {
                if (strlen($response) + strlen($chunk) > FindHubInventoryProtocol::MAX_RESPONSE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            }
        ]);
        if (curl_exec($curl) === false) {
            throw new FindHubException($tooLarge ? 'response' : 'network');
        }
        return ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $response, 'retryAfter' => $retryAfter];
    }

    private static function retryAt(string $value, int $now): int
    {
        if (preg_match('/\A[0-9]+\z/D', $value) === 1) {
            return $now + max(1, min(PHP_INT_MAX - $now, (int) $value));
        }
        $date = str_contains($value, "\0") ? false : DateTimeImmutable::createFromFormat(DATE_RFC7231, $value, new DateTimeZone('UTC'));
        return $date !== false && $date->getTimestamp() > $now ? $date->getTimestamp() : $now + 300;
    }
}
