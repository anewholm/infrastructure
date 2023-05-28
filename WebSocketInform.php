<?php namespace AcornAssociated;

use \AcornAssociated\WebSocketClient;
use WebSocket\ConnectionException;
use Flash;

trait WebSocketInform
{
    public function informClients(?array $options = [])
    {
        $object    = (isset($options['object']) ? $options['object'] : $this);
        $class     = (isset($options['class'])  ? $options['class']  : get_class($object));
        $className = preg_replace('#.*\\\#', '', $class);
        $id        = (isset($options['id'])     
            ? $options['id']     
            : (property_exists($object, 'id') ? $object->id : NULL)
        );
        $channel   = (isset($options['channel'])
            ? $options['channel']
            : (property_exists($object, 'channel') ? $object->channel : strtolower($className))
        );
        $contexts  = (isset($options['contexts']) 
            ? $options['contexts'] 
            : (method_exists($object, 'contexts') ? $object->contexts() : NULL)
        );

        try {
            WebSocketClient::send($channel, array(
                'class'    => $class,
                'ID'       => $id,
                'contexts' => $contexts,
                'object'   => $object,
                'options'  => $options,
            ));
        } catch (ConnectionException $ex) {
            // TODO: What to do if websockets:run not running?
            // proc_open('artisan websockets:run');
            // throw new ConnectionException
            Flash::error('WebSocket not found. Did you ./artisan websockets:run?');
        }
    }
}

