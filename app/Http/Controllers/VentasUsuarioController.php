<?php

namespace App\Http\Controllers;

use App\Exceptions\VentasUsuarioApiException;
use App\Http\Requests\FechaVentasUsuarioRequest;
use App\Models\VentaUsuario;
use App\Services\VentasUsuario\VentasUsuarioApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VentasUsuarioController extends Controller
{
    public function __construct(private VentasUsuarioApiService $service) {}

    public function index(): Response
    {
        $token = $this->service->currentToken();

        return Inertia::render('ventas-usuario/index', [
            'tokenExpiresAt' => $token?->expires_at?->toIso8601String(),
            'tokenValid' => (bool) $token?->isValid(),
        ]);
    }

    public function generarToken(): JsonResponse
    {
        try {
            $token = $this->service->generateToken();
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'message' => 'Token generado y guardado correctamente.',
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);
    }

    public function consultar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        try {
            $rows = $this->service->fetchVentas($request->validated('fecha'));
        } catch (VentasUsuarioApiException $e) {
            return response()->json(['ventas' => [], 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['ventas' => $rows, 'message' => 'Proceso completado.']);
    }

    public function guardar(FechaVentasUsuarioRequest $request): JsonResponse
    {
        $fecha = $request->validated('fecha');

        if (VentaUsuario::query()->whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => "Ya hay datos guardados en la fecha {$fecha}. No se guardo para evitar duplicar datos."], 409);
        }

        try {
            $rows = $this->service->fetchVentas($fecha);
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
                    fn (array $row) => $this->service->mapRow($row, $fecha) + ['created_at' => $now, 'updated_at' => $now],
                    $chunk,
                );
                VentaUsuario::query()->insert($data);
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
        $total = VentaUsuario::query()->whereDate('fecha', $request->validated('fecha'))->delete();

        return response()->json(['message' => 'Datos eliminados correctamente', 'total' => $total]);
    }
}
