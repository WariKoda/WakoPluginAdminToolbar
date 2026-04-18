# Contributing

Vielen Dank für dein Interesse an Beiträgen zu **WakoPluginAdminToolbar**.

## Bevor du loslegst

- Prüfe bitte zuerst bestehende Issues und Pull Requests.
- Für größere Änderungen oder neue Features bitte vorab ein Issue oder eine kurze Diskussion eröffnen.
- Kleine Bugfixes, Dokumentationsverbesserungen und Tests können direkt als Pull Request eingereicht werden.

## Lokales Setup

Voraussetzung ist eine laufende Shopware-6-Installation, in der das Plugin lokal entwickelt werden kann.

Aus dem Shopware-Root:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate WakoPluginAdminToolbar
bin/console cache:clear
./bin/build-storefront.sh
./bin/build-administration.sh
```

Optional während der Entwicklung:

```bash
./bin/watch-storefront.sh
```

## Entwicklungsrichtlinien

Bitte orientiere dich an der bestehenden Codebasis und halte Änderungen möglichst klein und fokussiert.

### Shopware / Plugin-spezifisch

- Keine Core-Dateien von Shopware ändern.
- PHP mit `declare(strict_types=1);` und im bestehenden Stil halten.
- Services in `src/Resources/config/services.xml` registrieren.
- Storefront-Templates mit Shopware Twig-Konventionen umsetzen (`sw_extends`, `sw_include`).
- Storefront-CSS immer unter `.wako-admin-toolbar` scopen.
- Benutzertexte nicht hartcodieren; immer Snippets verwenden.
- Für neue UI-Texte immer **beide** Sprachen pflegen:
  - `src/Resources/snippet/en-GB/`
  - `src/Resources/snippet/de-DE/`
  - sowie bei Admin-UI zusätzlich die Admin-Snippets

### Sicherheit / Berechtigungen

Dieses Plugin folgt einem strikten ACL-/Privilege-Modell. Deshalb gilt:

- Jede privilegierte Aktion muss **serverseitig** geprüft werden.
- Versteckte oder deaktivierte UI ist **keine** Sicherheitsmaßnahme.
- Neue Buttons, Endpoints oder Aktionen müssen immer:
  1. benötigte Privilegien definieren,
  2. im Backend erzwingen,
  3. falls nötig minimale Capability-Flags an die UI liefern,
  4. die UI entsprechend sichtbar/unsichtbar machen,
  5. bei plugin-spezifischen Rechten die Admin-Privilege-Registrierung und Snippets ergänzen.
- Admin-Bearer-Tokens dürfen niemals an Storefront-JavaScript zurückgegeben werden.
- Keine sensiblen Daten in `/admin/toolbar-auth` aufnehmen.

## Pull Requests

Bitte achte bei Pull Requests auf folgende Punkte:

- Eine klare, kurze Beschreibung des Problems und der Lösung
- Kleine, thematisch abgegrenzte Commits
- Keine unnötigen Refactorings im selben PR
- Dokumentation aktualisieren, wenn sich Verhalten oder Nutzung ändern
- Changelog ergänzen, wenn die Änderung für Nutzer relevant ist

## Validierung vor dem Einreichen

Bitte führe – soweit für deine Änderung relevant – mindestens diese Schritte aus:

```bash
bin/console cache:clear
./bin/build-storefront.sh
./bin/build-administration.sh
```

Zusätzlich bitte manuell prüfen:

- Toolbar erscheint nur für berechtigte und aktivierte Admin-Benutzer
- neue oder geänderte Aktionen respektieren ACL/Privileges serverseitig
- neue Snippets sind in `de-DE` und `en-GB` vorhanden
- Storefront-Styles bleiben auf `.wako-admin-toolbar` begrenzt

## Bugs melden

Wenn du einen Fehler meldest, sind diese Informationen hilfreich:

- Shopware-Version
- Plugin-Version
- betroffene Seite bzw. Route
- erwartetes Verhalten
- tatsächliches Verhalten
- Reproduktionsschritte
- relevante Screenshots oder Logs

## Lizenz

Mit dem Einreichen eines Beitrags erklärst du dich damit einverstanden, dass dein Beitrag unter der Lizenz des Projekts veröffentlicht wird. Details siehe [LICENSE](./LICENSE).
