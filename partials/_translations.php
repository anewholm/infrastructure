<ul>
<?php
foreach ($value as $object) {
    $attributeValues = json_decode($object->attribute_data);
    print("<li><b>$object->locale</b>: $attributeValues->name</li>");
}
?>
</ul>