<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class PlatformDisabledException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request)
    {
        return response()->json([
            'error' => 'Platform disabled',
            'description' => $this->getMessage()
        ], 400);
    }
}
