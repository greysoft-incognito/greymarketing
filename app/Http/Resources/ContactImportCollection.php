<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class ContactImportCollection extends ResourceCollection
{
    public $collects = ContactImportResource::class;
}
