<?php

namespace App\Http\Controllers;

use LucaDegasperi\OAuth2Server\Authorizer;

class CommentV1Controller extends CommentController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('validation');
    }

    private static $_validate = [
        'reply' => [
            'content' => 'required',
        ],
        'anonymousReply' => [
            'content' => 'required',
        ],
    ];
}