<?php

namespace App\Services\Reporte;

use App\Models\VentaRaza;
use App\Models\VentaUsuario;
use App\Services\Terminal\TerminalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ventas por periodo. Las ventas (ventas_usuarios) se relacionan con el catalogo de
 * terminales (centros de costo) por la terminal normalizada, igual que en la vista Terminal.
 * Las ventas de terminales que no estan en el catalogo se agrupan como "Sin catalogo".
 */
class ReporteVentasService
{
    public const MAX_FILAS = 5000;

    public const SIN_CATALOGO = 'Sin catalogo';

    public const ETIQUETA_RAZA = 'Raza';

    public const ETIQUETA_USUARIOS = 'Ventas usuario';

    public const TIPOS_PRODUCTO = ['No Tradicional', 'Recarga', 'Tradicional'];

    public const CLASIFICACION_UMBRALES = ['A' => 12500, 'B' => 10000, 'C' => 6500];

    /**
     * @var array<string, string>
     */
    public const DIMENSIONES = [
        'terminal' => 'Terminal',
        'grupo' => 'Grupo',
        'subgrupo' => 'Subgrupo',
        'division' => 'Division',
        'sociedad' => 'Sociedad',
        'estatus' => 'Estatus',
        'tipo' => 'Tipo',
        'fecha' => 'Fecha',
    ];

    public function __construct(private TerminalService $terminales, private ReporteCategoriasService $categorias) {}

    /**
     * Valores disponibles para los filtros, tomados del catalogo de terminales y de las ventas.
     *
     * @return array<string, array<int, string>>
     */
    public function opcionesFiltros(): array
    {
        $catalogo = $this->terminales->catalogo(paraReporte: true);
        $distintos = fn (string $campo): array => $catalogo->pluck($campo)->filter()->unique()->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->values()->all();

        return [
            'grupos' => $distintos('grupo'),
            'subgrupos' => $distintos('subgrupo'),
            'divisiones' => $distintos('division'),
            'sociedades' => $distintos('sociedad'),
            'tipos' => VentaUsuario::query()->whereIn('tipo', self::TIPOS_PRODUCTO)->distinct()->orderBy('tipo')->pluck('tipo')->all(),
        ];
    }

