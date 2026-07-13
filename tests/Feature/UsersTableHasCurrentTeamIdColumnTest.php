<?php

use Illuminate\Support\Facades\Schema;

it('adds the current team id column to the users table', function () {
    expect(Schema::hasColumn('users', 'current_team_id'))->toBeTrue();
});
