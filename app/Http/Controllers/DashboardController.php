<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardVentasRequest;
use App\Services\Reporte\DashboardVentasService;
use App\Services\Reporte\ReporteCategoriasService;
use App\Services\Reporte\ReporteVentasService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(ReporteCategoriasService $categorias): Response
    {
        return Inertia::render('dashboard/index', [
            'subgruposSeleccionados' => array_keys($categorias->activas()['subgrupo'] ?? []),
            'clasificacionUmbrales' => ReporteVentasService::CLASIFICACION_UMBRALES,
        ]);
    }

    public function datos(DashboardVentasRequest $request, DashboardVentasService $service): JsonResponse
    {
        $fechas = $request->validated();

        return response()->json($service->generar($fechas['desde'], $fechas['hasta']));
    }
}
