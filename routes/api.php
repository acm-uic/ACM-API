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
    Route::get('/', 'EventController@retrieveEvents');

    Route::post('/signin', 'EventController@signinEvent');
});

Route::group(['prefix' => 'user'], function() {
    Route::get('{uin}', 'UserController@retrieveUser');

    Route::post('/link', 'UserController@linkUser');
});