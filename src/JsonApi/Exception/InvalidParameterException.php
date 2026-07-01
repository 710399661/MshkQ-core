<?php

namespace MshkQ\JsonApi\Exception;

class InvalidParameterException extends \Exception
{
    protected $invalidParameter;

    public function __construct($message = '', $parameter = '', $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->invalidParameter = $parameter;
    }

    public function getInvalidParameter()
    {
        return $this->invalidParameter;
    }
}
