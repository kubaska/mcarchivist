<?php

namespace Tests\Constraints;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Constraint\Constraint;

class JsonCollectionOrderedByDate extends Constraint
{
    public function __construct(protected string $key, protected string $direction = 'asc', protected ?string $path = null)
    {
        $this->direction = $direction === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @param TestResponse $other
     * @return bool
     */
    protected function matches(mixed $other): bool
    {
        $json = $other->decodeResponseJson()->json($this->path);

        if (is_null($json)) {
            $this->fail($other->json(), sprintf('Invalid JSON response: \'%s\' key is null', $this->path));
        }

        $directionFn = $this->direction === 'asc' ? 'greaterThanOrEqualTo' : 'lessThanOrEqualTo';
        $previous = null;
        foreach (Arr::flatten(array_map(fn(array $items) => Arr::only($items, $this->key), $json)) as $item) {
            if (is_null($previous)) {
                $previous = $item;
                continue;
            }

            if (! Carbon::make($item)->$directionFn($previous)) {
                $directionFailText = $this->direction === 'asc' ? 'after or equal' : 'before or equal';
                $this->fail($other->json(), sprintf('Failed asserting that %s is %s to %s.', $item, $directionFailText, $previous));
                return false;
            }

            $previous = $item;
        }

        return true;
    }

    public function toString(): string
    {
        return sprintf('is ordered by date (%s)', $this->direction);
    }
}
