<?php

namespace App\Http\Requests\Workspace;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->workspace());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $campaign = $this->route('campaign');
        $creating = ! $campaign instanceof Campaign;
        $channel = $creating ? (string) $this->input('channel') : $campaign->channel->value;
        $workspaceId = $this->workspace()->id;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'channel' => [$creating ? 'required' : 'prohibited', Rule::enum(Channel::class)],
            'template' => ['nullable', 'string', Rule::exists('templates', 'ulid')
                ->where('workspace_id', $workspaceId)
                ->where('channel', $channel)],
            'connection' => ['nullable', 'string', Rule::exists('provider_connections', 'ulid')
                ->where('workspace_id', $workspaceId)
                ->where('channel', $channel)],
            'lists' => ['sometimes', 'array', 'max:50'],
            'lists.*' => ['string', Rule::exists('contact_lists', 'ulid')->where('workspace_id', $workspaceId)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'template.exists' => 'Choose a template from this workspace for the same channel.',
            'connection.exists' => 'Choose a provider connection from this workspace for the same channel.',
        ];
    }

    /**
     * Apply the validated input to the campaign.
     */
    public function fillCampaign(Campaign $campaign): Campaign
    {
        $valid = $this->validated();
        $workspace = $this->workspace();

        $campaign->fill(collect($valid)->only(['name', 'channel', 'scheduled_at'])->all());

        if ($this->has('template')) {
            $campaign->template()->associate($valid['template'] ? $workspace->templates()->firstWhere('ulid', $valid['template']) : null);
        }

        if ($this->has('connection')) {
            $campaign->providerConnection()->associate($valid['connection'] ? $workspace->connections()->firstWhere('ulid', $valid['connection']) : null);
        }

        $campaign->save();

        if ($this->has('lists')) {
            $campaign->lists()->sync($workspace->lists()->whereIn('ulid', $valid['lists'])->pluck('id'));
        }

        return $campaign;
    }

    protected function workspace(): Workspace
    {
        return $this->route('workspace');
    }
}
