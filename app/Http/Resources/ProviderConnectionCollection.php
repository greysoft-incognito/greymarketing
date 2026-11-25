<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class ProviderConnectionCollection extends ResourceCollection
{
    public $collects = ProviderConnectionResource::class;
}
