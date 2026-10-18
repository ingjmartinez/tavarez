<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_otra_empresa', function (Blueprint $table) {
            $table->dropColumn('pagado_agencia_id');
        });

        Schema::table('pagos_otra_empresa', function (Blueprint $table) {
            $table->string('pagado_consorcio_id', 5)->nullable()->after('agencia_id');
            $table->decimal('importe', 12, 2)->default(0)->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_otra_empresa', function (Blueprint $table) {
            $table->dropColumn(['pagado_consorcio_id', 'importe']);
        });

        Schema::table('pagos_otra_empresa', function (Blueprint $table) {
            $table->string('pagado_agencia_id', 25)->nullable()->after('agencia_id');
        });
    }
};
