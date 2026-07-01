<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Contracts\ElementInterface;
use MshkQ\JsonApi\Contracts\SerializerInterface;

class Resource implements ElementInterface
{
    protected $data;

    protected $serializer;

    protected $includes = [];

    protected $fields = [];

    protected $includedResources = [];

    public function __construct($data, SerializerInterface $serializer)
    {
        $this->data = $data;
        $this->serializer = $serializer;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getSerializer()
    {
        return $this->serializer;
    }

    public function getId()
    {
        return (string) $this->serializer->getId($this->data);
    }

    public function getType()
    {
        return $this->serializer->getType($this->data);
    }

    public function with($include)
    {
        if (is_string($include)) {
            $include = explode(',', $include);
        }

        $this->includes = array_merge($this->includes, $include);

        return $this;
    }

    public function fields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }

    public function getAttributes()
    {
        $type = $this->getType();
        $requestedFields = isset($this->fields[$type]) ? $this->fields[$type] : null;

        $attributes = $this->serializer->getAttributes($this->data, $requestedFields);

        if (is_array($attributes) && isset($attributes['id'])) {
            unset($attributes['id']);
        }

        return $attributes;
    }

    public function getRelationships()
    {
        $type = $this->getType();
        $requestedFields = isset($this->fields[$type]) ? $this->fields[$type] : null;

        $allRelationships = $this->serializer->getRelationships($this->data, $requestedFields);

        $relationships = [];

        foreach ($this->includes as $include) {
            if (strpos($include, '.') !== false) {
                [$relationName] = explode('.', $include, 2);
            } else {
                $relationName = $include;
            }

            if (isset($allRelationships[$relationName])) {
                $relationships[$relationName] = $allRelationships[$relationName];
            }
        }

        return $relationships;
    }

    public function getIncluded()
    {
        $included = [];
        $relationships = $this->getRelationships();

        foreach ($relationships as $name => $relationship) {
            if (!($relationship instanceof Relationship)) {
                continue;
            }

            $data = $relationship->getData();

            if ($data instanceof Collection) {
                foreach ($data->getResources() as $resource) {
                    $key = $resource->getType() . ':' . $resource->getId();
                    if (!isset($this->includedResources[$key])) {
                        $this->includedResources[$key] = $resource;
                        $included[] = $resource;

                        $nestedIncludes = $this->getNestedIncludes($name);
                        if (!empty($nestedIncludes)) {
                            $resource->with($nestedIncludes);
                            foreach ($resource->getIncluded() as $nested) {
                                $nestedKey = $nested->getType() . ':' . $nested->getId();
                                if (!isset($this->includedResources[$nestedKey])) {
                                    $this->includedResources[$nestedKey] = $nested;
                                    $included[] = $nested;
                                }
                            }
                        }
                    }
                }
            } elseif ($data instanceof Resource) {
                $key = $data->getType() . ':' . $data->getId();
                if (!isset($this->includedResources[$key])) {
                    $this->includedResources[$key] = $data;
                    $included[] = $data;

                    $nestedIncludes = $this->getNestedIncludes($name);
                    if (!empty($nestedIncludes)) {
                        $data->with($nestedIncludes);
                        foreach ($data->getIncluded() as $nested) {
                            $nestedKey = $nested->getType() . ':' . $nested->getId();
                            if (!isset($this->includedResources[$nestedKey])) {
                                $this->includedResources[$nestedKey] = $nested;
                                $included[] = $nested;
                            }
                        }
                    }
                }
            }
        }

        return $included;
    }

    protected function getNestedIncludes($relationName)
    {
        $nested = [];
        foreach ($this->includes as $include) {
            if (strpos($include, $relationName . '.') === 0) {
                $nested[] = substr($include, strlen($relationName . '.'));
            }
        }
        return array_unique($nested);
    }

    public function toArray()
    {
        $array = [
            'type' => $this->getType(),
            'id' => $this->getId(),
        ];

        $attributes = $this->getAttributes();
        if (!empty($attributes)) {
            $array['attributes'] = $attributes;
        }

        $relationships = $this->getRelationships();
        if (!empty($relationships)) {
            $array['relationships'] = [];
            foreach ($relationships as $name => $relationship) {
                if ($relationship instanceof Relationship) {
                    $array['relationships'][$name] = $relationship->toArray();
                }
            }
        }

        return $array;
    }

    public function merge(Resource $resource)
    {
        $this->includes = array_unique(array_merge($this->includes, $resource->includes));
        return $this;
    }
}
