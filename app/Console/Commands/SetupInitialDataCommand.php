<?php

namespace App\Console\Commands;

use App\Jobs\UpdateGameVersionsIndexJob;
use App\Services\CategoryService;
use App\Services\McaLoaderArchiver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class SetupInitialDataCommand extends Command implements Isolatable
{
    protected $signature = 'mca:setup-initial-data';

    protected $description = 'Set up initial application data';

    public function handle(
        McaLoaderArchiver $loaderArchiver,
        CategoryService $categoryService
    )
    {
        $this->info('Importing game versions...');
        try {
            dispatch_now(new UpdateGameVersionsIndexJob());
        } catch (\Exception $e) {
            $this->error('Failed to import game versions: '.$e->getMessage());
        }

        $this->info('Importing loaders...');
        try {
            $loaderArchiver->importRemoteLoaders();
        } catch (\Exception $e) {
            $this->error('Failed to import loaders: '.$e->getMessage());
        }

        $this->info('Importing project categories...');
        try {
            $categoryService->importRemoteCategories();
        } catch (\Exception $e) {
            $this->error('Failed to import project categories: '.$e->getMessage());
        }

        $this->info('Finished importing data.');
    }
}
