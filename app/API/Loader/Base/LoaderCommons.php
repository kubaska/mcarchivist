<?php

namespace App\API\Loader\Base;

use App\Mca\McaFile;
use App\Models\Version;
use Carbon\Carbon;
use GuzzleHttp\Utils as PsrUtils;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoaderCommons
{
    public static function getForgeVersionManifest(Collection $files): array|false
    {
        if ($files->isEmpty()) return false;

        /** @var McaFile $installer */
        $installer = $files->first(fn(McaFile $file) => str_contains($file->getFilename(), 'installer'));
        if (! $installer) return false;

        $zip = new \ZipArchive();
        $zipOpenResult = $zip->open($installer->getRealPath());
        if ($zipOpenResult !== true) {
            Log::stack(['queue', 'stack'])->error(sprintf('Failed to open loader installer file: %s. Code: %s', $installer->getRealPath(), $zipOpenResult));
            return false;
        }

        if ($zip->locateName('install_profile.json') === false) {
            Log::stack(['queue', 'stack'])->warning('Forge installer missing install profile: '.$installer->getFilename());
            return false;
        }

        $profile = $zip->getFromName('install_profile.json');
        $zip->close();

        return PsrUtils::jsonDecode($profile, true);
    }

    public static function getFabricReleaseDates(Collection $versions, \Closure $getVersionDownloadUrl): array
    {
        $chunked = $versions->chunk(10);
        $result = [];

        /** @var Collection $versions */
        foreach ($chunked as $versions) {
            $responses = Http::pool(fn(Pool $pool) => $versions->map(
                fn(Version $v) => $pool->as($v->version)->head($getVersionDownloadUrl($v))
            ));

            foreach ($versions as $version) {
                if ($responses[$version->version]->failed()) {
                    Log::stack(['queue', 'stack'])->error('Failed to fetch Fabric release date info for version '.$version->version);
                    continue;
                }
                $date = Carbon::make($responses[$version->version]->header('Last-Modified'));
                if (! $date) {
                    Log::stack(['queue', 'stack'])->error('Invalid time format: '.$date);
                    continue;
                }

                $result[$version->id] = $date;
            }
        }

        return $result;
    }
}
