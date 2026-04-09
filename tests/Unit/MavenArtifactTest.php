<?php

namespace Tests\Unit;

use App\Mca\MavenArtifact;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class MavenArtifactTest extends TestCase
{
    /** @test */
    public function it_correctly_deconstructs_name()
    {
        $libraries = [
            'cpw.mods:modlauncher:11.0.3@jar',
            'de.oceanlabs.mcp:mcp_config:1.20.1-20230612.114412@zip',
            'org.lwjgl.lwjgl:lwjgl-platform:2.9.0:natives-windows',
            'io.netty:netty-transport-native-epoll:4.1.97.Final:linux-x86_64',
            'net.fabricmc:sponge-mixin:0.13.3+mixin.0.8.5',
            'net.neoforged:neoform:1.21.10-20251010.172816:mappings@tsrg.lzma'
        ];

        foreach ($libraries as $library) {
            $mvn = new MavenArtifact($library);

            // Remove classifiers and extensions (e.g. "linux-x86_64" or "@zip")
            $basicName = implode(':', array_slice(explode(':', Str::beforeLast($library, '@')), 0, 3));
            $this->assertEquals($basicName, $mvn->name());

            $this->assertEquals(Str::beforeLast($library, '@'), $mvn->nameWithClassifier());
            $this->assertEquals($basicName.':classifier', $mvn->nameWithClassifier('classifier'));

            $filenameNoExt = str_replace(':', '-', Str::after($library, ':'));
            $filename = str_contains($filenameNoExt, '@')
                ? str_replace('@', '.', $filenameNoExt)
                : $filenameNoExt.'.jar';
            $this->assertEquals($filename, $mvn->filename());

            $classpath = Str::before($basicName, ':');
            $basicNameNoClasspath = str_replace($classpath, '', $basicName);
            $path = str_replace([':', '.'], '/', $classpath).str_replace(':', '/', $basicNameNoClasspath);
            $this->assertEquals($path.'/'.$filename, $mvn->path());
            $this->assertEquals($path, $mvn->pathWithoutFile());

            $this->assertEquals(
                'https://libraries.minecraft.net/'.$path.'/'.$filename,
                $mvn->guessUrl('https://libraries.minecraft.net/')
            );
            // Test without trailing slash too
            $this->assertEquals(
                'https://libraries.minecraft.net/'.$path.'/'.$filename,
                $mvn->guessUrl('https://libraries.minecraft.net')
            );
        }
    }
}
