<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

class McaHttpException extends Exception
{
    public function __construct(protected Response $response, Throwable $previous = null)
    {
        parent::__construct($response->reason(), $response->status(), $previous);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request)
    {
        return response()->json([
            'error' => $this->response->json('error', $this->response->reason()),
            'description' => $this->response->json('description')
        ], $this->response->status());
    }
}
