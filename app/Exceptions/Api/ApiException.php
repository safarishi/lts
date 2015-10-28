<?php

namespace App\Exceptions\Api;

use Symfony\Component\HttpFoundation\Request;

class ApiException extends \Exception
{

    public $httpStatusCode = 400;

    public $errorType = 'unknown_error';

    public $errorUri = '';

    public $errorMessages = array();

    /**
     * Throw a new exception
     * @param string|array $msg Exception Message
     */
    public function __construct($msg = 'An error occured', $code = 0)
    {
        if (is_array($msg)) {
            $msg = implode($msg, ' ');
        }
        parent::__construct($msg, $code);
    }

    /**
     * Get all headers that have to be send with the error response
     * @return array Array with header values
     */
    public function getHttpHeaders()
    {
        $headers = [];
        switch ($this->httpStatusCode) {
            case 401:
                $headers[] = 'HTTP/1.1 401 Unauthorized';
                break;
            case 500:
                $headers[] = 'HTTP/1.1 500 Internal Server Error';
                break;
            case 501:
                $headers[] = 'HTTP/1.1 501 Not Implemented';
                break;
            case 400:
            default:
                $headers[] = 'HTTP/1.1 400 Bad Request';
                break;
        }

        // Add "WWW-Authenticate" header
        //
        // RFC 6749, section 5.2.:
        // "If the client attempted to authenticate via the 'Authorization'
        // request header field, the authorization server MUST
        // respond with an HTTP 401 (Unauthorized) status code and
        // include the "WWW-Authenticate" response header field
        // matching the authentication scheme used by the client.
        // @codeCoverageIgnoreStart
        if ($this->errorType === 'invalid_client') {
            $authScheme = null;
            $request = new Request();
            if ($request->getUser() !== null) {
                $authScheme = 'Basic';
            } else {
                $authHeader = $request->headers->get('Authorization');
                if ($authHeader !== null) {
                    if (strpos($authHeader, 'Bearer') === 0) {
                        $authScheme = 'Bearer';
                    } elseif (strpos($authHeader, 'Basic') === 0) {
                        $authScheme = 'Basic';
                    }
                }
            }
            if ($authScheme !== null) {
                $headers[] = 'WWW-Authenticate: '.$authScheme.' realm=""';
            }
        }
        // @codeCoverageIgnoreEnd
        return $headers;
    }
}