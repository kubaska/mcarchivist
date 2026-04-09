<?php

namespace App\API;

class RequestUtils
{
    /**
     * Stringify array values.
     *
     * The function wraps all array values in double quotes,
     * separates them with a comma and finally wraps the whole thing with square brackets.
     *
     * @param array $values
     * @return string e.g. ["foo","bar"]
     */
    public static function stringifyArray(array $values): string
    {
        return sprintf('[%s]', implode(',', array_map(fn($ver) => sprintf('"%s"', $ver), $values)));
    }
}
