# WakoPluginAdminToolbar

Shopware 6 storefront plugin that shows a fixed administration toolbar at the top of the storefront for eligible administration users.

## Screenshots

### Screenshot 1

![WakoPluginAdminToolbar Screenshot 1](./docs/images/wakoAdminToolbar_001.png)

### Screenshot 2 — Kontext-Button für Produkte, Varianten und Erlebniswelten

![WakoPluginAdminToolbar Screenshot 2](./docs/images/wakoAdminToolbar_002.png)

### Screenshot 3 — Customer Context

![WakoPluginAdminToolbar Screenshot 3](./docs/images/wakoAdminToolbar_003.png)

## What it does

The toolbar can provide:
- quick links into Shopware Administration
- context-aware edit links for product/category/CMS/landing page related storefront pages
- variant lookup for variant products
- customer context info for the current sales channel session
- active rule visibility
- copy-to-clipboard helpers
- cache clear action

## Security & Permission Model

This plugin must adhere to the Shopware Administration **roles, permissions, and privileges** system.

### Mandatory principles

- All privileged actions are enforced **server-side**
- UI visibility is only convenience, not authorization
- The user custom field `wako_admin_toolbar_enabled` is only an opt-in toggle
- Toolbar access also requires Shopware ACL privileges
- New privileged features must integrate with ACL end-to-end

### Base access requirement

The toolbar is only available when all of the following are true:
- a valid Shopware admin session exists
- the user enabled the toolbar via `wako_admin_toolbar_enabled`
- the user has the plugin privilege `wako_admin_toolbar:use`

The plugin registers the role permission:
- `wako_admin_toolbar.viewer` → `wako_admin_toolbar:use`

## Feature to privilege mapping

| Feature | Required privilege(s) |
|---|---|
| Use toolbar at all | `wako_admin_toolbar:use` |
| Clear cache | `system:clear:cache` |
| Load variants | `product:read` |
| Edit product | `product:update` |
| Edit category | `category:update` |
| Edit CMS page / layout / shopping experience / page | `cms_page:update` |
| Edit landing page | `cms_page:update` + `landing_page:update` |
| View customer context | `customer:read` |
| View active rules / rule links | `rule:read` |

## Development rule for future changes

Whenever you add a new action, endpoint, or toolbar button:

1. define the needed Shopware/core or plugin privilege
2. enforce it in backend PHP code
3. expose only minimal capability flags if the storefront/admin UI needs them
4. hide or disable the related UI accordingly
5. register admin privilege labels/snippets for plugin-specific permissions

## Current user administration module

The plugin provides a dedicated administration settings module for the currently logged-in user.

Location:
- **Administration → Settings → Plugins → Admin Toolbar**

Current scope:
- enable or disable the storefront admin toolbar for the own account
- show a prepared "Available features" section with disabled placeholders for future per-feature toggles

The module itself is available with:
- `user.update_profile`

Changing the toolbar activation requires:
- `user_change_me`
- `wako_admin_toolbar:use`

Notes:
- the toolbar activation setting is no longer edited in the Shopware profile page
- future per-feature user preferences should be added to this dedicated settings module

## Relevant files

- `src/WakoPluginAdminToolbar.php`
- `src/Controller/AdminToolbarAuthController.php`
- `src/Controller/AdminToolbarProfileController.php`
- `src/Resources/views/storefront/component/admin-toolbar.html.twig`
- `src/Resources/app/storefront/src/js/admin-toolbar/admin-toolbar.plugin.js`
- `src/Resources/app/administration/src/acl/index.js`
- `src/Resources/app/administration/src/module/wako-admin-toolbar-settings/`
- `src/Resources/app/administration/src/snippet/en-GB.json`
- `src/Resources/app/administration/src/snippet/de-DE.json`

## Build

From the Shopware root:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate WakoPluginAdminToolbar
bin/console cache:clear
./bin/build-storefront.sh
./bin/build-administration.sh
```
