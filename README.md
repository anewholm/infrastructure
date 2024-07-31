# AcornAssociated shared module
This module is for shared code at Acorn Associated. It includes generic code for:

 * JavaScript Hashbang functions
 * WebSockets
 * Models with permissions and protection against dirty writes, etc.
 * Migrations with intelligent dropping, PostGreSQL aware column types, triggers and functions

Many of the AcornAssociated plugins depend on this module.

## Installation
`git clone` this module in to Laravel `~/modules`.
Add this in to your `~/composer.json` in order to pre-load the module classes:
```
    "autoload": {
        "psr-4": {
            "AcornAssociated\\": "modules/acornassociated/"
        }
    }
```
