<?php

namespace App\Http\Controllers;

use App\Exceptions\VentasUsuarioApiException;
use App\Models\CentroCosto;
use App\Services\CentroCosto\CentroCostoApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CentroCostoController extends Controller
{
    public function __construct(private CentroCostoApiService $service) {}

    public function index(): Response
    {
        $empresaId = $this->service->empresaId();

        return Inertia::render('centro-costo/index', [
            'totalGuardados' => CentroCosto::query()->where('empresa_id', $empresaId)->count(),
            'ultimaSincronizacion' => Cache::get($this->syncCacheKey($empresaId)),
        ]);
    }

    /**
     * Lista los centros de costo guardados en la base de datos.
     */
    public function consultar(): JsonResponse
    {
        $centros = CentroCosto::query()
            ->where('empresa_id', $this->service->empresaId())
            ->orderBy('id_centro_costo')
            ->get([
                'id_centro_costo', 'descripcion', 'cuenta', 'inactivo', 'id_grupo', 'id_sub_grupo',
                'id_division', 'id_sociedad', 'id_viejo', 'modificado_por', 'fecha_modificado',
            ]);

        return response()->json(['centros' => $centros, 'message' => 'Proceso completado.']);
    }

    /**
     * Descarga de la API solo los centros nuevos o modificados y los guarda en la base de datos.
     */
    public function sincronizar(): JsonResponse
    {
        try {
            $rows = $this->service->fetch();
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $empresaId = $this->service->empresaId();

        $existentes = DB::table('centros_costo')
            ->where('empresa_id', $empresaId)
            ->get(['id_centro_costo', 'fecha_modificado', 'fecha_grabado'])
            ->mapWithKeys(fn (object $row) => [
                (int) $row->id_centro_costo => $this->service->changeStamp($row->fecha_modificado, $row->fecha_grabado),
            ]);

        $now = now();
        $paraGuardar = [];
        $nuevos = 0;
        $actualizados = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['IdCentroCosto'] ?? 0);
            $stamp = $this->service->changeStamp($row['FechaModificado'] ?? null, $row['FechaGrabado'] ?? null);

            if (! $existentes->has($id)) {
                $nuevos++;
            } elseif ($existentes->get($id) !== $stamp) {
                $actualizados++;
            } else {
                continue;
            }

            $paraGuardar[] = $this->service->mapRow($row, $empresaId) + ['created_at' => $now, 'updated_at' => $now];
        }

        DB::transaction(function () use ($paraGuardar) {
            foreach (array_chunk($paraGuardar, 300) as $chunk) {
                CentroCosto::query()->upsert(
                    $chunk,
                    ['empresa_id', 'id_centro_costo'],
                    array_diff(array_keys($chunk[0]), ['empresa_id', 'id_centro_costo', 'created_at']),
                );
            }
        });

        $sinCambios = count($rows) - $nuevos - $actualizados;
        $sincronizadoEn = $now->toDateTimeString();
        Cache::forever($this->syncCacheKey($empresaId), $sincronizadoEn);

        return response()->json([
            'message' => "Sincronizacion completada. Nuevos: {$nuevos}, actualizados: {$actualizados}, sin cambios: {$sinCambios}.",
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'sin_cambios' => $sinCambios,
            'ultima_sincronizacion' => $sincronizadoEn,
            'total_guardados' => CentroCosto::query()->where('empresa_id', $empresaId)->count(),
        ]);
    }

    public function eliminar(): JsonResponse
    {
        $empresaId = $this->service->empresaId();
        $total = CentroCosto::query()->where('empresa_id', $empresaId)->delete();
        Cache::forget($this->syncCacheKey($empresaId));

        return response()->json(['message' => 'Datos eliminados correctamente', 'total' => $total]);
    }

    private function syncCacheKey(int $empresaId): string
    {
        return "centro_costo.ultima_sincronizacion.{$empresaId}";
    }
}
