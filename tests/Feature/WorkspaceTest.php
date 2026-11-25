<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    public function testRegistrationCreatesAPersonalWorkspace(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ada Obi',
            'email' => 'ada@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertSame(1, $user->workspaces()->count());
        $this->assertSame('owner', $user->workspaces()->first()->pivot->role);
    }

    public function testUserCanCreateAndListWorkspaces(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/workspaces', ['name' => 'Acme'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme')
            ->assertJsonPath('data.is_owner', true);

        $this->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Acme')
            ->assertJsonPath('data.0.role', 'owner');
    }

    public function testNonMembersCannotViewAWorkspace(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/workspaces/{$workspace->ulid}")->assertForbidden();
    }

    public function testMembersCannotRenameAWorkspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => 'member']);

        Sanctum::actingAs($member);

        $this->getJson("/api/workspaces/{$workspace->ulid}")->assertOk();
        $this->putJson("/api/workspaces/{$workspace->ulid}", ['name' => 'New'])->assertForbidden();
    }

    public function testOwnerCannotDeleteTheirOnlyWorkspace(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->deleteJson("/api/workspaces/{$workspace->ulid}")->assertUnprocessable();

        Workspace::createFor($workspace->owner, 'Second');

        $this->deleteJson("/api/workspaces/{$workspace->ulid}")->assertAccepted();
        $this->assertModelMissing($workspace);
    }

    public function testOwnerCanSetTheDefaultCountry(): void
    {
        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($workspace->owner);

        $this->patchJson("/api/workspaces/{$workspace->ulid}", ['default_country' => 'ke'])
            ->assertAccepted()
            ->assertJsonPath('data.default_country', 'KE');
    }
}
