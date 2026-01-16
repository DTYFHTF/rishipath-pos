<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_roles') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('create_roles') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('edit_roles') ?? false;
    }

    public static function canDelete($record): bool
    {
        if ($record->is_system_role || $record->users()->count() > 0) {
            return false;
        }

        return auth()->user()?->hasPermission('delete_roles') ?? false;
    }

    /**
     * All available permissions grouped by category
     */
    public static function getPermissionGroups(): array
    {
        return [
            'Dashboard & Analytics' => [
                'view_dashboard' => 'View Dashboard',
                'view_pos_stats' => 'View POS Statistics',
                'view_inventory_overview' => 'View Inventory Overview',
                'view_low_stock_alerts' => 'View Low Stock Alerts',
            ],
            'POS Operations' => [
                'access_pos_billing' => 'Access POS Billing',
                'create_sales' => 'Create Sales',
                'void_sales' => 'Void Sales',
                'apply_discounts' => 'Apply Discounts',
                'process_refunds' => 'Process Refunds',
                'view_sales' => 'View All Sales',
                'view_own_sales_only' => 'View Only Own Sales',
            ],
            'Product Management' => [
                'view_products' => 'View Products',
                'create_products' => 'Create Products',
                'edit_products' => 'Edit Products',
                'delete_products' => 'Delete Products',
                'view_product_variants' => 'View Product Variants',
                'create_product_variants' => 'Create Product Variants',
                'edit_product_variants' => 'Edit Product Variants',
                'delete_product_variants' => 'Delete Product Variants',
                'view_categories' => 'View Categories',
                'create_categories' => 'Create Categories',
                'edit_categories' => 'Edit Categories',
                'delete_categories' => 'Delete Categories',
            ],
            'Inventory Management' => [
                'view_inventory' => 'View Inventory',
                'view_stock_levels' => 'View Stock Levels',
                'view_product_batches' => 'View Product Batches',
                'create_product_batches' => 'Create Product Batches',
                'edit_product_batches' => 'Edit Product Batches',
                'delete_product_batches' => 'Delete Product Batches',
                'adjust_stock' => 'Adjust Stock',
                'view_stock_adjustments' => 'View Stock Adjustments',
                'view_inventory_movements' => 'View Inventory Movements',
                'view_suppliers' => 'View Suppliers',
                'create_suppliers' => 'Create Suppliers',
                'edit_suppliers' => 'Edit Suppliers',
                'delete_suppliers' => 'Delete Suppliers',
            ],
            'Customer Management' => [
                'view_customers' => 'View Customers',
                'create_customers' => 'Create Customers',
                'edit_customers' => 'Edit Customers',
                'delete_customers' => 'Delete Customers',
                'view_customer_purchase_history' => 'View Customer Purchase History',
            ],
            'Reporting' => [
                'view_sales_reports' => 'View Sales Reports',
                'view_inventory_reports' => 'View Inventory Reports',
                'view_profit_reports' => 'View Profit Reports',
                'export_reports' => 'Export Reports',
                'email_reports' => 'Email Reports',
            ],
            'User Management' => [
                'view_users' => 'View Users',
                'create_users' => 'Create Users',
                'edit_users' => 'Edit Users',
                'delete_users' => 'Delete Users',
                'manage_user_permissions' => 'Manage User Permissions',
            ],
            'Role Management' => [
                'view_roles' => 'View Roles',
                'create_roles' => 'Create Roles',
                'edit_roles' => 'Edit Roles',
                'delete_roles' => 'Delete Roles',
            ],
            'Settings & Configuration' => [
                'view_settings' => 'View Settings',
                'edit_settings' => 'Edit Settings',
                'view_organizations' => 'View Organizations',
                'edit_organizations' => 'Edit Organizations',
                'view_stores' => 'View Stores',
                'create_stores' => 'Create Stores',
                'edit_stores' => 'Edit Stores',
                'delete_stores' => 'Delete Stores',
                'view_terminals' => 'View Terminals',
                'create_terminals' => 'Create Terminals',
                'edit_terminals' => 'Edit Terminals',
                'delete_terminals' => 'Delete Terminals',
            ],
            'System Administration' => [
                'access_system_logs' => 'Access System Logs',
                'manage_backups' => 'Manage Backups',
                'manage_integrations' => 'Manage Integrations',
            ],
        ];
    }

    public static function form(Form $form): Form
    {
        $permissionGroups = static::getPermissionGroups();

        return $form
            ->schema([
                Forms\Components\Section::make('Role Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Role Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Store Manager')
                            ->helperText('Descriptive name for this role'),

                        Forms\Components\TextInput::make('slug')
                            ->label('Role Slug')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., store-manager')
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique identifier (lowercase, no spaces)')
                            ->rules(['alpha_dash']),

                        Forms\Components\Toggle::make('is_system_role')
                            ->label('System Role')
                            ->helperText('System roles cannot be deleted')
                            ->disabled(fn (?Role $record) => $record?->is_system_role === true)
                            ->default(false),

                        Forms\Components\Placeholder::make('users_count')
                            ->label('Users with this role')
                            ->content(fn (?Role $record) => $record ? $record->users()->count() : 0)
                            ->visible(fn (?Role $record) => $record !== null),
                    ])->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->description('Select the permissions this role should have')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('')
                            ->options(function () use ($permissionGroups) {
                                $options = [];
                                foreach ($permissionGroups as $group => $permissions) {
                                    foreach ($permissions as $key => $label) {
                                        $options[$key] = $label;
                                    }
                                }

                                return $options;
                            })
                            ->descriptions(function () use ($permissionGroups) {
                                $descriptions = [];
                                $currentGroup = '';
                                foreach ($permissionGroups as $group => $permissions) {
                                    foreach ($permissions as $key => $label) {
                                        if ($currentGroup !== $group) {
                                            $descriptions[$key] = "━━ {$group} ━━";
                                            $currentGroup = $group;
                                        }
                                    }
                                }

                                return $descriptions;
                            })
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_system_role')
                    ->label('System Role')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user-group')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'gray',
                        $state < 5 => 'success',
                        $state < 10 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('permissions')
                    ->label('Permissions')
                    ->formatStateUsing(fn ($state) => is_string($state) ? count(json_decode($state, true) ?? []) : count($state ?? []))
                    ->badge()
                    ->color('info')
                    ->suffix(' permissions'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_system_role')
                    ->label('System Roles')
                    ->placeholder('All Roles')
                    ->trueLabel('System Roles Only')
                    ->falseLabel('Custom Roles Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (Role $record) => $record->is_system_role || $record->users()->count() > 0)
                    ->tooltip(fn (Role $record) => match (true) {
                        $record->is_system_role => 'System roles cannot be deleted',
                        $record->users()->count() > 0 => 'Cannot delete role with assigned users',
                        default => 'Delete role',
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->disabled(fn ($records) => $records?->contains(fn ($record) => $record->is_system_role) ?? false),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
