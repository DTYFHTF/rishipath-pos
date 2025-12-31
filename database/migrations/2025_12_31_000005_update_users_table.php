<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20)->after('email')->nullable();
            $table->char('pin', 4)->after('password')->nullable();
            $table->foreignId('role_id')->after('pin')->nullable()->constrained()->nullOnDelete();
            $table->json('stores')->after('role_id')->nullable();
            $table->json('permissions')->after('stores')->nullable();
            $table->boolean('active')->after('permissions')->default(true);
            $table->timestamp('last_login_at')->after('active')->nullable();
            
            $table->index('organization_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'organization_id',
                'phone',
                'pin',
                'role_id',
                'stores',
                'permissions',
                'active',
                'last_login_at'
            ]);
        });
    }
};
