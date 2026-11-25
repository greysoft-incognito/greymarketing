<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class TemplateCollection extends ResourceCollection
{
    public $collects = TemplateResource::class;
}
