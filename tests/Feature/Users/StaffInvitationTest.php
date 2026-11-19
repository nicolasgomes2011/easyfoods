<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Livewire\Auth\LoginForm;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * EF-24: inviting a staff member sends its own e-mail (not the generic
 * "reset password" one), the link leads to the set-password screen and then
 * to the panel of the member's role, and the team list shows "Convite
 * pendente" until the first login, with a button to send the invite again.
 */
class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['name' => 'Cantina da Ana']);
        $this->admin = User::factory()->create([
            'role'          => UserRole::Admin,
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->actingAs($this->admin);
    }

    private function invite(string $email = 'bia@example.com', UserRole $role = UserRole::Kitchen): User
    {
        Volt::test('users.index')
            ->set('inviteName', 'Bia Cozinha')
            ->set('inviteEmail', $email)
            ->set('inviteRole', $role->value)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('inviteError', null)
            ->assertSet('inviteSuccess', "Convite enviado para {$email}.");

        return User::where('email', $email)->firstOrFail();
    }

    private function sentInvitation(User $user): StaffInvitation
    {
        $sent = Notification::sent($user, StaffInvitation::class);
        $this->assertNotEmpty($sent, 'StaffInvitation was not sent.');

        return $sent->last();
    }

    public function test_inviting_sends_the_staff_invitation_instead_of_the_reset_password_email(): void
    {
        Notification::fake();

        $member = $this->invite();

        Notification::assertSentToTimes($member, StaffInvitation::class, 1);
        Notification::assertNotSentTo($member, ResetPassword::class);

        $mail = $this->sentInvitation($member)->toMail($member);

        $this->assertSame('Convite para a equipe de Cantina da Ana', $mail->subject);
        $this->assertSame('Definir minha senha', $mail->actionText);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Cantina da Ana', $html);
        $this->assertStringContainsString(UserRole::Kitchen->label(), $html);
        $this->assertStringContainsString('Definir minha senha', $html);
        $this->assertStringContainsString('60 minutos', $html);
        $this->assertStringNotContainsString('Reset Password', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString("having trouble clicking", $html);

        $this->assertNotNull($member->invited_at);
        $this->assertTrue($member->isInvitationPending());
    }

    public function test_the_link_opens_the_set_password_screen_and_then_the_panel_of_the_role(): void
    {
        Notification::fake();

        $member = $this->invite(role: UserRole::Kitchen);
        $invitation = $this->sentInvitation($member);
        $url = $invitation->toMail($member)->actionUrl;

        $this->assertSame(
            route('password.reset', ['token' => $invitation->token, 'email' => $member->email]),
            $url,
        );
        $this->assertTrue(Password::broker()->tokenExists($member, $invitation->token));

        // The invited member is not logged in on this browser.
        auth()->logout();

        $this->get($url)
            ->assertOk()
            ->assertSee('Redefinir senha')
            ->assertSee('Nova senha')
            ->assertDontSee('Undefined');

        Volt::test('auth.reset-password', ['token' => $invitation->token])
            ->set('email', $member->email)
            ->set('password', 'senha-nova-123')
            ->set('password_confirmation', 'senha-nova-123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        $this->assertTrue(Hash::check('senha-nova-123', $member->fresh()->password));

        Livewire::test(LoginForm::class)
            ->set('email', $member->email)
            ->set('password', 'senha-nova-123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.kitchen.index', absolute: false));

        $member->refresh();
        $this->assertNotNull($member->last_login_at);
        $this->assertFalse($member->isInvitationPending());
    }

    public function test_the_team_list_shows_pending_invites_until_the_first_login(): void
    {
        Notification::fake();

        $pending  = $this->invite('pendente@example.com');
        $accepted = $this->invite('aceito@example.com');
        $accepted->forceFill(['last_login_at' => now()])->save();

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeInOrder(['aceito@example.com', 'Ativo'])
            ->assertSeeInOrder(['pendente@example.com', 'Convite pendente', 'Reenviar convite']);

        $component = Volt::test('users.index');
        $this->assertTrue($component->instance()->staff->firstWhere('id', $pending->id)->isInvitationPending());
        $this->assertFalse($component->instance()->staff->firstWhere('id', $accepted->id)->isInvitationPending());
        // Staff that was never invited through the panel (e.g. the owner) is not pending.
        $this->assertFalse($this->admin->fresh()->isInvitationPending());
    }

    public function test_resending_sends_a_new_invitation_once_a_minute(): void
    {
        Notification::fake();

        $member = $this->invite();
        $this->travel(2)->minutes();

        Volt::test('users.index')
            ->call('resendInvite', $member->id)
            ->assertSet('listNotice', "Convite reenviado para {$member->email}.");

        Notification::assertSentToTimes($member, StaffInvitation::class, 2);
        $this->assertTrue($member->fresh()->invited_at->isSameMinute(now()));

        // Right after a send, a second click waits.
        Volt::test('users.index')
            ->call('resendInvite', $member->id)
            ->assertSet('listNotice', "Aguarde um minuto para reenviar o convite de {$member->name}.");

        Notification::assertSentToTimes($member, StaffInvitation::class, 2);
    }

    public function test_resend_does_nothing_for_a_member_who_already_logged_in(): void
    {
        Notification::fake();

        $member = $this->invite();
        $member->forceFill(['last_login_at' => now()])->save();
        $this->travel(2)->minutes();

        Volt::test('users.index')->call('resendInvite', $member->id);

        Notification::assertSentToTimes($member, StaffInvitation::class, 1);
    }

    public function test_resend_refuses_a_member_of_another_restaurant(): void
    {
        Notification::fake();

        $other = User::factory()->create([
            'role'          => UserRole::Kitchen,
            'restaurant_id' => Restaurant::factory()->create()->id,
        ]);
        $other->forceFill(['invited_at' => now()->subHour()])->save();

        Volt::test('users.index')
            ->call('resendInvite', $other->id)
            ->assertNotFound();

        Notification::assertNothingSentTo($other);
    }

    public function test_the_migration_rolls_back(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // MySQL commits implicitly on DDL, which would leak this test's rows past RefreshDatabase.
            $this->markTestSkipped('Re-running a migration inside the test transaction is only safe on SQLite.');
        }

        $migration = require database_path('migrations/2026_11_19_000000_add_invitation_columns_to_users_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'invited_at'));
        $this->assertFalse(Schema::hasColumn('users', 'last_login_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'invited_at'));
        $this->assertTrue(Schema::hasColumn('users', 'last_login_at'));
    }

    public function test_a_login_records_the_last_login(): void
    {
        auth()->logout();

        $member = User::factory()->create([
            'role'          => UserRole::Attendant,
            'restaurant_id' => $this->restaurant->id,
            'password'      => 'senha-123',
        ]);
        $member->forceFill(['invited_at' => now()->subDay()])->save();
        $this->assertTrue($member->isInvitationPending());

        Livewire::test(LoginForm::class)
            ->set('email', $member->email)
            ->set('password', 'senha-123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertFalse($member->fresh()->isInvitationPending());
    }
}
