<?php

namespace App\Http\Requests\Workspace;

use App\Models\Contact;
use App\Models\Workspace;
use App\Services\Audience\ContactNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->workspace());
    }

    /**
     * Normalise identifiers first so uniqueness is checked on the stored form.
     */
    protected function prepareForValidation(): void
    {
        $normalizer = app(ContactNormalizer::class);

        if ($this->filled('email')) {
            $this->merge(['email' => $normalizer->email($this->input('email')) ?? $this->input('email')]);
        }

        if ($this->filled('phone')) {
            $phone = $normalizer->phone($this->input('phone'), $this->workspace()->default_country);
            $this->merge(['phone' => $phone ?? $this->input('phone')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contact = $this->route('contact');
        $creating = ! $contact instanceof Contact;

        $unique = fn (string $column) => Rule::unique('contacts', $column)
            ->where('workspace_id', $this->workspace()->id)
            ->ignore($contact?->id);

        return [
            'email' => [$creating ? 'required_without:phone' : 'nullable', 'nullable', 'email', 'max:255', $unique('email')],
            'phone' => [$creating ? 'required_without:email' : 'nullable', 'nullable', 'regex:/^\+[1-9]\d{6,14}$/', $unique('phone')],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'fields' => ['nullable', 'array', 'max:50'],
            'fields.*' => ['nullable', 'string', 'max:1000'],
            'email_subscribed' => ['sometimes', 'boolean'],
            'sms_subscribed' => ['sometimes', 'boolean'],
            'lists' => ['sometimes', 'array', 'max:50'],
            'lists.*' => ['string', Rule::exists('contact_lists', 'ulid')->where('workspace_id', $this->workspace()->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number is not valid.',
        ];
    }

    protected function workspace(): Workspace
    {
        return $this->route('workspace');
    }
}
