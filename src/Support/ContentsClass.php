<?php

namespace KusikusiCMS\Models\Support;

use stdClass;

class ContentsClass extends stdClass
{
    private array $fields = [];

    /**
     * Magic method to set fields dynamically
     */
    public function __set(string $name, string $value)
    {
        $this->fields[$name] = true;
        $this->$name = $value;
    }

    /**
     * Magic method to get fields dynamically
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->$name;
        } else {
            return '';
        }
    }

    /**
     * Magic method to check if a dynamic field is set (e.g., using isset() or empty())
     */
    public function __isset($name)
    {
        return isset($this->fields[$name]);
    }

    public function __toString(): string
    {
        return json_encode($this);
    }
}
