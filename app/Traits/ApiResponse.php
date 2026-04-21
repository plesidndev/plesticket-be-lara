<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function created(string $message, mixed $data = null): JsonResponse
    {
        return $this->success($message, $data, 201);
    }

    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        $body = ['status' => 'error', 'message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        return response()->json($body, $code);
    }

    protected function paginated(string $message, mixed $data, \Illuminate\Pagination\LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => [
                'total' => $paginator->total(),
                'page'  => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'pages' => $paginator->lastPage(),
            ],
        ]);
    }
}