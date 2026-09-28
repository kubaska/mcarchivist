<?php

namespace Tests;

use App\Models\File;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

trait InteractsWithFilesystem
{
    private string $fileData = 'testdata';
    protected string $exampleFileSha1 = '44115646e09ab3481adc2b1dc17be10dd9cdaa09';
    private array $createdFiles = [];

    protected function makeFile(string $fileDir, string $fileName, bool $makeDir = false): \SplFileInfo
    {
        if ($makeDir) {
            app(Filesystem::class)->ensureDirectoryExists($fileDir);
        }

        file_put_contents($filePath = Path::join($fileDir, $fileName), $this->fileData);
        $this->createdFiles[] = $filePath;

        return new \SplFileInfo($filePath);
    }

    protected function makeExampleFile(File $file): \SplFileInfo
    {
        $base = app(SettingsServiceFake::class)->get('general.storage.'.$file->storage_area->getSettingKey());
        return $this->makeFile(Path::join($base, $file->path), $file->file_name, true);
    }

    protected function cleanupTestFiles()
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
