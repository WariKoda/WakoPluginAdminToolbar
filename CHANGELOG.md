# 1.4.1
* Changed: Cleaned up the storefront SCSS for the admin toolbar without changing the intended feature set
* Changed: Simplified and clarified responsive toolbar styling to keep the stylesheet easier to maintain

# 1.4.0
* Changed: Active rules in the customer context now show all rules from the current SalesChannelContext instead of filtering to assigned core rule usages
* Changed: Customer context rule preview now shows the top 5 rules by priority and opens a dedicated modal for the full list
* Changed: Refined the active rules modal and rule card design to better match the admin toolbar style while staying compact
* Changed: Toolbar responsive behavior was reworked with Bootstrap breakpoint mixins; navigation links, route display, copy-id, and cache actions now appear only on larger viewports as intended

# 1.3.1
* Changed: Refactored `AdminToolbarAuthController` into dedicated toolbar services for session resolution, privilege evaluation, capability building, variant loading, customer context loading, and active rule lookup
* Changed: Introduced a dedicated `ToolbarSession` value object to replace the previous array-based toolbar session state
* Changed: Kept existing routes, response payloads, and the privilege model unchanged during the refactoring
* Security: Centralized toolbar session and privilege checks in dedicated services to keep the authorization logic explicit and reusable
* Security: Preserved existing server-side JWT validation, ACL enforcement, and session-based customer context resolution during the refactoring

# 1.3.0
* Added: Dedicated administration settings module for the current user under **Settings → Plugins → Admin Toolbar**
* Added: Current users can now manage toolbar activation outside of the Shopware profile page
* Added: Prepared an "Available features" section with disabled placeholder toggles for future per-user feature preferences
* Security: End-to-end Shopware ACL integration for toolbar usage and feature visibility
* Security: Toolbar activation now consistently respects both profile/self-update permissions and the plugin toolbar privileges
* Security: Endpoint for current-user toolbar activation is protected for self-service usage with explicit ACL checks
* Changed: Moved the toolbar activation UX from the profile override into the dedicated administration settings module
* Changed: Administration UI now shows clearer status and help messages for toolbar availability and missing privileges
* Changed: Status and info messages in the settings module now use `mt-banner`
* Removed: Profile page override for toolbar activation

# 1.2.4
* Security: Removed the admin bearer token from `/admin/toolbar-auth` responses
* Security: Replaced storefront-side admin API usage with dedicated server-side toolbar endpoints for cache clearing and variant loading
* Security: `/admin/toolbar-auth` now returns only the minimal `enabled` flag
* Changed: Customer context is now loaded lazily on the first dropdown interaction instead of during toolbar initialization
* Changed: Customer context now derives the sales channel server-side from the session instead of trusting a client-supplied `salesChannelId`
* Changed: Customer name is now displayed only inside the dropdown content; the trigger keeps its static label

# 1.2.3
* Added: Added customer context to the admin toolbar
* Added: Dropdown UI for customer context with customer information and active rules

# 1.2.2
* Changed: Switched to Meteor Kit icons

# 1.2.1
* Added: Quick-access navigation links for Orders, Extensions, and Settings in the left toolbar area
* Added: New SVG icons `receipt`, `extension`, `cog`, `chevron-up`
* Added: Storefront snippets for `orders`, `extensions`, `settings` (`en-GB` / `de-DE`)
* Changed: Context-dependent links (Edit Product, Edit Layout, etc.) moved into a dedicated `wako-admin-toolbar__center` area and visually separated from the static navigation
* Changed: Collapse toggle button now uses a `chevron-up` icon instead of `chevron-down`
* Changed: Navigation links and the center area are hidden on screens ≤576px

