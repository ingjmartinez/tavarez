<?php

namespace App\Services\Reporte;

use App\Models\CentroCosto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteCategoriasService
{
    public const DIMENSIONES = [
        'grupo' => 'id_grupo',
        'subgrupo' => 'id_sub_grupo',
        'division' => 'id_division',
        'sociedad' => 'id_sociedad',
    ];

    /**
     * @return array<string, array<string, bool>>
     */
    public function activas(): array
    {
        $activas = [];

        foreach (DB::table('reporte_categorias_activas')->get(['dimension', 'valor']) as $categoria) {
            $activas[$categoria->dimension][$categoria->valor] = true;
        }

        return $activas;
    }

    /**
     * @param  array<string, array<string, bool>>  $activas
     */
    public function centroVisible(CentroCosto $centro, array $activas): bool
    {
        if ($activas === []) {
            return true;
        }

        foreach (self::DIMENSIONES as $dimension => $campo) {
            $valor = $centro->{$campo};

            if ($valor !== null && isset($activas[$dimension][$valor])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, array{valor: string, activo: bool}>>
     */
    public function opciones(): array
    {
        $activas = $this->activas();
        $opciones = [];

        foreach (self::DIMENSIONES as $dimension => $campo) {
            $valores = CentroCosto::query()->whereNotNull($campo)->distinct()->orderBy($campo)->pluck($campo);
            $opciones[$dimension] = $valores
                ->filter(fn (string $valor): bool => trim($valor) !== '')
                ->map(fn (string $valor): array => [
                    'valor' => $valor,
                    'activo' => isset($activas[$dimension][$valor]),
                ])->values()->all();
        }

        return $opciones;
    }

    /**
     * @param  array<int, array{dimension: string, valor: string}>  $activas
     */
    public function guardar(array $activas): void
    {
        $permitidas = [];

        foreach ($this->opciones() as $dimension => $opciones) {
            foreach ($opciones as $opcion) {
                $permitidas[$dimension][$opcion['valor']] = true;
            }
        }

        $recibidas = [];

        foreach ($activas as $categoria) {
            if (! isset($permitidas[$categoria['dimension']][$categoria['valor']])) {
                throw ValidationException::withMessages(['activas' => 'La seleccion contiene una categoria que no existe en el catalogo.']);
            }

            if (isset($recibidas[$categoria['dimension']][$categoria['valor']])) {
                throw ValidationException::withMessages(['activas' => 'La seleccion contiene categorias repetidas.']);
            }

            $recibidas[$categoria['dimension']][$categoria['valor']] = true;
        }

        DB::transaction(function () use ($activas): void {
            DB::table('reporte_categorias_activas')->delete();

            if ($activas !== []) {
                $ahora = now();
                DB::table('reporte_categorias_activas')->insert(array_map(fn (array $categoria): array => $categoria + [
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ], $activas));
            }
        });
    }
}
