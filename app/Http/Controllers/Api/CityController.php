<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CityRepositoryInterface $cities) {}

    public function index(Request $request): JsonResponse
    {
        $cities = $this->cities->all($request->query('province_code'));

        return $this->success('ok', CityResource::collection($cities));
    }
}