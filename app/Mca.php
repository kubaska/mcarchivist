<?php

namespace App;

class Mca
{
    public const VERSION = '1.0.0';

    /**
     * Determines which hashes will be computed for every saved file.
     */
    public const FILE_HASHES_ALGOS = ['md5', 'sha1', 'sha256', 'sha512'];

    public static function getApplicationIdentifier(): string
    {
        return 'mcarchivist/'.self::VERSION;
    }
}
