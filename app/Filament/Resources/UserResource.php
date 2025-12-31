<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_users') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('create_users') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('edit_users') ?? false;
    }

    public static function canDelete($record): bool
    {
        // Prevent deleting own account
        if ($record->id === auth()->id()) {
            return false;
        }
        return auth()->user()?->hasPermission('delete_users') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Full name'),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('email@example.com'),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+977 1234567890'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->helperText('Minimum 8 characters')
                            ->placeholder('Enter password'),

                        Forms\Components\TextInput::make('pin')
                            ->numeric()
                            ->maxLength(6)
                            ->minLength(4)
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Optional 4-6 digit PIN for quick login')
                            ->placeholder('1234'),

                        Forms\Components\Toggle::make('active')
                            ->label('Active User')
                            ->helperText('Inactive users cannot login')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Role & Permissions')
                    ->schema([
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->relationship('role', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('User will inherit all permissions from this role')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $role = Role::find($state);
                                    // Show role permissions as a hint
                                }
                            }),

                        Forms\Components\Select::make('stores')
                            ->label('Assigned Stores')
                            ->relationship('organization.stores', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty for access to all stores')
                            ->placeholder('Select stores'),

                        Forms\Components\Placeholder::make('role_permissions')
                            ->label('Role Permissions')
                            ->content(function ($get) {
                                $roleId = $get('role_id');
                                if (!$roleId) {
                                    return 'Select a role to see permissions';
                                }
                                
                                $role = Role::find($roleId);
                                if (!$role) {
                                    return 'Role not found';
                                }
                                
                                $count = count($role->permissions ?? []);
                                return "{$role->name} has {$count} permissions";
                            }),
                    ])->columns(1),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Placeholder::make('last_login_at')
                            ->label('Last Login')
                            ->content(fn (?User $record) => $record?->last_login_at?->diffForHumans() ?? 'Never')
                            ->visible(fn (?User $record) => $record !== null),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn (?User $record) => $record?->created_at?->format('M d, Y H:i') ?? '-')
                            ->visible(fn (?User $record) => $record !== null),
                    ])->columns(2)->visible(fn (string $context) => $context === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Super Administrator' => 'danger',
                        'Store Manager' => 'warning',
                        'Cashier' => 'success',
                        'Inventory Clerk' => 'info',
                        'Accountant' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('role', 'name')
                    ->label('Role')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All Users')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (User $record) => $record->id === auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->disabled(fn ($records) => $records?->contains(fn ($record) => $record->id === auth()->id()) ?? false),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('active', true)->count();
    }
}
