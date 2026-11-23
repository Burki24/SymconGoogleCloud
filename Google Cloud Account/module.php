<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\DebugFlowHelper;
use SymconGoogleCloud\DeviceInventory;
use SymconGoogleCloud\FindHubClient;
use SymconGoogleCloud\FindHubException;

require_once __DIR__ . '/../libs/DeviceInventory.php';
require_once __DIR__ . '/../libs/FindHubClient.php';
require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';

class GoogleCloudAccount extends IPSModuleStrict
{
    use DebugFlowHelper;

    private const STATUS_AUTHORIZATION = 201;
    private const STATUS_REQUEST_FAILED = 202;
    private const STATUS_RATE_LIMIT = 203;

    public function Create(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterAttributeString('DeviceCache', '');
            $this->RegisterAttributeInteger('FindHubRetryAt', 0);
        }, true);
    }

    public function ApplyChanges(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            if ($this->GetStatus() === IS_CREATING) {
                $this->setAccountStatus(IS_INACTIVE);
            }
        }, true);
    }

    public function RequestAction(string $Ident, #[\SensitiveParameter] mixed $Value): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($Ident !== 'RefreshDevices') {
                throw new InvalidArgumentException('Unsupported account action.');
            }
            try {
                $this->RefreshDevices(is_string($Value) ? $Value : '');
            } finally {
                $this->UpdateFormField('FindHubAccessToken', 'value', '');
            }
        }, true);
    }

    /** Fetches the inventory once using an existing authorized Find Hub bearer token. */
    public function RefreshDevices(#[\SensitiveParameter] string $AccessToken): bool
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($AccessToken): bool
        {
            $semaphore = 'SymconGoogleCloud.FindHub.' . $this->InstanceID;
            if (!IPS_SemaphoreEnter($semaphore, 1000)) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Eine Geräteabfrage läuft bereits.');
                return false;
            }
            try {
                $now = time();
                if ($this->ReadAttributeInteger('FindHubRetryAt') > $now) {
                    throw new FindHubException('rateLimit');
                }
                if (!FindHubClient::validAccessToken($AccessToken)) {
                    throw new FindHubException('authorization');
                }
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Die Geräteliste wird bei Google Find Hub abgefragt.');
                $devices = $this->CreateFindHubClient()->listDevices($AccessToken, $now);
                $snapshot = json_encode(['updatedAt' => time(), 'devices' => $devices], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if ($this->ReadAttributeString('DeviceCache') !== $snapshot && !$this->WriteAttributeString('DeviceCache', $snapshot)) {
                    throw new FindHubException('storage');
                }
                $this->WriteAttributeInteger('FindHubRetryAt', 0);
                $this->setAccountStatus(IS_ACTIVE);
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Die Geräteliste wurde im lokalen Cache gespeichert.', ['AnzahlGeräte' => count($devices)]);
                return true;
            } catch (FindHubException $failure) {
                if ($failure->retryAt > 0) {
                    $this->WriteAttributeInteger('FindHubRetryAt', $failure->retryAt);
                }
                $this->setAccountStatus(match ($failure->reason) {
                    'authorization'  => self::STATUS_AUTHORIZATION,
                    'rateLimit'      => self::STATUS_RATE_LIMIT,
                    default          => self::STATUS_REQUEST_FAILED
                });
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', $this->refreshFailureMessage($failure->reason));
                return false;
            } finally {
                IPS_SemaphoreLeave($semaphore);
            }
        });
    }

    /** Returns the local device inventory as JSON. */
    public function GetDevices(): string
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): string
        {
            $snapshot = DeviceInventory::read($this->ReadAttributeString('DeviceCache'), time());
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Der lokale Gerätestand wurde ausgewertet.', [
                'Schnittstelle' => 'DeviceCache',
                'Zustand'       => $snapshot['state'],
                'AnzahlGeräte'  => count($snapshot['devices'])
            ]);
            return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        });
    }

    protected function CreateFindHubClient(): FindHubClient
    {
        return new FindHubClient();
    }

    private function setAccountStatus(int $status): void
    {
        if ($this->GetStatus() !== $status) {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Der Kontostatus wird aktualisiert.', ['Status' => $status]);
            $this->SetStatus($status);
        }
    }

    private function refreshFailureMessage(string $reason): string
    {
        return match ($reason) {
            'authorization'  => 'Die Geräteabfrage benötigt ein gültiges Find-Hub-Zugriffstoken.',
            'rateLimit'      => 'Die Geräteabfrage wartet auf das Ende der Sperrfrist.',
            'dependency'     => 'Die PHP-Erweiterung cURL ist nicht verfügbar.',
            'network'        => 'Google Find Hub konnte nicht erreicht werden.',
            'response'       => 'Die Antwort enthält keine auswertbare Geräteliste.',
            'storage'        => 'Die Geräteliste konnte lokal nicht gespeichert werden.',
            default          => 'Google Find Hub hat die Geräteabfrage abgelehnt.'
        };
    }
}
