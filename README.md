# AcornAssociated shared module
This module is for shared code at Acorn Associated. It includes generic code for:

 * JavaScript Hashbang functions
 * WebSockets
 * Models with permissions and protection against dirty writes, etc.
 * Migrations with intelligent dropping, PostGreSQL aware column types, triggers and functions

Many of the AcornAssociated plugins depend on this module.

## Installation
`git clone` this module in to Laravel `~/modules`.
The `acorn-setup-new-winter` script should do these things with its patch system. Still trying to work out which of these is actually necessary...

**Maybe** add this in to your `~/composer.json` in order to pre-load the module classes:
```
    "autoload": {
        "psr-4": {
            "AcornAssociated\\": "modules/acornassociated/"
        }
    }
```

**Or try** adding **AcornAssociated** in your `config/cms.php`:
```
    'loadModules' => [
        'System',
        'Backend',
        'Cms',
        'AcornAssociated',
    ],
```

Also `AcornAssociated\ServiceProvider` can be added _before_ `System\ServiceProvider` in `config/app.php`:
```
    'providers' => array_merge(include(base_path('modules/system/providers.php')), [

        // 'Illuminate\Html\HtmlServiceProvider', // Example

        AcornAssociated\ServiceProvider::class,
        System\ServiceProvider::class,
    ]),
```
