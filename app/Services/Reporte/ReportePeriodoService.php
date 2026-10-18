<?php

namespace App\Services\Reporte;

use DateTimeImmutable;

class ReportePeriodoService
{
    /**
     * @return array<string, mixed>
     */
    public function __construct(private ReporteVentasService $ventas) {}

    private const MESES = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    /**
     * @return array<string, mixed>
     */
    public function generar(string $desde, string $hasta, string $modo, ?string $categoria, bool $porTerminal, bool $agruparMeses): array
    {
        $inicio = new DateTimeImmutable($desde);
        $fin = new DateTimeImmutable($hasta);
        $mesActual = $inicio->modify('first day of this month');
        $mesFinal = $fin->modify('first day of this month');
        $periodos = [];
        $meses = [];
        $filas = [];
        $tipos = [];
        $totalVenta = 0.0;
        $truncado = false;

        if (! $agruparMeses) {
            while ($mesActual <= $mesFinal) {
                $inicioMes = $inicio > $mesActual ? $inicio : $mesActual;
                $ultimoDia = $mesActual->modify('last day of this month');
                $finMes = $fin < $ultimoDia ? $fin : $ultimoDia;
                $periodos[] = [
                    'clave' => $mesActual->format('Y-m'),
                    'etiqueta' => self::MESES[(int) $mesActual->format('n')].' '.$mesActual->format('Y'),
                    'desde' => $inicioMes->format('Y-m-d'),
                    'hasta' => $finMes->format('Y-m-d'),
                ];
                $mesActual = $mesActual->modify('first day of next month');
            }
        } else {
            $nombreInicio = self::MESES[(int) $inicio->format('n')].' '.$inicio->format('Y');
            $nombreFin = self::MESES[(int) $fin->format('n')].' '.$fin->format('Y');
            $periodos[] = [
                'clave' => 'periodo',
                'etiqueta' => 'Ventas agrupadas',
                'periodo' => $nombreInicio === $nombreFin ? $nombreInicio : $nombreInicio.' a '.$nombreFin,
                'desde' => $desde,
                'hasta' => $hasta,
            ];
        }

        foreach ($periodos as $periodo) {
            $claveMes = $periodo['clave'];
            $resultado = $this->ventas->generar(
                $periodo['desde'],
                $periodo['hasta'],
                $porTerminal ? [$modo, 'terminal', 'tipo'] : [$modo, 'tipo'],
                ['raza' => true],
                dimensionSeleccionada: $modo,
                valorSeleccionado: $categoria,
                ordenarPorAgrupacion: $porTerminal,
            );

            $meses[] = [
                'clave' => $claveMes,
                'etiqueta' => $periodo['etiqueta'],
                'periodo' => $periodo['periodo'] ?? null,
                'desde' => $periodo['desde'],
                'hasta' => $periodo['hasta'],
                'totales' => $resultado['totales'],
                'cobertura' => $resultado['cobertura'],
            ];
            $totalVenta += $resultado['totales']['total_venta'];
            $truncado = $truncado || $resultado['truncado'];

            foreach ($resultado['tipos'] as $tipo) {
                $tipos[$tipo] = true;
            }

            foreach ($resultado['filas'] as $fila) {
                $claveFila = json_encode([$fila[$modo], $porTerminal ? $fila['terminal'] : null], JSON_THROW_ON_ERROR);
                $filas[$claveFila] ??= [
                    $modo => $fila[$modo],
                    'terminal' => $porTerminal ? $fila['terminal'] : null,
                    'nombre' => $porTerminal ? $fila['nombre'] : null,
                    'meses' => [],
                ];
                $filas[$claveFila]['meses'][$claveMes] = [
                    'tipos' => $fila['tipos'],
                    'promedios_por_tipo' => $fila['promedios_por_tipo'],
                    'promedio' => $fila['promedio'],
                    'total_venta' => $fila['total_venta'],
                ];
            }

        }

        $tipos = array_keys($tipos);
        usort($tipos, 'strnatcasecmp');
        if (in_array(ReporteVentasService::ETIQUETA_RAZA, $tipos, true)) {
            $tipos = array_values(array_merge(array_diff($tipos, [ReporteVentasService::ETIQUETA_RAZA]), [ReporteVentasService::ETIQUETA_RAZA]));
        }

        $filas = array_values($filas);
        usort($filas, function (array $primera, array $segunda) use ($modo): int {
            $grupo = strnatcasecmp($primera[$modo], $segunda[$modo]);

            return $grupo !== 0 ? $grupo : strnatcasecmp((string) $primera['terminal'], (string) $segunda['terminal']);
        });

        return [
            'modo' => $modo,
            'por_terminal' => $porTerminal,
            'agrupar_meses' => $agruparMeses,
            'meses' => $meses,
            'tipos' => $tipos,
            'filas' => $filas,
            'total_filas' => count($filas),
            'total_venta' => round($totalVenta, 2),
            'truncado' => $truncado,
        ];
    }
}
