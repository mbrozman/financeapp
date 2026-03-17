<?php

use App\Http\Controllers\MigrationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/migrate', [MigrationController::class, 'migrate']);
