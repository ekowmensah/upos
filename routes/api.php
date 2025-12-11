<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('web')->group(function () {
    Route::get('/health', 'OfflineController@health');
    
    Route::middleware('auth')->group(function () {
        Route::post('/sync/transaction', 'SyncController@syncTransaction');
        Route::get('/sync/pending-count', 'SyncController@getPendingCount');
        
        Route::get('/offline/products', 'OfflineController@getProducts');
        Route::get('/offline/customers', 'OfflineController@getCustomers');
        Route::get('/offline/locations', 'OfflineController@getLocations');
    });
});
