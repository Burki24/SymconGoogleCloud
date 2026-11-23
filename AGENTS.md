# SymconGoogleCloud

Lies zuerst [SymconDevelopment/AGENTS.md](../SymconDevelopment/AGENTS.md),
die [Architektur](../SymconDevelopment/ARCHITECTURE.md) und die
[Engineering-Prinzipien](../SymconDevelopment/principles/). Anschließend gelten
[Projektkontext und Umsetzungsplan](PROJECT_CONTEXT.md) und die relevanten ADRs.

- Zielplattform ist Symcon 9.0 / PHP 8.5 mit `IPSModuleStrict`.
- SymconiCloud ist die Referenz für Modulaufteilung, Bedienung, Kontoschutz,
  lokalen Cache und Datenfluss. OpenWoIst ist der Standort-Consumer.
- Die Kontoinstanz besitzt die Google-Verbindungen und den Cache.
  Zugangsdaten und rohe Anbieterantworten bleiben im Kontomodul.
- Verwende die zentralen Helper gemäß ihrem Vertrag. `libs/helper` enthält
  unveränderte Bundle-Dateien aus Symcon_ModuleHelper.
- Tests nutzen synthetische Daten und die in CI gepinnten SymconStubs.
  Reale Anmeldungen und Änderungen an laufenden Systemen benötigen einen
  gesonderten Auftrag.
- Bildschirm-, Fenster- und Desktopaufnahmen sowie screenshotbasierte
  Computersteuerung benötigen vorab die ausdrückliche Zustimmung für die
  aktuelle Aufgabe. Ziel, Zweck und Umfang müssen vor der Erfassung klar sein.
  Die Erfassung endet unmittelbar danach. Computer-Use und Unified-Computer-Use
  bleiben bis zur ausdrücklichen Wiederaktivierung deaktiviert.
- Entwicklung auf `dev`. Commit und Push führt der Benutzer in GitHub Desktop
  aus. Die Übergabe enthält den englischen Commit-Betreff und die Beschreibung.
