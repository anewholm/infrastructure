# Acorn — Shared base module for WinterCMS plugins

[![CI](https://github.com/anewholm/acorn/actions/workflows/ci.yml/badge.svg)](https://github.com/anewholm/acorn/actions/workflows/ci.yml)
[![CodeQL](https://github.com/anewholm/acorn/actions/workflows/codeql.yml/badge.svg)](https://github.com/anewholm/acorn/actions/workflows/codeql.yml)

Acorn is a WinterCMS module that provides shared base classes and infrastructure for the Acorn plugin family. It extends Laravel/WinterCMS with PostgreSQL-aware migrations, permission-aware models, WebSocket support, and dirty-write protection.

## What it provides

- **`Acorn\Migration`** — extends WinterCMS migrations with PostgreSQL-specific DDL: `createFunction()`, `createExtension()`, `createTrigger()`, native column types (`integer[]`, `interval`, etc.), and intelligent drop-before-create helpers
- **`Acorn\Model`** — base Eloquent model with built-in owner/group/other permissions (linux-style rwx), dirty-write protection via PostgreSQL advisory locks, and audit timestamps
- **`Acorn\Collection`** — extended collection with additional query helpers
- **`Acorn\Controller`** / **`Acorn\BackendRequestController`** — base controllers with permission enforcement
- **JavaScript** — hashbang routing utilities and WebSocket client helpers shared across plugins
- **Traits** — reusable mixins for lockable records, translatable fields, and more

## Who uses it

| Plugin | Repository |
|--------|-----------|
| Calendar | [anewholm/calendar](https://github.com/anewholm/calendar) |
| DBAuth | [anewholm/dbauth](https://github.com/anewholm/dbauth) |
| Location | [anewholm/location](https://github.com/anewholm/location) |
| Messaging | [anewholm/messaging](https://github.com/anewholm/messaging) |
| Reporting | [anewholm/reporting](https://github.com/anewholm/reporting) |

## Compatibility

| WinterCMS | Laravel | PHP  |
|-----------|---------|------|
| 1.2.0     | 9       | 8.1+ |
| 1.2.x     | 10      | 8.1+ |
| 1.2.x     | 11      | 8.2+ |

## Prerequisites

- WinterCMS 1.2+
- PostgreSQL 12+ (the Migration extensions target PostgreSQL; standard Laravel migrations still work on other databases)

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
