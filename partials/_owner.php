<?php
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
$model = $value;
if (!$model instanceof Model) {
    $columnName = $listColumn->columnName;
    $fieldParts = HtmlHelper::nameToArray($columnName);
    $finalField = array_pop($fieldParts);
    if ($finalField != 'name') throw new \Exception("ListColumn field attribute is not name");

    // Back column name legalcase[owner_user_group]
    $backFieldName = $fieldParts[0];
    if (count($fieldParts) > 1) {
        $fieldNests     = implode('][', array_slice($fieldParts, 1));
        $backFieldName .= "[$fieldNests]";
    }
    $backColumn = new ListColumn($backFieldName, '');

    $model = $backColumn->getValueFromData($record);
}

// TODO: Should this partial be in User plugin?
$isOwner = FALSE;
if ($model instanceof UserGroup) {
    $isOwner = $user->groups->contains($model);
}
else if ($model instanceof User) {
    $isOwner = ($model->is($user));
}
else throw new \Exception("_owner.php requires a User or UserGroup Model");

// Output
// TODO: Translate tooltip
$valueEscaped = e($value);
if ($isOwner) print(<<<HTML
    <div href="javascript:;"
        data-toggle="tooltip"
        data-placement="top"
        data-delay="0"
        class='is-owner' 
        title='Grupe te'
    >
        <span class="hover-indicator">$valueEscaped</span>
    </div>
HTML
    );
else print($valueEscaped);