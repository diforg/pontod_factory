<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('login with remember me sets recaller cookie', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $this->assertAuthenticated();
    $response->assertCookie(Auth::guard('web')->getRecallerName());
});

test('login without remember me does not set recaller cookie', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
});

test('logout invalidates recaller cookie', function () {
    $user = User::factory()->create();

    $loginResponse = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ])->assertCookie(Auth::guard('web')->getRecallerName());

    $cookieName = Auth::guard('web')->getRecallerName();
    $recallerCookie = $loginResponse->getCookie($cookieName);

    expect($recallerCookie)->not->toBeNull();

    $response = $this->withCookie($cookieName, $recallerCookie->getValue())->post('/logout');

    $this->assertGuest();
    $response->assertCookieExpired($cookieName);
});
