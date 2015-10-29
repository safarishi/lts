<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {

    return view('welcome');
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function($api)
{
    $api->get('articles', 'App\Http\Controllers\ArticleController@index');
    $api->get('articles/{id}', 'App\Http\Controllers\ArticleController@show');
    $api->get('reports', 'App\Http\Controllers\ArticleController@report');
    $api->put('articles/{id}/stars', 'App\Http\Controllers\ArticleController@star');
    $api->post('oauth/access_token', 'App\Http\Controllers\OauthController@postAccessToken');
});
