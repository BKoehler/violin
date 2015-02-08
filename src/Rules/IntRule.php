<?php

namespace Violin\Rules;

class IntRule
{
    /**
     * Run the validation
     *
     * @param  string $name
     * @param  mixed $value
     * @return bool
     */
    public function run($name, $value)
    {
        return is_numeric($value) && (int)$value == $value;
    }
}
