<?php

namespace App\Services;

use App\API\DTO\FileDTO;
use App\Mca\McaFile;
use Symfony\Component\Filesystem\Path;

class ArchiverCommons
{
    public static function downloadFileIfMissing(
        McaDownloader $downloader, FileDTO $fileDTO, string $dir, string $fileName, bool $alreadyHaveFile
    ): McaFile
    {
        if ($alreadyHaveFile) {
            return new McaFile(Path::join($dir, $fileName));
        } else {
            [$algo, $hash] = $fileDTO->hashes->getFirstHash();

            return $downloader->download(
                $fileDTO->url,
                $dir,
                $fileName,
                $algo,
                $hash,
                $fileDTO->size
            );
        }
    }
}
