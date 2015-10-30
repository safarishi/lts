<?php

namespace App\Http\Middleware;

use Input;
use Closure;
use Validator;
use App\Exceptions\ValidationException;

class FieldRequiredMiddleware
{
    public function handle($request, Closure $next, $parameter)
    {
        $paramArr = explode(':', $parameter);

        $rules = array();
        foreach ($paramArr as $param) {
            $rules[$param] = 'required';
        }

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->messages()->all());
        }

        return $next($request);
    }
}