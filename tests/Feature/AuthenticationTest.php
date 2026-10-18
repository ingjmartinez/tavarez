<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Inertia\Testing\AssertableInertia as Assert;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth-pages/signin/basic')
            ->where('canResetPassword', true));
});

test('basic sign-in template is available to guests', function () {
    $response = $this->get('/auth/signin-basic');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth-pages/signin/basic')
            ->where('canResetPassword', true));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(RouteServiceProvider::HOME);

    $this->get(RouteServiceProvider::HOME)->assertOk();
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});
