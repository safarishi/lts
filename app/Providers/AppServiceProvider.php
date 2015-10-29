<?php

namespace App\Providers;

// use Illuminate\Http\JsonResponse;
use Illuminate\Support\ServiceProvider;
// use App\Exceptions\Api\ApiException;
// use App\Exceptions\Api\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        // $this->registerExceptionHandlers();
    }

    /**
     * Retister exception handlers
     *
     * @return mixed
     */
    // private function registerExceptionHandlers()
    // {
    //     $this->app->call(function(ApiException $e) {
    //         return $this->handleException($e);
    //     });
    // }

    // private function handleException($exception)
    // {
    //     $res = [
    //         'error' => $exception->errorType,
    //         'error_description' => $exception->getMessage(),
    //     ];

    //     if ($code = $exception->getCode()) {
    //         $res['error_code'] = $code;
    //     }

    //     if ($uri = $exception->errorUri) {
    //         $res['error_uri'] = $uri;
    //     }

    //     return new JsonResponse($res, $exception->httpStatusCode, $exception->getHttpHeaders());
    // }
}
