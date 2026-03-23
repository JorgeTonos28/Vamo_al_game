<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Branding\AppBrandingService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingAssetController extends Controller
{
    public function logo(AppBrandingService $brandingService): BinaryFileResponse
    {
        return $brandingService->logoResponse();
    }

    public function favicon(AppBrandingService $brandingService): BinaryFileResponse
    {
        return $brandingService->faviconResponse();
    }
}
