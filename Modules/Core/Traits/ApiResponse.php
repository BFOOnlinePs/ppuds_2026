<?php

namespace Modules\Core\Traits;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

trait ApiResponse {
    protected function successResponse($data, $message = null, $code = 200): \Illuminate\Http\JsonResponse
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            return response()->json(array_merge(
                [
                    'status'  => true,
                    'message' => $message,
                ],
                $data->response()->getData(true)
            ), $code, [], $options);
        }
        return response()->json([
            'status'    => true,
            'message'   => $message,
            'data'      => $data
        ], $code, [], $options);
    }

    protected function errorResponse($message = null, $code, $data = null): \Illuminate\Http\JsonResponse
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        return response()->json([
            'status'    => false,
            'message'   => $message,
            'data'      => $data
        ], $code, [], $options);
    }
}
