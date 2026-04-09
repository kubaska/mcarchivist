<?php

namespace App\Console;

use App\Console\Commands\AutomaticArchiveCommand;
use App\Console\Commands\SetupInitialDataCommand;
use App\Console\Commands\MakeRulesetsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        AutomaticArchiveCommand::class,
        MakeRulesetsCommand::class,
        SetupInitialDataCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(AutomaticArchiveCommand::class)->hourly();
        $schedule->command(SetupInitialDataCommand::class)->daily();
    }
}
