<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Contracts\ElementInterface;

class Document implements \JsonSerializable
{
    protected $data;

    protected $errors = [];

    protected $meta = [];

    protected $links = [];

    protected $jsonapi;

    public function setData(ElementInterface $data)
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
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

    public function setJsonApi($jsonapi)
    {
        $this->jsonapi = $jsonapi;
        return $this;
    }

    public function toArray()
    {
        $document = [];

        if ($this->jsonapi) {
            $document['jsonapi'] = $this->jsonapi;
        }

        if ($this->data !== null) {
            $document['data'] = $this->data->toArray();

            if ($this->data instanceof Resource) {
                $included = $this->data->getIncluded();
            } elseif ($this->data instanceof Collection) {
                $included = $this->data->getIncluded();
            } else {
                $included = [];
            }

            if (!empty($included)) {
                $document['included'] = array_map(function (Resource $resource) {
                    return $resource->toArray();
                }, $included);
            }
        }

        if (!empty($this->errors)) {
            $document['errors'] = $this->errors;
        }

        if (!empty($this->meta)) {
            $document['meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $document['links'] = $this->links;
        }

        return $document;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
