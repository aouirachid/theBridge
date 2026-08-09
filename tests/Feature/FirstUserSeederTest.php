<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('app.first_user', [
        'name' => 'First Operator',
        'email' => 'FIRST.OPERATOR@example.com',
        'password' => 'InitialPassphrase!2026',
    ]);
});

test('database seeder creates the first verified operator', function () {
    $this->seed();

    $user = User::query()->sole();

    expect($user)
        ->name->toBe('First Operator')
        ->email->toBe('first.operator@example.com')
        ->email_verified_at->not->toBeNull()
        ->is_sourcing_operator->toBeTrue()
        ->is_operations_operator->toBeTrue()
        ->and(Hash::check('InitialPassphrase!2026', $user->password))->toBeTrue();
});
