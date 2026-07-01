<?php

namespace MshkQ\JsonApi\Contracts;

interface ElementInterface
{
    public function getId();

    public function getType();

    public function with($include);

    public function fields(array $fields);

    public function toArray();
}
