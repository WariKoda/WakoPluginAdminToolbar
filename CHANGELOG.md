# 1.3.1
* Geändert: `AdminToolbarAuthController` in dedizierte Toolbar-Services für Session-Auflösung, Privileg-Auswertung, Capability-Building, Varianten-Laden, Customer-Context-Laden und Active-Rule-Lookup refaktoriert
* Geändert: Dediziertes `ToolbarSession`-Value-Object als Ersatz für den bisherigen array-basierten Toolbar-Session-State eingeführt
* Geändert: Bestehende Routen, Response-Payloads und das Privileg-Modell beim Refactoring unverändert beibehalten
* Sicherheit: Toolbar-Session- und Privileg-Prüfungen in dedizierten Services zentralisiert, damit die Autorisierungslogik explizit und wiederverwendbar bleibt
* Sicherheit: Bestehende serverseitige JWT-Validierung, ACL-Enforcement und sessionbasierte Customer-Context-Ermittlung während des Refactorings beibehalten

# 1.3.0
* Hinzugefügt: Dediziertes Administrations-Einstellungsmodul für den aktuellen Benutzer unter **Settings → Plugins → Admin Toolbar**
* Hinzugefügt: Aktueller Benutzer kann die Toolbar-Aktivierung jetzt außerhalb der Shopware-Profilseite verwalten
* Hinzugefügt: Bereich „Available features“ mit deaktivierten Placeholder-Toggles für zukünftige benutzerspezifische Feature-Präferenzen vorbereitet
* Sicherheit: End-to-End-Shopware-ACL-Integration für Toolbar-Nutzung und Feature-Sichtbarkeit
* Sicherheit: Toolbar-Aktivierung berücksichtigt jetzt konsistent sowohl Profile-/Self-Update-Berechtigungen als auch die Plugin-Toolbar-Privilegien
* Sicherheit: Endpoint für die Toolbar-Aktivierung des aktuellen Benutzers ist für Self-Service mit expliziten ACL-Prüfungen geschützt
* Geändert: Toolbar-Aktivierungs-UX aus dem Profil-Override in das dedizierte Administrations-Einstellungsmodul verschoben
* Geändert: Administrations-UI zeigt klarere Status-/Hilfsmeldungen für Toolbar-Verfügbarkeit und fehlende Privilegien
* Geändert: Status-/Infomeldungen im Einstellungsmodul verwenden jetzt `mt-banner`
* Entfernt: Profile-Page-Override für die Toolbar-Aktivierung

# 1.2.4
* Sicherheit: Admin-Bearer-Token aus `/admin/toolbar-auth`-Responses entfernt
* Sicherheit: Storefront-seitige Admin-API-Nutzung durch dedizierte serverseitige Toolbar-Endpoints für Cache-Clearing und Varianten-Laden ersetzt
* Sicherheit: `/admin/toolbar-auth` liefert jetzt nur noch das minimale `enabled`-Flag zurück
* Geändert: Customer Context wird jetzt lazy beim ersten Dropdown-Interaktionspunkt geladen statt während der Toolbar-Initialisierung
* Geändert: Customer Context leitet den Sales Channel jetzt serverseitig aus der Session ab, statt einem clientseitig gelieferten `salesChannelId` zu vertrauen
* Geändert: Customer-Name wird nur noch innerhalb des Dropdown-Inhalts angezeigt; der Trigger behält sein statisches Label

# 1.2.3
* Hinzugefügt: Customer Context zur Admin Toolbar hinzugefügt
* Hinzugefügt: Dropdown-UI für Customer Context mit Kundeninformationen und aktiven Regeln

# 1.2.2
* Geändert: Auf Meteor-Kit-Icons umgestellt

# 1.2.1
* Hinzugefügt: Quick-Access-Navigationslinks für Orders, Extensions und Settings im linken Toolbar-Bereich
* Hinzugefügt: Neue SVG-Icons `receipt`, `extension`, `cog`, `chevron-up`
* Hinzugefügt: Storefront-Snippets für `orders`, `extensions`, `settings` (`en-GB` / `de-DE`)
* Geändert: Kontextabhängige Links (Edit Product, Edit Layout usw.) in einen dedizierten `wako-admin-toolbar__center`-Bereich verschoben und visuell von der statischen Navigation getrennt
* Geändert: Collapse-Toggle-Button verwendet jetzt ein `chevron-up`-Icon statt `chevron-down`
* Geändert: Navigationslinks und Center-Bereich werden auf Screens ≤576px ausgeblendet

