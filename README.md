# Acorn shared module
This module is for shared code at Acorn. It includes generic code for:

 * JavaScript Hashbang functions
 * WebSockets
 * Models with permissions and protection against dirty writes, etc.
 * Migrations with intelligent dropping, PostGreSQL aware column types, triggers and functions

Many of the Acorn plugins depend on this module.

## Installation
`git clone` this module in to Laravel `~/modules`.
The `acorn-setup-new-winter` script should do these things with its patch system. Still trying to work out which of these is actually necessary...

**Maybe** add this in to your `~/composer.json` in order to pre-load the module classes:
```
    "autoload": {
        "psr-4": {
            "Acorn\\": "modules/acorn/"
        }
    }
```

**Or try** adding **Acorn** in your `config/cms.php`:
```
    'loadModules' => [
        'System',
        'Backend',
        'Cms',
        'Acorn',
    ],
```

Also `Acorn\ServiceProvider` can be added _before_ `System\ServiceProvider` in `config/app.php`:
```
    'providers' => array_merge(include(base_path('modules/system/providers.php')), [

        // 'Illuminate\Html\HtmlServiceProvider', // Example

        Acorn\ServiceProvider::class,
        System\ServiceProvider::class,
    ]),
```
