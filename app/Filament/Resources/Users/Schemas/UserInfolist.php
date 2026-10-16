<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email'),
                        TextEntry::make('name'),
                        IconEntry::make('email_verified')
                            ->label('Verified')
                            ->boolean()
                            ->state(fn ($record): bool => $record->email_verified_at !== null),
                        IconEntry::make('google_linked')
                            ->label('Google linked')
                            ->boolean()
                            ->state(fn ($record): bool => $record->google_sub !== null),
                        IconEntry::make('is_admin')->label('Admin')->boolean(),
                        TextEntry::make('created_at')->label('Joined')->dateTime(),
                        TextEntry::make('devices_max_last_seen_at')->label('Last seen')->dateTime()->placeholder('Never'),
                    ]),
                Section::make('Activity')
                    ->description('Counts only. Shots on the heart now (not deleted or undone).')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('devices_count')->label('Devices')->numeric(),
                        TextEntry::make('shots_count')->label('Shots')->numeric(),
                        TextEntry::make('white_shots_count')->label('White')->numeric(),
                        TextEntry::make('black_shots_count')->label('Black')->numeric(),
                    ]),
            ]);
    }
}
