<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankResource;
use App\Repositories\Contracts\BankRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BankController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BankRepositoryInterface $banks) {}

    public function index(): JsonResponse
    {
        return $this->success('Banks retrieved.', BankResource::collection($this->banks->all()));
    }
}
