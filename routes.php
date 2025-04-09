<?php
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use AcornAssociated\Controllers\DB;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\ShowDashboard;

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

Event::listen('system.route', function () {
    Event::fire('acornassociated.beforeRoute');

    Route::get('/api/datachange', DB::class . '@datachange');
    // TODO: Route::get('/laravel-dashboard', ShowDashboard::class);

    Route::match(
        ['get', 'post'], '/broadcasting/auth',
        '\\'.AuthWrapper::class.'@authenticate'
    )->middleware('web');

    Route::get( '/api/comment', DB::class . '@comment');
    Route::post('/api/comment', DB::class . '@comment');

    Event::fire('acornassociated.route');
}, PHP_INT_MIN);
