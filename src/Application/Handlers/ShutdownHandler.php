<?php

namespace App\Application\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use App\Application\ResponseEmitter\ResponseEmitter;

class ShutdownHandler
{
    private $request;
    private $errorHandler;
    private $displayErrorDetails;

    public function __construct(Request $request, $errorHandler, bool $displayErrorDetails)
    {
        $this->request = $request;
        $this->errorHandler = $errorHandler;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null) {
            $response = $this->errorHandler->handleError($this->request, $error);
            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