    /**
     * @param  array<int, string>  $agrupar
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(string $desde, string $hasta, array $agrupar, array $filtros, ?string $dimensionSeleccionada = null, ?string $valorSeleccionado = null, bool $ordenarPorAgrupacion = false, ?array $valoresSeleccionados = null): array
    {
        $catalogo = $this->terminales->catalogo(paraReporte: true, dimensionSeleccionada: $dimensionSeleccionada, valorSeleccionado: $valorSeleccionado, valoresSeleccionados: $valoresSeleccionados);
        $seleccionActiva = $dimensionSeleccionada !== null || $this->categorias->activas() !== [];
        $porTipo = in_array('tipo', $agrupar, true);
        $incluirRaza = ! empty($filtros['raza']);
        $pivotear = $porTipo || $incluirRaza;
        $terminalesSeleccionadas = $seleccionActiva || $this->filtraCatalogo($filtros)
            ? $this->terminalesFiltradas($catalogo, $filtros)
            : null;

        $query = VentaUsuario::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('tipo', self::TIPOS_PRODUCTO)
            ->whereNotNull('agencia_id')
            ->selectRaw($this->terminalKeyExpression().' AS terminal_key')
            ->selectRaw('SUM(monto) AS total_venta')
            ->selectRaw('COUNT(*) AS registros')
            ->groupBy('terminal_key');

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if ($porTipo) {
            $query->addSelect('tipo')->groupBy('tipo');
        }

        $query->addSelect('fecha')->groupBy('fecha');

        if ($terminalesSeleccionadas !== null) {
            $query->whereIn(DB::raw($this->terminalKeyExpression()), $terminalesSeleccionadas);
        }

        $dimensiones = array_values(array_diff($agrupar, ['tipo']));

        if ($dimensiones === []) {
            $dimensiones = ['resumen'];
        }

        $acumulado = [];
        $totalesPorTipo = [];
        $terminalesTotales = [];
        $diasTotales = [];
        $diasPorTipo = [];
        $totalVenta = 0.0;
        $totalRegistros = 0;

        $acumular = function (iterable $rows, callable $etiquetaTipo) use ($catalogo, $seleccionActiva, $filtros, $dimensiones, $pivotear, &$acumulado, &$totalesPorTipo, &$terminalesTotales, &$diasTotales, &$diasPorTipo, &$totalVenta, &$totalRegistros): void {
            foreach ($rows as $row) {
                $key = (string) $row->terminal_key;

                if ($key === '0') {
                    continue;
                }

                $info = $catalogo->get($key);

                if ($seleccionActiva && $info === null) {
                    continue;
                }

                if (! $this->pasaFiltros($key, $info, $filtros)) {
                    continue;
                }

                $valores = [];
                foreach ($dimensiones as $dimension) {
                    $valores[$dimension] = $dimension === 'resumen'
                        ? 'Todas las ventas'
                        : $this->valorDimension($dimension, $key, $info, $row);
                }

                $clave = implode('|', $valores);
                $acumulado[$clave] ??= [
                    'valores' => $valores,
                    'nombre' => $info['nombre'] ?? null,
                    'terminales' => [],
                    'registros' => 0,
                    'total_venta' => 0.0,
                    'tipos' => [],
                    'dias' => [],
                    'dias_por_tipo' => [],
                ];
                $fecha = substr((string) $row->fecha, 0, 10);
                $acumulado[$clave]['dias'][$fecha] = true;
                $diasTotales[$fecha] = true;
                $acumulado[$clave]['terminales'][$key] = true;
                $acumulado[$clave]['registros'] += (int) $row->registros;
                $acumulado[$clave]['total_venta'] += (float) $row->total_venta;

                if ($pivotear) {
                    $tipo = $etiquetaTipo($row);
                    $acumulado[$clave]['tipos'][$tipo] = ($acumulado[$clave]['tipos'][$tipo] ?? 0.0) + (float) $row->total_venta;
                    $totalesPorTipo[$tipo] = ($totalesPorTipo[$tipo] ?? 0.0) + (float) $row->total_venta;
                    $acumulado[$clave]['dias_por_tipo'][$tipo][$fecha] = true;
                    $diasPorTipo[$tipo][$fecha] = true;
                }

                $terminalesTotales[$key] = true;
                $totalVenta += (float) $row->total_venta;
                $totalRegistros += (int) $row->registros;
            }
        };

        $acumular($query->toBase()->cursor(), fn (object $row): string => $porTipo ? (string) ($row->tipo ?? 'Sin tipo') : self::ETIQUETA_USUARIOS);

        if ($incluirRaza) {
            $queryRaza = $this->consultaRaza($desde, $hasta);

            if ($terminalesSeleccionadas !== null) {
                $queryRaza->whereIn(DB::raw($this->terminalKeyExpression()), $terminalesSeleccionadas);
            }

            $acumular($queryRaza->toBase()->cursor(), fn (): string => self::ETIQUETA_RAZA);
        }

        $filas = collect($acumulado)
            ->map(fn (array $fila): array => $fila['valores'] + [
                'nombre' => in_array('terminal', $agrupar, true) ? $fila['nombre'] : null,
                'terminales' => count($fila['terminales']),
                'registros' => $fila['registros'],
                'total_venta' => round($fila['total_venta'], 2),
                'tipos' => array_map(fn (float $monto): float => round($monto, 2), $fila['tipos']),
                'promedio' => round($fila['total_venta'] / count($fila['dias']), 2),
                'clasificacion' => $this->clasificacion($fila['total_venta'] / count($fila['dias'])),
                'promedios_por_tipo' => collect($fila['tipos'])->map(fn (float $monto, string $tipo): float => round($monto / count($fila['dias_por_tipo'][$tipo]), 2))->all(),
            ]);

        $filas = ($ordenarPorAgrupacion
            ? $filas->sort(function (array $primera, array $segunda) use ($dimensionSeleccionada): int {
                $grupo = strnatcasecmp((string) ($primera[$dimensionSeleccionada] ?? ''), (string) ($segunda[$dimensionSeleccionada] ?? ''));

                return $grupo !== 0 ? $grupo : strnatcasecmp((string) ($primera['terminal'] ?? ''), (string) ($segunda['terminal'] ?? ''));
            })
            : $filas->sortByDesc('total_venta'))->values();

        $tipos = array_values(array_filter(
            array_keys($totalesPorTipo),
            fn (string $tipo): bool => $filas->contains(fn (array $fila): bool => ($fila['tipos'][$tipo] ?? 0.0) != 0.0),
        ));
        usort($tipos, 'strnatcasecmp');

        if (in_array(self::ETIQUETA_RAZA, $tipos, true)) {
            $tipos = array_merge(array_diff($tipos, [self::ETIQUETA_RAZA]), [self::ETIQUETA_RAZA]);
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'agrupar' => $dimensiones,
            'tipos' => $tipos,
            'total_filas' => $filas->count(),
            'truncado' => $filas->count() > self::MAX_FILAS,
            'filas' => $filas->take(self::MAX_FILAS)->values(),
            'totales' => [
                'terminales' => count($terminalesTotales),
                'registros' => $totalRegistros,
                'total_venta' => round($totalVenta, 2),
                'por_tipo' => array_map(fn (float $monto): float => round($monto, 2), $totalesPorTipo),
                'promedio' => $diasTotales === [] ? 0.0 : round($totalVenta / count($diasTotales), 2),
                'promedios_por_tipo' => collect($totalesPorTipo)->map(fn (float $monto, string $tipo): float => round($monto / count($diasPorTipo[$tipo]), 2))->all(),
            ],
            'cobertura' => $this->cobertura($desde, $hasta),
            'cobertura_raza' => $incluirRaza ? $this->coberturaRaza($desde, $hasta) : null,
        ];
    }

    /**
     * Solo el campo `ventas` de ventas raza; premios y premios pagados no se incluyen.
     */
    private function terminalKeyExpression(): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "COALESCE(NULLIF(LTRIM(TRIM(agencia_id), '0'), ''), '0')";
        }

        return "COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0')";
    }

    private function clasificacion(float $promedio): string
    {
        foreach (self::CLASIFICACION_UMBRALES as $letra => $minimo) {
            if ($promedio >= $minimo) {
                return $letra;
            }
        }

        return 'D';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function filtraCatalogo(array $filtros): bool
    {
        foreach (['grupo', 'subgrupo', 'division', 'sociedad', 'buscar'] as $campo) {
            if (! empty($filtros[$campo])) {
                return true;
            }
        }

        return ($filtros['estatus'] ?? 'todos') !== 'todos';
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $catalogo
     * @param  array<string, mixed>  $filtros
     * @return array<int, string>
     */
    private function terminalesFiltradas(Collection $catalogo, array $filtros): array
    {
        return $catalogo
            ->filter(fn (array $info, string $key): bool => $this->pasaFiltros($key, $info, $filtros))
            ->keys()
            ->map(fn (string $key): string => $key)
            ->all();
    }

    private function consultaRaza(string $desde, string $hasta): Builder
    {
        $query = VentaRaza::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('agencia_id')
            ->selectRaw($this->terminalKeyExpression().' AS terminal_key')
            ->selectRaw('SUM(ventas) AS total_venta')
            ->selectRaw('COUNT(*) AS registros')
            ->groupBy('terminal_key');

        $query->addSelect('fecha')->groupBy('fecha');

        return $query;
    }

    /**
     * @return array{dias_con_datos: int, dias_rango: int}
     */
    private function coberturaRaza(string $desde, string $hasta): array
    {
        return [
            'dias_con_datos' => VentaRaza::query()->whereBetween('fecha', [$desde, $hasta])->distinct()->count('fecha'),
            'dias_rango' => (int) (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $info
     * @param  array<string, mixed>  $filtros
     */
    private function pasaFiltros(string $key, ?array $info, array $filtros): bool
    {
        foreach (['grupo', 'subgrupo', 'division', 'sociedad'] as $campo) {
            if (! empty($filtros[$campo]) && ($info[$campo] ?? null) !== $filtros[$campo]) {
                return false;
            }
        }

        $estatus = $filtros['estatus'] ?? 'todos';

        if ($estatus !== 'todos' && ($info === null || $info['activa'] !== ($estatus === 'activas'))) {
            return false;
        }

        $buscar = mb_strtolower(trim((string) ($filtros['buscar'] ?? '')));

        if ($buscar !== '') {
            $texto = mb_strtolower(($info['terminal'] ?? $key).' '.($info['nombre'] ?? ''));

            if (! str_contains($texto, $buscar)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $info
     */
    private function valorDimension(string $dimension, string $key, ?array $info, object $row): string
    {
        return match ($dimension) {
            'terminal' => (string) ($info['terminal'] ?? $key),
            'estatus' => $info === null ? self::SIN_CATALOGO : ($info['activa'] ? 'Activa' : 'Inactiva'),
            'tipo' => (string) ($row->tipo ?? 'Sin tipo'),
            'fecha' => substr((string) $row->fecha, 0, 10),
            default => (string) (($info[$dimension] ?? null) ?: self::SIN_CATALOGO),
        };
    }

    /**
     * @return array{dias_con_datos: int, dias_rango: int}
     */
    private function cobertura(string $desde, string $hasta): array
    {
        return [
            'dias_con_datos' => VentaUsuario::query()->whereBetween('fecha', [$desde, $hasta])->whereIn('tipo', self::TIPOS_PRODUCTO)->distinct()->count('fecha'),
            'dias_rango' => (int) (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1,
        ];
    }

    /**
     * @return Collection<string, string>
     */
    public function dimensiones(): Collection
    {
        return collect(self::DIMENSIONES);
    }
}
