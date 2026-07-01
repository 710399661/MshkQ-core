<?php

namespace MshkQ\JsonApi\Exception\Handler;

class ResponseBag
{
    protected $status;

    protected $errors;

    public function __construct($status, array $errors)
    {
        $this->status = (int) $status;
        $this->errors = $errors;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
