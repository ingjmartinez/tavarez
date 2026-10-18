<?php

namespace App\Http\Controllers;

use App\Exceptions\VentasUsuarioApiException;
use App\Http\Requests\FechaVentasUsuarioRequest;
use App\Models\PagoOtraEmpresa;
use App\Services\VentasUsuario\VentasUsuarioApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PagosOtraEmpresaController extends Controller
{
    public function __construct(private VentasUsuarioApiService $service) {}

    public function index(): Response
    {
        $token = $this->service->currentToken();

        return Inertia::render('pagos-otra-empresa/index', [
            'tokenExpiresAt' => $token?->expires_at?->toIso8601String(),
            'tokenValid' => (bool) $token?->isValid(),
        ]);
    }

    public function consultar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        try {
            $rows = $this->service->fetchPagosOtraEmpresa($request->validated('fecha'));
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['pagos' => [], 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['pagos' => $rows, 'message' => 'Proceso completado.']);
    }

    public function guardar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        $fecha = $request->validated('fecha');

        if (PagoOtraEmpresa::query()->whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => "Ya hay datos guardados en la fecha {$fecha}. No se guardo para evitar duplicar datos."], 409);
        }

        try {
            $rows = $this->service->fetchPagosOtraEmpresa($fecha);
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        if ($rows === []) {
            return response()->json(['message' => "No hay datos para guardar en la fecha: {$fecha}", 'total' => 0]);
        }

        $now = now();
        $data = array_map(
            fn (array $row) => $this->service->mapPagoOtraEmpresaRow($row, $fecha) + ['created_at' => $now, 'updated_at' => $now],
            $rows,
        );

        DB::transaction(function () use ($data) {
            foreach (array_chunk($data, 1000) as $chunk) {
                PagoOtraEmpresa::query()->insert($chunk);
            }
        });

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: '.count($data),
            'total' => count($data),
        ]);
    }

    public function eliminar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        $total = PagoOtraEmpresa::query()->whereDate('fecha', $request->validated('fecha'))->delete();

        return response()->json(['message' => 'Datos eliminados correctamente', 'total' => $total]);
    }
}
