<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportePeriodoRequest;
use App\Services\Reporte\ReporteCategoriasService;
use App\Services\Reporte\ReportePeriodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportePeriodoController extends Controller
{
    public function __construct(private ReportePeriodoService $service, private ReporteCategoriasService $categorias) {}

    public function index(): Response
    {
        $activas = $this->categorias->activas();

        return Inertia::render('reporte-periodo/index', [
            'categoriasActivas' => [
                'subgrupo' => array_keys($activas['subgrupo'] ?? []),
                'sociedad' => array_keys($activas['sociedad'] ?? []),
            ],
        ]);
    }

    public function datos(ReportePeriodoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $activas = $this->categorias->activas()[$datos['modo']] ?? [];
        $todas = ($datos['categoria'] ?? null) === '__todos__';
        $seleccionadas = $todas ? [] : ($datos['categorias'] ?? [$datos['categoria']]);

        if ($activas === [] || (! $todas && ! collect($seleccionadas)->every(fn (string $categoria): bool => isset($activas[$categoria])))) {
            throw ValidationException::withMessages(['categoria' => 'Seleccione una categoría marcada en Mantenimiento de terminales / Grupo.']);
        }

        return response()->json($this->service->generar(
            $datos['desde'],
            $datos['hasta'],
            $datos['modo'],
            $todas ? null : ($datos['categoria'] ?? null),
            (bool) ($datos['por_terminal'] ?? false),
            (bool) ($datos['agrupar_meses'] ?? false),
            $todas ? null : $seleccionadas,
        ));
    }
}
