<?php

namespace App\Http\Controllers;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Http\Resources\BrandingResource;
use App\Services\Branding\Branding;

class BrandingController extends Controller
{
    public function show(Branding $branding)
    {
        return (new BrandingResource($branding))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Remove the uploaded sign-in image so the app's default shows again.
     */
    public function resetAuthImage(Branding $branding)
    {
        Permission::MANAGE_CONFIGURATION->authorize();

        $branding->resetAuthImage();

        return (new BrandingResource($branding))->additional([
            'message' => 'Sign-in image reset to the default.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
