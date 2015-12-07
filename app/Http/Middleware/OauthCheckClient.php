<?php

namespace App\Http\Middleware;

use DB;
use Input;
use Closure;
use App\Exceptions\UnauthorizedClientException;
use League\OAuth2\Server\Exception\InvalidRequestException;

class OauthCheckClient
{
    public function handle($request, Closure $next)
    {
        $clientId = Input::get('client_id', null);
        if (is_null($clientId)) {
            throw new InvalidRequestException('client_id');
        }

        $clientSecret = Input::get('client_secret', null);
        if (is_null($clientSecret)) {
            throw new InvalidRequestException('client_secret');
        }

        $client = DB::connection('mysql')->table('oauth_clients')
            ->where('id', $clientId)
            ->where('secret', $clientSecret)
            ->get();
        if (empty($client)) {
            throw new UnauthorizedClientException;
        }

        return $next($request);
    }
}