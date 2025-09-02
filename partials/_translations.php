<ul>
<?php
foreach ($value as $object) {
    $attributeValues = json_decode($object->attribute_data);
    $name            = (property_exists($attributeValues, 'name') ? $attributeValues->name : '<no name>');
    $nameEscaped     = e($name);
    print("<li><b>$object->locale</b>: $nameEscaped</li>");
}
?>
</ul>