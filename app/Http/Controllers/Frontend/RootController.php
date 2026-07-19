<?php

namespace App\Http\Controllers\Frontend;


use App\Enums\Status;
use App\Models\Analytic;
use App\Models\ThemeSetting;
use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\Request;

class RootController extends Controller
{
    public function index(Request $request, TenantResolver $tenantResolver): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        if ($tenantResolver->isReservedStorefrontHost($request->getHost())) {
            abort(404);
        }

        $analytics    = Analytic::with('analyticSections')->where(['status' => Status::ACTIVE])->get();
        $themeFavicon = ThemeSetting::where(['key' => 'theme_favicon_logo'])->first();
        $favIcon      = $themeFavicon->faviconLogo;
        return view('master', ['analytics' => $analytics, 'favicon' => $favIcon]);
    }
}
