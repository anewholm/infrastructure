<?php

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;

class AuthWrapper
{

    public function authenticate(Request $request){
        $request->setUserResolver(function($guard){
            return \BackendAuth::user();
        });
        $bc = new BroadcastController();
        return $bc->authenticate($request);
    }

}


Event::fire('acorn.beforeRoute');

Route::match(
    ['get', 'post'], '/broadcasting/auth',
    '\\'.AuthWrapper::class.'@authenticate'
)->middleware('web');


Event::fire('acorn.route');