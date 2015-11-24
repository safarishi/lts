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
    return 'lts';
    return view('welcome');
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['namespace' => 'App\Http\Controllers'], function ($api) {
    // app route list
    $api->get('articles', 'ArticleController@index');
    $api->get('articles/{id}', 'ArticleController@show');
    $api->get('reports', 'ArticleController@report');
    $api->put('articles/{id}/stars', 'ArticleController@star');
    $api->delete('articles/{id}/stars', 'ArticleController@unstar');
    $api->post('articles/{id}/comments', 'ArticleController@comment');
    $api->post('articles/{id}/anonymous_comments', 'ArticleController@anonymousComment');
    // 用户注册
    $api->post('users', 'UserController@store');
    // 用户登录
    $api->post('oauth/access_token', 'OauthController@postAccessToken');
    // 退出登录
    $api->delete('oauth/invalidate_token', 'UserController@logout');
    $api->post('articles/{id}/comments/{comment_id}/replies', 'ArticleController@reply');
    $api->post('articles/{id}/comments/{comment_id}/anonymous_replies', 'ArticleController@anonymousReply');
    $api->put('articles/{id}/comments/{comment_id}/favours', 'ArticleController@favour');
    $api->delete('articles/{id}/comments/{comment_id}/favours', 'ArticleController@unfavour');
    $api->get('articles/{id}/comments', 'ArticleController@commentList');
    $api->get('search/articles', 'ArticleController@search');
    $api->get('more_articles/{column_id}', 'ArticleController@moreArticle');
    $api->get('user', 'UserController@show');
    $api->get('user/comments', 'UserController@myComment');
    $api->get('user/stars', 'UserController@myStar');
    $api->get('user/informations', 'UserController@myInformation');
    // 修改用户个人信息
    $api->post('user', 'UserController@modify');
    $api->get('products', 'ArticleController@product');
    $api->get('teams', 'ArticleController@team');
    // generate token
    $api->get('generate_token', 'MultiplexController@generateToken');
    $api->get('generate_captcha', 'MultiplexController@generateCaptcha');
    // third party login url version 1
    $api->get('weibo_url', 'ThirdPartyLoginController@generateWeiboUrl');
    $api->get('qq_url', 'ThirdPartyLoginController@generateQqUrl');
    $api->get('weixin_url', 'ThirdPartyLoginController@generateWeixinUrl');
    // third party login url version 2
    $api->get('redirect_url/{type}', 'ThirdPartyLoginController@redirectUrl');
    $api->get('weibo_callback', 'ThirdPartyLoginController@weiboCallback');
    $api->get('entry', 'ThirdPartyLoginController@entry');
    // 小红点
    $api->get('user/notices', 'UserController@notice');
    // 点击红点
    $api->delete('user/notices', 'UserController@removeNotice');
});

Route::group(array('prefix' => 'v2'), function()
{
    // route for qq callback
    Route::get('qq_callback', 'ThirdPartyLoginController@qqCallback');
});

Route::group(array('prefix' => 'v3'), function()
{
    // route for weinxi callback
    Route::get('weixin_callback', 'ThirdPartyLoginController@weixinCallback');
});
