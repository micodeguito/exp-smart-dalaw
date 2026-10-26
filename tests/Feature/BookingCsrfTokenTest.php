<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking form can read the csrf token for its active session', function () {
    $token = str_repeat('a', 40);

    $this->withSession(['_token' => $token])
        ->getJson(route('csrf-token'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['token' => $token]);
});
