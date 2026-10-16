<?php

namespace App\Filament\Resources\Users;

use App\Actions\DeleteUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

/**
 * Actions shared by the users table and the view page. Admins can't demote or delete themselves.
 */
class UserActions
{
    public static function toggleAdmin(): Action
    {
        return Action::make('toggleAdmin')
            ->label(fn (User $record): string => $record->is_admin ? 'Remove admin' : 'Make admin')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->requiresConfirmation()
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->action(fn (User $record) => $record->forceFill(['is_admin' => ! $record->is_admin])->save());
    }

    /**
     * Deletes immediately with the same cascade as DELETE /me.
     */
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('The account and all of its data are deleted immediately. This cannot be undone.')
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->using(fn (User $record): bool => app(DeleteUser::class)->handle($record));
    }
}
