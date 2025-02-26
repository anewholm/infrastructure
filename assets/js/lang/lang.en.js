// TODO: This code snippet demonstrates how custom frontend language phrases are loaded in Winter CMS.
// Winter's language compilation normally uses the command `php artisan winter:util compile lang`
// but this command only compiles language phrases located in the main path `modules/system/lang`.
// This means that language phrases for [acorn modules] won't be included in the default language files.
//
// Therefore, we manually created this file to define custom language phrases for the `modules/acorn` making them available in the frontend without altering the core system.
// Here, we add phrases to the `$.wn.langMessages['en']` object, which Winter uses
// to retrieve language phrases as needed.
if ($.wn === undefined) $.wn = {};
if ($.wn.langMessages === undefined) $.wn.langMessages = {};
$.wn.langMessages["en"] = $.extend($.wn.langMessages["en"] || {}, {
  "links": {
    "viewselection": "View Selection",
  },
});
