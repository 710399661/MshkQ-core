<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Exception\Handler\ExceptionHandlerInterface;
use MshkQ\JsonApi\Exception\Handler\ResponseBag;
use MshkQ\JsonApi\Exception\InvalidParameterException;

class ErrorHandler
{
    protected $handlers = [];

    public function registerHandler(ExceptionHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function handle(\Exception $e)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->manages($e)) {
                $response = $handler->handle($e);
                if ($response instanceof ResponseBag) {
                    return $response;
                }
                if (is_array($response)) {
                    $status = $e->getCode() ?: 500;
                    return new ResponseBag($status, $response);
                }
            }
        }

        $errors = [
            [
                'status' => '500',
                'code' => $e->getMessage() ?: 'internal_error',
            ],
        ];

        return new ResponseBag(500, $errors);
    }
}
