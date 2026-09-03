<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'legal_name',
        'country_code',
        'currency',
        'timezone',
        'locale',
        'config',
        'active',
        'price_list_public_token',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
    ];

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The token in the public price list's URL (/prices/{token}). Generated
     * lazily on first request rather than at organization creation, since
     * most organizations will never turn the feature on.
     */
    public function ensurePriceListToken(): string
    {
        if (! $this->price_list_public_token) {
            $this->forceFill(['price_list_public_token' => Str::random(40)])->save();
        }

        return $this->price_list_public_token;
    }

    /**
     * Invalidates the current public link (if any) and issues a new one - the
     * only way to revoke a link that has been shared too widely.
     */
    public function rotatePriceListToken(): string
    {
        $this->forceFill(['price_list_public_token' => Str::random(40)])->save();

        return $this->price_list_public_token;
    }
}
