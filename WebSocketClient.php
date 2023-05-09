<?php namespace AcornAssociated;

use WebSocket\Client; // TODO: Add dependency to textalk/websocket

class WebSocketClient
{
    static $clients = array();

    public static function send(string $name, ?array $payload = array(), ?string $route = '', ?string $host = 'localhost', ?int $port = 8080)
    {
        // Require name
        $payload['name'] = $name;
        unset($payload['_session_key']);
        unset($payload['_token']);

        $connString = "ws://$host:$port/$route";
        if (isset($clients[$connString])) {
            $conn = $clients[$connString];
        } else {
            $conn = new Client($connString);
            $clients[$connString] = $conn;
        }
        $conn->send(json_encode($payload));
    }
}
