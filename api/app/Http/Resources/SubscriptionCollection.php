<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class SubscriptionCollection extends ResourceCollection
{
    public $collects = SubscriptionResource::class;
}
