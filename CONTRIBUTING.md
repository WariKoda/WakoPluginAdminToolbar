# Contributing

Thank you for your interest in contributing to **WakoPluginAdminToolbar**.

## Before you start

- Please check existing issues and pull requests first.
- For larger changes or new features, open an issue or start a short discussion before implementation.
- Small bug fixes, documentation improvements, and tests can be submitted directly as a pull request.

## Local setup

You need a working Shopware 6 installation where the plugin can be developed locally.

From the Shopware root:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate WakoPluginAdminToolbar
bin/console cache:clear
./bin/build-storefront.sh
./bin/build-administration.sh
```

Optional during development:

```bash
./bin/watch-storefront.sh
```

## Development guidelines

Please follow the existing code style and keep changes as small and focused as possible.

### Shopware / plugin-specific

- Do not modify Shopware core files.
- Use PHP with `declare(strict_types=1);` and follow the existing style.
- Register services in `src/Resources/config/services.xml`.
- Implement storefront templates with Shopware Twig conventions (`sw_extends`, `sw_include`).
- Always scope storefront CSS under `.wako-admin-toolbar`.
- Do not hardcode user-facing text; always use snippets.
- For new UI text, always maintain **both** languages:
  - `src/Resources/snippet/en-GB/`
  - `src/Resources/snippet/de-DE/`
  - and for admin UI also the corresponding admin snippets

### Security / permissions

This plugin follows a strict ACL / privilege model. Therefore:

- Every privileged action must be enforced **server-side**.
- Hidden or disabled UI is **not** a security measure.
- New buttons, endpoints, or actions must always:
  1. define the required privileges,
  2. enforce them in the backend,
  3. expose minimal capability flags to the UI when needed,
  4. hide or disable the UI accordingly,
  5. extend admin privilege registration and snippets for plugin-specific permissions.
- Admin bearer tokens must never be exposed to storefront JavaScript.
- Do not include sensitive data in `/admin/toolbar-auth`.

## Pull requests

Please make sure your pull request includes:

- a clear and short description of the problem and the solution
- small, focused commits
- no unnecessary refactorings in the same PR
- updated documentation when behavior or usage changes
- a changelog entry if the change is relevant for users

## Validation before submitting

Please run at least these steps when relevant for your change:

```bash
bin/console cache:clear
./bin/build-storefront.sh
./bin/build-administration.sh
```

Please also verify manually:

- the toolbar is only visible for authorized and enabled admin users
- new or changed actions respect ACL / privileges server-side
- new snippets exist in both `de-DE` and `en-GB`
- storefront styles remain scoped to `.wako-admin-toolbar`

## Reporting bugs

If you report a bug, the following information is helpful:

- Shopware version
- plugin version
- affected page or route
- expected behavior
- actual behavior
- reproduction steps
- relevant screenshots or logs

## License

By submitting a contribution, you agree that your work will be licensed under the project's license. See [LICENSE](./LICENSE) for details.
