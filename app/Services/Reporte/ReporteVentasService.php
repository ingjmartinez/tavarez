<?php

namespace App\Services\Reporte;

use App\Models\VentaRaza;
use App\Models\VentaUsuario;
use App\Services\Terminal\TerminalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    public function __construct(private TerminalService $terminales) {}

    /**
     * Valores disponibles para los filtros, tomados del catalogo de terminales y de las ventas.
     *
     * @return array<string, array<int, string>>
     */
    public function opcionesFiltros(): array
    {
        $catalogo = $this->terminales->catalogo();
        $distintos = fn (string $campo): array => $catalogo->pluck($campo)->filter()->unique()->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->values()->all();

        return [
            'grupos' => $distintos('grupo'),
            'subgrupos' => $distintos('subgrupo'),
            'divisiones' => $distintos('division'),
            'sociedades' => $distintos('sociedad'),
            'tipos' => VentaUsuario::query()->whereNotNull('tipo')->distinct()->orderBy('tipo')->pluck('tipo')->all(),
        ];
    }

    /**
     * @param  array<int, string>  $agrupar
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(string $desde, string $hasta, array $agrupar, array $filtros): array
    {
        $catalogo = $this->terminales->catalogo();
        $porTipo = in_array('tipo', $agrupar, true);
        $porFecha = in_array('fecha', $agrupar, true);
        $incluirRaza = ! empty($filtros['raza']);
        $pivotear = $porTipo || $incluirRaza;

        $query = VentaUsuario::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('agencia_id')
            ->selectRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0') AS terminal_key")
            ->selectRaw('SUM(monto) AS total_venta')
            ->selectRaw('COUNT(*) AS registros')
            ->groupBy('terminal_key');

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if ($porTipo) {
            $query->addSelect('tipo')->groupBy('tipo');
        }

        if ($porFecha) {
            $query->addSelect('fecha')->groupBy('fecha');
        }

        $dimensiones = array_values(array_diff($agrupar, ['tipo']));

        if ($dimensiones === []) {
            $dimensiones = ['resumen'];
        }

        $acumulado = [];
        $totalesPorTipo = [];
        $terminalesTotales = [];
        $totalVenta = 0.0;
        $totalRegistros = 0;

        $acumular = function (iterable $rows, callable $etiquetaTipo) use ($catalogo, $filtros, $dimensiones, $pivotear, &$acumulado, &$totalesPorTipo, &$terminalesTotales, &$totalVenta, &$totalRegistros): void {
            foreach ($rows as $row) {
                $key = (string) $row->terminal_key;

                if ($key === '0') {
                    continue;
                }

                $info = $catalogo->get($key);

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
                ];
                $acumulado[$clave]['terminales'][$key] = true;
                $acumulado[$clave]['registros'] += (int) $row->registros;
                $acumulado[$clave]['total_venta'] += (float) $row->total_venta;

                if ($pivotear) {
                    $tipo = $etiquetaTipo($row);
                    $acumulado[$clave]['tipos'][$tipo] = ($acumulado[$clave]['tipos'][$tipo] ?? 0.0) + (float) $row->total_venta;
                    $totalesPorTipo[$tipo] = ($totalesPorTipo[$tipo] ?? 0.0) + (float) $row->total_venta;
                }

                $terminalesTotales[$key] = true;
                $totalVenta += (float) $row->total_venta;
                $totalRegistros += (int) $row->registros;
            }
        };

        $acumular($query->get(), fn (object $row): string => $porTipo ? (string) ($row->tipo ?? 'Sin tipo') : self::ETIQUETA_USUARIOS);

        if ($incluirRaza) {
            $acumular($this->consultaRaza($desde, $hasta, $porFecha)->get(), fn (): string => self::ETIQUETA_RAZA);
        }

        $filas = collect($acumulado)
            ->map(fn (array $fila): array => $fila['valores'] + [
                'nombre' => in_array('terminal', $agrupar, true) ? $fila['nombre'] : null,
                'terminales' => count($fila['terminales']),
                'registros' => $fila['registros'],
                'total_venta' => round($fila['total_venta'], 2),
                'tipos' => array_map(fn (float $monto): float => round($monto, 2), $fila['tipos']),
            ])
            ->sortByDesc('total_venta')
            ->values();

        $tipos = array_keys($totalesPorTipo);
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
            ],
            'cobertura' => $this->cobertura($desde, $hasta),
            'cobertura_raza' => $incluirRaza ? $this->coberturaRaza($desde, $hasta) : null,
        ];
    }

    /**
     * Solo el campo `ventas` de ventas raza; premios y premios pagados no se incluyen.
     */
    private function consultaRaza(string $desde, string $hasta, bool $porFecha): Builder
    {
        $query = VentaRaza::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('agencia_id')
            ->selectRaw("COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0') AS terminal_key")
            ->selectRaw('SUM(ventas) AS total_venta')
            ->selectRaw('COUNT(*) AS registros')
            ->groupBy('terminal_key');

        if ($porFecha) {
            $query->addSelect('fecha')->groupBy('fecha');
        }

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
            'dias_con_datos' => VentaUsuario::query()->whereBetween('fecha', [$desde, $hasta])->distinct()->count('fecha'),
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
