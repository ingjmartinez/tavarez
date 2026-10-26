<?php

namespace App\Services;

use App\Models\PagoMismaEmpresa;
use App\Models\PagoOtraEmpresa;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;
use App\Services\VentasUsuario\VentasUsuarioApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcesarTodoProgramadoService
{
    public function __construct(private VentasUsuarioApiService $api) {}

    /** @return array<string, array{estado: string, filas_guardadas: int}> */
    public function procesar(string $fecha): array
    {
        $resultados = [];

        try {
            $this->api->generateToken();
        } catch (Throwable $exception) {
            Log::error('No se pudo generar el token del proceso automatico', ['fecha' => $fecha, 'exception' => $exception]);

            return ['token' => ['estado' => 'error', 'filas_guardadas' => 0]];
        }

        $modulos = [
            'ventas-usuario' => [VentaUsuario::class, 'fetchVentas', 'mapRow'],
            'pagos-misma-empresa' => [PagoMismaEmpresa::class, 'fetchPagos', 'mapPagoRow'],
            'pagos-otra-empresa' => [PagoOtraEmpresa::class, 'fetchPagosOtraEmpresa', 'mapPagoOtraEmpresaRow'],
            'ventas-raza' => [VentaRaza::class, 'fetchVentasRaza', 'mapVentaRazaRow'],
        ];

        foreach ($modulos as $nombre => [$modelo, $fetch, $map]) {
            if ($modelo::query()->whereDate('fecha', $fecha)->exists()) {
                $resultados[$nombre] = ['estado' => 'omitido', 'filas_guardadas' => 0];

                continue;
            }

            try {
                $rows = $this->api->{$fetch}($fecha);
                $now = now();

                $total = count($rows);

                DB::transaction(function () use (&$rows, $total, $modelo, $map, $fecha, $now): void {
                    for ($offset = 0; $offset < $total; $offset += 1000) {
                        $chunk = array_slice($rows, $offset, 1000);
                        $data = array_map(
                            fn (array $row): array => $this->api->{$map}($row, $fecha) + ['created_at' => $now, 'updated_at' => $now],
                            $chunk,
                        );
                        $modelo::query()->insert($data);
                        unset($chunk, $data);
                    }
                });
                unset($rows);

                $resultados[$nombre] = ['estado' => 'ok', 'filas_guardadas' => $total];
            } catch (Throwable $exception) {
                $resultados[$nombre] = ['estado' => 'error', 'filas_guardadas' => 0];
                Log::error('Error en modulo del proceso automatico', ['fecha' => $fecha, 'modulo' => $nombre, 'exception' => $exception]);
            }
        }

        return $resultados;
    }
}
