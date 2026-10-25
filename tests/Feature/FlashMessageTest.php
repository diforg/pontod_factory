<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('success flash is shared via Inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $response = $this->actingAs($user)
        ->withSession(['success' => 'Teste flash'])
        ->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Teste flash')
    );
});
