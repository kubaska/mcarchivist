<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\Handler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\ResettableInterface;

class QueueLogHandler extends Handler implements ResettableInterface
{
    protected bool $enabled;
    protected array $buffer = [];

    public function __construct(protected Level $level = Level::Debug)
    {
        $this->enabled = app()->runningInConsole();
    }

    public function getMessages(): array
    {
        return $this->buffer;
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->enabled && $record->level->value >= $this->level->value;
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) return false;

        $this->buffer[] = sprintf(
            '[%s] %s: %s',
            $record->datetime->format('Y-m-d H:i:s'),
            $record->level->getName(),
            $record->message
        );

        // Supporting helper, should always bubble higher
        return false;
    }

    public function reset(): void
    {
        $this->buffer = [];
    }
}
