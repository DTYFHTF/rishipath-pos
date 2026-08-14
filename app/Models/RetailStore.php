<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class RetailStore extends Model
{
    use HasFactory, SoftDeletes;

    /** Store fields the linked Customer record mirrors. */
    private const CUSTOMER_MIRRORED = [
        'organization_id', 'store_name', 'contact_number', 'address',
        'area', 'landmark', 'city', 'state', 'pincode', 'status',
    ];

    protected static function booted(): void
    {
        static::saved(function (RetailStore $store): void {
            if (! $store->wasRecentlyCreated && ! $store->wasChanged(self::CUSTOMER_MIRRORED)) {
                return;
            }

            // The customer mirror is a convenience, not part of the store
            // record. A phone clash or any other write failure here must
            // never take down the save that triggered it — recording a
            // visit used to 500 for every store with two contact numbers.
            try {
                $store->syncLinkedCustomer();
            } catch (\Throwable $e) {
                Log::warning('Retail store customer sync failed', [
                    'retail_store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
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

    public function visits(): HasMany
    {
        return $this->hasMany(RetailStoreVisit::class)->orderByDesc('visit_date');
    }

    public function latestVisit(): HasOne
    {
        return $this->hasOne(RetailStoreVisit::class)->latestOfMany('visit_date');
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
        // Only a timestamp changes, and nothing the linked customer mirrors,
        // so skip the save hooks rather than re-running the customer sync.
        $this->forceFill(['last_visited_at' => now()])->saveQuietly();
    }

    /**
     * The single number that fits `customers.phone` (unique, varchar 20).
     *
     * Field teams routinely record several numbers in one field
     * ("9847378934, 9847967263"), which overflows the column. The customer
     * account keeps the first one; the store row keeps the full list.
     */
    public function customerPhone(): ?string
    {
        $raw = trim((string) $this->contact_number);

        if ($raw === '') {
            return null;
        }

        $first = trim(preg_split('/[,;\/]|\s{2,}/', $raw)[0] ?? $raw);

        return mb_substr($first, 0, 20) ?: null;
    }

    public function syncLinkedCustomer(): Customer
    {
        $phone = $this->customerPhone();

        $customer = $this->linkedCustomer()->first();

        if (! $customer && $phone) {
            // Adopt a phone match only when it is not already another
            // store's account — shops share owner and landline numbers,
            // and re-pointing one would silently steal its ledger.
            $customer = Customer::query()
                ->where('organization_id', $this->organization_id)
                ->where('phone', $phone)
                ->where(fn ($q) => $q->whereNull('retail_store_id')->orWhere('retail_store_id', $this->id))
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

        // `customers.phone` is globally unique — leave it alone rather than
        // colliding when another account already holds this number.
        if ($phone && ! Customer::query()
            ->where('phone', $phone)
            ->when($customer->exists, fn ($q) => $q->whereKeyNot($customer->getKey()))
            ->exists()
        ) {
            $customer->phone = $phone;
        }

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
