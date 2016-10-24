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
Route::group(['prefix' => 'events'], function(){
    Route::get('/', 'EventsController@retrieveEvents');

    Route::post('/signin', 'EventsController@signinEvent');
});

Route::group(['prefix' => 'user'], function() {
    Route::get('{uin}', 'UserController@retrieveUser');
});