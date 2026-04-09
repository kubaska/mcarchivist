<?php

namespace App\API\DTO;

use App\API\DTO\Library\BasicComponent;
use App\API\DTO\Library\LibraryRule;
use App\API\DTO\Library\LibraryRuleset;
use App\Mca\MavenArtifact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LibraryDTO extends DTO
{
    protected MavenArtifact $artifact;

    /**
     * @param string $name
     * @param BasicComponent|null $basicComponent
     * @param LibraryRuleset|null $rules
     * @param Collection<LibraryDTO>|null $classifiers
     */
    public function __construct(
        public readonly string $name,
        public readonly ?BasicComponent $basicComponent,
        public readonly ?LibraryRuleset $rules = null,
        public readonly ?Collection $classifiers = null
    )
    {
        $this->artifact = new MavenArtifact($this->name);
    }

    public function getMavenArtifact(): MavenArtifact
    {
        return $this->artifact;
    }

    public function getPath(): string
    {
        return $this->basicComponent?->path ?? $this->artifact->path();
    }

    public function getPathWithoutFile(): string
    {
        return $this->basicComponent?->getPathWithoutFile() ?? $this->artifact->pathWithoutFile();
    }

    public function hasHash(): bool
    {
        return $this->basicComponent?->hasHash() ?? false;
    }

    public function getHash(): ?string
    {
        return $this->basicComponent?->sha1;
    }

    public function getHashAlgo(): ?string
    {
        return $this->basicComponent?->hasHash() ? 'sha1' : null;
    }

    public function getHashAssoc(): ?array
    {
        return $this->basicComponent?->getHashAssoc();
    }

    public function getSize(): ?int
    {
        return $this->basicComponent?->size;
    }

    public function getHost(): ?string
    {
        return $this->basicComponent?->host;
    }

    public function getUrl(?string $host = null): string
    {
        return $this->basicComponent?->getUrl($host)
            ?? $this->artifact->guessUrl($host ?? $this->basicComponent?->host ?? 'https://libraries.minecraft.net/');
    }

    public function getFileName(): string
    {
        return $this->basicComponent?->getFileName() ?? $this->artifact->filename();
    }

    public static function fromMojang(array $library): LibraryDTO
    {
        $rules = new LibraryRuleset(collect(array_map(fn(array $rule) => new LibraryRule(
            data_get($rule, 'action'),
            data_get($rule, 'os.name'),
            data_get($rule, 'os.version'),
        ), data_get($library, 'rules', []))));

        return new self(
            $library['name'],
            data_get($library, 'downloads.artifact')
                ? new BasicComponent(
                    data_get($library, 'downloads.artifact.path'),
                    null,
                    data_get($library, 'downloads.artifact.url'),
                    data_get($library, 'downloads.artifact.sha1'),
                    data_get($library, 'downloads.artifact.size')
                )
                : null,
            $rules,
            self::processNatives(
                $library['name'],
                data_get($library, 'natives', []),
                data_get($library, 'downloads.classifiers', []),
                $rules
            )
        );
    }

    public static function fromForgeV1(array $library): LibraryDTO
    {
        $hasNatives = isset($library['natives']);
        $rules = new LibraryRuleset(collect(array_map(fn(array $rule) => new LibraryRule(
            data_get($rule, 'action'),
            data_get($rule, 'os.name'),
            data_get($rule, 'os.version'),
        ), data_get($library, 'rules', []))));

        if ($hasNatives) {
            Log::warning(sprintf('Forge library %s will be discarded because it has natives', $library['name']));
        }

        return new self(
            $library['name'],
            // Include the library itself only if it doesn't have natives.
            $hasNatives ? null : new BasicComponent(
                null,
                data_get($library, 'url'),
                null,
                isset($library['checksums']) ? $library['checksums'][0] : null,
                null
            ),
            $rules,
            $hasNatives ? self::processNatives($library['name'], $library['natives'], [], $rules) : null
        );
    }

    public static function fromFabric(array $library): LibraryDTO
    {
        return new self(
            $library['name'],
            new BasicComponent(
                null,
                data_get($library, 'url'),
                null,
                data_get($library, 'sha1'),
                data_get($library, 'size')
            )
        );
    }

    /**
     * @param string $libraryName Library name
     * @param array $natives Natives in Mojang format, e.g. [ ['windows' => 'natives-windows', 'linux' => 'natives-linux' ] ]
     * @param array $classifiers Classifiers in Mojang format, if exist
     * @param LibraryRuleset $rules
     * @return Collection
     */
    private static function processNatives(string $libraryName, array $natives, array $classifiers, LibraryRuleset $rules): Collection
    {
        $artifact = new MavenArtifact($libraryName);

        return collect(array_reduce(array_keys($natives), function (array $carry, string $os) use ($artifact, $libraryName, $natives, $classifiers, $rules) {
            // Filter native libraries down to what is actually used by the game.
            // MC 1.7 specifies a LWJGL native for all OSes (win, linux, osx), but osx is filtered out by rules.
            // So, by archiving it we'd waste storage space.
            // More serious case: Forge 1.5.2-7.8.1.738 specifies natives for all OSes, but only OSX is allowed by rules.
            // Turns out, only OSX native exists on the server, windows and linux doesn't; and trying to archive them fail with 404.
            if ($rules->allowsOS($os)) {
                $allNatives = self::processClassifierTemplates($natives[$os]);

                foreach ($allNatives as $native) {
                    $classifier = data_get($classifiers, $native, []);

                    $carry[] = new self($artifact->nameWithClassifier($native), new BasicComponent(
                        data_get($classifier, 'path'),
                        null,
                        data_get($classifier, 'url'),
                        data_get($classifier, 'sha1'),
                        data_get($classifier, 'size')
                    ));
                }
            } else {
                Log::warning(sprintf('Skipping unused "%s" native in library "%s"', $natives[$os], $libraryName));
            }

            return $carry;
        }, []));
    }

    /**
     * Process classifier name templates.
     *
     * @param string $name
     * @return string[]
     */
    private static function processClassifierTemplates(string $name): array
    {
        $result = [];

        // Replace 'arch' key for windows builds
        if (str_contains($name, '${arch}')) {
            $result = array_map(fn(int $arch) => str_replace('${arch}', $arch, $name), [32, 64]);
        }

        // When there were no templates, return the original name
        if (count($result) === 0) {
            return [$name];
        }

        return $result;
    }
}
