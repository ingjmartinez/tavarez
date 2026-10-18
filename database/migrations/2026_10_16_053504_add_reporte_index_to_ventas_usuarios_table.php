<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_usuarios', function (Blueprint $table) {
            $table->index(['fecha', 'agencia_id', 'tipo', 'monto'], 'ventas_usuarios_reporte_index');
        });
    }

    public function down(): void
    {
        Schema::table('ventas_usuarios', function (Blueprint $table) {
            $table->dropIndex('ventas_usuarios_reporte_index');
        });
    }
};
