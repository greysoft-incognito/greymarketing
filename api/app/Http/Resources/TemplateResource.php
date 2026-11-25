<?php

namespace App\Http\Resources;

use App\Enums\Channel;
use App\Messaging\SmsSegments;
use App\Messaging\TemplateRenderer;
use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $renderer = app(TemplateRenderer::class);

        return [
            'id' => $this->ulid,
            'channel' => $this->channel,
            'name' => $this->name,
            'subject' => $this->subject,
            'html' => $this->html,
            'body' => $this->body,
            'tags' => $renderer->tags("{$this->subject} {$this->html} {$this->body}"),
            'sms' => $this->when(
                $this->channel === Channel::SMS,
                fn () => SmsSegments::for((string) $this->body)->toArray()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
