<?php

namespace App\Actions;

class Encoding
{

    static function make_utf8(string $string)
    {

        $utf8 = \mb_detect_encoding($string, ["UTF-8"], true);

        if ($utf8 !== false) {
            return $string;
        }
        $encoding = \mb_detect_encoding($string, mb_detect_order(), true);

        if ($encoding === false) {
            throw new \RuntimeException("String encoding cannot be detected");
        }

        return \mb_convert_encoding($string, "UTF-8", $encoding);

    }
}
