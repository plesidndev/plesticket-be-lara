<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinceResource;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProvinceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProvinceRepositoryInterface $provinces) {}

    public function index(): JsonResponse
    {
        return $this->success('ok', ProvinceResource::collection($this->provinces->all()));
    }
}