# Automatische Versionierung

**Update library metadata** läuft bei Pushs auf `dev`. Der Workflow
checkt den Entwicklungszweig mit vollständiger Git-Historie aus und führt
vor der Aktualisierung dieselben Modultests wie der normale Testworkflow aus.

Der zentrale Generator aus Symcon_ModuleCI verwendet die Quellcommits
des Pushs:

- `version` erhöht die zweite Versionszahl um die Anzahl der Quellcommits.
- `build` ist der Integerwert der ersten sieben Hexziffern des Quell-SHA.
- `date` ist der Unix-Zeitstempel des Quellcommits.

Ein fehlender Vorgänger führt zu einem Versionsschritt. Automatische
Metadaten-Commits zählen nicht mit. Wiederholungen desselben Laufs erhöhen
die Version nicht erneut. Überholte Quellcommits werden übersprungen.

Wenn der Generator Änderungen liefert und die Strukturprüfung erfolgreich
ist, schreibt der Bot ausschließlich `library.json` auf `dev` zurück.
Der Bot-Commit startet die vorhandenen Tests und die Stilprüfung. Sein
Commit-Präfix verhindert eine erneute Versionserhöhung. Ein Push, der
wegen neuer Änderungen auf `dev` abgewiesen wird, kann über den Workflow des
neuesten Quellcommits erneut geprüft werden. Für die nächste lokale
Bearbeitung den Bot-Commit über GitHub Desktop übernehmen.

## GitHub-Einrichtung

Die für die Helper-Verteilung verwendete GitHub App benötigt Zugriff auf
dieses Repository und **Contents: write**. In GitHub Actions verwendet der
Workflow die Variable `HELPER_SYNC_APP_CLIENT_ID` und das Secret
`HELPER_SYNC_APP_PRIVATE_KEY`. Die Branch-Regeln für `dev` müssen den
normalen Push der App zulassen. Der Workflow erstellt keine Release-Tags.

Die zentrale Metadaten-Action ist auf einen geprüften Commit von
[Symcon_ModuleCI](https://github.com/Burki24/Symcon_ModuleCI/tree/0cbd8ff21031325f7335e2e3f6ab767090fe19b9/library-metadata)
gepinnt. Die verfügbaren Release-Tags enthielten die Action bei der
Einrichtung noch nicht. Tests und Stilprüfung verwenden weiterhin
`Symcon_ModuleCI@v1.0.0`.
