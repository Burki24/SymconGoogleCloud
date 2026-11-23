<?php

declare(strict_types=1);

use Burki24\SymconModuleHelper\DebugFlowHelper;
use SymconGoogleCloud\DeviceInventory;

require_once __DIR__ . '/../libs/DeviceInventory.php';
require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';

class GoogleCloudAccount extends IPSModuleStrict
{
    use DebugFlowHelper;

    public function Create(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterAttributeString('DeviceCache', '');
        }, true);
    }

    public function ApplyChanges(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            if ($this->GetStatus() !== IS_INACTIVE) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Das Konto bleibt bis zur Verbindung inaktiv.', ['Status' => IS_INACTIVE]);
                $this->SetStatus(IS_INACTIVE);
            }
        }, true);
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
}
