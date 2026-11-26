<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class CampaignPerformanceCollection extends ResourceCollection
{
    public $collects = CampaignPerformanceResource::class;
}
