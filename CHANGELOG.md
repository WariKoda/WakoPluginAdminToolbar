# Changelog

## 1.3.1 — 2026-04-13

### Changed
- Refactored `AdminToolbarAuthController` into dedicated toolbar services for session resolution, privilege evaluation, capability building, variant loading, customer context loading, and active rule lookup
- Introduced a dedicated `ToolbarSession` value object to replace the previous array-based toolbar session state
- Kept the existing routes, response payloads, and privilege model intact while moving business logic out of the controller

### Security
- Centralized toolbar session and privilege checks in dedicated services to keep authorization logic explicit and reusable across toolbar endpoints
- Preserved the existing server-side JWT validation, ACL enforcement, and session-derived customer context handling during the refactor

## 1.3.0 — 2026-04-12

### Added
- Dedicated administration settings module for the current user at **Settings → Plugins → Admin Toolbar**
- Current-user toolbar activation can now be managed outside the Shopware profile page
- Prepared "Available features" section with disabled placeholder toggles for future per-feature user preferences

### Security
- End-to-end Shopware ACL integration for toolbar usage and feature visibility
- Toolbar activation handling now consistently respects both profile/self-update permissions and plugin toolbar privileges
- Current-user toolbar activation endpoint is protected for self-service usage with explicit ACL checks

### Changed
- Moved toolbar activation UX from the profile override into the dedicated administration settings module
- Administration UI now surfaces clearer status/help messages for toolbar availability and missing privileges
- Settings module status/info messages now use `mt-banner`

### Removed
- Profile-page override for toolbar activation

## 1.2.4 — 2026-04-11

### Security
- Removed the admin bearer token from `/admin/toolbar-auth` responses
- Replaced storefront-side Admin API usage with dedicated server-side toolbar endpoints for cache clearing and variant loading
- `/admin/toolbar-auth` now returns only the minimal `enabled` flag

### Changed
- Customer context is now lazy-loaded on first dropdown interaction instead of during toolbar initialisation
- Customer context now derives the sales channel from the server-side session instead of trusting a client-supplied `salesChannelId`
- Customer name is shown only inside the dropdown content; the trigger keeps its static label

## 1.2.3 — 2026-04-12

### Added
- Customer context added to the Admin Toolbar
- Dropdown UI for customer context showing the customer info and active rules

## 1.2.2 — 2026-04-10

### Changed
- switched to meteor kit icons

## 1.2.1 — 2026-04-10

### Added
- Quick-access navigation links for Orders, Extensions, and Settings in the toolbar left section
- New SVG icons: `receipt`, `extension`, `cog`, `chevron-up`
- Storefront snippets for `orders`, `extensions`, `settings` (en-GB / de-DE)

### Changed
- Context-aware links (Edit Product, Edit Layout, etc.) moved into a dedicated `wako-admin-toolbar__center` section, visually separated from the static navigation
- Collapse toggle button now uses a chevron-up icon instead of chevron-down
- Navigation links and center section hidden on screens ≤576px

---

## 1.2.0 — 2026-04-10

### Security
- **JWT verification**: The `/admin/toolbar-auth` endpoint now cryptographically validates the JWT signature using Shopware's HMAC-SHA256 key (`APP_SECRET`) before returning any data — forged tokens are rejected
- **PII reduction**: Removed `email` from the auth response; only `firstName` and `lastName` are returned
- **Debug headers removed**: All `X-Wako-Debug` response headers and the `debug()` method have been removed — failure responses are now identical `204 No Content` with no distinguishing information
- **Rate limiting**: Added IP-based rate limiting (fixed window, 30 req/60s) to `/admin/toolbar-auth` using Symfony's `RateLimiterFactory` to prevent brute-force and enumeration attacks
- **`innerHTML` eliminated**: All dynamic DOM updates in the storefront JS now use safe APIs (`createElement`, `createElementNS`, `textContent`) instead of `innerHTML`
- **External CDN removed**: Boxicons icon font is no longer loaded from `unpkg.com` — eliminates supply chain risk, third-party network requests, and ~100 KB of unnecessary downloads

### Changed
- Icons are now inline SVG symbols defined in a dedicated `admin-toolbar-icons.html.twig` template, referenced via `<svg><use href="#wako-icon-*">` throughout the toolbar
- Auth response now includes `Cache-Control: private, no-store` header
- Dead `$no` lambda removed from `AdminToolbarAuthController`

### Added
- User icon (`bx-user` SVG) displayed next to the admin user name in the toolbar
- `admin-toolbar-icons.html.twig` — standalone SVG sprite with 8 icon symbols (check, chevron-down, copy, cube, layout, refresh, user, x)

---

## 1.1.2 — 2026-04-09

### Changed
- Replaced three sequential JS API calls (`toolbar-session` + `_info/me` + `user/{id}`) with a single `GET /admin/toolbar-auth` endpoint that reads the bearer cookie, decodes the JWT, and queries the user in one DB round trip — significantly faster toolbar initialisation
- Toolbar outer shell now appears synchronously by peeking at the `bearerAuth` cookie via `document.cookie`, eliminating layout shift on page load
- Removed `AdminToolbarSessionController` and its `/admin/toolbar-session` route (superseded by `/admin/toolbar-auth`)

### Added
- Variant product dropdown submenu: hovering "Edit Product" on a variant page lazy-loads all sibling variants via the admin API and shows per-variant deep links labelled by their option combination (e.g. "Blue / XL")

### Fixed
- `UserEntity::getActive()` called correctly (was `isActive()`)

---

## 1.1.0 — 2026-04-09

### Added
- Per-user opt-in toggle in the admin profile page (Settings → Profile → General)
- `CustomFieldInstaller` creates the `wako_admin_toolbar_enabled` boolean custom field on the user entity on plugin install; removes it cleanly on uninstall
- Toolbar now only appears for admin users who have explicitly enabled it

### Changed
- Toolbar auth flow now calls `/api/user/{id}` in addition to `/api/_info/me` to reliably read `customFields`, which are not included in the `/api/_info/me` response
- Context links (product, CMS, category, landing page) are now rendered server-side in Twig instead of being built by JavaScript
- Icons use Boxicons web font (`bx-*`) loaded lazily via JS, only for authenticated admin users
- Dashboard link corrected to `#/sw/dashboard/index`
- Route name in toolbar stripped of `frontend.` prefix

### Fixed
- Product pages with a custom CMS layout now show both an "Edit Product" and an "Edit Layout" link
- Collapsed toolbar tab (`data-toolbar-tab`) now responds to keyboard events (Enter / Space)

### Removed
- `AdminToolbarConfigController` (toggle endpoint) — setting is now saved through the standard profile save flow
- `data-admin-toolbar-options` JSON attribute — page data is no longer serialised into the DOM

---

## 1.0.0 — 2026-03-01

### Added
- Initial release
- Fixed toolbar injected into every storefront page, hidden by default
- Client-side admin session detection via `bearerAuth` cookie + `/api/_info/me`
- Dashboard link, context-aware deep links for products, categories, CMS/Shopping Experiences, and landing pages
- Copy-to-clipboard button for entity IDs
- One-click cache clear via `DELETE /api/_action/cache`
- HTTP cache status indicator (HEAD request, `X-Symfony-Cache` / `Age` header)
- Collapse/expand with persistent state in `localStorage`
- Pushes page content down (no overlap) — fully cache-safe (toolbar is always `display:none` server-side)
- Plugin config: `adminBasePath`, `toolbarBgColor`, `toolbarTextColor`, `toolbarHeight`
- Storefront snippet translations: `en-GB`, `de-DE`
