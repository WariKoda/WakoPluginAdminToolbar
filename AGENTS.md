# WakoPluginAdminToolbar — Development Guidelines

## Overview

A Shopware 6 storefront plugin that displays a fixed admin toolbar at the top of the storefront for logged-in administration users. It provides quick-access links to edit the current page's entity (product, category, landing page, CMS layout) in the admin, shows the current route name, allows copying entity IDs, and clearing caches — all without leaving the storefront.

**Namespace:** `WakoPluginAdminToolbar`
**Compatibility:** Shopware 6.6 / 6.7
**License:** MIT
**Vendor:** WariKoda
**Lead Developer:** Niklas Braun

## Architecture

### Authentication, ACL & Action Flow

The toolbar now uses a **minimal auth endpoint plus dedicated server-side action endpoints** with mandatory **Shopware ACL / role privilege checks**. The flow:

1. Storefront JS fetches `GET /admin/toolbar-auth`
2. The controller reads the `bearerAuth` cookie server-side under `/admin`
3. The JWT is **cryptographically verified** using Shopware's JWT configuration
4. The user is loaded from the database together with assigned `aclRoles`
5. The opt-in custom field `wako_admin_toolbar_enabled` is checked
6. The plugin-specific base privilege `wako_admin_toolbar:use` is required to expose the toolbar at all
7. The endpoint returns only minimal state plus capability flags derived from Shopware ACL
8. Toolbar actions use dedicated server-side endpoints instead of exposing reusable admin credentials to storefront JS:
   - `DELETE /admin/toolbar-clear-cache`
   - `GET /admin/toolbar-variants/{parentId}`
   - `GET /admin/toolbar-customer-context`
9. Every endpoint performs its **own server-side privilege checks**

**Important:** The admin bearer token must never be returned to storefront JavaScript. Customer context is lazy-loaded on first interaction and the sales channel is derived server-side from the session. UI visibility is never a substitute for backend authorization.

### Key Components

| Component | Path | Purpose |
|-----------|------|---------|
| Plugin bootstrap | `src/WakoPluginAdminToolbar.php` | Install/uninstall custom fields via `CustomFieldInstaller` and enrich Shopware ACL privileges |
| Custom field installer | `src/Installer/CustomFieldInstaller.php` | Creates `wako_admin_toolbar` custom field set on `user` entity with boolean `wako_admin_toolbar_enabled` |
| Auth/action controller | `src/Controller/AdminToolbarAuthController.php` | Resolves toolbar session, validates JWT, evaluates ACL roles/privileges, serves capability flags, clears cache, loads variants, and returns customer context |
| Page data subscriber | `src/Subscriber/ToolbarPageDataSubscriber.php` | Attaches `wakoAdminToolbar` extension to page structs (pageType, entityId, parentId, cmsPageId) |
| Twig template | `src/Resources/views/storefront/component/admin-toolbar.html.twig` | Full toolbar markup with context-aware admin links and dropdown shells annotated for feature-level permission gating |
| Storefront JS | `src/Resources/app/storefront/src/js/admin-toolbar/admin-toolbar.plugin.js` | `AdminToolbarPlugin` — session check, capability gating, copy ID, clear cache, variant dropdown, lazy customer context, collapse/expand |
| SCSS | `src/Resources/app/storefront/src/scss/base.scss` | All styles scoped under `.wako-admin-toolbar` |
| Admin ACL registration | `src/Resources/app/administration/src/acl/` | Registers plugin privileges in the Shopware administration role editor |
| Admin settings module | `src/Resources/app/administration/src/module/wako-admin-toolbar-settings/` | Current-user admin module for toolbar enable/disable and future per-feature preferences |

### Page Type Detection

The subscriber listens to:
- `GenericPageLoadedEvent` → baseline `generic` type
- `ProductPageLoadedEvent` → `product` (includes parentId for variants, cmsPageId for custom layouts)
- `NavigationPageLoadedEvent` → `navigation` (category or CMS page)
- `LandingPageLoadedEvent` → `landingPage`
- Twig also detects `frontend.cms.page.full` route directly (no event fired for `CmsController::pageFull()`)

### Toolbar Features

