<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    public function test_contains_any()
    {
        $c = new Collection(['foo', 'bar']);

        $this->assertTrue($c->containsAny([]));
        $this->assertTrue($c->containsAny(['foo']));
        $this->assertTrue($c->containsAny(['foo', 'foo']));
        $this->assertTrue($c->containsAny(['foo', 'bar']));
        $this->assertTrue($c->containsAny(['foo', 'bar', 'baz']));
        $this->assertTrue($c->containsAny(new Collection(['foo', 'bar', 'baz'])));
        $this->assertFalse($c->containsAny(['something']));
    }

    public function test_contains_all()
    {
        $c = new Collection(['foo', 'bar']);

        $this->assertTrue($c->containsAll([]));
        $this->assertTrue($c->containsAll(['foo']));
        $this->assertTrue($c->containsAll(['bar']));
        $this->assertTrue($c->containsAll(['foo', 'foo']));
        $this->assertTrue($c->containsAll(['foo', 'bar']));
        $this->assertFalse($c->containsAll(['something']));
        $this->assertFalse($c->containsAll(['foo', 'baz']));
        $this->assertFalse($c->containsAll(['foo', 'bar', 'baz']));
        $this->assertFalse($c->containsAll(new Collection(['foo', 'bar', 'baz'])));
    }

    public function test_sort_grouping()
    {
        $data = [
            ['type' => 2, 'published_at' => \Carbon\Carbon::make('2025-07-16 23:54:53')],
            ['type' => 1, 'published_at' => \Carbon\Carbon::make('2025-06-10 05:50:38')],
            ['type' => 0, 'published_at' => \Carbon\Carbon::make('2025-06-01 00:25:43')],
            ['type' => 1, 'published_at' => \Carbon\Carbon::make('2025-03-05 06:55:50')],
            ['type' => 2, 'published_at' => \Carbon\Carbon::make('2025-01-21 20:08:40')],
        ];
        $c = (new Collection($data))->sortGrouping(['type', 'asc'], [['published_at', 'desc']]);

        $this->assertEquals(
            (new Collection([$data[2], $data[1], $data[3], $data[0], $data[4]]))->toArray(),
            $c->toArray()
        );
    }

    public function test_unique_partition()
    {
        // Simple values
        $c = new Collection(['foo', 'bar', 'foo']);
        list($unique, $duplicate) = $c->uniquePartition();
        $this->assertEqualsCanonicalizing(['foo', 'bar'], $unique->toArray());
        $this->assertEqualsCanonicalizing(['foo'], $duplicate->toArray());

        // Arrays
        $c = new Collection([
            ['v' => 'foo'],
            ['v' => 'bar'],
            ['v' => 'foo'],
        ]);
        list($unique, $duplicate) = $c->uniquePartition('v');
        $this->assertEqualsCanonicalizing([['v' => 'foo'], ['v' => 'bar']], $unique->toArray());
        $this->assertEqualsCanonicalizing([['v' => 'foo']], $duplicate->toArray());

        // Objects
        $uniques = [(object)['id' => 1, 'type' => 0], (object)['id' => 2, 'type' => 1]];
        $dupes = [(object)['id' => 3, 'type' => 0]];
        $c = new Collection([...$uniques, ...$dupes]);
        list($unique, $duplicate) = $c->uniquePartition('type');
        $this->assertEqualsCanonicalizing($uniques, $unique->toArray());
        $this->assertEqualsCanonicalizing($dupes, $duplicate->toArray());
    }
}
