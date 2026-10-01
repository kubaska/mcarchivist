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

    public function assertEmptyDirectory(string $path): void
    {
        $this->assertTrue(!(new \FilesystemIterator($path))->valid(), "Failed asserting that directory $path is empty.");
    }

    public function assertDirectoryHasOnlyOneFile(string $path)
    {
        $this->assertDirectoryFileCount($path, 1);
    }

    public function assertDirectoryFileCount(string $path, int $count)
    {
        $this->assertTrue(
            count(app(Filesystem::class)->files($path)) === $count,
            sprintf('Failed asserting that directory "%s" contains %s %s.', $path, $count, $count === 1 ? 'file' : 'files')
        );
    }
}
