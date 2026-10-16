<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->state(fn ($record): bool => $record->email_verified_at !== null),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
                TextColumn::make('devices_max_last_seen_at')->label('Last seen')->dateTime()->since()->sortable()->placeholder('Never'),
                TextColumn::make('devices_count')->label('Devices')->numeric()->sortable(),
                TextColumn::make('shots_count')->label('Shots')->numeric()->sortable(),
                TextColumn::make('white_shots_count')->label('White')->numeric()->sortable(),
                TextColumn::make('black_shots_count')->label('Black')->numeric()->sortable(),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')->label('Verified')->nullable(),
                TernaryFilter::make('is_admin')->label('Admin'),
                TernaryFilter::make('google')
                    ->label('Google linked')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('google_sub'),
                        false: fn (Builder $query) => $query->whereNull('google_sub'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                UserActions::toggleAdmin(),
                UserActions::delete(),
            ]);
    }
}
