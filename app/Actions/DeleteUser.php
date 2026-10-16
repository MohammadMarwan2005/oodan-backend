<?php

namespace App\Actions;

use App\Models\User;

/**
 * Deletes an account immediately. Every table that belongs to the user cascades on delete.
 */
class DeleteUser
{
    public function handle(User $user): bool
    {
        return (bool) $user->delete();
    }
}
