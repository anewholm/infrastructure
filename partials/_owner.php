<?php
use Acorn\Traits\PathsHelper;
use Winter\Storm\Html\Helper as HtmlHelper;
use Backend\Classes\ListColumn;
use Acorn\User\Models\User;
use Acorn\User\Models\UserGroup;

$user = User::authUser();

if (!isset($record)) throw new \Exception("_owner.php is a column partial only");
if (!isset($user))   throw new \Exception("_owner.php requires an authenticated user");

// The field may well be a text attribute on the last model
// like legalcase[owner_user_group][name]
// So $value is a text name, not a Model
// TODO: Deal with relation: & select: situations
$model = $value;
if (!$model instanceof Model) {
    if ($backColumnName = PathsHelper::backColumnName($listColumn->columnName, FALSE)) {
        $backColumn     = new ListColumn($backColumnName, '');
        $model          = $backColumn->getValueFromData($record);
    }
}

// TODO: Should this partial be in User plugin?
$isOwner = FALSE;
if ($model instanceof UserGroup) {
    $isOwner = $user->groups->contains($model);
}
else if ($model instanceof User) {
    $isOwner = ($model->is($user));
}
// else throw new \Exception("_owner.php requires a User or UserGroup Model");

// Output
// TODO: Translate tooltip
$valueEscaped = e($value);
if ($isOwner) print(<<<HTML
    <div class="is-owner">
        <div class="tooltip fade top">
            <div class="tooltip-arrow"></div>
            <div class="tooltip-inner">Grupe <i>te</i></div>
        </div>
        <span class="hover-indicator">$valueEscaped</span>
    </div>
HTML
    );
else print($valueEscaped);