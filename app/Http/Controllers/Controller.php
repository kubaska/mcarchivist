<?php

namespace App\Http\Controllers;

use App\API\ThirdPartyApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function validateValues(array $data, array $rules, array $messages = [])
    {
        $validator = $this->getValidationFactory()->make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException(
                $validator,
                new JsonResponse($validator->errors()->messages(), 422)
            );
        }

        return true;
    }

    protected function withPaginatorMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage()
            ]
        ];
    }

    protected function makeResponseFromRemoteData(ThirdPartyApiResponse $response, mixed $data = null): Response
    {
        $responseData = [
            'cached' => $response->isCached(),
            'data' => $data?->toArray() ?? $response->getData()
        ];

        if ($pagination = $response->getPagination()) {
            $responseData['meta'] = $pagination->toArray();
        }

        return response($responseData);
    }
}
