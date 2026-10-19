<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('ventas_usuarios', function (Blueprint $table) {
                $table->string('terminal_key', 25)->storedAs("COALESCE(NULLIF(LTRIM(TRIM(agencia_id), '0'), ''), '0')");
                $table->index(['terminal_key', 'fecha', 'tipo', 'monto'], 'ventas_usuarios_terminal_reporte_index');
                $table->index(['tipo', 'fecha'], 'ventas_usuarios_tipo_fecha_index');
            });

            Schema::table('ventas_raza', function (Blueprint $table) {
                $table->string('terminal_key', 25)->storedAs("COALESCE(NULLIF(LTRIM(TRIM(agencia_id), '0'), ''), '0')");
                $table->index(['terminal_key', 'fecha', 'ventas'], 'ventas_raza_terminal_reporte_index');
            });

            return;
        }

        $usuariosChanges = [];

        if (! Schema::hasColumn('ventas_usuarios', 'terminal_key')) {
            $usuariosChanges[] = "ADD COLUMN terminal_key VARCHAR(25) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0')) STORED";
        }
        if (! Schema::hasIndex('ventas_usuarios', 'ventas_usuarios_terminal_reporte_index')) {
            $usuariosChanges[] = 'ADD INDEX ventas_usuarios_terminal_reporte_index (terminal_key, fecha, tipo, monto)';
        }
        if (! Schema::hasIndex('ventas_usuarios', 'ventas_usuarios_tipo_fecha_index')) {
            $usuariosChanges[] = 'ADD INDEX ventas_usuarios_tipo_fecha_index (tipo, fecha)';
        }

        if ($usuariosChanges !== []) {
            DB::statement('ALTER TABLE ventas_usuarios '.implode(', ', $usuariosChanges).', ALGORITHM=COPY');
        }

        $razaChanges = [];

        if (! Schema::hasColumn('ventas_raza', 'terminal_key')) {
            $razaChanges[] = "ADD COLUMN terminal_key VARCHAR(25) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0')) STORED";
        }
        if (! Schema::hasIndex('ventas_raza', 'ventas_raza_terminal_reporte_index')) {
            $razaChanges[] = 'ADD INDEX ventas_raza_terminal_reporte_index (terminal_key, fecha, ventas)';
        }

        if ($razaChanges !== []) {
            DB::statement('ALTER TABLE ventas_raza '.implode(', ', $razaChanges).', ALGORITHM=COPY');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('ventas_raza', function (Blueprint $table) {
                $table->dropIndex('ventas_raza_terminal_reporte_index');
                $table->dropColumn('terminal_key');
            });

            Schema::table('ventas_usuarios', function (Blueprint $table) {
                $table->dropIndex('ventas_usuarios_terminal_reporte_index');
                $table->dropIndex('ventas_usuarios_tipo_fecha_index');
                $table->dropColumn('terminal_key');
            });

            return;
        }

        $razaChanges = [];
        if (Schema::hasIndex('ventas_raza', 'ventas_raza_terminal_reporte_index')) {
            $razaChanges[] = 'DROP INDEX ventas_raza_terminal_reporte_index';
        }
        if (Schema::hasColumn('ventas_raza', 'terminal_key')) {
            $razaChanges[] = 'DROP COLUMN terminal_key';
        }
        if ($razaChanges !== []) {
            DB::statement('ALTER TABLE ventas_raza '.implode(', ', $razaChanges).', ALGORITHM=COPY');
        }

        $usuariosChanges = [];
        if (Schema::hasIndex('ventas_usuarios', 'ventas_usuarios_terminal_reporte_index')) {
            $usuariosChanges[] = 'DROP INDEX ventas_usuarios_terminal_reporte_index';
        }
        if (Schema::hasIndex('ventas_usuarios', 'ventas_usuarios_tipo_fecha_index')) {
            $usuariosChanges[] = 'DROP INDEX ventas_usuarios_tipo_fecha_index';
        }
        if (Schema::hasColumn('ventas_usuarios', 'terminal_key')) {
            $usuariosChanges[] = 'DROP COLUMN terminal_key';
        }
        if ($usuariosChanges !== []) {
            DB::statement('ALTER TABLE ventas_usuarios '.implode(', ', $usuariosChanges).', ALGORITHM=COPY');
        }
    }
};
