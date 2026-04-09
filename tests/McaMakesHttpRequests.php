<?php

namespace Tests;

use Illuminate\Testing\Assert as PHPUnit;
use Laravel\Lumen\Testing\Concerns\MakesHttpRequests;

trait McaMakesHttpRequests
{
    use MakesHttpRequests;

    /**
     * Assert that the given json path value is not null.
     *
     * @param string $path
     * @return $this
     */
    public function assertJsonPathNotNull(string $path): static
    {
        PHPUnit::assertNotNull($this->response->json($path), sprintf('Failed to assert that json path "%s" is not null.', $path));

        return $this;
    }
}
