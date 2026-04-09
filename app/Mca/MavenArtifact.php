<?php

namespace App\Mca;

use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

class MavenArtifact
{
    protected string $classpath;
    protected string $library;
    protected string $version;
    protected ?string $classifier;
    protected ?string $extension;

    public function __construct(string $name)
    {
        // cpw.mods:modlauncher:11.0.3@jar
        // de.oceanlabs.mcp:mcp_config:1.20.1-20230612.114412@zip
        // org.lwjgl.lwjgl:lwjgl-platform:2.9.0:natives-windows
        // io.netty:netty-transport-native-epoll:4.1.97.Final:linux-x86_64
        // net.fabricmc:sponge-mixin:0.13.3+mixin.0.8.5
        // net.neoforged:neoform:1.21.10-20251010.172816:mappings@tsrg.lzma
        if (! preg_match('/^([\w\d.+-]+):([\w\d.+-]+):([\w\d.+-]+)(?::([\w\d.+-]+))?(?:@([\w.-]+))?$/', $name, $matches, PREG_UNMATCHED_AS_NULL)) {
            throw new \RuntimeException('Invalid MavenArtifact name: '.$name);
        }

        $this->classpath = $matches[1];
        $this->library = $matches[2];
        $this->version = $matches[3];
        $this->classifier = $matches[4];
        $this->extension = $matches[5];
    }

    public function name(): string
    {
        return implode(':', [$this->classpath, $this->library, $this->version]);
    }

    public function nameWithClassifier(?string $classifier = null): string
    {
        return implode(':', array_filter([$this->classpath, $this->library, $this->version, $classifier ?? $this->classifier]));
    }

    public function pathWithoutFile(): string
    {
        return Path::join(str_replace('.', DIRECTORY_SEPARATOR, $this->classpath), $this->library, $this->version);
    }

    public function filename(): string
    {
        return sprintf('%s-%s%s.%s', $this->library, $this->version, $this->classifier ? '-'.$this->classifier : '', $this->extension ?? 'jar');
    }

    public function path(): string
    {
        return Path::join($this->pathWithoutFile(), $this->filename());
    }

    public function guessUrl(string $host): string
    {
        // org.apache.commons:commons-lang3:3.5  -->
        // https://libraries.minecraft.net/org/apache/commons/commons-lang3/3.5/commons-lang3-3.5.jar
        $host = Str::endsWith($host, '/') ? $host : $host.'/';
        $dirs = sprintf('%s/%s/%s/', str_replace('.', '/', $this->classpath), $this->library, $this->version);

        return $host.$dirs.$this->filename();
    }
}
