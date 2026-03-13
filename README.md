<div align="center">
  <img src="https://avatars.githubusercontent.com/u/255415480?s=200&v=4" alt="Momo Framework" width="96" height="96" />

  <h1>momo-framework/discovery</h1>

  <p>Zero-config local module autodiscovery for <a href="https://github.com/momo-framework">Momo Framework</a></p>

  <p>
    <img src="https://github.com/momo-framework/discovery/actions/workflows/ci.yml/badge.svg" alt="CI" />
    <img src="https://img.shields.io/badge/php-%3E%3D8.5-8892bf.svg" alt="PHP Version" />
    <img src="https://img.shields.io/badge/type-composer--plugin-orange.svg" alt="Type" />
    <img src="https://img.shields.io/badge/license-proprietary-red.svg" alt="License" />
    <img src="https://img.shields.io/badge/coverage-100%25-brightgreen.svg" alt="Coverage" />
    <img src="https://img.shields.io/badge/PHPStan-level%2010-brightblue.svg" alt="PHPStan" />
  </p>
</div>

---

## What this does

Drop a module folder into `modules/` — it works. No config, no `composer.json` edits, no `require` entries.

```
core/
  modules/
    Shop/          ← just created this folder
      composer.json
      src/
  vendor/          ← Shop namespace automatically injected here
```

After `composer dump-autoload`:

```
momo-discovery: injected 1 local module namespace(s): Momo\Module\Shop\
```

---

## How it works

```
composer dump-autoload
       │
       ▼
POST_AUTOLOAD_DUMP event
       │
       ▼
MomoPlugin::onPostAutoloadDump()
       │
       ├── ModuleScanner::scan()
       │     Reads modules/*/composer.json
       │     Collects autoload.psr-4 entries
       │
       └── AutoloadPatcher::patch()
             Merges into vendor/composer/autoload_psr4.php
             Uses var_export — guaranteed valid PHP
             Replaces absolute paths with $baseDir expressions
```

`autoload_static.php` is intentionally **not touched** — regex-patching a generated PHP class is fragile. The file-based PSR-4 loader is always active unless `--optimize` or `--classmap-authoritative` flags are used.

---

## Local module format

A local module's `composer.json` contains **only metadata** — no `require`, no `repositories`:

```json
{
  "name": "momo-module/shop",
  "type": "momo-module",
  "autoload": {
    "psr-4": {
      "Momo\\Module\\Shop\\": "src/"
    }
  },
  "extra": {
    "momo": {
      "providers": [
        "Momo\\Module\\Shop\\Application\\Providers\\ShopServiceProvider"
      ]
    }
  }
}
```

Dependencies are declared in `core/composer.json` — all local modules share one `vendor/`.

---

## Local vs Vendor modules

| | Local | Vendor |
|---|---|---|
| Location | `core/modules/` | `core/vendor/` |
| Autoload | injected by this plugin | standard Composer |
| Dependencies | shared from `core/composer.json` | own `composer.json` |
| Editable | yes — `make:*` commands work | no — `module:publish` first |
| composer.json | metadata only | full package |

---

## Installation

Already included in Momo Framework core. For manual setup:

```bash
composer require momo-framework/discovery
```

Add to `core/composer.json`:

```json
{
  "config": {
    "optimize-autoloader": false,
    "classmap-authoritative": false
  }
}
```

---

## Development

```bash
composer install
composer test
composer test:coverage
composer stan
composer ci
```

---

<div align="center">
  <sub>Part of <a href="https://github.com/momo-framework">Momo Framework</a> — high-performance modular e-commerce engine</sub>
</div>
