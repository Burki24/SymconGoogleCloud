# ADR 0001 – Library und Kontogrenze

Status angenommen für die Grundlage am 23.11.2026.

## Entscheidung

Die eigenständige Library SymconGoogleCloud verwendet für das Konto einen
Splitter ohne eigene I/O-Instanz. Das Konto ist der Einstieg für den späteren
direkten Google-Zugriff. Gerätekonfigurator, Geräte, Karte, Anwesenheit und
weitere Dienste werden als getrennte Module nach dem Vorbild von SymconiCloud
angelegt. Sie lesen normalisierte Daten der zugewiesenen Kontoinstanzen.
OpenWoIst erhält in Schritt 3 einen Cachezugriff über `GCAC_GetDevices`.

Find Hub und offiziell freigegebene Google-APIs haben eigene
Autorisierungsgrenzen. Für öffentliche Dienste ist der dokumentierte OAuth-2.0-
Ablauf mit Berechtigungen je Dienst vorgesehen. Für Find Hub sind direkter
Zugriff und Protokollkompatibilität gesondert nachzuweisen. Google bietet
in seiner öffentlichen Scope-Liste keine Find-Hub-Standortberechtigung.
Die eigenständigen Referenzclients dienen als Protokollquellen.

## Cachevertrag

Der Export ordnet sich am Aufbau von `ICAC_GetDevices` aus SymconiCloud an.

- `success` bestätigt einen auswertbaren gespeicherten Snapshot.
- `state` ist im ersten Schritt `signedOut`, `cached` oder `unavailable`.
  `cached` bestätigt den gespeicherten Stand. Die Kontoverbindung hat ihren
  eigenen Status.
- `updatedAt` ist der Unix-Zeitpunkt des Snapshots oder `0`.
- `devices` ist eine Liste mit stabiler, opaker `id`, `name`, optionalem
  `model`, `deviceClass` und `location`. Ein Consumer kombiniert die Kennung
  mit der Konto-Instanz-ID. Der spätere Cloud-Adapter erzeugt die opake Kennung
  aus der Anbieteridentität vor dem Speichern im Cache.
- `location` ist optional und enthält dezimale Koordinaten, die ursprüngliche
  Messzeit `timestamp` als Unix-Sekunden, die horizontale Genauigkeit in
  Metern oder `null` und `isOld`. Fehlende Altersinformation wird als alt
  behandelt. Ein unbrauchbarer Standort wird als `null` zurückgegeben.
- `error` enthält einen festen Fehlercode oder ist beim erfolgreichen Export
  leer. Rohantworten, Zugangsdaten und Google-interne Identifikatoren sind
  keine Felder des Vertrags.

Das Konto speichert den Snapshot als ein Attribut. Einer leeren gültigen
Geräteliste steht der Zeitstempel des Snapshots zur Verfügung. Der lesende
Export benötigt weder eine Netzwerkverbindung noch eine Schreiboperation.

## Quellen

[Symcon SDK für PHP-Module](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module.md) ·
[SymconiCloud-Kontogrenze](https://github.com/Burki24/SymconiCloud/blob/dev/docs/adr/0001-library-and-account-boundary.md) ·
[Google OAuth-Scopes](https://developers.google.com/identity/protocols/oauth2/scopes) ·
[Google Find Hub für Partner](https://developers.google.com/nearby/fast-pair/landing-page-find-hub) ·
[Eigenständiger Find-Hub-Referenzclient](https://github.com/leonboe1/GoogleFindMyTools) ·
[Google Fotos API](https://developers.google.com/photos/support/updates)
