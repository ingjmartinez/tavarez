<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarReporteCategoriasRequest;
use App\Services\Reporte\ReporteCategoriasService;
use App\Services\Terminal\TerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TerminalController extends Controller
{
    public function __construct(private TerminalService $service, private ReporteCategoriasService $categorias) {}

    public function index(): Response
    {
        return Inertia::render('terminal/index', [
            'puedeConfigurarReportes' => Gate::allows('configurar-reportes'),
        ]);
    }

    public function categoriasReporte(): JsonResponse
    {
        return response()->json(['categorias' => $this->categorias->opciones()]);
    }

    public function guardarCategoriasReporte(GuardarReporteCategoriasRequest $request): JsonResponse
    {
        $this->categorias->guardar($request->validated('activas'));

        return response()->json(['message' => 'Categorias del reporte guardadas correctamente.']);
    }

    /**
     * Listado de terminales con los conteos de activas e inactivas.
     */
    public function resumen(): JsonResponse
    {
        $catalogo = $this->service->catalogo();

        return response()->json([
            'total' => $catalogo->count(),
            'activas' => $catalogo->where('activa', true)->count(),
            'inactivas' => $catalogo->where('activa', false)->count(),
            'terminales' => $catalogo->values(),
        ]);
    }

    /**
     * Terminales desactivadas en el catalogo de centros de costo.
     */
    public function inactivas(): JsonResponse
    {
        $terminales = $this->service->catalogo()
            ->where('activa', false)
            ->values();

        return response()->json([
            'modo' => 'inactivas',
            'desde' => null,
            'hasta' => null,
            'total' => $terminales->count(),
            'terminales' => $terminales,
            'cobertura' => null,
        ]);
    }

    /**
     * Terminales activas sin venta positiva en los ultimos 30 dias.
     */
    public function sinVentaTreintaDias(): JsonResponse
    {
        ['desde' => $desde, 'hasta' => $hasta] = $this->service->periodo();
        $ventas = $this->service->ventasPorTerminal($desde, $hasta);

        $terminales = $this->service->catalogo()
            ->filter(fn (array $terminal, string $key) => $terminal['activa'] && ! $ventas->has($key))
            ->values();

        return response()->json([
            'modo' => 'sin_venta',
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => $terminales->count(),
            'terminales' => $terminales,
            'cobertura' => $this->service->cobertura($desde, $hasta),
        ]);
    }

    /**
     * Terminales desactivadas que si tuvieron venta positiva en los ultimos 30 dias.
     */
    public function inactivasConVentaTreintaDias(): JsonResponse
    {
        ['desde' => $desde, 'hasta' => $hasta] = $this->service->periodo();
        $ventas = $this->service->ventasPorTerminal($desde, $hasta);

        $terminales = $this->service->catalogo()
            ->filter(fn (array $terminal, string $key) => ! $terminal['activa'] && $ventas->has($key))
            ->map(fn (array $terminal, string $key) => $terminal + $ventas->get($key))
            ->sortByDesc('total_venta')
            ->values();

        return response()->json([
            'modo' => 'inactivas_con_venta',
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => $terminales->count(),
            'terminales' => $terminales,
            'cobertura' => $this->service->cobertura($desde, $hasta),
        ]);
    }
}
