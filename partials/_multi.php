<?php
print('<ul class="multi">');
if ($value) {
    $i     = 0;
    $count = $value->count();
    $limit = 2; // TODO: Make configurable per model
    $value->each(function($model) use (&$i, &$limit) {
        $name       = $model->name();
        $id         = $model->id();
        $controller = $model->controllerFullyQualifiedClass();

        $controllerEscaped = str_replace('\\', '\\\\', $controller);
        print(<<<HTML
            <li><a
                data-handler="onPopupRoute"
                data-request-data="route: '$controllerEscaped@update', params: '$id'"
                data-control="popup"
            >
                $name
            </a></li>
    HTML
        );

        return (++$i < $limit);
    });
    print('</ul>');
    if ($count > $limit) {
        $more = e(trans('more...'));
        print("<a class='more'>$more</a>");
    }
}
?>
