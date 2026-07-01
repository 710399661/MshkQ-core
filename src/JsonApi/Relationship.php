<?php

namespace MshkQ\JsonApi;

class Relationship
{
    protected $data;

    protected $links = [];

    protected $meta = [];

    public function __construct($data = null)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function addLink($key, $value)
    {
        $this->links[$key] = $value;
        return $this;
    }

    public function setLinks(array $links)
    {
        $this->links = $links;
        return $this;
    }

    public function addMeta($key, $value)
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function setMeta(array $meta)
    {
        $this->meta = $meta;
        return $this;
    }

    public function toArray()
    {
        $array = [];

        if ($this->data !== null) {
            if ($this->data instanceof Resource) {
                $array['data'] = [
                    'type' => $this->data->getType(),
                    'id' => $this->data->getId(),
                ];
            } elseif ($this->data instanceof Collection) {
                $array['data'] = array_map(function (Resource $resource) {
                    return [
                        'type' => $resource->getType(),
                        'id' => $resource->getId(),
                    ];
                }, $this->data->getResources());
            } else {
                $array['data'] = $this->data;
            }
        }

        if (!empty($this->links)) {
            $array['links'] = $this->links;
        }

        if (!empty($this->meta)) {
            $array['meta'] = $this->meta;
        }

        return $array;
    }
}
