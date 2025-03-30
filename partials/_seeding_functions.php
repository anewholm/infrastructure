<?php 
// TODO: Rationalise this with the Acorn module Seed.php Command

// ---------------------------- Seeding db functions
$slugSnake      = str_replace('-', '_', $record->slug);
$dbFunctionBase = "fn_${slugSnake}_seed";
$dbSubFunction  = "${dbFunctionBase}_%";
try {
    $results = DB::select("select 
        proname as name, 'function' as type, proargnames as parameters, proargtypes as types, oid, obj_description(oid) as comment
        from pg_proc
        where proname = :dbFunctionBase or proname like(:dbSubFunction)
        ORDER BY proname", array(
            'dbFunctionBase' => $dbFunctionBase,
            'dbSubFunction'  => $dbSubFunction
    ));
} catch (Exception $e) {
    $results = array();
}

// ---------------------------- Seeding files
$slugDir      = str_replace('-', '/', $record->slug);
$seedPath     = "plugins/$slugDir/updates/seed.sql";
if (File::exists($seedPath)) {
    array_push($results, (object)array(
        'name'    => 'seed.sql',
        'type'    => 'file',
        'comment' => '',
    ));
}

// ---------------------------- Output
print('<ul>');
foreach ($results as $result) {
    $name   = $result->name;
    $suffix = ($result->type == 'file' ? '' : '()');

    if ($this->action == 'manage' && FALSE) {
        // TODO: Enable running in manage mode
        $dataRequestData = array_merge((array)$result, array('record' => [$record->id]));
        $dataRequestDataString = e(substr(json_encode($dataRequestData), 1, -1));

        print(<<<HTML
            <li><button class="btn btn-primary" style="padding:2px 8px;"
                data-request="onSeed"
                data-request-data="$dataRequestDataString"
                data-request-update="list_manage_toolbar: '#plugin-toolbar'"
                name="seed"
                data-stripe-load-indicator
            >$name$suffix</button></li>
HTML
        );
    } else {
        print("<li>$name$suffix</li>");
    }
}
print('</ul>');
?>