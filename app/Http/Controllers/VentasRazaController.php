<?php

namespace App\Http\Controllers;

use App\Exceptions\VentasUsuarioApiException;
use App\Http\Requests\FechaVentasUsuarioRequest;
use App\Models\VentaRaza;
use App\Services\VentasUsuario\VentasUsuarioApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VentasRazaController extends Controller
{
    public function __construct(private VentasUsuarioApiService $service) {}

    public function index(): Response
    {
        $token = $this->service->currentToken();

        return Inertia::render('ventas-raza/index', [
            'tokenExpiresAt' => $token?->expires_at?->toIso8601String(),
            'tokenValid' => (bool) $token?->isValid(),
        ]);
    }

    public function consultar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        try {
            $rows = $this->service->fetchVentasRaza($request->validated('fecha'));
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['ventas' => [], 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['ventas' => $rows, 'message' => 'Proceso completado.']);
    }

    public function guardar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        $fecha = $request->validated('fecha');

        if (VentaRaza::query()->whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => "Ya hay datos guardados en la fecha {$fecha}. No se guardo para evitar duplicar datos."], 409);
        }

        try {
            $rows = $this->service->fetchVentasRaza($fecha);
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        if ($rows === []) {
            return response()->json(['message' => "No hay datos para guardar en la fecha: {$fecha}", 'total' => 0]);
        }

        $now = now();
        $total = count($rows);

        DB::transaction(function () use (&$rows, $total, $fecha, $now) {
            for ($offset = 0; $offset < $total; $offset += 1000) {
                $chunk = array_slice($rows, $offset, 1000);
                $data = array_map(
                    fn (array $row) => $this->service->mapVentaRazaRow($row, $fecha) + ['created_at' => $now, 'updated_at' => $now],
                    $chunk,
                );
                VentaRaza::query()->insert($data);
                unset($chunk, $data);
            }
        });
        unset($rows);

        return response()->json([
            'message' => 'Datos guardados correctamente. Total insertados: '.$total,
            'total' => $total,
        ]);
    }

    public function eliminar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        $total = VentaRaza::query()->whereDate('fecha', $request->validated('fecha'))->delete();

        return response()->json(['message' => 'Datos eliminados correctamente', 'total' => $total]);
    }
}
