<?php

namespace App\Services\CentroCosto;

use App\Exceptions\VentasUsuarioApiException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CentroCostoApiService
{
    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    public function fetch(): array
    {
        $empresaId = $this->empresaId();

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.centro_costo.timeout', 120))
                ->withQueryParameters([
                    'strToken' => $this->config('token'),
                    'intIdEmpresa' => $empresaId,
                ])
                ->post($this->config('url'));
        } catch (ConnectionException) {
            throw new VentasUsuarioApiException('No se pudo conectar con la API de centros de costo.', 502);
        }

        if ($response->failed()) {
            throw new VentasUsuarioApiException("La API de centros de costo respondio con HTTP {$response->status()}.", $response->status() >= 500 ? 502 : $response->status());
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            throw new VentasUsuarioApiException('La API de centros de costo devolvio una respuesta invalida.', 502);
        }

        return array_values($rows);
    }

    public function empresaId(): int
    {
        return (int) $this->config('empresa_id');
    }

    /**
     * Marca de cambio de un centro de costo: la fecha de modificacion o, si nunca se
     * modifico, la fecha en que se grabo. Sirve para detectar cambios sin comparar campo por campo.
     */
    public function changeStamp(?string $fechaModificado, ?string $fechaGrabado): ?int
    {
        $value = $fechaModificado ?: $fechaGrabado;

        return $value ? Carbon::parse($value)->timestamp : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row, int $empresaId): array
    {
        $atributos = array_filter(
            $row,
            fn (mixed $value, string $key) => str_starts_with($key, 'Atr') && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            'empresa_id' => $empresaId,
            'id_centro_costo' => (int) ($row['IdCentroCosto'] ?? 0),
            'company' => $row['CompanyID'] ?? null,
            'descripcion' => $row['Descripcion'] ?? null,
            'cuenta' => isset($row['Cuenta']) ? (string) $row['Cuenta'] : null,
            'inactivo' => (bool) ($row['Inactivo'] ?? false),
            'id_grupo' => $row['IdGrupo'] ?? null,
            'id_sub_grupo' => $row['IdSubGrupo'] ?? null,
            'id_division' => $row['IdDivision'] ?? null,
            'id_sociedad' => $row['IdSociedad'] ?? null,
            'id_viejo' => isset($row['IdViejo']) ? (string) $row['IdViejo'] : null,
            'id_centro_costo_resumir_en' => isset($row['IdCentroCostoResumirEn']) ? (string) $row['IdCentroCostoResumirEn'] : null,
            'com_recarga' => (bool) ($row['ComRecarga'] ?? false),
            'gasto_vta_tradicional' => (bool) ($row['GastoVtaTradicional'] ?? false),
            'varios_locales' => (bool) ($row['VariosLocales'] ?? false),
            'aplica_para_ponderar' => (bool) ($row['AplicaParaPonderar'] ?? false),
            'valor_ponderar' => $row['ValorPonderar'] ?? 0,
            'creado_por' => $row['CreadoPor'] ?? null,
            'fecha_grabado' => $row['FechaGrabado'] ?? null,
            'modificado_por' => $row['ModificadoPor'] ?? null,
            'fecha_modificado' => $row['FechaModificado'] ?? null,
            'atributos' => $atributos === [] ? null : json_encode($atributos),
        ];
    }

    private function config(string $key): string
    {
        $value = config("services.centro_costo.{$key}");

        if ($value === null || $value === '') {
            throw new VentasUsuarioApiException("Falta configurar services.centro_costo.{$key} en el .env.", 500);
        }

        return (string) $value;
    }
}
