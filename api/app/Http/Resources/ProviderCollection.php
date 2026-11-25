<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class ProviderCollection extends ResourceCollection
{
    public $collects = ProviderResource::class;
}
