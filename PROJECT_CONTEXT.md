# SymconGoogleCloud Entwicklung

SymconGoogleCloud soll SymconiCloud in Modulaufteilung und Bedienung möglichst
entsprechen. Die Library verbindet sich direkt mit Google. OpenWoIst verwendet
die Kontoinstanz als lokale Standortquelle. Maßgeblich für Google-spezifische
Abweichungen sind die verfügbaren Schnittstellen und die Fähigkeiten der Geräte.

## Umsetzungsplan

| Schritt | Umsetzung | Abnahme |
| --- | --- | --- |
| 1 · Grundlage | Eigenständige Library, Konto-Splitter, persistenter Gerätesnapshot und lesender Export `GCAC_GetDevices`. | Zwei synthetische Geräte bleiben separat. Messzeit, Genauigkeit und Alter bleiben erhalten. Leerer, abgemeldeter und beschädigter Cache sind unterscheidbar. |
| 2 · Direkter Find-Hub-Abruf | Kontogebundene Google-Anmeldung, Sitzung, Geräteenumeration, Standortberichte und Entschlüsselung. Geräte-ID und Ortungen in den Cachevertrag überführen. | Synthetische Protokollantworten für Android und Bluetooth-Tag. Erneute Anmeldung, Ratenbegrenzung, Teilfehler, Zeitüberschreitungen und verspätete Berichte prüfen. Ein Hardwaretest bleibt offen. |
| 3 · Geräte und OpenWoIst | Wie in SymconiCloud Konto, Gerätekonfigurator und Geräteinstanz getrennt halten. OpenWoIst erhält eine eigene Google-Quellenauswahl. Geräte- und Akkuwerte sowie Aktionen folgen den tatsächlich gemeldeten Fähigkeiten. | Zwei Konten und gleichnamige Geräte getrennt zuordnen. Kachel und IPSView zeigen dieselben Positionen. Suchton am vorgesehenen Gerät prüfen, sobald Hardware vorliegt. |
| 4 · WoIst und Anwesenheit | Eigene WoIst-Karte und Anwesenheitsmodul mit gemeinsamer Geräteauswahl, mehreren Konten für die Karte, Zonen und Gesamtanwesenheit. Vorhandene Karten-, Designer- und Geofencing-Helper verwenden. | Kachel und IPSView, Kontofilter, Geräteauswahl, Zonenübergänge und Wiederanlauf mit synthetischen Daten prüfen. |
| 5 · Google-Dienste über OAuth | Offizielle OAuth-2.0-Autorisierung dienstbezogen erschließen. Kontakte und Fotos sind die Pendants aus SymconiCloud. Für Kalender besteht bereits OpenCalendar. | Berechtigungen, Erneuerung und Widerruf je Dienst prüfen. Bei Fotos gelten die Google-Picker- und Library-Grenzen. |
| 6 · Funktionsabgleich | Unterstützte Find-Hub-Aktionen und weitere Google-Dienste anhand belegter Schnittstellen ergänzen. Für Freigaben, Verloren-Modus und Fernlöschung zunächst Zugriff und Gerätewirkung separat belegen. | Funktionsmatrix anhand der Referenz aktualisieren. Zustandsänderungen und irreversible Aktionen erfordern eine eigene Abnahme. |

Schritt 1 ist umgesetzt. Die Cache-Daten werden im Test über die offizielle
Stub-Laufzeit eingespeist. Das ist ein isolierter Nachweis des Modulvertrags.
Der nächste technische Meilenstein ist der direkte Find-Hub-Abruf.

## Projektunterlagen

- [Kontogrenze und Cachevertrag](docs/adr/0001-library-and-account-boundary.md)
- SymconiCloud unter `../SymconiCloud`, insbesondere Konto-README und ADR 0001,
  0002, 0007, 0008, 0011 und 0012 für Konto, Geräte, Anwesenheit und Karte
- OpenWoIst unter `../OpenWoIst`, insbesondere ADR 0001 zu Standortquellen
- SymconDevelopment, Symcon_ModuleHelper und Symcon_ModuleCI in den
  benachbarten Projektordnern

## Lokale Prüfung

Verwende PHP 8.5 mit `mbstring` und [SymconStubs bei Commit
`31f2e0478eb3763236fcf44ea4144271fdeddfd5`](https://github.com/symcon/SymconStubs/tree/31f2e0478eb3763236fcf44ea4144271fdeddfd5).
Standardpfad ist `tests/stubs`. Alternativ kann `SYMCON_STUBS_PATH` auf einen
passenden vorhandenen Checkout verweisen.

```text
php tests/run.php
php tests/validate_structure.php
php-cs-fixer fix --dry-run --diff --allow-risky=yes --config=.style/.php-cs-fixer.php
```

CI verwendet die gemeinsamen Workflows aus Symcon_ModuleCI v1.0.0.
Der zentrale DebugFlowHelper erfasst `Create`, `ApplyChanges` und
`GCAC_GetDevices`. Der Cache-Lesevorgang meldet Zustand und Geräteanzahl.
Die Positions- und Gerätewerte bleiben im Export für zugewiesene Consumer.
