<?php

namespace App\Http\Requests\Workspace;

use App\Enums\Channel;
use App\Messaging\ProviderManager;
use App\Models\ProviderConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProviderConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('workspace'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $manager = app(ProviderManager::class);
        $connection = $this->connection();
        $provider = $connection?->provider ?? $this->input('provider');

        $rules = [
            'name' => [$connection ? 'sometimes' : 'required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'credentials' => [$connection ? 'sometimes' : 'required', 'array'],
            'settings' => ['sometimes', 'array'],
        ];

        if (! $connection) {
            $rules['provider'] = ['required', 'string', Rule::in($manager->keys())];
            $rules['channel'] = ['required', Rule::enum(Channel::class)];
        }

        if (in_array($provider, $manager->keys(), true)) {
            $rules += $manager->rules($provider, partial: (bool) $connection);
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->connection() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $channel = Channel::from($this->input('channel'));

                if (! app(ProviderManager::class)->supports($this->input('provider'), $channel)) {
                    $validator->errors()->add('channel', "This provider cannot send {$channel->value} messages.");
                }
            },
        ];
    }

    /**
     * Only the fields the provider defines, so stray input is never stored.
     *
     * @return array<string, mixed>
     */
    public function connectionData(): array
    {
        $valid = $this->validated();

        $data = collect($valid)->only(['name', 'provider', 'channel', 'is_default'])->all();

        if (isset($valid['credentials'])) {
            $data['credentials'] = array_merge($this->connection()?->credentials ?? [], $valid['credentials']);
        }

        if (isset($valid['settings'])) {
            $data['settings'] = array_merge($this->connection()?->settings ?? [], $valid['settings']);
        }

        return $data;
    }

    protected function connection(): ?ProviderConnection
    {
        return $this->route('connection');
    }
}
