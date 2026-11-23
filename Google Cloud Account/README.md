# Google Cloud Konto

Das Konto fragt die Find-Hub-Geräteliste direkt bei Google ab und hält sie
lokal als JSON bereit. Es unterscheidet Android-Geräte und Bluetooth-Tracker
mit einer verwendbaren Gerätekennung. Die eigene Anmeldung und das Lesen
von Standortberichten folgen in weiteren Ausbaustufen.

## Geräteliste abrufen

Wenn ein gültiges Find-Hub-Zugriffstoken vorliegt, kann es im Aktionsfeld
**Find-Hub-Zugriffstoken** eingegeben werden. **Geräte aktualisieren**
sendet eine einmalige Anfrage an Google und leert das Eingabefeld.
Das Modul speichert das Token weder im Konto noch im Gerätecache.
Bei einem Fehler bleibt die letzte gültige Liste abrufbar. Der
Instanzstatus zeigt den Ausgang des letzten Abrufs an.

## Öffentliche Funktionen

| Funktion | Wirkung |
| --- | --- |
| GCAC_RefreshDevices(int $InstanceID, string $AccessToken): bool | Ruft die Geräteliste einmal ab. Gibt bei erfolgreicher Aktualisierung true zurück. |
| GCAC_GetDevices(int $InstanceID): string | Liest den lokalen Gerätestand als JSON. |

`InstanceID` bezeichnet die Konto-Instanz. Ein aktueller Geräteabruf speichert
`location` als `null`, bis der Standortabruf verfügbar ist.

| Feld | Bedeutung |
| --- | --- |
| `success` | Ein auswertbarer Cache ist vorhanden. |
| `state` | `signedOut` ohne Cache, `cached` bei einem verfügbaren Stand oder `unavailable` bei beschädigten Daten. |
| `updatedAt` | Zeitpunkt des Gerätestands in Unix-Sekunden oder `0`. |
| `devices` | Geräteliste mit Kennung, Name, Modell, Klasse und optionaler Position. |
| `error` | `notConnected`, `invalidCache` oder eine leere Zeichenfolge. |

Eine Position enthält Breitengrad, Längengrad, Messzeit als Unix-Sekunden,
Genauigkeit in Metern oder `null` und das Alterskennzeichen `isOld`. Ein
Gerät ohne verwendbare Position hat `location: null`.
