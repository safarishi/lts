<?php

namespace App\Http\Middleware;

use DB;
use Closure;

class DatabaseDisconnection
{
    public function handle($request, Closure $next, $parameter)
    {
        $response = $next($request);

        $paramArr = explode(':', $parameter);

        foreach ($paramArr as $value) {
            // 手动断开数据库连接
            DB::disconnect($value);
        }

        return $response;
    }
}