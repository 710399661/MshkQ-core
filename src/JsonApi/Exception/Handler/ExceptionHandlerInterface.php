<?php

namespace MshkQ\JsonApi\Exception\Handler;

interface ExceptionHandlerInterface
{
    public function manages(\Exception $e);

    public function handle(\Exception $e);
}
