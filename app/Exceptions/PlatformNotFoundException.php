<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Throwable;

class PlatformNotFoundException extends Exception
{
    public function __construct(protected string $platform, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('Platform [%s] not found', $platform), $code, $previous);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request)
    {
        return response()->json([
            'error' => sprintf('Platform %s not found.', $this->platform),
            'description' => 'Specified platform does not exist.'
        ], 400);
    }
}
