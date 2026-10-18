<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteSubgruposRequest;
use App\Services\Reporte\ReporteCategoriasService;
use App\Services\Reporte\ReporteVentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReporteSubgruposController extends Controller
{
    public function __construct(private ReporteVentasService $service, private ReporteCategoriasService $categorias) {}

    public function index(): Response
    {
        return Inertia::render('reporte-subgrupos/index', [
            'categoriasActivas' => [
                'subgrupo' => array_keys($this->categorias->activas()['subgrupo'] ?? []),
                'sociedad' => array_keys($this->categorias->activas()['sociedad'] ?? []),
            ],
        ]);
    }

    public function datos(ReporteSubgruposRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $todasCategorias = $datos['categoria'] === '__todos__';
        $categoriasActivas = $this->categorias->activas()[$datos['modo']] ?? [];
        $categoriaValida = $categoriasActivas !== [] && ($todasCategorias || isset($categoriasActivas[$datos['categoria']]));

        if (! $categoriaValida) {
            throw ValidationException::withMessages(['categoria' => 'Seleccione una categoría marcada en Mantenimiento de terminales / Grupo.']);
        }

        return response()->json($this->service->generar(
            $datos['desde'],
            $datos['hasta'],
            ($datos['por_terminal'] ?? false) ? [$datos['modo'], 'terminal', 'tipo'] : [$datos['modo'], 'tipo'],
            ['raza' => true],
            dimensionSeleccionada: $datos['modo'],
            valorSeleccionado: $todasCategorias ? null : $datos['categoria'],
            ordenarPorAgrupacion: (bool) ($datos['por_terminal'] ?? false),
        ));
    }
}
