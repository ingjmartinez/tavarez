<?php

namespace App\Services\Reporte;

use App\Models\VentaUsuario;
use App\Services\Terminal\TerminalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardVentasService
{
    public function __construct(private TerminalService $terminales, private ReporteCategoriasService $categorias) {}

    /**
     * @return array<string, mixed>
     */
    public function generar(string $desde, string $hasta): array
    {
        $seleccionados = array_keys($this->categorias->activas()['subgrupo'] ?? []);
        natcasesort($seleccionados);
        $seleccionados = array_values($seleccionados);

        $catalogo = $seleccionados === []
            ? collect()
            : $this->terminales->catalogo(paraReporte: true, dimensionSeleccionada: 'subgrupo', valoresSeleccionados: $seleccionados);

        $subgrupos = [];
        foreach ($seleccionados as $nombre) {
            $subgrupos[$nombre] = [
                'total_venta' => 0.0,
                'terminales' => 0,
                'dias' => [],
                'clasificaciones' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0],
                'terminales_clasificadas' => ['A' => [], 'B' => [], 'C' => [], 'D' => []],
            ];
        }

        $terminales = [];
        $dias = [];
        $totalVenta = 0.0;
        $tieneClaveGenerada = Schema::hasColumn('ventas_usuarios', 'terminal_key');
        $expresionClave = $tieneClaveGenerada ? 'terminal_key' : $this->terminales->terminalKeyExpression();

        foreach ($catalogo->keys()->chunk($tieneClaveGenerada ? 5000 : 50000) as $keys) {
            $query = VentaUsuario::query()
                ->whereBetween('fecha', [$desde, $hasta.' 23:59:59'])
                ->whereIn('tipo', ReporteVentasService::TIPOS_PRODUCTO)
                ->whereIn(DB::raw($expresionClave), $keys->map(fn (string|int $key): string => (string) $key)->all())
                ->selectRaw($expresionClave.' AS terminal_key')
                ->addSelect('fecha')
                ->selectRaw('SUM(monto) AS total_venta')
                ->groupBy('terminal_key', 'fecha');

            $ventas = $query
                ->toBase()
                ->cursor();

            foreach ($ventas as $venta) {
                $key = (string) $venta->terminal_key;
                $subgrupo = (string) $catalogo->get($key)['subgrupo'];
                $fecha = substr((string) $venta->fecha, 0, 10);
                $monto = (float) $venta->total_venta;

                $terminales[$key] ??= ['total_venta' => 0.0, 'dias' => 0, 'subgrupo' => $subgrupo];
                $terminales[$key]['total_venta'] += $monto;
                $terminales[$key]['dias']++;
                $subgrupos[$subgrupo]['total_venta'] += $monto;
                $subgrupos[$subgrupo]['dias'][$fecha] = true;
                $dias[$fecha] = true;
                $totalVenta += $monto;
            }
        }

        $clasificaciones = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($terminales as $key => $terminal) {
            $promedio = $terminal['total_venta'] / $terminal['dias'];
            $letra = 'D';
            foreach (ReporteVentasService::CLASIFICACION_UMBRALES as $clasificacion => $minimo) {
                if ($promedio >= $minimo) {
                    $letra = $clasificacion;
                    break;
                }
            }
            $clasificaciones[$letra]++;
            $subgrupos[$terminal['subgrupo']]['terminales']++;
            $subgrupos[$terminal['subgrupo']]['clasificaciones'][$letra]++;
            $subgrupos[$terminal['subgrupo']]['terminales_clasificadas'][$letra][] = [
                'terminal' => $catalogo->get($key)['terminal'],
                'nombre' => $catalogo->get($key)['nombre'],
            ];
        }

        $resumenSubgrupos = collect($subgrupos)
            ->map(fn (array $datos, string $nombre): array => [
                'nombre' => $nombre,
                'total_venta' => round($datos['total_venta'], 2),
                'promedio' => $datos['dias'] === [] ? 0.0 : round($datos['total_venta'] / count($datos['dias']), 2),
                'terminales' => $datos['terminales'],
                'clasificaciones' => $datos['clasificaciones'],
                'terminales_clasificadas' => $datos['terminales_clasificadas'],
                'participacion' => $totalVenta == 0.0 ? 0.0 : round($datos['total_venta'] / $totalVenta * 100, 1),
            ])
            ->sortByDesc('total_venta')
            ->values()
            ->all();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'total_venta' => round($totalVenta, 2),
            'promedio' => $dias === [] ? 0.0 : round($totalVenta / count($dias), 2),
            'dias_con_datos' => count($dias),
            'dias_rango' => (int) (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1,
            'terminales_con_venta' => count($terminales),
            'terminales_configuradas' => $catalogo->count(),
            'subgrupos_configurados' => count($seleccionados),
            'clasificaciones' => $clasificaciones,
            'subgrupos' => $resumenSubgrupos,
        ];
    }
}
