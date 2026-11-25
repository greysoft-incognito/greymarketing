<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class CampaignMessageCollection extends ResourceCollection
{
    public $collects = CampaignMessageResource::class;
}
