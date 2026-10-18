<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_raza', function (Blueprint $table) {
            $table->id();
            $table->string('consorcio_id', 5)->nullable();
            $table->string('agencia_id', 25)->nullable();
            $table->string('proveedor_id', 10)->nullable();
            $table->string('proveedor_nombre')->nullable();
            $table->decimal('ventas', 12, 2)->default(0);
            $table->decimal('premios_pagados', 12, 2)->default(0);
            $table->decimal('premios', 12, 2)->default(0);
            $table->date('fecha')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_raza');
    }
};
