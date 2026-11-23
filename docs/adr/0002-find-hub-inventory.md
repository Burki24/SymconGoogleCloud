# ADR 0002 – Find-Hub-Geräteliste

Status offline umgesetzt am 23.11.2026. Die Google-Abnahme steht aus.

## Entscheidung

Der Konto-Splitter sendet eine explizit ausgelöste, lesende HTTPS-Anfrage
an den festen Google-Endpunkt `android.googleapis.com/nova/nbe_list_devices`.
Der Aufruf verwendet ein bereits für Find Hub autorisiertes Zugriffstoken
aus dem Aktionsformular. Die Kontoinstanz speichert das Token nicht.
Transport und Parser arbeiten mit begrenzten Antwortgrößen. TLS wird
geprüft, Weiterleitungen werden verworfen und der Servername ist fest.
Upstream-Projekte dienen als Protokollreferenz. Der PHP-Code ist
eigenständig.

Die binäre Antwort liefert Android-Geräte und Spot-Geräte mit verwertbarer
kanonischer Kennung. Der Cache enthält ausschließlich die normalisierte
Liste. Der Geräteexport verwendet eine SHA-256-Kennung und die Kontoinstanz
als Kontext. Registrierungs- und Standortberichte verbleiben außerhalb
dieses Inventarabrufs. Ein späterer direkter Standortabruf benötigt die
passende kontogebundene Sitzung und Entschlüsselung.

Der Kontoabruf läuft pro Instanz serialisiert. Bei Ablehnung, einer
beschädigten Antwort oder einem Transportfehler bleibt der letzte
gültige Gerätestand erhalten. HTTP 429 und HTTP 503 mit Retry-After
setzen eine persistente Wartefrist. Eine leere Protobuf-Liste ist ein
gültiger neuer Gerätestand. Ein nichtleeres, nicht auswertbares
Inventar gilt als Fehler.

## Nachweis

Die Tests führen Formularaktion und Skriptfunktion über SymconStubs aus.
Nur der HTTPS-Transport wird durch synthetische Antworten ersetzt. Geprüft
werden Aufrufziel und Anfrageformat, beide Identitätszweige, Eingabe- und
Antwortfehler, Cacheerhalt, Kontotrennung und die Wartefrist. Ein Live-Test
muss später Zugriffstoken, Annahme durch Google und Geräteabdeckung bestätigen.

[Find-Hub-Inventarbeschreibung](https://github.com/beabel/ioBroker.googlefindmydevice/blob/482635c46b8f190c53f7e14c77f855c23546f56b/lib/nova-api.js#L22-L81) ·
[Protokollfelder](https://github.com/leonboe1/GoogleFindMyTools/blob/660be5395702ff7f1a6174f4ec4c865a57c7b527/ProtoDecoders/DeviceUpdate.proto) ·
[Protobuf-Wire-Format](https://protobuf.dev/programming-guides/encoding/)
