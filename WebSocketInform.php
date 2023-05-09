<?php namespace AcornAssociated;

use \AcornAssociated\WebSocketClient;

trait WebSocketInform
{
    public function informClients(?array $options = [])
    {
        $object    = (isset($options['object']) ? $options['object'] : $this);
        $class     = get_class($object);
        $className = preg_replace('#.*\\\#', '', $class);
        $channel   = (isset($options['channel'])
            ? $options['channel']
            : (property_exists($object, 'channel') ? $object->channel : strtolower($className))
        );
        $context   = (isset($options['context']) ? $options['context'] : NULL);

        WebSocketClient::send($channel, array(
            'class'   => $class,
            'ID'      => $object->id,
            'context' => $context,
            'object'  => $object,
            'options' => $options,
        ));
    }
}

