<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryCollection;
use App\Services\Audience\ContactNormalizer;

/**
 * Countries a workspace can read local phone numbers in.
 */
class CountryController extends Controller
{
    public function __invoke(ContactNormalizer $normalizer)
    {
        return (new CountryCollection($normalizer->countries()))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
