<div align="center">
  <img src="https://avatars.githubusercontent.com/u/255415480?s=200&v=4" alt="Momo Framework" width="120" height="120" />

  <h1>momo-framework/discovery</h1>

  <p>Zero-config local module autoloading for <a href="https://github.com/momo-framework">Momo Framework</a></p>

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

## Overview

`momo-framework/discovery` is a Composer plugin that automatically registers local module namespaces after every `composer dump-autoload`. Drop a module into `modules/` — its PSR-4 namespace is available immediately, with no edits to `composer.json`.

```
core/
  modules/
    Shop/              ← drop a module here
      composer.json
      src/
  vendor/              ← namespace injected automatically
```

```bash
$ composer dump-autoload

momo-discovery: injected 1 local module namespace(s): Momo\Module\Shop\
```

---

## Requirements

- PHP `>= 8.5`
- Composer 2

---

## Installation

Already included in Momo Framework core. For standalone use:

```bash
composer require momo-framework/discovery
```

---

## How it works

The plugin hooks into Composer's `POST_AUTOLOAD_DUMP` event. After each dump it scans `modules/`, collects PSR-4 declarations from each module's `composer.json`, and injects them into the autoloader.

Two files are patched:

**`vendor/composer/autoload_psr4.php`** — rewritten from scratch with the merged namespace map. Absolute paths are replaced with `$baseDir` expressions to keep the file portable across machines.

**`vendor/autoload_real.php`** — `$loader->addPsr4()` calls are injected just before `return $loader;`. This ensures module namespaces are registered regardless of whether `--optimize` or `--classmap-authoritative` flags are active. `autoload_static.php` is intentionally not touched — its format is not a stable Composer contract.

Both patches are idempotent — a guard comment prevents duplicate entries across repeated `dump-autoload` runs.

```
composer dump-autoload
        │
        ▼
POST_AUTOLOAD_DUMP
        │
        ├─ ModuleScanner::scan()
        │    reads modules/*/composer.json
        │    collects autoload.psr-4 entries
        │    resolves relative paths to absolute
        │
        └─ AutoloadPatcher::patch()
             rewrites vendor/composer/autoload_psr4.php
             injects addPsr4() calls into vendor/autoload_real.php
```

---

## Local module format

Each module declares its own `composer.json` with metadata and autoload config. No `require` section — all modules share the root `vendor/`.

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
        "Momo\\Module\\Shop\\ShopServiceProvider"
      ]
    }
  }
}
```

---

## Local vs vendor modules

|               | Local module                          | Vendor module               |
|---------------|---------------------------------------|-----------------------------|
| Location      | `core/modules/`                       | `core/vendor/`              |
| Autoload      | injected by this plugin               | standard Composer           |
| Dependencies  | shared via `core/composer.json`       | own `composer.json`         |
| Editable      | yes — `make:*` commands work directly | no — `module:publish` first |
| composer.json | metadata + autoload only              | full package definition     |

---

## Development

```bash
# install dependencies
composer install

# run tests
composer test

# run tests with coverage report (requires PCOV)
composer test:coverage

# static analysis — PHPStan level 10
composer stan

# code style check
composer lint

# code style fix
composer lint:fix

# rector — check for upgrades
composer rector:check

# run full CI pipeline locally
composer ci
```

### CI pipeline

```
composer ci
  ├── lint          php-cs-fixer --dry-run
  ├── stan          phpstan level 10
  ├── rector:check  rector --dry-run
  └── test          phpunit
```

---

<div align="center">
  <sub>Part of <a href="https://github.com/momo-framework">Momo Framework</a> — a high-performance, modular PHP framework for building resilient distributed systems.</sub>
</div>