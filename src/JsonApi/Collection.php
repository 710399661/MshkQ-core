<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Contracts\ElementInterface;
use MshkQ\JsonApi\Contracts\SerializerInterface;

class Collection implements ElementInterface
{
    protected $data;

    protected $serializer;

    protected $includes = [];

    protected $fields = [];

    protected $resources = [];

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

    public function getResources()
    {
        if (empty($this->resources)) {
            foreach ($this->data as $item) {
                $resource = new Resource($item, $this->serializer);
                $resource->with($this->includes);
                $resource->fields($this->fields);
                $this->resources[] = $resource;
            }
        }

        return $this->resources;
    }

    public function getId()
    {
        return null;
    }

    public function getType()
    {
        $resources = $this->getResources();
        return $resources ? $resources[0]->getType() : null;
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

    public function getIncluded()
    {
        $included = [];
        $seen = [];

        foreach ($this->getResources() as $resource) {
            foreach ($resource->getIncluded() as $inc) {
                $key = $inc->getType() . ':' . $inc->getId();
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $included[] = $inc;
                }
            }
        }

        return $included;
    }

    public function toArray()
    {
        return array_map(function (Resource $resource) {
            return $resource->toArray();
        }, $this->getResources());
    }

    public function merge(Collection $collection)
    {
        $this->includes = array_unique(array_merge($this->includes, $collection->includes));
        return $this;
    }
}
