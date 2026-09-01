<?php

namespace Tests\Unit;

use App\Support\McaFilesystem;
use Tests\TestCase;

class McaFilesystemTest extends TestCase
{
    protected string $workDir;
    protected McaFilesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        mkdir($dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mcatest_'.microtime(true));
        $this->workDir = realpath($dir);

        $this->filesystem = app(McaFilesystem::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->filesystem->deleteDirectory($this->workDir);
    }

    /** @test */
    public function it_moves_files_and_directories()
    {
        $dirPath = $this->createDir('foo');
        $filePath = $this->createFile('foo'.DIRECTORY_SEPARATOR.'file.txt', 'Test');

        $this->filesystem->move($dirPath, $targetDir = $this->workDir.DIRECTORY_SEPARATOR.'bar');

        $this->assertDirectoryDoesNotExist($dirPath);
        $this->assertDirectoryExists($targetDir);
        $this->assertFileExists($targetDir.DIRECTORY_SEPARATOR.'file.txt');

        // Move back recursively
        $this->filesystem->moveRecursive($targetDir, $dirPath);

//        $this->assertDirectoryDoesNotExist($targetDir);
        $this->assertDirectoryExists($dirPath);
        $this->assertFileExists($filePath);
    }

    /** @test */
    public function it_normalizes_file_names()
    {
        // Trims file names
        $this->assertEquals('Tinker.jar', $this->filesystem::makeFileName('Tinkers-Construct.jar', limit: 10));
        $this->assertEquals('Tinkers_C_foobar.jar', $this->filesystem::makeFileName('Tinkers-Construct.jar', 'foobar', limit: 20));

        // Removes special chars
        $this->assertEquals('Tinkers_Construct_1_0_0.jar', $this->filesystem::makeFileName('Tinkers-Construct-1.0.0.jar'));

        // Trims excessive replacement chars
        $this->assertEquals('Tinkers_Construct.jar', $this->filesystem::makeFileName('Tinkers---Construct.jar'));

        // Includes extra string
        $this->assertEquals('TC_foobar.jar', $this->filesystem::makeFileName('TC.jar', 'foobar'));
    }

    /** @test */
    public function it_fails_to_normalize_file_name_when_there_is_no_extension()
    {
        $this->expectException(\RuntimeException::class);
        $this->filesystem::makeFileName('test');
    }

    /** @test */
    public function it_makes_directory_names()
    {
        $this->assertEquals('Tinkers_Construct', $this->filesystem::makeDirName('Tinkers Construct'));
        $this->assertEquals('Tinkers_Construct', $this->filesystem::makeDirName('Tinkers - Construct'));

        $this->assertEquals('Tinkers_Co', $this->filesystem::makeDirName('Tinkers Construct', limit: 10));

        $this->assertEquals('Tinkers-Construct', $this->filesystem::makeDirName('Tinkers-Construct', true));
    }

    /** @test */
    public function it_cleans_up_empty_directories()
    {
        $first = $this->createDir('first');
        $second = $this->createDir('first'.DIRECTORY_SEPARATOR.'second');
        $third = $this->createDir('first'.DIRECTORY_SEPARATOR.'second'.DIRECTORY_SEPARATOR.'third');
        $file = $this->createFile('first'.DIRECTORY_SEPARATOR.'test.txt', 'Test');

        $this->filesystem->cleanupEmptyDirectories($third, $this->workDir);

        $this->assertDirectoryExists($first);
        $this->assertDirectoryDoesNotExist($second);
        $this->assertDirectoryDoesNotExist($third);
        $this->assertFileExists($file);
    }

    /** @test */
    public function it_fails_to_cleanup_empty_directories_when_limit_dir_is_not_base_path_to_dir()
    {
        $a = $this->createDir('a');
        $b = $this->createDir('b');

        $this->expectException(\RuntimeException::class);
        $this->filesystem->cleanupEmptyDirectories($a, $b);
    }

    protected function createFile(string $name, string $content): string
    {
        file_put_contents($path = $this->workDir.DIRECTORY_SEPARATOR.$name, $content);
        return $path;
    }

    protected function createDir(string $name): string
    {
        mkdir($path = $this->workDir.DIRECTORY_SEPARATOR.$name);
        return $path;
    }
}
