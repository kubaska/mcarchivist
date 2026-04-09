<?php

namespace Tests\Unit;

use App\API\DTO\LibraryDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use GuzzleHttp\Utils as PsrUtils;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class ManifestParseTest extends TestCase
{
    private function getProfiles(): array
    {
        $manifests = Finder::create()->in(Path::join(base_path('tests'), 'data', 'manifest'))->files();
        $profiles = [];

        foreach ($manifests as $manifest) {
            $filename = Str::afterLast($manifest, DIRECTORY_SEPARATOR);
            $loader = Str::before($filename, '-');
            if ($loader === 'forge') {
                // "forge-1.12.2-9.11" -> "12"
                $version = Str::before(Str::after($filename, 'forge-1.'), '.');
                if ($version > 12) $profiles['forge-v2'][] = $manifest;
                else $profiles['forge-v1'][] = $manifest;
                continue;
            }
            $profiles[$loader][] = $manifest;
        }

        return $profiles;
    }

    /** @test */
    public function it_parses_manifests()
    {
        $profiles = $this->getProfiles();

        foreach ($profiles as $version => $manifests) {
            foreach ($manifests as $manifest) {
                $json = PsrUtils::jsonDecode(file_get_contents($manifest), true);

                match ($version) {
                    'forge-v1' => $this->parse_forge_v1($json, LoaderInstallProfileDTO::fromForgeV1($json)),
                    'forge-v2' => $this->parse_forge_v2($json, LoaderInstallProfileDTO::fromForgeV2($json)),
                    'neoforge' => $this->parse_forge_v2($json, LoaderInstallProfileDTO::fromNeoforge($json)),
                    default => $this->fail(sprintf('Undefined loader: %s [%s]', $version, $manifest))
                };
            }
        }
    }

    private function parse_forge_v1(array $json, LoaderInstallProfileDTO $installProfile)
    {
        $this->assertEquals($json['install']['version'], $installProfile->version);
        $this->assertEquals($json['install']['minecraft'], $installProfile->gameVersion);

        foreach ($json['versionInfo']['libraries'] as $library) {
            $ipLib = $installProfile->libraries->first(fn(LibraryDTO $lib) => $lib->name === $library['name']);
            $this->assertNotNull($ipLib, 'Failed asserting that parsed libraries contain library '.$library['name']);
        }
    }

    private function parse_forge_v2(array $json, LoaderInstallProfileDTO $installProfile)
    {
        $this->assertEquals($json['version'], $installProfile->version);
        $this->assertEquals($json['minecraft'], $installProfile->gameVersion);

        foreach ($json['libraries'] as $library) {
            $ipLib = $installProfile->libraries->first(fn(LibraryDTO $lib) => $lib->name === $library['name']);

            $this->assertNotNull($ipLib);
        }
    }
}
