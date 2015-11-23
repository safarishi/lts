<?php

namespace App\Http\Middleware;

use DB;
use Input;
use Closure;
use App\Exceptions\UnauthorizedClientException;

class OauthCheckClient
{
    public function handle($request, Closure $next)
    {
        $clientId     = Input::get('client_id');
        $clientSecret = Input::get('client_secret');

        $client = DB::connection('mysql')->table('oauth_clients')
                ->where('id', $clientId)
                ->where('secret', $clientSecret)
                ->get();

        if (empty($client)) {
            throw new UnauthorizedClientException('Unauthorized client.');
        }

        return $next($request);
    }
}