<?php

namespace App\Services;


use Exception;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\SliderRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Models\Slider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class SliderService
{
    protected $sliderFilter = [
        'title',
        'description',
        'status',
    ];

    protected $exceptFilter = [
        'excepts'
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            return $this->runListQuery($this->scopedQuery($request), $request);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function storefrontList(PaginateRequest $request)
    {
        try {
            return $this->runListQuery($this->scopedQuery($request), $request);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function store(SliderRequest $request)
    {
        try {
            $slider = Slider::create($request->validated() + ['link' => $request->link]);
            if ($request->image) {
                $slider->addMediaFromRequest('image')->toMediaCollection('slider');
            }
            return $slider;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(SliderRequest $request, Slider|int|string $slider): Slider
    {
        $slider = $this->findScoped($slider);

        try {
            $slider->update($request->validated() + ['link' => $request->link]);
            if ($request->image) {
                $slider->clearMediaCollection('slider');
                $slider->addMediaFromRequest('image')->toMediaCollection('slider');
            }
            return $slider;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Slider|int|string $slider)
    {
        $slider = $this->findScoped($slider);

        try {
            $slider->delete();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Slider|int|string $slider): Slider
    {
        return $this->findScoped($slider);
    }

    private function scopedQuery(PaginateRequest $request): Builder
    {
        $query = Slider::withoutGlobalScope('tenant')->with('media');
        $tenantId = app(TenantContext::class)->currentId($request);

        $tenantId === null
            ? $query->whereNull($query->getModel()->qualifyColumn('tenant_id'))
            : $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);

        return $query;
    }

    private function findScoped(Slider|int|string $slider): Slider
    {
        $sliderId = $slider instanceof Slider ? $slider->getKey() : $slider;
        $model = new Slider();
        $query = Slider::withoutGlobalScope('tenant')
            ->with('media')
            ->whereKey($sliderId);
        $tenantId = app(TenantContext::class)->currentId();

        $tenantId === null
            ? $query->whereNull($model->qualifyColumn('tenant_id'))
            : $query->where($model->qualifyColumn('tenant_id'), $tenantId);

        return $query->firstOrFail();
    }

    private function runListQuery(Builder $query, PaginateRequest $request)
    {
        $requests    = $request->all();
        $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
        $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
        $orderColumn = $request->get('order_column') ?? 'id';
        $orderType   = $request->get('order_type') ?? 'desc';

        return $query->where(function ($query) use ($requests) {
            foreach ($requests as $key => $request) {
                if (in_array($key, $this->sliderFilter)) {
                    $query->where($key, 'like', '%' . $request . '%');
                }

                if (in_array($key, $this->exceptFilter)) {
                    $explodes = explode('|', $request);
                    if (is_array($explodes)) {
                        foreach ($explodes as $explode) {
                            $query->where('id', '!=', $explode);
                        }
                    }
                }
            }
        })->orderBy($orderColumn, $orderType)->$method(
            $methodValue
        );
    }
}
