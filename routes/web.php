<?php

use App\Http\Controllers\MigrationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/migrate', [MigrationController::class, 'forceSchemaFix']);
Route::get('/schema-fix', [MigrationController::class, 'forceSchemaFix']);
Route::get('/fresh', [MigrationController::class, 'fresh']);
Route::get('/refresh-all', [MigrationController::class, 'refreshAll']);
Route::get('/debug-investments', [MigrationController::class, 'debugInvestments']);
