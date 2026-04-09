<?php

namespace App\Support;

use App\Mca;
use Illuminate\Contracts\Support\Arrayable;

class HashList implements Arrayable
{
    protected array $hashes = [];

    public function __construct(array $hashes)
    {
        foreach ($hashes as $algo => $hash) {
            $this->set($algo, $hash);
        }
    }

    public function get(string $algo): ?string
    {
        return $this->hashes[$algo] ?? null;
    }

    public function getAlgos(): array
    {
        return array_keys($this->hashes);
    }

    public function getFirstHash(): array
    {
        if ($this->isEmpty()) return [null, null];
        $first = array_key_first($this->hashes);
        return [$first, $this->hashes[$first]];
    }

    public function set(string $algo, string $hash): static
    {
        if (in_array($algo, Mca::FILE_HASHES_ALGOS)) {
            $this->hashes[$algo] = $hash;
        }

        return $this;
    }

    public function has(string $algo): bool
    {
        return array_key_exists($algo, $this->hashes);
    }

    public function fill(array $hashes): static
    {
        foreach ($hashes as $algo => $hash) {
            $this->set($algo, $hash);
        }

        return $this;
    }

    public function all(): array
    {
        return $this->hashes;
    }

    /**
     * Determine whether at least one hash algo occurs in both sets, and therefore can be compared.
     *
     * @param array|HashList $hashes
     * @return bool
     */
    public function isComparable(array|HashList $hashes): bool
    {
        $hashes = $this->getItems($hashes);
        if (empty($hashes)) return false;
        return ! empty(array_intersect($this->getAlgos(), array_keys($hashes)));
    }

    /**
     * Compare a list of hashes against current hashes.
     *
     * @param array|HashList $hashes
     * @return bool
     */
    public function compareTo(array|HashList $hashes): bool
    {
        foreach ($this->getItems($hashes) as $algo => $hash) {
            $validHash = $this->get($algo);

            if ($validHash && $validHash !== $hash) return false;
        }

        return true;
    }

    public function differentTo(array|HashList $hashes): bool
    {
        return ! $this->compareTo($hashes);
    }

    public function isEmpty(): bool
    {
        return empty($this->hashes);
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function toArray(): array
    {
        return $this->hashes;
    }

    protected function getItems(array|HashList $hashes): array
    {
        return ($hashes instanceof HashList)
            ? $hashes->all()
            : $hashes;
    }
}
