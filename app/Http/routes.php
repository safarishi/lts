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
    echo 'lts';
    return view('welcome');
});

Route::patterns(['id' => '[1-9][0-9]*']);

Route::group(['prefix' => 'v1'], function () {
    Route::get('articles', 'ArticleV1Controller@index');
    Route::get('articles/{id}', 'ArticleV1Controller@show');
    // 收藏文章
    Route::put('articles/{id}/stars', 'ArticleV1Controller@star');
    Route::delete('articles/{id}/stars', 'ArticleV1Controller@unstar');
    // 评论文章
    Route::post('articles/{id}/comments', 'ArticleV1Controller@comment');
    // 匿名评论文章
    Route::post('articles/{id}/anonymous_comments', 'ArticleV1Controller@anonymousComment');
    // 评论回复
    Route::post('articles/{id}/comments/{comment_id}/replies', 'CommentV1Controller@reply');
    // 匿名回复评论
    Route::post('articles/{id}/comments/{comment_id}/anonymous_replies', 'CommentV1Controller@anonymousReply');
    // 用户注册
    Route::post('users', 'UserV1Controller@store');
    Route::post('oauth/access_token', 'OAuthController@postAccessToken');
});

// middleware auth todo
// 可能需要去掉
// Route::get('oauth/authorize', ['as' => 'oauth.authorize.get','middleware' => ['check-authorization-params', 'auth'], function() {
Route::get('oauth/authorize', ['as' => 'oauth.authorize.get', 'middleware' => ['check-authorization-params'], function() {
    // display a form where the user can authorize the client to access it's data
    $authParams = Authorizer::getAuthCodeRequestParams();
    $formParams = array_except($authParams,'client');
    $formParams['client_id'] = $authParams['client']->getId();
    return View::make('oauth.authorization-form', ['params'=>$formParams,'client'=>$authParams['client']]);
}]);

// Route::post('oauth/authorize', ['as' => 'oauth.authorize.post','middleware' => ['csrf', 'check-authorization-params', 'auth'], function() {
Route::post('oauth/authorize', ['as' => 'oauth.authorize.post','middleware' => ['check-authorization-params'], function() {

    $params = Authorizer::getAuthCodeRequestParams();
    // add extra
    Auth::attempt(['email' => Input::get('email'), 'password' => Input::get('password')]);
    $params['user_id'] = Auth::user()->id;

    $redirectUri = '';

    // if the user has allowed the client to access its data, redirect back to the client with an auth code
    if (Input::get('approve') !== null) {
        $redirectUri = Authorizer::issueAuthCode('user', $params['user_id'], $params);
    }

    // if the user has denied the client to access its data, redirect back to the client with an error message
    if (Input::get('deny') !== null) {
        $redirectUri = Authorizer::authCodeRequestDeniedRedirectUri();
    }
    return Redirect::to($redirectUri);
}]);

Route::post('oauth/access_token', function() {
    return Response::json(Authorizer::issueAccessToken());
});

Route::get('auth/login', 'MultiplexController@authLogin');

Route::get('redirect_url', 'MultiplexController@redirectUrl');

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['namespace' => 'App\Http\Controllers'], function ($api) {
    // app route list
    $api->get('articles', 'ArticleController@index');
    $api->get('articles/{id}', 'ArticleController@show')
        ->where('id', '[1-9][0-9]*');
    $api->get('reports', 'ArticleController@report');
    $api->put('articles/{id}/stars', 'ArticleController@star');
    $api->delete('articles/{id}/stars', 'ArticleController@unstar');
    $api->post('articles/{id}/comments', 'ArticleController@comment');
    $api->post('articles/{id}/anonymous_comments', 'ArticleController@anonymousComment');
    // 用户注册
    $api->post('users', 'UserController@store');
    // 用户登录
    $api->post('oauth/access_token', 'OAuthController@postAccessToken');
    // 退出登录
    $api->delete('oauth/invalidate_token', 'UserController@logout');
    $api->post('articles/{id}/comments/{comment_id}/replies', 'CommentController@reply');
    $api->post('articles/{id}/comments/{comment_id}/anonymous_replies', 'CommentController@anonymousReply');
    $api->put('articles/{id}/comments/{comment_id}/favours', 'CommentController@favour');
    $api->delete('articles/{id}/comments/{comment_id}/favours', 'CommentController@unfavour');
    $api->get('articles/{id}/comments', 'ArticleController@commentList');
    $api->get('search/articles', 'ArticleController@search');
    $api->get('more_articles/{column_id}', 'ArticleController@moreArticle');
    $api->get('user', 'UserController@show');
    $api->get('user/comments', 'UserController@myComment');
    $api->get('user/stars', 'UserController@myStar');
    $api->get('user/informations', 'UserController@myInformation');
    // 修改用户个人信息
    $api->post('user', 'UserController@modify');
    // 修改用户的登录密码
    $api->put('user/password', 'UserPasswordController@modify');
    // 发送验证邮件
    $api->post('send/emails', 'UserPasswordController@sendEmail');
    // 重置用户的密码
    $api->put('reset/password', 'UserPasswordController@reset');
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
