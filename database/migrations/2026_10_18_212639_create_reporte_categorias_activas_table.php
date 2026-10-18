<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reporte_categorias_activas', function (Blueprint $table) {
            $table->id();
            $table->string('dimension', 20);
            $table->string('valor');
            $table->timestamps();

            $table->unique(['dimension', 'valor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporte_categorias_activas');
    }
};
