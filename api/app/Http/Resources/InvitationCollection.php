<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class InvitationCollection extends ResourceCollection
{
    public $collects = InvitationResource::class;
}
