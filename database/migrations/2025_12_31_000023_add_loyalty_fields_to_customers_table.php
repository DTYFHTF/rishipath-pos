<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('loyalty_tier_id')->nullable()->after('loyalty_points')->constrained()->nullOnDelete();
            $table->date('birthday')->nullable()->after('loyalty_tier_id');
            $table->date('last_birthday_bonus_at')->nullable()->after('birthday');
            $table->timestamp('loyalty_enrolled_at')->nullable()->after('last_birthday_bonus_at');
            
            $table->index('loyalty_tier_id');
            $table->index('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['loyalty_tier_id']);
            $table->dropIndex(['loyalty_tier_id']);
            $table->dropIndex(['birthday']);
            $table->dropColumn([
                'loyalty_tier_id',
                'birthday',
                'last_birthday_bonus_at',
                'loyalty_enrolled_at',
            ]);
        });
    }
};
