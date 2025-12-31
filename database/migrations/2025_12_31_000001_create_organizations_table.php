<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->string('locale', 5)->default('en');
            $table->json('config')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('country_code');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
