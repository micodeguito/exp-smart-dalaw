<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dormitories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('max_online_bookings_per_hour')->default(7);
            $table->unsignedSmallInteger('max_bookings_per_hour')->default(15);
            $table->string('badge_image_path')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dormitories');
    }
};
