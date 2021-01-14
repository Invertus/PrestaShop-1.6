<?php

namespace Invertus\dpdBaltics\Util;

class HashUtility
{
    public static function hash($text)
    {
        return md5(_COOKIE_KEY_ . $text);
    }
}