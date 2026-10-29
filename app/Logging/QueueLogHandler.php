<?php

namespace App\Logging;

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

    /**
     * @return LogRecord[]
     */
    public function getRecords(): array
    {
        return $this->buffer;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return array_map(function (LogRecord $record) {
            if (is_null($record->formatted)) {
                $record->formatted = sprintf(
                    '[%s] %s: %s',
                    $record->datetime->format('Y-m-d H:i:s'),
                    $record->level->getName(),
                    $record->message
                );
            }

            return $record->formatted;
        }, $this->buffer);
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->enabled && $record->level->value >= $this->level->value;
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) return false;

        if (count($this->buffer) > 1000) {
            array_shift($this->buffer);
        }

        $this->buffer[] = $record;

        // Supporting helper, should always bubble higher
        return false;
    }

    public function reset(): void
    {
        $this->buffer = [];
    }
}