- **Home link:** Opens admin dashboard
- **Context links:** Edit Product / Edit Layout / Edit Category / Edit Landing Page / Edit Shopping Experience (context-aware)
- **Variant dropdown:** On variant product pages, lazy-loads sibling variants on hover via `/admin/toolbar-variants/{parentId}`
- **Customer context:** Lazy-loads customer info and active rules on first dropdown interaction
- **Route name display:** Shows current Symfony route (stripped of `frontend.` prefix)
- **Copy entity ID:** Copies current entity UUID to clipboard
- **Clear cache:** Calls `DELETE /admin/toolbar-clear-cache` server-side
- **Collapse/expand:** Persists state in `localStorage` (`wako.admin-toolbar.collapsed`)

### ACL & Permission Handling (Mandatory)

This plugin must always adhere to the Shopware Administration role and privilege system.

#### Mandatory rules

- Every privileged action must be protected **server-side**
- UI hiding/disabling is only UX; it is **not** authorization
- New toolbar features must define which Shopware/core or plugin privilege unlocks them
- Prefer **core Shopware privileges** where they already exist instead of inventing custom ones
- The opt-in custom field `wako_admin_toolbar_enabled` is only an additional enable flag, never the sole authorization mechanism
- If a feature is exposed in the administration, also register the corresponding privilege labels/snippets in the admin app

#### Current privilege model

- Base toolbar access: `wako_admin_toolbar:use`
- Clear cache: `system:clear:cache`
- Variant loading: `product:read`
- Product edit link: `product:update`
- Category edit link: `category:update`
- CMS/layout/shopping experience/page edit links: `cms_page:update`
- Landing page edit link: `cms_page:update` + `landing_page:update`
- Customer context: `customer:read`
- Active rules list / rule detail links: `rule:read`

#### Implementation requirements for future work

When adding a new action, endpoint, admin route, or toolbar button:

1. define the required privilege(s)
2. enforce them in PHP/backend code
3. expose minimal capability flags only if needed for storefront/admin UI gating
4. hide/disable the UI based on those flags
5. add/update admin privilege registration and snippets when plugin-specific permissions are introduced

## Coding Conventions

- All storefront CSS is scoped under `.wako-admin-toolbar` — never use global selectors
- Twig uses `{% sw_extends %}` and `{% sw_include %}` (Shopware block inheritance)
- Toolbar icons are inline SVG symbols rendered via Twig includes — do not reintroduce external icon CDNs
- Current-user toolbar preferences belong in the dedicated administration module, not in the Shopware profile page
- Translation snippets in `src/Resources/snippet/{locale}/` (storefront) and `src/Resources/app/administration/src/snippet/` (admin)
- Both `en-GB` and `de-DE` snippets are mandatory for all user-facing strings
- Plugin config in `config.xml` — currently only `adminBasePath` (default: `/admin`)
- Services registered in `src/Resources/config/services.xml`
- Routes imported via attribute-based routing from `src/Controller/`
- Every new privileged feature must integrate with Shopware ACL/privileges end-to-end: admin privilege registration, backend enforcement, and UI gating
- Reuse core privileges where possible; only add plugin-specific privileges when no suitable core privilege exists
- Keep `src/Resources/app/storefront/dist/` in sync with storefront source changes when shipping a release

## Build & Development

```bash
# From Shopware root
bin/console plugin:refresh
bin/console plugin:install --activate WakoPluginAdminToolbar
bin/console cache:clear

# Build storefront (required after JS/SCSS changes)
./bin/build-storefront.sh

# Build administration (required after admin component changes)
./bin/build-administration.sh

# Watch mode (storefront)
./bin/watch-storefront.sh
```

## Don't

- Don't add HttpOnly to `bearerAuth` cookie handling — the cookie is set by Shopware's admin JS and must remain readable by the admin app
- Don't expose the admin bearer token to storefront JavaScript
- Don't remove JWT verification from the auth controller
- Don't trust client-supplied `salesChannelId` for customer context reconstruction
- Don't eagerly fetch customer context during toolbar initialisation
- Don't use global CSS selectors — everything must be scoped under `.wako-admin-toolbar`
- Don't add sensitive data to the `/admin/toolbar-auth` response — keep it minimal
- Don't hardcode admin URLs — always use the configurable `adminBasePath`
- Don't forget to handle the collapsed/expanded state via `localStorage`
- Don't break HTTP cache — the toolbar is rendered `display:none` by default and only revealed client-side
- Don't ship new actions, endpoints, or edit links without explicit Shopware ACL / privilege integration
- Don't rely on hidden buttons, disabled controls, or missing links as security controls
- Don't use only `wako_admin_toolbar_enabled` to authorize access to functionality
