<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class ContactListCollection extends ResourceCollection
{
    public $collects = ContactListResource::class;
}
