<?php

namespace App\Logging;

use Monolog\Handler\Handler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConsoleOutputHandler extends Handler
{
    protected ConsoleOutput $output;
    protected bool $enabled;

    public function __construct(protected Level $level = Level::Debug)
    {
        $this->output = new ConsoleOutput();
        $this->enabled = app()->runningInConsole();
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->enabled && $record->level->value >= $this->level->value;
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) return false;

        $this->output->writeln(sprintf(
            '%s | %s',
            str_pad(strtoupper($record->level->getName()), 7),
            $record->message
        ));

        // Supporting helper, should always bubble higher
        return false;
    }
}
