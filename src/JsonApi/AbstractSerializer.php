<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Contracts\SerializerInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractSerializer implements SerializerInterface
{
    protected $type;

    protected $request;

    public function getType($model)
    {
        return $this->type;
    }

    public function getId($model)
    {
        if (is_object($model)) {
            return $model->id ?? null;
        }

        if (is_array($model)) {
            return $model['id'] ?? null;
        }

        return null;
    }

    abstract public function getAttributes($model, ?array $fields = null);

    public function getRelationships($model, ?array $fields = null)
    {
        return [];
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    protected function formatDate(?\DateTime $date = null)
    {
        if ($date) {
            return $date->format(\DateTime::RFC3339);
        }
        return null;
    }

    public function hasOne($model, $serializer, $relation = null)
    {
        return $this->buildRelationship($model, $serializer, $relation);
    }

    public function hasMany($model, $serializer, $relation = null)
    {
        return $this->buildRelationship($model, $serializer, $relation, true);
    }

    protected function buildRelationship($model, $serializer, $relation = null, $many = false)
    {
        if (is_null($relation)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $relation = $trace[2]['function'] ?? null;
        }

        $data = $this->getRelationshipData($model, $relation);

        if ($data) {
            $serializer = $this->resolveSerializer($serializer, $model, $data);

            $elementClass = $many ? Collection::class : Resource::class;
            $element = new $elementClass($data, $serializer);

            return new Relationship($element);
        }

        return null;
    }

    protected function getRelationshipData($model, $relation)
    {
        if (is_object($model)) {
            return $model->$relation ?? null;
        } elseif (is_array($model)) {
            return $model[$relation] ?? null;
        }
        return null;
    }

    protected function resolveSerializer($serializer, $model, $data)
    {
        if ($serializer instanceof \Closure) {
            $serializer = call_user_func($serializer, $model, $data);
        }

        if (is_string($serializer)) {
            $serializer = $this->resolveSerializerClass($serializer);
        }

        if (!($serializer instanceof SerializerInterface)) {
            throw new \InvalidArgumentException(
                'Serializer must be an instance of ' . SerializerInterface::class
            );
        }

        return $serializer;
    }

    protected function resolveSerializerClass($class)
    {
        if (function_exists('app')) {
            $serializer = app()->make($class);
            if (method_exists($serializer, 'setRequest')) {
                $serializer->setRequest($this->request);
            }
            return $serializer;
        }

        return new $class();
    }
}
