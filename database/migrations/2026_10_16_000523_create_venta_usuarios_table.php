<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('consorcio_id', 5)->nullable();
            $table->string('agencia_id', 25)->nullable();
            $table->string('cedula', 50)->nullable();
            $table->string('tipo', 45)->nullable();
            $table->string('producto_id', 50)->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->date('fecha')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_usuarios');
    }
};
