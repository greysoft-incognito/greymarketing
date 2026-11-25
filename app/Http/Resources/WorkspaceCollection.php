<?php

namespace App\Http\Resources;

use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

class WorkspaceCollection extends ResourceCollection
{
    public $collects = WorkspaceResource::class;
}
