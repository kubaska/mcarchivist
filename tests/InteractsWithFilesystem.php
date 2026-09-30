<?php

namespace Tests;

use App\Models\File;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

trait InteractsWithFilesystem
{
    private function makeFile(string $fileDir, string $fileName, bool $makeDir = false): \SplFileInfo
    {
        if ($makeDir) {
            app(Filesystem::class)->ensureDirectoryExists($fileDir);
        }

        file_put_contents($filePath = Path::join($fileDir, $fileName), 'testdata');

        return new \SplFileInfo($filePath);
    }

    protected function makeExampleFile(File $file): \SplFileInfo
    {
        return $this->makeFile($file->getAbsoluteDirectoryPath(), $file->file_name, true);
    }
}
