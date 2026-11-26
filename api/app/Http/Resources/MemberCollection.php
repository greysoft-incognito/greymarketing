<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class MemberCollection extends ResourceCollection
{
    public $collects = MemberResource::class;
}
