<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => config('app.name', 'Pragyan Pathashala').' API',
        'status' => 'ok',
        'endpoints' => [
            'health' => '/up',
            'login' => '/api/login',
            'publicContent' => '/api/public/content',
            'admin' => '/api/admin/*',
        ],
    ]);
});
