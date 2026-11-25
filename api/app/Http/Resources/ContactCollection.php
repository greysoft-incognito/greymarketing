<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class ContactCollection extends ResourceCollection
{
    public $collects = ContactResource::class;
}
