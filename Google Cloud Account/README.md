# Google Cloud Konto

Das Konto dient als gemeinsame Quelle für Google-Geräte und deren
Standortdaten. Es gibt den zuletzt lokal verfügbaren Gerätestand als JSON
aus. Das Konto ist in diesem Entwicklungsstand inaktiv. Die Google-Anmeldung
folgt mit der Find-Hub-Anbindung.

## Öffentliche Funktion

`GCAC_GetDevices(int $InstanceID): string` liest den lokalen Gerätestand.
`InstanceID` bezeichnet die Konto-Instanz.

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
