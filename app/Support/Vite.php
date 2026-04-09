<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Vite
{
    public function getHotFilePath(): string
    {
        return base_path('public/hot');
    }

    public function isRunningHot(): bool
    {
        return is_file($this->getHotFilePath());
    }

    public function getHotServerUrl(): string
    {
        return file_get_contents($this->getHotFilePath());
    }

    public function getManifestPath(): string
    {
        return base_path('public/build/manifest.json');
    }

    public function getManifest()
    {
        if (! is_file($this->getManifestPath())) {
            throw new \RuntimeException('Vite manifest not found');
        }

        return json_decode(file_get_contents($this->getManifestPath()), true, flags: JSON_THROW_ON_ERROR);
    }

    public function compileCssEntry(string $url): string
    {
        return "<link rel=\"stylesheet\" href=\"$url\" />";
    }

    public function compileJsEntry(string $url): string
    {
        return "<script type=\"module\" src=\"$url\"></script>";
    }

    public function compileAsset(string $url, string $entrypoint): string
    {
        $assetPath = Str::finish($url, '/').$entrypoint;

        if (Str::endsWith($entrypoint, ['.css', '.sass', '.scss'])) {
            return $this->compileCssEntry($assetPath);
        }

        return $this->compileJsEntry($assetPath);
    }

    public function getChunk($manifest, $entrypoint)
    {
        if (! isset($manifest[$entrypoint])) {
            throw new \RuntimeException("Undefined Vite chunk: $entrypoint");
        }

        return $manifest[$entrypoint];
    }

    public function __invoke(array|string $entrypoints): HtmlString
    {
        $entrypoints = Arr::wrap($entrypoints);

        if ($this->isRunningHot()) {
            array_unshift($entrypoints, '@vite/client');
            return new HtmlString(
                Arr::join(
                    array_map(fn($entrypoint) => $this->compileAsset($this->getHotServerUrl(), $entrypoint), $entrypoints),
                    ''
                )
            );
        }

        $manifest = $this->getManifest();
        $compiled = [];

        foreach ($entrypoints as $entrypoint) {
            $chunk = $this->getChunk($manifest, $entrypoint);
            $compiled[] = $this->compileAsset('/build/', $chunk['file']);

            foreach ($chunk['css'] ?? [] as $cssEntrypoint) {
                $compiled[] = $this->compileAsset('/build/', $cssEntrypoint);
            }
        }

        return new HtmlString(Arr::join($compiled, PHP_EOL));
    }
}
