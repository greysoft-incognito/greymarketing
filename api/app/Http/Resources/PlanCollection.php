<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class PlanCollection extends ResourceCollection
{
    public $collects = PlanResource::class;
}
