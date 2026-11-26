<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    protected function actingAsAdmin(): User
    {
        $user = User::first();

        $this->artisan('app:sync-roles');
        $user->syncRoles(config('permission-defs.roles', []));
        $user->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function testBrandingHasNoSignInImageByDefault(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('data.auth_image', null);
    }

    public function testAdminsCanUploadAndResetTheSignInImage(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post('/api/admin/configurations', [
            'auth_image' => UploadedFile::fake()->image('panel.jpg', 1200, 1600),
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $image = $this->getJson('/api/branding')->assertOk()->json('data.auth_image');
        $this->assertIsString($image);

        $this->deleteJson('/api/admin/branding/auth-image')->assertAccepted();

        $this->getJson('/api/branding')->assertJsonPath('data.auth_image', null);
    }

    public function testAdminsCanReplaceAndClearThePanelText(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/branding')->assertJsonPath('data.auth_messages.sign_in', null);

        $this->postJson('/api/admin/configurations', [
            'auth_signin_message' => 'Welcome to Kola Marketing.',
            'auth_invite_message' => 'Your team is waiting.',
        ])->assertStatus(202);

        $this->getJson('/api/branding')
            ->assertJsonPath('data.auth_messages.sign_in', 'Welcome to Kola Marketing.')
            ->assertJsonPath('data.auth_messages.sign_up', null)
            ->assertJsonPath('data.auth_messages.invite', 'Your team is waiting.');

        $this->postJson('/api/admin/configurations', ['auth_signin_message' => ''])->assertStatus(202);

        $this->getJson('/api/branding')->assertJsonPath('data.auth_messages.sign_in', null);
    }

    public function testPanelTextHasALengthLimit(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/configurations', ['auth_signup_heading' => str_repeat('a', 301)])
            ->assertUnprocessable();
    }

    public function testTheSignInImageMustBeAnImage(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/admin/configurations', [
            'auth_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function testMembersCannotResetTheSignInImage(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/admin/branding/auth-image')->assertForbidden();
    }
}
