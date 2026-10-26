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
        Schema::table('procesar_todo_configuraciones', function (Blueprint $table) {
            $table->string('correo_secundario')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesar_todo_configuraciones', function (Blueprint $table) {
            $table->dropColumn('correo_secundario');
        });
    }
};
