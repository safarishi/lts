<?php

namespace App\Http\Controllers;

use LucaDegasperi\OAuth2Server\Authorizer;
use League\OAuth2\Server\Exception\InvalidRequestException;

class ApiController extends Controller
{
    protected $authorizer;

    protected $accessToken;

    public function __construct(Authorizer $authorizer = null){
        $this->authorizer = $authorizer;

        try {
            $this->accessToken = $authorizer->getChecker()->determineAccessToken();
            if ($this->accessToken) {
                $this->beforeFilter('oauth');
            }
        } catch (InvalidRequestException $e) {
            // do nothing
        }

    }

    public function index() {
      return Input::all();
    }
}
