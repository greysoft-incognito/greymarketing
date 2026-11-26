<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanInterval;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        Permission::PLANS->authorize();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');
        $required = $plan ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'slug' => [$required, 'string', 'max:100', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => [$required, 'numeric', 'min:0', 'max:9999999999'],
            'currency' => [$required, 'string', 'size:3'],
            'interval' => [$required, Rule::enum(PlanInterval::class)],
            'contacts_limit' => ['nullable', 'integer', 'min:0'],
            'emails_limit' => ['nullable', 'integer', 'min:0'],
            'sms_limit' => ['nullable', 'integer', 'min:0'],
            'seats_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planData(): array
    {
        $data = $this->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return $data;
    }
}
