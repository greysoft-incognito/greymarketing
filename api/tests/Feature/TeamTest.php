<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\TeamInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTest extends TestCase
{
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Plan::fallback()->update(['seats_limit' => 3]);

        $this->workspace = Workspace::factory()->create();
    }

    /**
     * Invite an email as the owner and return the plain link token.
     */
    protected function invite(string $email = 'kemi@example.com', string $role = 'member'): string
    {
        Sanctum::actingAs($this->workspace->owner);

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations", ['email' => $email, 'role' => $role])
            ->assertCreated();

        $token = null;

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            TeamInvitation::class,
            function (TeamInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token) {
                $token = $notification->token;

                return $notifiable->routes['mail'] === strtolower($email);
            }
        );

        return $token;
    }

    protected function addMember(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $this->workspace->members()->attach($user, ['role' => $role]);

        return $user;
    }

    public function testMembersCanListTheTeam(): void
    {
        $member = $this->addMember();

        Sanctum::actingAs($member);

        $this->getJson("/api/workspaces/{$this->workspace->ulid}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonPath('data.1.is_you', true);
    }

    public function testOwnerCanInviteAndTheInviteeCanAccept(): void
    {
        $token = $this->invite('Kemi@Example.com', 'admin');

        $this->getJson('/api/invites/' . $token)
            ->assertOk()
            ->assertJsonPath('data.email', 'kemi@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.workspace.name', $this->workspace->name);

        $kemi = User::factory()->create(['email' => 'kemi@example.com']);
        Sanctum::actingAs($kemi);

        $this->postJson("/api/invites/{$token}/accept")
            ->assertAccepted()
            ->assertJsonPath('data.id', $this->workspace->ulid)
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame('admin', $this->workspace->roleOf($kemi)->value);
        $this->assertSame(0, $this->workspace->invitations()->count());
        $this->getJson('/api/invites/' . $token)->assertNotFound();
    }

    public function testTheInviteEmailLinksToTheInvitePage(): void
    {
        $this->invite();

        $invitation = $this->workspace->invitations()->firstOrFail();
        $mail = (new TeamInvitation($invitation, 'plain-token'))->toMail(new AnonymousNotifiable());

        $this->assertStringContainsString($this->workspace->name, $mail->subject);
        $this->assertStringContainsString('/invites/plain-token', (string) $mail->render());
    }

    public function testInvitesOnlyWorkForTheirEmail(): void
    {
        $token = $this->invite();

        Sanctum::actingAs(User::factory()->create(['email' => 'someone@example.com']));

        $this->postJson("/api/invites/{$token}/accept")->assertForbidden();
        $this->postJson("/api/invites/{$token}/decline")->assertForbidden();
    }

    public function testExpiredInvitesCannotBeAccepted(): void
    {
        $token = $this->invite();

        $this->travel(WorkspaceInvitation::LIFETIME_DAYS + 1)->days();

        Sanctum::actingAs(User::factory()->create(['email' => 'kemi@example.com']));

        $this->postJson("/api/invites/{$token}/accept")->assertNotFound();
    }

    public function testInviteeCanDecline(): void
    {
        $token = $this->invite();

        Sanctum::actingAs(User::factory()->create(['email' => 'kemi@example.com']));

        $this->postJson("/api/invites/{$token}/decline")->assertAccepted();
        $this->assertSame(0, $this->workspace->invitations()->count());
    }

    public function testPendingInvitesHoldASeat(): void
    {
        $this->addMember();
        $this->invite('one@example.com');

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations", ['email' => 'two@example.com', 'role' => 'member'])
            ->assertUnprocessable();

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations", ['email' => 'one@example.com', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $this->getJson("/api/workspaces/{$this->workspace->ulid}/billing")
            ->assertOk()
            ->assertJsonPath('data.usage.seats.used', 3)
            ->assertJsonPath('data.usage.seats.limit', 3);
    }

    public function testExistingMembersCannotBeInvited(): void
    {
        $member = $this->addMember();

        Sanctum::actingAs($this->workspace->owner);

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations", ['email' => $member->email, 'role' => 'member'])
            ->assertUnprocessable();
    }

    public function testAdminsCanResendAndRevokeInvites(): void
    {
        $this->invite();
        $invitation = $this->workspace->invitations()->firstOrFail();
        $oldToken = $invitation->token;

        Sanctum::actingAs($this->addMember('admin'));

        $this->getJson("/api/workspaces/{$this->workspace->ulid}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations/{$invitation->ulid}/resend")->assertAccepted();
        $this->assertNotSame($oldToken, $invitation->fresh()->token);

        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/invitations/{$invitation->ulid}")->assertAccepted();
        $this->assertModelMissing($invitation);
    }

    public function testMembersCannotManageTheTeam(): void
    {
        $member = $this->addMember();
        $other = $this->addMember();

        Sanctum::actingAs($member);

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/invitations", ['email' => 'x@example.com', 'role' => 'member'])
            ->assertForbidden();
        $this->getJson("/api/workspaces/{$this->workspace->ulid}/invitations")->assertForbidden();
        $this->patchJson("/api/workspaces/{$this->workspace->ulid}/members/{$other->username}", ['role' => 'admin'])
            ->assertForbidden();
        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/members/{$other->username}")->assertForbidden();
    }

    public function testAdminsCanChangeRolesButNotTheOwners(): void
    {
        $member = $this->addMember();

        Sanctum::actingAs($this->addMember('admin'));

        $this->patchJson("/api/workspaces/{$this->workspace->ulid}/members/{$member->username}", ['role' => 'admin'])
            ->assertAccepted()
            ->assertJsonPath('data.role', 'admin');

        $this->patchJson("/api/workspaces/{$this->workspace->ulid}/members/{$member->username}", ['role' => 'owner'])
            ->assertUnprocessable();

        $this->patchJson("/api/workspaces/{$this->workspace->ulid}/members/{$this->workspace->owner->username}", ['role' => 'member'])
            ->assertUnprocessable();
    }

    public function testAdminsCanRemoveMembersButNotTheOwnerOrThemselves(): void
    {
        $member = $this->addMember();
        $admin = $this->addMember('admin');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/members/{$member->username}")->assertAccepted();
        $this->assertNull($this->workspace->roleOf($member));

        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/members/{$this->workspace->owner->username}")
            ->assertUnprocessable();
        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/members/{$admin->username}")
            ->assertUnprocessable();
    }

    public function testMembersOfOtherWorkspacesCannotBeTouched(): void
    {
        $stranger = User::factory()->create();

        Sanctum::actingAs($this->workspace->owner);

        $this->deleteJson("/api/workspaces/{$this->workspace->ulid}/members/{$stranger->username}")->assertNotFound();
    }
}
