<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'channel' => 'sms',
            'name' => fake()->words(2, true),
            'body' => 'Hi {{ first_name | there }}, our sale starts today.',
        ];
    }

    public function email(): static
    {
        return $this->state([
            'channel' => 'email',
            'subject' => 'Hello {{ first_name }}',
            'html' => '<p>Hi {{ first_name | there }}</p><p><a href="{{ unsubscribe_url }}">Unsubscribe</a></p>',
            'body' => null,
        ]);
    }
}
