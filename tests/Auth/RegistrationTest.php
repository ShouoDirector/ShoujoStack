<?php

namespace Tests\Auth;

use BookStack\Access\Notifications\ConfirmEmailNotification;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_confirmed_registration()
    {
        // Fake notifications
        Notification::fake();

        // Set settings and get user instance
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'true']);
        $user = User::factory()->make();

        // Go through registration process
        $resp = $this->post('/register', $user->only('name', 'email', 'password'));
        $resp->assertRedirect('/register/confirm');
        $this->assertDatabaseHas('users', ['name' => $user->name, 'email' => $user->email, 'email_confirmed' => false]);

        $resp = $this->get('/register/confirm');
        $resp->assertSee('Thanks for registering!');

        // Ensure notification sent
        /** @var User $dbUser */
        $dbUser = User::query()->where('email', '=', $user->email)->first();
        Notification::assertSentTo($dbUser, ConfirmEmailNotification::class);

        // Test access and resend confirmation email
        $resp = $this->post('/login', ['email' => $user->email, 'password' => $user->password]);
        $resp->assertRedirect('/register/confirm/awaiting');

        $resp = $this->get('/register/confirm/awaiting');
        $this->withHtml($resp)->assertElementContains('form[action="' . url('/register/confirm/resend') . '"]', 'Resend');

        $this->get('/books')->assertRedirect('/login');
        $this->post('/register/confirm/resend', $user->only('email'));

        // Get confirmation and confirm notification matches
        $emailConfirmation = DB::table('email_confirmations')->where('user_id', '=', $dbUser->id)->first();
        Notification::assertSentTo($dbUser, ConfirmEmailNotification::class, function ($notification, $channels) use ($emailConfirmation) {
            return $notification->token === $emailConfirmation->token;
        });

        // Check confirmation email confirmation accept page.
        $resp = $this->get('/register/confirm/' . $emailConfirmation->token);
        $acceptPage = $this->withHtml($resp);
        $resp->assertOk();
        $resp->assertSee('Thanks for confirming!');
        $acceptPage->assertElementExists('form[method="post"][action$="/register/confirm/accept"][component="auto-submit"] button');
        $acceptPage->assertFieldHasValue('token', $emailConfirmation->token);

        // Check acceptance confirm
        $this->post('/register/confirm/accept', ['token' => $emailConfirmation->token])->assertRedirect('/login');

        // Check state on login redirect
        $this->get('/login')->assertSee('Your email has been confirmed! You should now be able to login using this email address.');
        $this->assertDatabaseMissing('email_confirmations', ['token' => $emailConfirmation->token]);
        $this->assertDatabaseHas('users', ['name' => $dbUser->name, 'email' => $dbUser->email, 'email_confirmed' => true]);
    }

    public function test_restricted_registration()
    {
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'true', 'registration-restrict' => 'example.com']);
        $user = User::factory()->make();

        // Go through registration process
        $this->post('/register', $user->only('name', 'email', 'password'))
            ->assertRedirect('/register');
        $resp = $this->get('/register');
        $resp->assertSee('That email domain does not have access to this application');
        $this->assertDatabaseMissing('users', $user->only('email'));

        $user->email = 'barry@example.com';

        $this->post('/register', $user->only('name', 'email', 'password'))
            ->assertRedirect('/register/confirm');
        $this->assertDatabaseHas('users', ['name' => $user->name, 'email' => $user->email, 'email_confirmed' => false]);

        $this->assertNull(auth()->user());

        $this->get('/')->assertRedirect('/login');
        $resp = $this->followingRedirects()->post('/login', $user->only('email', 'password'));
        $resp->assertSee('Email Address Not Confirmed');
        $this->assertNull(auth()->user());
    }

    public function test_restricted_registration_with_confirmation_disabled()
    {
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'false', 'registration-restrict' => 'example.com']);
        $user = User::factory()->make();

        // Go through registration process
        $this->post('/register', $user->only('name', 'email', 'password'))
            ->assertRedirect('/register');
        $this->assertDatabaseMissing('users', $user->only('email'));
        $this->get('/register')->assertSee('That email domain does not have access to this application');

        $user->email = 'barry@example.com';

        $this->post('/register', $user->only('name', 'email', 'password'))
            ->assertRedirect('/register/confirm');
        $this->assertDatabaseHas('users', ['name' => $user->name, 'email' => $user->email, 'email_confirmed' => false]);

        $this->assertNull(auth()->user());

        $this->get('/')->assertRedirect('/login');
        $resp = $this->post('/login', $user->only('email', 'password'));
        $resp->assertRedirect('/register/confirm/awaiting');
        $this->get('/register/confirm/awaiting')->assertSee('Email Address Not Confirmed');
        $this->assertNull(auth()->user());
    }

    public function test_registration_role_unset_by_default()
    {
        $this->assertFalse(setting('registration-role'));

        $resp = $this->asAdmin()->get('/settings/registration');
        $this->withHtml($resp)->assertElementContains('select[name="setting-registration-role"] option[value="0"][selected]', '-- None --');
    }

    public function test_registration_showing()
    {
        // Ensure registration form is showing
        $this->setSettings(['registration-enabled' => 'true']);
        $resp = $this->get('/login');
        $this->withHtml($resp)->assertElementContains('a[href="' . url('/register') . '"]', 'Sign up');
    }

    public function test_normal_registration()
    {
        // Set settings and get user instance
        /** @var Role $registrationRole */
        $registrationRole = Role::query()->first();
        $this->setSettings(['registration-enabled' => 'true', 'registration-role' => $registrationRole->id]);
        /** @var User $user */
        $user = User::factory()->make();

        // Test form and ensure user is created
        $resp = $this->get('/register')
            ->assertSee('Sign Up');
        $this->withHtml($resp)->assertElementContains('form[action="' . url('/register') . '"]', 'Create Account');

        $resp = $this->post('/register', $user->only('password', 'name', 'email'));
        $resp->assertRedirect('/');

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee($user->name);

        $this->assertDatabaseHas('users', ['name' => $user->name, 'email' => $user->email]);

        $user = User::query()->where('email', '=', $user->email)->first();
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals($registrationRole->id, $user->roles()->first()->id);
    }

    public function test_empty_registration_redirects_back_with_errors()
    {
        // Set settings and get user instance
        $this->setSettings(['registration-enabled' => 'true']);

        // Test form and ensure user is created
        $this->get('/register');
        $this->post('/register', [])->assertRedirect('/register');
        $this->get('/register')->assertSee('The name field is required');
    }

    public function test_registration_validation()
    {
        $this->setSettings(['registration-enabled' => 'true']);

        $this->get('/register');
        $resp = $this->followingRedirects()->post('/register', [
            'name'     => '1',
            'email'    => '1',
            'password' => '1',
        ]);
        $resp->assertSee('The name must be at least 2 characters.');
        $resp->assertSee('The email must be a valid email address.');
        $resp->assertSee('The password must be at least 8 characters.');
    }

    public function test_registration_simple_honeypot_active()
    {
        $this->setSettings(['registration-enabled' => 'true']);

        $resp = $this->get('/register');
        $this->withHtml($resp)->assertElementExists('form input[name="username"]');

        $resp = $this->post('/register', [
            'name' => 'Barry',
            'email' => 'barrybot@example.com',
            'password' => 'barryIsTheBestBot',
            'username' => 'MyUsername'
        ]);
        $resp->assertRedirect('/register');

        $resp = $this->followRedirects($resp);
        $this->withHtml($resp)->assertElementExists('form input[name="username"].text-neg');
    }

    public function test_registration_endpoint_throttled()
    {
        $this->setSettings(['registration-enabled' => 'true']);

        for ($i = 0; $i < 11; $i++) {
            $response = $this->post('/register/', [
                'name' => "Barry{$i}",
                'email' => "barry{$i}@example.com",
                'password' => "barryIsTheBest{$i}",
            ]);
            auth()->logout();
        }

        $response->assertStatus(429);
    }

    public function test_registration_confirmation_throttled()
    {
        $this->setSettings(['registration-enabled' => 'true']);

        for ($i = 0; $i < 11; $i++) {
            $response = $this->post('/register/confirm/accept', [
                'token' => "token{$i}",
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_registration_confirmation_resend()
    {
        Notification::fake();
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'true']);
        $user = User::factory()->make();

        $resp = $this->post('/register', $user->only('name', 'email', 'password'));
        $resp->assertRedirect('/register/confirm');
        $dbUser = User::query()->where('email', '=', $user->email)->first();

        $resp = $this->post('/login', ['email' => $user->email, 'password' => $user->password]);
        $resp->assertRedirect('/register/confirm/awaiting');

        $resp = $this->post('/register/confirm/resend');
        $resp->assertRedirect('/register/confirm');
        Notification::assertSentToTimes($dbUser, ConfirmEmailNotification::class, 2);
    }

    public function test_registration_confirmation_expired_resend()
    {
        Notification::fake();
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'true']);
        $user = User::factory()->make();

        $resp = $this->post('/register', $user->only('name', 'email', 'password'));
        $resp->assertRedirect('/register/confirm');
        $dbUser = User::query()->where('email', '=', $user->email)->first();

        $resp = $this->post('/login', ['email' => $user->email, 'password' => $user->password]);
        $resp->assertRedirect('/register/confirm/awaiting');

        $emailConfirmation = DB::table('email_confirmations')->where('user_id', '=', $dbUser->id)->first();
        $this->travel(2)->days();

        $resp = $this->post("/register/confirm/accept", [
            'token' => $emailConfirmation->token,
        ]);
        $resp->assertRedirect('/register/confirm');
        $this->assertSessionError('The confirmation token has expired, A new confirmation email has been sent.');

        Notification::assertSentToTimes($dbUser, ConfirmEmailNotification::class, 2);
    }

    public function test_registration_confirmation_awaiting_and_resend_returns_to_log_if_no_login_attempt_user_found()
    {
        $this->setSettings(['registration-enabled' => 'true', 'registration-confirmation' => 'true']);

        $this->get('/register/confirm/awaiting')->assertRedirect('/login');
        $this->assertSessionError('A user for this action could not be found.');
        $this->flushSession();

        $this->post('/register/confirm/resend')->assertRedirect('/login');
        $this->assertSessionError('A user for this action could not be found.');
    }
}
