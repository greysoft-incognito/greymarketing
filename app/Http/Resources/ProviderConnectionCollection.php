<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProviderConnectionCollection extends ResourceCollection
{
    public $collects = ProviderConnectionResource::class;
}
