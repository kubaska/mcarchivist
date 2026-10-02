<?php

namespace App\Services;

use App\Mca\McaFile;
use App\Support\McaFilesystem;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Path;

class McaDownloader
{
    protected array $clientOptions = [
        'http_errors' => true,
        'connect_timeout' => 15,
        'read_timeout' => 15,
        'timeout' => 3000
    ];
    protected bool $shouldVerify = true;

    public function __construct(protected HttpClient $client, protected McaFilesystem $filesystem)
    {
    }

    public function setSkipVerify(): static
    {
        $this->shouldVerify = false;
        return $this;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function download(string $url, string $path, string $filename, ?string $checksumAlgo, ?string $expectedChecksum, ?int $expectedSize, array $options = []): McaFile
    {
        $target = Path::join($path, $filename);

        if (! $this->filesystem->isDirectory($path)) {
            throw new \RuntimeException(sprintf('Target directory [%s] is missing', $path));
        }
        if ((! $this->optEq($options, 'force_overwrite', true)) && $this->filesystem->exists($target)) {
            throw new \RuntimeException(sprintf('File [%s] already exists at [%s]', $filename, $path));
        }

        $tempDir = $this->filesystem->getTemporaryDir();

        // Check if file already exists in temporary directory, and if it does and passes checksum verification return it.
        if ($this->filesystem->exists($tempFilePath = Path::join($tempDir, $filename))) {
            if ($checksumAlgo && $expectedChecksum && $this->verifyChecksum($tempFilePath, $checksumAlgo, $expectedChecksum)) {
                $this->filesystem->move($tempFilePath, $target);
                return new McaFile($target);
            }
        }

        $this->downloadFile($url, $tempFilePath, $checksumAlgo, $expectedChecksum, $expectedSize);

        // Move file to permanent storage
        $this->filesystem->move($tempFilePath, $target);

        return new McaFile($target);
    }

    public function downloadToTemporaryDirectory(
        string $url, string $filename, ?string $checksumAlgo, ?string $expectedChecksum, ?int $expectedSize, array $options = []
    ): McaFile
    {
        $tempDir = $this->filesystem->getTemporaryDir();
        $tempFilePath = Path::join($tempDir, $filename);

        // Check if file already exists in temporary directory, and if it does and passes checksum verification return it.
        if ($this->filesystem->exists($tempFilePath)) {
            if ($checksumAlgo && $expectedChecksum && $this->verifyChecksum($tempFilePath, $checksumAlgo, $expectedChecksum)) {
                return new McaFile($tempFilePath);
            } else {
                $tempFilePath = Path::join($tempDir, $this->filesystem->makeUniqueFileName($tempDir, $filename));
            }
        }

        $this->downloadFile($url, $tempFilePath, $checksumAlgo, $expectedChecksum, $expectedSize);

        return new McaFile($tempFilePath);
    }

    public function downloadFromMirrorList(array $urls, string $path, string $filename, ?string $checksumAlgo, ?string $expectedChecksum, ?int $expectedSize, array $options = [])
    {
        foreach ($urls as $url) {
            try {
                return $this->download($url, $path, $filename, $checksumAlgo, $expectedChecksum, $expectedSize, $options);
            } catch (GuzzleException $e) {
                // Rethrow if we exhausted url list
                if ($urls[count($urls) - 1] === $url) throw $e;
            }
        }
    }

    private function downloadFile(string $url, string $path, ?string $checksumAlgo, ?string $expectedChecksum, ?int $expectedSize)
    {
        Log::debug(sprintf('Downloading %s to %s', $url, $path));

        $this->client->get($url, ['sink' => $path, ...$this->clientOptions]);

        if ($this->shouldVerify && is_int($expectedSize)) {
            if (! $this->verifySize($path, $expectedSize)) {
                throw new \RuntimeException(sprintf('File size mismatch while downloading %s', $url));
            }
        }

        if ($this->shouldVerify && $checksumAlgo && $expectedChecksum) {
            if (! $this->verifyChecksum($path, $checksumAlgo, $expectedChecksum)) {
                throw new \RuntimeException(sprintf('File checksum mismatch while downloading %s', $url));
            }
        }
    }

    private function verifyChecksum($file, $algo, $checksum): bool
    {
        return hash_file($algo, $file) === $checksum;
    }

    private function verifySize($file, $size): bool
    {
        return filesize($file) === $size;
    }

    private function hasOpt(array $options, string $option): bool
    {
        return isset($options[$option]) && $options[$option] === true;
    }

    private function optEq(array $options, string $option, mixed $value): bool
    {
        return isset($options[$option]) && $options[$option] === $value;
    }
}
