<?php

namespace App\Exceptions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\ProvidesConvenienceMethods;
use Symfony\Component\HttpFoundation\Response;

class McaValidationException extends ValidationException
{
    // No need to reinvent the wheel, just use this
    use ProvidesConvenienceMethods;

    public function __construct(Validator $validator, ?Response $response = null, $errorBag = 'default')
    {
        if ($response === null) {
            $response = $this->buildFailedValidationResponse(request(), $this->formatValidationErrors($validator));
        }

        parent::__construct($validator, $response, $errorBag);
    }
}
