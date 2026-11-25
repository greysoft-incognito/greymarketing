<?php

namespace App\Http\Requests\Workspace;

use App\Enums\Channel;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('workspace'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $template = $this->route('template');
        $channel = $template instanceof Template ? $template->channel : Channel::tryFrom((string) $this->input('channel'));
        $isEmail = $channel === Channel::EMAIL;
        $presence = $template instanceof Template ? 'sometimes' : 'required';

        return [
            'channel' => [$template instanceof Template ? 'prohibited' : 'required', Rule::enum(Channel::class)],
            'name' => [$presence, 'string', 'max:100'],
            'subject' => [$isEmail ? $presence : 'prohibited', 'string', 'max:200'],
            'html' => [$isEmail ? 'required_without:body' : 'prohibited', 'nullable', 'string', 'max:200000'],
            'body' => [$isEmail ? 'nullable' : $presence, 'nullable', 'string', $isEmail ? 'max:100000' : 'max:1600'],
        ];
    }
}
