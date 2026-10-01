<?php

namespace Tests\Unit;

use App\API\DTO\FileDTO;
use App\Enums\FileSide;
use App\Mca\McaFile;
use App\Models\Version;
use App\Services\BaseArchiver;
use App\Services\McaDownloader;
use App\Support\McaFilesystem;
use Mockery\MockInterface;
use Tests\Laravel\RefreshDatabase;
use Tests\TestCase;

class ArchiverServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FILE_CONTENT = 'McaArchiverServiceTest';
    private const FILE_SHA1 = '784b6372254d0e6b046829a566d93d16802ea2b6';
    private const ALT_FILE_CONTENT = 'AltFileTest';
    private const ALT_FILE_SHA1 = '98deb93ac657f6a1af8e359faaf4e79ddcd98ce6';

    private function setupMocksWithDowloadedFile(string $fileName, string $content, $willCallDownloadTimes = 1)
    {
        $filesystem = app(McaFilesystem::class);

        $createFile = function () use ($filesystem, $fileName, $content) {
            file_put_contents(
                $filePath = $filesystem->getTemporaryDir().DIRECTORY_SEPARATOR.$fileName,
                $content
            );

            return new McaFile($filePath);
        };

        $this->instance(McaDownloader::class, \Mockery::mock(McaDownloader::class, function (MockInterface $mock) use ($willCallDownloadTimes, $createFile) {
            $mock->shouldReceive('downloadToTemporaryDirectory')->times($willCallDownloadTimes)->andReturnUsing($createFile);
        }));
        $archiver = app(ArchiverService::class);

        return [$archiver, $filesystem];
    }

    /** @test */
    public function it_archives_file()
    {
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file = $archiver->archiveFile($version = Version::factory()->create(), $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_recovers_the_model_when_it_is_missing_and_same_file_already_exists()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file = $archiver->archiveFile($version, $dto, 'test');

        $file->delete();

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_archives_file_when_model_already_exists()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT, 2);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file = $archiver->archiveFile($version, $dto, 'test');

        $filesystem->delete($file->getAbsoluteFilePath());
        $version->refresh();

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_updates_model_attributes_when_model_and_file_already_exist()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $archiver->archiveFile($version, $dto, 'test');

        $dto = FileDTO::make('client', 'client_updated.jar', ['sha1' => self::FILE_SHA1],false, side: FileSide::UNIVERSAL, url: '');
        $version->refresh();

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertEquals(
            $file->only(['side', 'original_file_name', 'primary']),
            ['side' => $dto->side, 'original_file_name' => $dto->name, 'primary' => $dto->primary]
        );
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_updates_the_model_when_it_is_missing_there_are_no_hashes_and_same_file_already_exists()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT, 2);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', '', 0);
        $archiver->archiveFile($version, $dto, 'test');

        $version->refresh();

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_recovers_the_model_when_it_is_missing_there_are_no_hashes_and_same_file_already_exists()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT, 2);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', '', 0);
        $file = $archiver->archiveFile($version, $dto, 'test');

        $file->delete();

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_archives_file_with_unique_name_when_its_attached_to_another_model_already()
    {
        $version1 = Version::factory()->create();
        [$archiver] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file1 = $archiver->archiveFile($version1, $dto, 'test');

        $version2 = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::ALT_FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::ALT_FILE_SHA1, 0);

        $file2 = $archiver->archiveFile($version2, $dto, 'test');

        $this->assertFileExists($file1->getAbsoluteFilePath());
        $this->assertFileExists($file2->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryFileCount($file1->getAbsoluteDirectoryPath(), 2);
        $this->assertDatabaseCount('files', 2);
        $this->assertSame(1, $version1->files()->count());
        $this->assertSame(1, $version2->files()->count());
        $this->assertNotSame($version1->files()->first()->file_name, $version2->files()->first()->file_name);
    }

    /** @test */
    public function it_overwrites_existing_file_if_it_is_not_attached_to_any_model()
    {
        $version = Version::factory()->create();
        [$archiver] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file = $archiver->archiveFile($version, $dto, 'test');

        $file->delete();
        $version->delete();

        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::ALT_FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::ALT_FILE_SHA1, 0);

        $file = $archiver->archiveFile($version, $dto, 'test');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryFileCount($file->getAbsoluteDirectoryPath(), 1);
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }

    /** @test */
    public function it_uses_existing_duplicate_file_and_does_not_save_a_new_one()
    {
        $version1 = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT, 2);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $file1 = $archiver->archiveFile($version1, $dto, 'test');

        $version2 = Version::factory()->create();

        $file2 = $archiver->archiveFile($version2, $dto, 'another');

        $this->assertFileExists($file1->getAbsoluteFilePath());
        $this->assertFileExists($file2->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryFileCount($file1->getAbsoluteDirectoryPath(), 1);
        $this->assertDirectoryFileCount($file2->getAbsoluteDirectoryPath(), 1);
        $this->assertDatabaseCount('files', 2);
        $this->assertSame(1, $version1->files()->count());
        $this->assertSame(1, $version2->files()->count());
    }

    /** @test */
    public function it_uses_file_location_from_model_instead_of_making_new_one()
    {
        $version = Version::factory()->create();
        [$archiver, $filesystem] = $this->setupMocksWithDowloadedFile('client.jar', self::FILE_CONTENT);
        $dto = FileDTO::fromMojang('client', 'client.jar', '', self::FILE_SHA1, 0);

        $archiver->archiveFile($version, $dto, 'test');

        $dto = FileDTO::fromMojang('client', 'client_name_changed.jar', '', self::FILE_SHA1, 0);
        $version->refresh();

        $file = $archiver->archiveFile($version, $dto, 'other_directory');

        $this->assertFileExists($file->getAbsoluteFilePath());
        $this->assertEmptyDirectory($filesystem->getTemporaryDir());
        $this->assertDirectoryHasOnlyOneFile($file->getAbsoluteDirectoryPath());
        $this->assertDatabaseCount('files', 1);
        $this->assertSame(1, $version->files()->count());
    }
}

class ArchiverService extends BaseArchiver {

}
