<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteVentasRequest;
use App\Services\Reporte\ReporteVentasService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReporteVentasController extends Controller
{
    public function __construct(private ReporteVentasService $service) {}

    public function index(): Response
    {
        return Inertia::render('reporte-ventas/index', [
            'dimensiones' => ReporteVentasService::DIMENSIONES,
        ]);
    }

    public function filtros(): JsonResponse
    {
        return response()->json($this->service->opcionesFiltros());
    }

    public function datos(ReporteVentasRequest $request): JsonResponse
    {
        $datos = $request->validated();

        return response()->json($this->service->generar(
            $datos['desde'],
            $datos['hasta'],
            $datos['agrupar'],
            collect($datos)->except(['desde', 'hasta', 'agrupar'])->all(),
        ));
    }
}
