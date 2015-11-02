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

Route::get('/', function()
{
    return view('welcome');
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function($api)
{
    $api->get('articles', 'App\Http\Controllers\ArticleController@index');
    $api->get('articles/{id}', 'App\Http\Controllers\ArticleController@show');
    $api->get('reports', 'App\Http\Controllers\ArticleController@report');
    $api->put('articles/{id}/stars', 'App\Http\Controllers\ArticleController@star');
    $api->delete('articles/{id}/stars', 'App\Http\Controllers\ArticleController@unstar');
    $api->post('articles/{id}/comments', 'App\Http\Controllers\ArticleController@comment');
    $api->post('articles/{id}/anonymous_comments', 'App\Http\Controllers\ArticleController@anonymousComment');
    // 用户注册
    $api->post('users', 'App\Http\Controllers\UserController@store');
    // 用户登录
    $api->post('oauth/access_token', 'App\Http\Controllers\OauthController@postAccessToken');
    // 退出登录
    $api->delete('oauth/invalidate_token', 'App\Http\Controllers\UserController@logout');
    $api->post('articles/{id}/comments/{comment_id}/replies', 'App\Http\Controllers\ArticleController@reply');
    $api->post('articles/{id}/comments/{comment_id}/anonymous_replies', 'App\Http\Controllers\ArticleController@anonymousReply');
    $api->put('articles/{id}/comments/{comment_id}/favours', 'App\Http\Controllers\ArticleController@favour');
    $api->delete('articles/{id}/comments/{comment_id}/favours', 'App\Http\Controllers\ArticleController@unfavour');
    $api->get('articles/{id}/comments', 'App\Http\Controllers\ArticleController@commentList');
    $api->get('search/articles', 'App\Http\Controllers\ArticleController@search');
    $api->get('more_articles/{column_id}', 'App\Http\Controllers\ArticleController@moreArticle');
    $api->get('user', 'App\Http\Controllers\UserController@show');
    $api->get('user/comments', 'App\Http\Controllers\UserController@myComment');
    $api->get('user/stars', 'App\Http\Controllers\UserController@myStar');
    $api->get('user/informations', 'App\Http\Controllers\UserController@myInformation');
    // 修改用户个人信息
    $api->post('user', 'App\Http\Controllers\UserController@modify');
    $api->get('products', 'App\Http\Controllers\ArticleController@product');
    $api->get('teams', 'App\Http\Controllers\ArticleController@team');
    // generate token
    $api->get('generate_token', 'App\Http\Controllers\MultiplexController@generateToken');
    $api->get('generate_captcha', 'App\Http\Controllers\MultiplexController@generateCaptcha');
    $api->get('weibo_url', 'App\Http\Controllers\MultiplexController@generateWeiboUrl');
    $api->get('weibo_callback', 'App\Http\Controllers\MultiplexController@weiboCallback');
    $api->get('qq_url', 'App\Http\Controllers\MultiplexController@generateQqUrl');

    $api->get('entry', 'App\Http\Controllers\MultiplexController@entry');
});

Route::group(array('prefix' => 'v2'), function()
{
    Route::get('qq_callback', 'MultiplexController@qqCallback');
});
