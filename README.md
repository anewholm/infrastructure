# Acorn — Shared base module for WinterCMS plugins

![Human made content](human-made-content.png "Human made content")
[![CI](https://github.com/anewholm/acorn/actions/workflows/ci.yml/badge.svg)](https://github.com/anewholm/acorn/actions/workflows/ci.yml)
[![Security Scan](https://github.com/anewholm/acorn/actions/workflows/semgrep.yml/badge.svg)](https://github.com/anewholm/acorn/actions/workflows/semgrep.yml)

> **Note:** CodeQL security scanning is unfortunately not available for PHP on GitHub's free tier.

Acorn is a WinterCMS module that provides shared base classes and infrastructure for the Acorn plugin family. It extends Laravel/WinterCMS with PostgreSQL-aware migrations, permission-aware models, WebSocket support, and dirty-write protection.

## What it provides

- **`Acorn\Migration`** — extends WinterCMS migrations with PostgreSQL-specific DDL: `createFunction()`, `createExtension()`, `createTrigger()`, native column types (`integer[]`, `interval`, etc.), and intelligent drop-before-create helpers
- **`Acorn\Model`** — base Eloquent model with built-in owner/group/other permissions (linux-style rwx), dirty-write protection via PostgreSQL advisory locks, and audit timestamps
- **`Acorn\Collection`** — extended collection with additional query helpers
- **`Acorn\Controller`** / **`Acorn\BackendRequestController`** — base controllers with permission enforcement
- **JavaScript** — hashbang routing utilities and WebSocket client helpers shared across plugins
- **Traits** — reusable mixins for lockable records, translatable fields, and more

## Who uses it

| Plugin | Repository | Status |
|--------|-----------|---------|
| Calendar | [anewholm/calendar](https://github.com/anewholm/calendar) | Production ready, Live, CI |
| DBAuth | [anewholm/dbauth](https://github.com/anewholm/dbauth) | Production ready, Live, CI |
| Location | [anewholm/location](https://github.com/anewholm/location) | In-development |
| Messaging | [anewholm/messaging](https://github.com/anewholm/messaging) | In-development |
| Reporting | [anewholm/reporting](https://github.com/anewholm/reporting) | In-development |

## Compatibility

| WinterCMS | Laravel | PHP  |
|-----------|---------|------|
| 1.2.0     | 9       | 8.1+ |
| 1.2.x     | 10      | 8.1+ |
| 1.2.x     | 11      | 8.2+ |

## Prerequisites

- WinterCMS 1.2+
- PostgreSQL 15+ (the Migration extensions target PostgreSQL; standard Laravel migrations still work on other databases)

## Installation

1. Clone this repository into `modules/acorn` inside your WinterCMS root:
   ```bash
   git clone https://github.com/anewholm/acorn modules/acorn
   ```

2. Add `Acorn` to the module list in `config/cms.php` **after** `Cms`:
   ```php
   'loadModules' => ['System', 'Backend', 'Cms', 'Acorn'],
   ```

3. Run migrations:
   ```bash
   php artisan winter:up
   ```

That is all that is required. The PSR-4 autoloader resolves `Acorn\` from `modules/acorn/` automatically once the module is registered.

## License

MIT