# 1.2.0
* Security: The `/admin/toolbar-auth` endpoint now validates the JWT signature cryptographically with Shopware's HMAC-SHA256 key (`APP_SECRET`) before returning any data; forged tokens are rejected
* Security: Removed `email` from the auth response; only `firstName` and `lastName` are returned now
* Security: Removed all `X-Wako-Debug` response headers and the `debug()` method; error responses are now consistently `204 No Content` without distinguishable extra information
* Security: Added IP-based rate limiting (fixed window, 30 requests / 60s) for `/admin/toolbar-auth` using Symfony `RateLimiterFactory` to make brute-force and enumeration harder
* Security: All dynamic DOM updates in the storefront JS now use safe APIs (`createElement`, `createElementNS`, `textContent`) instead of `innerHTML`
* Security: The Boxicons icon font is no longer loaded from `unpkg.com`, removing supply-chain risks, third-party requests, and roughly 100 KB of unnecessary downloads
* Changed: Icons are now inline SVG symbols in a dedicated `admin-toolbar-icons.html.twig` template and are referenced in the toolbar markup via `<svg><use href="#wako-icon-*">`
* Changed: Auth response now includes the header `Cache-Control: private, no-store`
* Changed: Removed dead `$no` lambda from `AdminToolbarAuthController`
* Added: Displayed a user icon (`bx-user` SVG) next to the admin user name in the toolbar
* Added: `admin-toolbar-icons.html.twig` as a standalone SVG sprite with 8 icon symbols (check, chevron-down, copy, cube, layout, refresh, user, x)

# 1.1.2
* Changed: Replaced three sequential JS API calls (`toolbar-session` + `_info/me` + `user/{id}`) with a single `GET /admin/toolbar-auth` endpoint that reads the bearer cookie, decodes the JWT, and loads the user in one DB roundtrip; this significantly speeds up toolbar initialization
* Changed: Outer toolbar shell now appears synchronously by checking the `bearerAuth` cookie via `document.cookie`, eliminating layout shift during page load
* Changed: Removed `AdminToolbarSessionController` and its `/admin/toolbar-session` route, as it was replaced by `/admin/toolbar-auth`
* Added: Variant dropdown on variant product pages; hovering over "Edit Product" lazily loads sibling variants via the Admin API and displays them as deep links with option combinations (e.g. "Blue / XL")
* Fixed: `UserEntity::getActive()` is now used correctly (instead of `isActive()`)

# 1.1.0
* Added: Per-user opt-in toggle on the admin profile page (Settings → Profile → General)
* Added: `CustomFieldInstaller` creates the boolean custom field `wako_admin_toolbar_enabled` on the user entity during plugin installation and removes it cleanly during uninstall
* Added: Toolbar is now shown only for admin users who explicitly enabled it
* Changed: Toolbar auth flow now additionally calls `/api/user/{id}` alongside `/api/_info/me` to read `customFields` reliably, since they are not included in `/api/_info/me`
* Changed: Context links (product, CMS, category, landing page) are now rendered server-side in Twig instead of JavaScript
* Changed: Icons now use the Boxicons webfont (`bx-*`), which is lazy-loaded via JS only for authenticated admin users
* Changed: Corrected dashboard link to `#/sw/dashboard/index`
* Changed: Route name in the toolbar now removes the `frontend.` prefix
* Fixed: Product pages with an individual CMS layout now show both an "Edit Product" and an "Edit Layout" link
* Fixed: Collapsed toolbar tab (`data-toolbar-tab`) now responds to keyboard events (Enter / Space)
* Removed: `AdminToolbarConfigController` (toggle endpoint), since the setting is now saved through the standard profile save flow
* Removed: `data-admin-toolbar-options` JSON attribute; page data is no longer serialized into the DOM

# 1.0.0
* Added: Initial release
* Added: Injected a fixed toolbar into every storefront page, hidden by default
* Added: Client-side admin session detection via the `bearerAuth` cookie and `/api/_info/me`
* Added: Dashboard link plus context-aware deep links for products, categories, CMS / shopping experiences, and landing pages
* Added: Copy-to-clipboard button for entity IDs
* Added: One-click cache clear via `DELETE /api/_action/cache`
* Added: HTTP cache status indicator (HEAD request, `X-Symfony-Cache` / `Age` headers)
* Added: Collapse / expand with persistent state in `localStorage`
* Added: Page content is pushed down (no overlap) and remains fully cache-safe because the toolbar is always rendered server-side with `display:none`
* Added: Plugin configuration `adminBasePath`, `toolbarBgColor`, `toolbarTextColor`, `toolbarHeight`
* Added: Storefront snippet translations for `en-GB` and `de-DE`
