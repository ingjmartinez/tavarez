<?php

namespace App\Services\Terminal;

use App\Models\CentroCosto;
use App\Models\VentaUsuario;
use App\Services\Reporte\ReporteCategoriasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas aplicadas:
 *  - Una terminal es el campo id_viejo de un centro de costo; en ventas_usuarios es agencia_id.
 *    Ambos se comparan sin ceros a la izquierda (08001003 = 8001003).
 *  - Activa o inactiva depende unicamente del flag `inactivo` del centro de costo. Una terminal
 *    con varios centros se considera activa si al menos uno esta activo.
 *  - "Con venta" significa monto > 0 en ventas_usuarios dentro de los ultimos 30 dias (hoy inclusive).
 *    No tener venta nunca implica que la terminal este desactivada.
 */
class TerminalService
{
    public const DIAS_PERIODO = 30;

    public function __construct(private ReporteCategoriasService $categorias) {}

    public function terminalKey(mixed $value): string
    {
        $key = ltrim(trim((string) $value), '0');

        return $key === '' ? '0' : $key;
    }

    private function terminalKeyExpression(): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "COALESCE(NULLIF(LTRIM(TRIM(agencia_id), '0'), ''), '0')";
        }

        return "COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(agencia_id)), ''), '0')";
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public function periodo(): array
    {
        return [
            'desde' => now()->subDays(self::DIAS_PERIODO - 1)->toDateString(),
            'hasta' => now()->toDateString(),
        ];
    }

    /**
     * Catalogo de terminales indexado por terminal normalizada.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function catalogo(bool $paraReporte = false, ?string $dimensionSeleccionada = null, ?string $valorSeleccionado = null, ?array $valoresSeleccionados = null): Collection
    {
        $activas = ($paraReporte || $dimensionSeleccionada !== null) ? $this->categorias->activas() : [];

        return CentroCosto::query()
            ->whereNotNull('id_viejo')
            ->whereRaw("TRIM(id_viejo) <> ''")
            ->orderBy('id_centro_costo')
            ->get(['id_centro_costo', 'id_viejo', 'descripcion', 'id_grupo', 'id_sub_grupo', 'id_division', 'id_sociedad', 'inactivo'])
            ->filter(fn (CentroCosto $centro): bool => $dimensionSeleccionada !== null
                ? ($valoresSeleccionados !== null
                    ? in_array($centro->{ReporteCategoriasService::DIMENSIONES[$dimensionSeleccionada]}, $valoresSeleccionados, true)
                    : ($valorSeleccionado === null || $centro->{ReporteCategoriasService::DIMENSIONES[$dimensionSeleccionada]} === $valorSeleccionado))
                    && isset($activas[$dimensionSeleccionada][$centro->{ReporteCategoriasService::DIMENSIONES[$dimensionSeleccionada]}])
                : (! $paraReporte || $this->categorias->centroVisible($centro, $activas)))
            ->groupBy(fn (CentroCosto $centro) => $this->terminalKey($centro->id_viejo))
            ->reject(fn (Collection $grupo, string $key) => $key === '0')
            ->map(function (Collection $grupo): array {
                $base = $grupo->first(fn (CentroCosto $centro) => ! $centro->inactivo) ?? $grupo->first();

                return [
                    'terminal' => trim($base->id_viejo),
                    'nombre' => $base->descripcion,
                    'grupo' => $base->id_grupo,
                    'subgrupo' => $base->id_sub_grupo,
                    'division' => $base->id_division,
                    'sociedad' => $base->id_sociedad,
                    'activa' => $grupo->contains(fn (CentroCosto $centro) => ! $centro->inactivo),
                ];
            });
    }

    /**
     * Ventas positivas por terminal normalizada dentro del rango.
     *
     * @return Collection<string, array{dias_con_venta: int, ultima_fecha: string, total_venta: float}>
     */
    public function ventasPorTerminal(string $desde, string $hasta): Collection
    {
        return VentaUsuario::query()
            ->whereBetween('fecha', [$desde, $hasta.' 23:59:59'])
            ->where('monto', '>', 0)
            ->whereNotNull('agencia_id')
            ->selectRaw($this->terminalKeyExpression().' AS terminal_key')
            ->selectRaw('COUNT(DISTINCT fecha) AS dias_con_venta')
            ->selectRaw('MAX(fecha) AS ultima_fecha')
            ->selectRaw('SUM(monto) AS total_venta')
            ->groupBy('terminal_key')
            ->get()
            ->reject(fn ($row) => $row->terminal_key === '0')
            ->mapWithKeys(fn ($row) => [
                (string) $row->terminal_key => [
                    'dias_con_venta' => (int) $row->dias_con_venta,
                    'ultima_fecha' => substr((string) $row->ultima_fecha, 0, 10),
                    'total_venta' => round((float) $row->total_venta, 2),
                ],
            ]);
    }

    /**
     * Cuantos dias del periodo tienen ventas cargadas, para saber si "sin venta" es confiable.
     *
     * @return array{dias_con_datos: int, dias_periodo: int, completa: bool}
     */
    public function cobertura(string $desde, string $hasta): array
    {
        $diasConDatos = VentaUsuario::query()
            ->whereBetween('fecha', [$desde, $hasta.' 23:59:59'])
            ->distinct()
            ->count('fecha');

        return [
            'dias_con_datos' => $diasConDatos,
            'dias_periodo' => self::DIAS_PERIODO,
            'completa' => $diasConDatos >= self::DIAS_PERIODO,
        ];
    }
}
