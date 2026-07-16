<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\DamageDetailsResource;
use App\Http\Resources\DamageResource;
use App\Models\Damage;
use App\Services\DamageService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantDamageController extends Controller
{
    public function __construct(private readonly DamageService $damageService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return DamageResource::collection($this->damageService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(DamageRequest $request)
    {
        try {
            return new DamageResource($this->damageService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(int $damageId)
    {
        try {
            return new DamageDetailsResource($this->damageService->show(Damage::query()->findOrFail($damageId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function edit(int $damageId)
    {
        try {
            return new DamageDetailsResource($this->damageService->edit(Damage::query()->findOrFail($damageId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(DamageRequest $request, int $damageId)
    {
        try {
            return new DamageResource($this->damageService->update($request, Damage::query()->findOrFail($damageId)));
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(int $damageId)
    {
        try {
            $this->damageService->destroy(Damage::query()->findOrFail($damageId));
            return response('', 202);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
