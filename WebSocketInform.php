<?php namespace AcornAssociated;

use \AcornAssociated\WebSocketClient;
use WebSocket\ConnectionException;
use Flash;

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

        try {
            WebSocketClient::send($channel, array(
                'class'   => $class,
                'ID'      => $object->id,
                'context' => $context,
                'object'  => $object,
                'options' => $options,
            ));
        } catch (ConnectionException $ex) {
            // TODO: What to do if websockets:run not running?
            // proc_open('artisan websockets:run');
            // throw new ConnectionException
            Flash::error('WebSocket not found. Did you ./artisan websockets:run?');
        }
    }
}