# 1.2.0
* Sicherheit: Der `/admin/toolbar-auth`-Endpoint validiert jetzt die JWT-Signatur kryptographisch mit Shopwares HMAC-SHA256-Key (`APP_SECRET`) vor jeder Datenrückgabe; gefälschte Tokens werden abgewiesen
* Sicherheit: `email` aus der Auth-Response entfernt; nur `firstName` und `lastName` werden noch zurückgegeben
* Sicherheit: Alle `X-Wako-Debug`-Response-Header und die `debug()`-Methode entfernt; Fehler-Responses sind jetzt einheitlich `204 No Content` ohne unterscheidbare Zusatzinformationen
* Sicherheit: IP-basiertes Rate-Limiting (Fixed Window, 30 Requests / 60s) für `/admin/toolbar-auth` mit Symfony `RateLimiterFactory` hinzugefügt, um Brute-Force und Enumeration zu erschweren
* Sicherheit: Alle dynamischen DOM-Updates im Storefront-JS verwenden jetzt sichere APIs (`createElement`, `createElementNS`, `textContent`) statt `innerHTML`
* Sicherheit: Boxicons-Icon-Font wird nicht mehr von `unpkg.com` geladen; dadurch entfallen Supply-Chain-Risiken, Third-Party-Requests und rund 100 KB unnötige Downloads
* Geändert: Icons sind jetzt Inline-SVG-Symbole in einem dedizierten `admin-toolbar-icons.html.twig`-Template und werden im Toolbar-Markup per `<svg><use href="#wako-icon-*">` referenziert
* Geändert: Auth-Response enthält jetzt den Header `Cache-Control: private, no-store`
* Geändert: Totes `$no`-Lambda aus `AdminToolbarAuthController` entfernt
* Hinzugefügt: User-Icon (`bx-user` SVG) neben dem Admin-Benutzernamen in der Toolbar angezeigt
* Hinzugefügt: `admin-toolbar-icons.html.twig` als eigenständiges SVG-Sprite mit 8 Icon-Symbolen (check, chevron-down, copy, cube, layout, refresh, user, x)

# 1.1.2
* Geändert: Drei sequentielle JS-API-Calls (`toolbar-session` + `_info/me` + `user/{id}`) durch einen einzelnen `GET /admin/toolbar-auth`-Endpoint ersetzt, der das Bearer-Cookie liest, das JWT dekodiert und den User in einem DB-Roundtrip lädt; dadurch deutlich schnellere Toolbar-Initialisierung
* Geändert: Äußere Toolbar-Hülle erscheint jetzt synchron, indem das `bearerAuth`-Cookie via `document.cookie` geprüft wird; dadurch kein Layout-Shift mehr beim Laden der Seite
* Geändert: `AdminToolbarSessionController` und dessen `/admin/toolbar-session`-Route entfernt, da durch `/admin/toolbar-auth` ersetzt
* Hinzugefügt: Varianten-Dropdown auf Varianten-Produktseiten; beim Hover über „Edit Product“ werden Geschwistervarianten lazy über die Admin API geladen und als Deep Links mit Optionskombinationen angezeigt (z. B. „Blue / XL“)
* Behoben: `UserEntity::getActive()` wird jetzt korrekt verwendet (statt `isActive()`)

# 1.1.0
* Hinzugefügt: Per-User-Opt-in-Toggle in der Admin-Profilseite (Settings → Profile → General)
* Hinzugefügt: `CustomFieldInstaller` legt beim Plugin-Install das Boolean-Custom-Field `wako_admin_toolbar_enabled` auf der User-Entity an und entfernt es beim Uninstall sauber
* Hinzugefügt: Toolbar erscheint jetzt nur noch für Admin-Benutzer, die sie explizit aktiviert haben
* Geändert: Toolbar-Auth-Flow ruft zusätzlich zu `/api/_info/me` jetzt `/api/user/{id}` auf, um `customFields` zuverlässig zu lesen, da diese in `/api/_info/me` nicht enthalten sind
* Geändert: Kontextlinks (Produkt, CMS, Kategorie, Landingpage) werden jetzt serverseitig in Twig statt in JavaScript gerendert
* Geändert: Icons verwenden die Boxicons-Webfont (`bx-*`), die per JS lazy nur für authentifizierte Admin-Benutzer geladen wird
* Geändert: Dashboard-Link auf `#/sw/dashboard/index` korrigiert
* Geändert: Routenname in der Toolbar entfernt jetzt den Präfix `frontend.`
* Behoben: Produktseiten mit individuellem CMS-Layout zeigen jetzt sowohl einen „Edit Product“- als auch einen „Edit Layout“-Link
* Behoben: Eingeklappter Toolbar-Tab (`data-toolbar-tab`) reagiert jetzt auf Tastaturereignisse (Enter / Space)
* Entfernt: `AdminToolbarConfigController` (Toggle-Endpoint), da die Einstellung jetzt über den Standard-Profile-Save-Flow gespeichert wird
* Entfernt: `data-admin-toolbar-options`-JSON-Attribut; Page-Daten werden nicht mehr ins DOM serialisiert

# 1.0.0
* Hinzugefügt: Initial Release
* Hinzugefügt: Fixed Toolbar wird in jede Storefront-Seite injiziert und ist standardmäßig ausgeblendet
* Hinzugefügt: Clientseitige Admin-Session-Erkennung über `bearerAuth`-Cookie und `/api/_info/me`
* Hinzugefügt: Dashboard-Link sowie kontextabhängige Deep Links für Produkte, Kategorien, CMS-/Shopping-Experiences und Landingpages
* Hinzugefügt: Copy-to-Clipboard-Button für Entity-IDs
* Hinzugefügt: One-Click-Cache-Clear via `DELETE /api/_action/cache`
* Hinzugefügt: HTTP-Cache-Statusindikator (HEAD-Request, `X-Symfony-Cache` / `Age`-Header)
* Hinzugefügt: Collapse/Expand mit persistentem Status in `localStorage`
* Hinzugefügt: Page-Content wird nach unten verschoben (kein Overlap) und bleibt vollständig cache-sicher, da die Toolbar serverseitig immer `display:none` ist
* Hinzugefügt: Plugin-Konfiguration `adminBasePath`, `toolbarBgColor`, `toolbarTextColor`, `toolbarHeight`
* Hinzugefügt: Storefront-Snippet-Übersetzungen für `en-GB` und `de-DE`
