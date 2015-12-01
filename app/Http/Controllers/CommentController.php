<?php

namespace App\Http\Controllers;

use LucaDegasperi\OAuth2Server\Authorizer;

class CommentController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth', ['except' => ['']]);
        $this->middleware('validation');
    }

    private static $_validate = [
        // 'comment' => [
        //     'content' => 'required',
        // ],
    ];

}