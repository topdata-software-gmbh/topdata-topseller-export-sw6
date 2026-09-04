# AGENTS.md — `topdata-topseller-export-sw6`

Shopware 6.7 plugin: topseller CSV export (CLI + Admin API + admin UI), general product CSV export (YAML-configured CLI), and an admin "Bestseller" module. No tests, no CI, no PHPStan, no ESLint, no `phpunit.xml` in this repo — verification happens inside a host Shopware project.

## What this repo is

- **Package**: `topdata/topdata-topseller-export-sw6` (composer `type: shopware-platform-plugin`).
- **Target**: `shopware/core: 6.7.*`. PHP 8.2+.
- **Plugin class**: `Topdata\TopdataTopsellerExportSW6\TopdataTopsellerExportSW6` (declared in `composer.json` `extra.shopware-plugin-class`).
- **PSR-4 root**: `Topdata\TopdataTopsellerExportSW6\` → `src/`.
- **Autoloaded in host**: drop into `custom/plugins/`, then `bin/console plugin:refresh && bin/console plugin:install --activate TopdataTopsellerExportSW6`.
- **Planned rename**: README notes the plugin will be renamed (e.g. `topdata-better-orders-sw6`); when refactoring, do not bake the old name deeper into new code.

## Layout (what's where)

- `src/Command/Command_ExportTopsellers.php` — `topdata:topseller:export` CLI. Uses `DateRangePreset` enum + `DateRangeResolver`.
- `src/Command/Command_ExportProducts.php` — `topdata:product:export` CLI. Uses `ProductExportService` + YAML config.
- `src/Controller/Admin/TopsellerExportController.php` — `GET /api/_action/topdata-topseller-export-sw6/export` (CSV download, `api` route scope).
- `src/Controller/Admin/TopsellerViewController.php` — `GET /api/topdata/topseller-export/topsellers` (JSON, used by the admin module).
- `src/Controller/AdminApiExampleController.php` + `StorefrontExampleController.php` — boilerplate left from plugin scaffold; do not extend them with real features.
- `src/Service/TopsellerDataService.php` — DBAL query on `order_line_item`/`product`/`product_translation`. Returns `TopsellerItem[]`. Filters on `liveVersionId` and `lineItem.type = 'product'`.
- `src/Service/ProductExportService.php` — DAL pagination (page size 250) over `product.repository` with **dynamic `addAssociation`** parsed from YAML field paths.
- `src/Service/CsvGenerator.php` — in-memory CSV via `php://temp` + `fputcsv` (used only by topseller exports).
- `src/Service/CsvFileWriter.php` — streaming `fputcsv` to a real file (used by `topdata:product:export` for memory safety on large catalogs).
- `src/Service/DateRangeResolver.php` — resolves presets to UTC `DateTimeImmutable` ranges; end-of-day is set to `23:59:59`.
- `src/Enum/DateRangePreset.php` — `TODAY` … `LAST_365_DAYS`. Adding a preset requires updating this enum + the resolver's `match` (PHP will fatal on missing arm).
- `src/Dto/TopsellerItem.php` — readonly DTO with `toArray()` (header order is derived from this).
- `src/Resources/config/services.xml` — DI wiring; **controllers must be `public="true"`** and get an explicit `setContainer` call. `TopsellerDataService` gets `Doctrine\DBAL\Connection` injected.
- `src/Resources/config/routes.xml` — auto-imports every `*Controller.php` under `src/Controller/`.
- `src/Resources/config/config.xml` — admin config form. Already exposes `defaultLanguageCode` (currently unused by code — CLI/API language still comes from `--language-code` / `?languageCode=`).
- `src/Resources/config/product_export.yaml` — default columns for `topdata:product:export`.
- `src/Resources/app/administration/` — Vue 2 admin bundle.
  - `main.js` only imports the order-list extension and the bestseller module; new admin entrypoints must be wired here.
  - `extension/sw-order-list/` adds the "Payment method" column to `sw-order-list` via `Component.override` + a Twig template.
  - `module/topdata-topseller-view/` registers a sidebar entry under `sw-order` (parent) at `position: 50`, icon `regular:star`, color `#ff5722`.
  - Snippets live in `src/Resources/app/administration/src/snippet/{de-DE,en-GB}.json`. Two namespaces: `topdata-topseller-export-sw6.*` and `topdata-topseller-view.*`. The bestseller module fetches `/api/topdata/topseller-export/topsellers` (relative path; relies on Shopware's admin base URL).
- `src/Resources/views/storefront/example.html.twig` — only the scaffold example page; real storefront output is not part of the plugin's current scope.

## Conventions specific to this repo

- **CSV delimiter is `;`** with `"` enclosure and `\` escape (see both `CsvGenerator` and `CsvFileWriter`).
- **Dates are UTC** everywhere (`DateRangeResolver` constructs in `new DateTimeZone('UTC')`). The CLI filename uses UTC `Hms`; the Admin controller uses local `His` — if you touch filename generation, keep them consistent with their own callers, but note the inconsistency exists.
- **Language fallback chain** (in `TopsellerDataService::getTopsellers`): requested language → `Defaults::LANGUAGE_SYSTEM` → first row in `language` table. `COALESCE(pt_requested.name, pt_default.name)` in the SQL.
- **DBAL, not DAL, for topseller aggregation** — `TopsellerDataService` uses raw `Connection` because DAL can't `SUM(lineItem.quantity) GROUP BY product` cheaply. Product export, by contrast, uses the DAL with offset pagination.
- **Topseller CLI vs API share business logic** but duplicate the `getLanguageIdByCode` helper. If you add a third surface, extract it (don't copy-paste again).
- **Service IDs in `services.xml` use FQCNs**, not Symfony short-names. Match that style for new services.
- **Command classes are named `Command_*`** (not `*Command`). `services.xml` tags them with `console.command`.
- **Routes are declared with PHP attributes** (`#[Route(...)]` from `symfony/routing`, with `#[Route(defaults: ['_routeScope' => ['api']])]` on the controller class). Don't add entries to `routes.xml` by hand — it auto-imports.
- **YAML field paths** in `product_export.yaml` support dot-notation and `translated[...]`. `ProductExportService` only auto-adds associations for the first path segment if it's not `translated` — see `src/Service/ProductExportService.php:46`.
- **Admin module tokens**: the bestseller page reads the bearer token via `Shopware.Service('loginService').getToken()`; if Shopware's login service name changes, this is the call to update.
- **No tests, no CI, no formatter config** in this repo. Don't add `phpunit.xml` or `.github/workflows/` speculatively.

## How to verify a change

There is no local "run all tests" command — verification requires a Shopware 6.7 host. Standard sequence once the plugin is installed in `<host>/custom/plugins/`:

1. `bin/console cache:clear`
2. `bin/console topdata:topseller:export -p LAST_30_DAYS -o /tmp/td-out` (or `--start-date/--end-date`).
3. `bin/console topdata:product:export -o /tmp/td-out` (or `--config path/to/yaml -l de-DE`).
4. Hit `GET /api/_action/topdata-topseller-export-sw6/export?startDate=2024-01-01&endDate=2024-01-31&languageCode=en-GB` with an admin bearer.
5. Hit `GET /api/topdata/topseller-export/topsellers?startDate=...&endDate=...&languageCode=...` (JSON) — this is what the admin module calls.
6. In the admin: **Orders → Bestsellers** should load; the order list should show the new "Payment method" column.

If you add a new service, register it in `src/Resources/config/services.xml`. If you add a new admin snippet key, add it to **both** `de-DE.json` and `en-GB.json`.

## Don't trip on

- `services.xml` has `StorefrontExampleController` and `AdminApiExampleController` registered explicitly even though they're scaffold leftovers — leave them alone unless you're removing the example storefront route too.
- `config.xml` defines `defaultLanguageCode` but no PHP reads `SystemConfigService` for it. Don't claim it works in the README until you wire it up.
- `TopsellerViewController` returns `JsonResponse` keyed `data`/`total`/`startDate`/`endDate`. The admin module's `fetchTopsellers` reads `response.data.data` — keep that shape or update both sides in one commit.
- `TopsellerExportController` does not use `services.xml`'s `DateRangeResolver` — it parses dates inline. If you refactor, push the parsing through the resolver so CLI and API share validation.
- The `extension/sw-order-list` override uses `#column-primaryOrderTransaction.paymentMethod.name` slot — if Shopware renames the slot, the column header disappears silently. Check the rendered DOM after a `sw-order-list` upgrade.

## Existing instruction sources

- `.opencode/opencode.jsonc` already pulls in the global `AGENTS.md` via `instructions`; do not duplicate global hygiene rules here.
- Backlog artifacts live in `_ai/backlog/{active,archive,epics,reports}/`. New implementation plans go to `active/`, reports to `reports/`. Filenames follow `<YYMMDD>_<HHMM>__<DOC_TYPE>__<slug>.md`.
