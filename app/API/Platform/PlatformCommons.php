<?php

namespace App\API\Platform;

class PlatformCommons
{
    public static function tryOpenAndParseModpackInstallProfile(string $filePath, string $manifestFileName)
    {
        $zip = new \ZipArchive();
        $zip->open($filePath);
        $manifest = $zip->getFromName($manifestFileName);
        if ($manifest === false) return false;

        return json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
    }
}
