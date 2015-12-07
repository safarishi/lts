<?php

namespace App\Http\Middleware;

use Lang;
use Closure;
use Illuminate\Http\JsonResponse;
use League\OAuth2\Server\Exception\OAuthException;

class OAuthExceptionHandlerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (OAuthException $e) {
            // 自定义捕获 oauth2 异常处理逻辑
            return $this->customHandleException($e);
        }
    }

    protected function customHandleException($e)
    {
        $attrs = [];

        $errorType    = $e->errorType;
        $errorMessage = $e->getMessage();

        if ($errorType == 'invalid_request') {
            if ($errorMessage == 'The refresh token is invalid.') {
                $errorType = 'invalid_refresh_token';
            } else {
                preg_match('/"(.*?)"/', $errorMessage, $matches);
                $parameter = $matches[1];
                $attrs['parameter'] = $parameter;
            }
        }

        $message = Lang::get('oauth.'.$errorType, $attrs);
        $message = starts_with($message, 'oauth.') ? $errorMessage : $message;

        return new JsonResponse([
                'error' => $e->errorType,
                'error_description' => $message
            ],
            $e->httpStatusCode,
            $e->getHttpHeaders()
        );
    }

}