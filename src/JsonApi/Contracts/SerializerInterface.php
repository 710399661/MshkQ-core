<?php

namespace MshkQ\JsonApi\Contracts;

interface SerializerInterface
{
    public function getType($model);

    public function getId($model);

    public function getAttributes($model, ?array $fields = null);

    public function getRelationships($model, ?array $fields = null);
}
