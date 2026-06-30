<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetailStore extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (RetailStore $store): void {
            $store->syncLinkedCustomer();
        });

        static::deleted(function (RetailStore $store): void {
            $store->linkedCustomer()?->update(['active' => false]);
        });

        static::restored(function (RetailStore $store): void {
            $store->syncLinkedCustomer();
        });
    }

    protected $fillable = [
        'organization_id',
        'store_name',
        'contact_person',
        'contact_number',
        'address',
        'area',
        'landmark',
        'city',
        'state',
        'country',
        'pincode',
        'google_location_url',
        'latitude',
        'longitude',
        'status',
        'assigned_to',
        'created_by',
        'notes',
        'last_visited_at',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'last_visited_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bulkOrderInquiries(): HasMany
    {
        return $this->hasMany(BulkOrderInquiry::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(RetailStoreVisit::class)->orderByDesc('visit_date');
    }

    public function latestVisit(): HasOne
    {
        return $this->hasOne(RetailStoreVisit::class)->latestOfMany('visit_date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function linkedCustomer(): HasOne
    {
        return $this->hasOne(Customer::class, 'retail_store_id');
    }

    public function feedbacks(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'feedbackable')->latest();
    }

    public function unresolvedFeedbacks(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'feedbackable')
            ->whereIn('status', ['new', 'in_progress'])
            ->latest();
    }

    // ─── Accessors ───────────────────────────────

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->area,
            $this->landmark,
            $this->city,
            $this->state,
            $this->pincode,
        ]);

        return implode(', ', $parts);
    }

    public function getMapLinkAttribute(): ?string
    {
        if ($this->google_location_url) {
            return $this->google_location_url;
        }

        if ($this->latitude && $this->longitude) {
            return "https://maps.google.com/?q={$this->latitude},{$this->longitude}";
        }

        return null;
    }

    // ─── Methods ─────────────────────────────────

    public function markVisited(): void
    {
        $this->update(['last_visited_at' => now()]);
    }

    public function syncLinkedCustomer(): Customer
    {
        $customer = $this->linkedCustomer()->first();

        if (! $customer && filled($this->contact_number)) {
            $customer = Customer::query()
                ->where('organization_id', $this->organization_id)
                ->where('phone', $this->contact_number)
                ->first();
        }

        if (! $customer) {
            $customer = Customer::query()
                ->firstOrNew([
                    'organization_id' => $this->organization_id,
                    'retail_store_id' => $this->id,
                ]);
        }

        $existingNotes = trim(strip_tags((string) ($customer->notes ?? '')));
        $linkNote = 'Auto-linked retail store account.';

        $customer->organization_id = $this->organization_id;
        $customer->retail_store_id = $this->id;
        $customer->name = $this->store_name;
        $customer->phone = $this->contact_number ?: $customer->phone;
        $customer->address = $this->full_address ?: $customer->address;
        $customer->city = $this->city ?: $customer->city;
        $customer->active = $this->status !== 'inactive';
        $customer->notes = str_contains($existingNotes, $linkNote)
            ? $customer->notes
            : trim($existingNotes === '' ? $linkNote : $existingNotes."\n".$linkNote);

        if (blank($customer->customer_code)) {
            $customer->customer_code = Customer::generateNextCustomerCode();
        }

        $customer->save();

        return $customer;
    }
}
