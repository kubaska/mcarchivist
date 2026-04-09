<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class UnsupportedApiMethodException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request)
    {
        return response()->json([
            'error' => 'This platform does not support this action.',
            'description' => 'Specified platform does not support performing this action.'
        ], 403);
    }
}
