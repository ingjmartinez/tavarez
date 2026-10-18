<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('id_centro_costo');
            $table->string('company')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('cuenta')->nullable();
            $table->boolean('inactivo')->default(false);
            $table->string('id_grupo')->nullable();
            $table->string('id_sub_grupo')->nullable();
            $table->string('id_division')->nullable();
            $table->string('id_sociedad')->nullable();
            $table->string('id_viejo', 50)->nullable()->index();
            $table->string('id_centro_costo_resumir_en')->nullable();
            $table->boolean('com_recarga')->default(false);
            $table->boolean('gasto_vta_tradicional')->default(false);
            $table->boolean('varios_locales')->default(false);
            $table->boolean('aplica_para_ponderar')->default(false);
            $table->decimal('valor_ponderar', 12, 2)->default(0);
            $table->string('creado_por', 50)->nullable();
            $table->dateTime('fecha_grabado')->nullable();
            $table->string('modificado_por', 50)->nullable();
            $table->dateTime('fecha_modificado')->nullable();
            $table->json('atributos')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'id_centro_costo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
    }
};
