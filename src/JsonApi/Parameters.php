<?php

namespace MshkQ\JsonApi;

use MshkQ\JsonApi\Exception\InvalidParameterException;

class Parameters
{
    protected $input;

    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getInclude(?array $available = null)
    {
        $include = isset($this->input['include']) ? $this->input['include'] : null;

        if (is_array($include)) {
            return $include;
        }

        if (is_string($include) && !empty($include)) {
            $includes = explode(',', $include);
        } else {
            $includes = [];
        }

        if ($available !== null) {
            foreach ($includes as $includeItem) {
                if (!in_array($includeItem, $available)) {
                    throw new InvalidParameterException(
                        sprintf('Invalid include: %s', $includeItem),
                        'include'
                    );
                }
            }
        }

        return $includes;
    }

    public function getFields()
    {
        $fields = isset($this->input['fields']) ? $this->input['fields'] : [];

        if (!is_array($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $type => $fieldString) {
            if (is_string($fieldString)) {
                $result[$type] = explode(',', $fieldString);
            } elseif (is_array($fieldString)) {
                $result[$type] = $fieldString;
            }
        }

        return $result;
    }

    public function getSort(?array $available = null)
    {
        $sort = isset($this->input['sort']) ? $this->input['sort'] : null;

        if (is_array($sort)) {
            return $sort;
        }

        if (is_string($sort) && !empty($sort)) {
            $sortFields = explode(',', $sort);
        } else {
            return null;
        }

        if ($available !== null) {
            foreach ($sortFields as $field) {
                $fieldName = ltrim($field, '-');
                if (!in_array($fieldName, $available)) {
                    throw new InvalidParameterException(
                        sprintf('Invalid sort field: %s', $fieldName),
                        'sort'
                    );
                }
            }
        }

        return $sortFields;
    }

    public function getFilter()
    {
        return isset($this->input['filter']) ? $this->input['filter'] : null;
    }

    public function getLimit($max = null)
    {
        $limit = isset($this->input['page']['limit']) || isset($this->input['page']['size'])
            ? ($this->input['page']['limit'] ?? $this->input['page']['size'])
            : (isset($this->input['limit']) ? $this->input['limit'] : null);

        if ($limit !== null) {
            $limit = (int) $limit;

            if ($max !== null && $limit > $max) {
                $limit = $max;
            }
        }

        return $limit;
    }

    public function getOffset($limit = null)
    {
        $offset = isset($this->input['page']['offset']) || isset($this->input['page']['number'])
            ? ($this->input['page']['offset'] ?? null)
            : (isset($this->input['offset']) ? $this->input['offset'] : null);

        if ($offset !== null) {
            return (int) $offset;
        }

        $pageNumber = isset($this->input['page']['number']) ? (int) $this->input['page']['number'] : null;
        if ($pageNumber !== null && $limit !== null && $limit > 0) {
            return ($pageNumber - 1) * $limit;
        }

        return null;
    }

    public function getPage()
    {
        return isset($this->input['page']) ? $this->input['page'] : [];
    }
}
