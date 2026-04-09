<?php

namespace Tests\Unit;

use App\API\DTO\LibraryDTO;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryDTOTest extends TestCase
{
    /** @test */
    public function it_parses_forge_v1()
    {
        $library = [
            "name" => "org.scala-lang:scala-library:2.10.0-custom",
            "url" => "https://maven.minecraftforge.net/",
            "checksums" => [
                "458d046151ad179c85429ed7420ffb1eaf6ddf85",
                "3bebc9dda993024c88344a044f23059e27f73ed7"
            ],
            "rules" => [
                [
                    "action" => "allow"
                ],
                [
                    "action" => "disallow",
                    "os" => [
                        "name" => "osx",
                        "version" => "^10\\.5\\.\\d$"
                    ]
                ]
            ]
        ];

        $dto = LibraryDTO::fromForgeV1($library);
        $this->assertEquals($library['name'], $dto->name);
        $this->assertEquals($library['url'], $dto->getHost());
        $this->assertEquals(
            'https://maven.minecraftforge.net/org/scala-lang/scala-library/2.10.0-custom/scala-library-2.10.0-custom.jar',
            $dto->getUrl()
        );
        $this->assertEquals(
            'https://maven.examplehost.net/org/scala-lang/scala-library/2.10.0-custom/scala-library-2.10.0-custom.jar',
            $dto->getUrl('https://maven.examplehost.net/')
        );
        $this->assertEquals($library['checksums'][0], $dto->getHash());
        $this->assertEquals('sha1', $dto->getHashAlgo());
        $this->assertNull($dto->classifiers);
    }

    /** @test */
    public function it_parses_forge_v1_name_only()
    {
        $library = [
            "name" => "org.scala-lang:scala-library:2.10.0-custom",
        ];

        $dto = LibraryDTO::fromForgeV1($library);

        $this->assertEquals($library['name'], $dto->name);
        $this->assertNull($dto->getHost());
        $this->assertEquals(
            'https://libraries.minecraft.net/org/scala-lang/scala-library/2.10.0-custom/scala-library-2.10.0-custom.jar',
            $dto->getUrl()
        );
        $this->assertNull($dto->getHash());
    }

    /** @test */
    public function it_discards_forge_v1_libraries_with_natives()
    {
        $library = [
            "name" => "org.scala-lang:scala-library:2.10.0-custom",
            "natives" => [
                "linux" => "natives-linux",
                "windows" => "natives-windows",
                "osx" => "natives-osx"
            ]
        ];

        $dto = LibraryDTO::fromForgeV1($library);
        $this->assertNull($dto->basicComponent);
        $this->assertCount(3, $dto->classifiers);
    }

    /** @test */
    public function it_parses_mojang_minimal()
    {
        $library = [
            "name" => "com.github.jponge:lzma-java:1.3",
            "downloads" => [
                "artifact" => [
                    "path" => "com/github/jponge/lzma-java/1.3/lzma-java-1.3.jar",
                    "url" => "https://maven.neoforged.net/releases/com/github/jponge/lzma-java/1.3/lzma-java-1.3.jar",
                    "sha1" => "a25db9d4d385ccda4825ae1b47a7a61d86e595af",
                    "size" => 51041
                ]
            ]
        ];

        $dto = LibraryDTO::fromMojang($library);

        $this->assertEquals($library['name'], $dto->name);
        $this->assertEquals(
            Str::beforeLast($library['downloads']['artifact']['path'], '/'),
            $dto->getPathWithoutFile()
        );
        $this->assertEquals($library['downloads']['artifact']['path'], $dto->getPath());
        $this->assertEquals($library['downloads']['artifact']['url'], $dto->getUrl());
        $this->assertEquals(
            'https://maven.examplehost.net/releases/com/github/jponge/lzma-java/1.3/lzma-java-1.3.jar',
            $dto->getUrl('https://maven.examplehost.net/')
        );
        $this->assertEquals($library['downloads']['artifact']['sha1'], $dto->getHash());
        $this->assertEquals('sha1', $dto->getHashAlgo());
        $this->assertEquals($library['downloads']['artifact']['size'], $dto->getSize());
        $this->assertEquals(
            Str::afterLast($library['downloads']['artifact']['path'], '/'),
            $dto->getFileName()
        );
    }

    /** @test */
    public function it_parses_natives_with_name_templates()
    {
        $library = [
            "downloads" => [
                "classifiers" => [
                    "natives-windows-32" => [
                        "path" => "tv/twitch/twitch-external-platform/4.5/twitch-external-platform-4.5-natives-windows-32.jar",
                        "sha1" => "18215140f010c05b9f86ef6f0f8871954d2ccebf",
                        "size" => 5654047,
                        "url" => "https://libraries.minecraft.net/tv/twitch/twitch-external-platform/4.5/twitch-external-platform-4.5-natives-windows-32.jar"
                    ],
                    "natives-windows-64" => [
                        "path" => "tv/twitch/twitch-external-platform/4.5/twitch-external-platform-4.5-natives-windows-64.jar",
                        "sha1" => "c3cde57891b935d41b6680a9c5e1502eeab76d86",
                        "size" => 7457619,
                        "url" => "https://libraries.minecraft.net/tv/twitch/twitch-external-platform/4.5/twitch-external-platform-4.5-natives-windows-64.jar"
                    ]
                ]
            ],
            "name" => "tv.twitch:twitch-external-platform:4.5",
            "natives" => [
                "windows" => 'natives-windows-${arch}'
            ],
            "rules" => [
                [
                    "action" => "allow",
                    "os" => [
                        "name" => "windows"
                    ]
                ]
            ]
        ];

        $dto = LibraryDTO::fromMojang($library);

        $this->assertCount(2, $dto->classifiers);
        $this->assertSame('tv.twitch:twitch-external-platform:4.5:natives-windows-32', $dto->classifiers[0]->name);
        $this->assertSame('tv.twitch:twitch-external-platform:4.5:natives-windows-64', $dto->classifiers[1]->name);
    }
}
