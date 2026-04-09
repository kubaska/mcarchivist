<?php

namespace App\Exceptions;

use Illuminate\Http\Request;

class NotFoundApiException extends McaHttpException
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request)
    {
        return response()->json(null, 404);
    }
}
