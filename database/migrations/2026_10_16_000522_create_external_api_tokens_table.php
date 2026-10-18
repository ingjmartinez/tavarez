<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('token');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_tokens');
    }
};
