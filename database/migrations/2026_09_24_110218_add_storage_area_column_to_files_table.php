<?php

use App\Enums\StorageArea;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\Project;
use App\Models\Version;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->unsignedTinyInteger('storage_area')->default(0)->after('side');
        });

        File::query()->chunk(1000, function (Collection $files) {
            $versions = Version::query()->whereIn('id', $files->pluck('version_id')->unique())->get()->keyBy('id');

            foreach ($files as $file) {
                $area = match ($type = $versions->get($file->version_id)->versionable_type) {
                    GameVersion::class => StorageArea::GAME,
                    Loader::class => StorageArea::LOADERS,
                    Project::class => StorageArea::PROJECTS,
                    default => throw new \RuntimeException('Invalid storage area: '.$type)
                };

                $file->storage_area = $area;
                $file->save();
            }
        });
    }

    public function down()
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('storage_area');
        });
    }
};
