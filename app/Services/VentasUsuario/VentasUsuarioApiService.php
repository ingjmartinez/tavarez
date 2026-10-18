<?php

namespace App\Services\VentasUsuario;

use App\Exceptions\VentasUsuarioApiException;
use App\Models\ExternalApiToken;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class VentasUsuarioApiService
{
    public const TOKEN_NAME = 'ventas_usuario';

    public function generateToken(): ExternalApiToken
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get($this->config('token_url')));
        $data = $this->decode($response);

        $tokenValue = data_get($data, 'Content.Token');

        if (! is_string($tokenValue) || trim($tokenValue) === '') {
            throw new VentasUsuarioApiException($this->messageFrom($data, 'La API no devolvio un token valido.'), 502);
        }

        $expiresAt = $this->parseExpiry(data_get($data, 'Content.DateExpire'));

        return ExternalApiToken::query()->updateOrCreate(
            ['name' => self::TOKEN_NAME],
            ['token' => trim($tokenValue), 'expires_at' => $expiresAt],
        );
    }

    public function currentToken(): ?ExternalApiToken
    {
        return ExternalApiToken::query()->where('name', self::TOKEN_NAME)->first();
    }

    /**
     * @throws VentasUsuarioApiException
     */
    public function validToken(): ExternalApiToken
    {
        $token = $this->currentToken();

        if (! $token) {
            throw new VentasUsuarioApiException('Genere un token antes de consultar la data.', 404);
        }

        if (! $token->isValid()) {
            throw new VentasUsuarioApiException('El token ha expirado, genere uno nuevo.', 401);
        }

        return $token;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    public function fetchVentas(string $fecha): array
    {
        $token = $this->validToken();
        $url = rtrim($this->config('ventas_url'), '/').'/'.$token->token.'/'.$fecha.'/'.$this->config('codigo');

        return $this->extractRows($this->decode($this->send(fn (PendingRequest $request) => $request->get($url))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row, string $fecha): array
    {
        return [
            'consorcio_id' => $row['consorcio_id'] ?? null,
            'agencia_id' => $row['agencia_id'] ?? null,
            'producto_id' => $row['producto_id'] ?? null,
            'cedula' => $this->normalizeCedula($row['cedula'] ?? null),
            'descripcion' => $row['descripcion'] ?? null,
            'tipo' => $row['tipo'] ?? null,
            'monto' => $row['monto'] ?? 0,
            'fecha' => $fecha,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    public function fetchPagos(string $fecha): array
    {
        $token = $this->validToken();
        $url = rtrim($this->config('pagos_url'), '/').'/'.$token->token.'/'.$fecha.'/'.$this->config('codigo');

        return $this->extractRows($this->decode($this->send(fn (PendingRequest $request) => $request->get($url))));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    public function fetchPagosOtraEmpresa(string $fecha): array
    {
        $token = $this->validToken();
        $url = rtrim($this->config('pagos_otra_empresa_url'), '/').'/'.$token->token.'/'.$fecha.'/'.$this->config('codigo');

        return $this->extractRows($this->decode($this->send(fn (PendingRequest $request) => $request->get($url))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapVentaRazaRow(array $row, string $fecha): array
    {
        return [
            'consorcio_id' => isset($row['consorcio_id']) ? (string) $row['consorcio_id'] : null,
            'agencia_id' => isset($row['agencia_id']) ? (string) $row['agencia_id'] : null,
            'proveedor_id' => isset($row['proveedor_id']) ? (string) $row['proveedor_id'] : null,
            'proveedor_nombre' => $row['proveedor_nombre'] ?? null,
            'ventas' => $row['ventas'] ?? 0,
            'premios_pagados' => $row['premios_pagados'] ?? 0,
            'premios' => $row['premios'] ?? 0,
            'fecha' => $fecha,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    public function fetchVentasRaza(string $fecha): array
    {
        $token = $this->validToken();
        $url = rtrim($this->config('ventas_raza_url'), '/').'/'.$token->token.'/'.$fecha.'/'.$this->config('codigo');

        return $this->extractRows($this->decode($this->send(fn (PendingRequest $request) => $request->get($url))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapPagoOtraEmpresaRow(array $row, string $fecha): array
    {
        return [
            'consorcio_id' => isset($row['consorcio_id']) ? (string) $row['consorcio_id'] : null,
            'agencia_id' => isset($row['agencia_id']) ? (string) $row['agencia_id'] : null,
            'pagado_consorcio_id' => isset($row['pagado_consorcio_id']) ? (string) $row['pagado_consorcio_id'] : null,
            'producto_id' => isset($row['producto_id']) ? (string) $row['producto_id'] : null,
            'descripcion' => $row['descripcion'] ?? null,
            'plataforma_pago' => $row['plataforma_pago'] ?? null,
            'monto' => $row['monto'] ?? 0,
            'importe' => $row['importe'] ?? 0,
            'fecha' => $fecha,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapPagoRow(array $row, string $fecha): array
    {
        return [
            'consorcio_id' => isset($row['consorcio_id']) ? (string) $row['consorcio_id'] : null,
            'agencia_id' => isset($row['agencia_id']) ? (string) $row['agencia_id'] : null,
            'pagado_agencia_id' => isset($row['pagado_agencia_id']) ? (string) $row['pagado_agencia_id'] : null,
            'producto_id' => isset($row['producto_id']) ? (string) $row['producto_id'] : null,
            'descripcion' => $row['descripcion'] ?? null,
            'plataforma_pago' => $row['plataforma_pago'] ?? null,
            'monto' => $row['monto'] ?? 0,
            'fecha' => $fecha,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     *
     * @throws VentasUsuarioApiException
     */
    private function extractRows(array $data): array
    {
        $rows = $data['Content'] ?? [];
        $code = (int) ($data['code'] ?? 0);

        if (! is_array($rows)) {
            throw new VentasUsuarioApiException($this->messageFrom($data, 'La API no devolvio el listado esperado.'), 502);
        }

        if ($code !== 0 && $rows === []) {
            throw new VentasUsuarioApiException($this->messageFrom($data, 'La API devolvio un error.'), 422);
        }

        return $rows;
    }

    public function normalizeCedula(mixed $rawCedula): ?string
    {
        $cedula = preg_replace('/\D/', '', (string) $rawCedula);

        if ($cedula === '') {
            return null;
        }

        $cedula = str_pad(substr($cedula, 0, 11), 11, '0', STR_PAD_LEFT);

        return $cedula === '00000000000' ? null : $cedula;
    }

    private function config(string $key): string
    {
        $value = config("services.ventas_usuario.{$key}");

        if (! is_string($value) || $value === '') {
            throw new VentasUsuarioApiException("Falta configurar services.ventas_usuario.{$key} en el .env.", 500);
        }

        return $value;
    }

    private function send(callable $call): Response
    {
        $request = Http::withHeaders([
            'AhfCC' => $this->config('header_cc'),
            'AhfVB' => $this->config('header_vb'),
        ])->acceptJson()->timeout((int) config('services.ventas_usuario.timeout', 60));

        try {
            $response = $call($request);
        } catch (ConnectionException) {
            throw new VentasUsuarioApiException('No se pudo conectar con la API externa.', 502);
        }

        if ($response->failed()) {
            $message = $this->messageFrom($response->json() ?? [], "La API externa respondio con HTTP {$response->status()}.");

            throw new VentasUsuarioApiException($message, $response->status() >= 500 ? 502 : $response->status());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw new VentasUsuarioApiException('La API externa devolvio una respuesta invalida.', 502);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function messageFrom(array $data, string $default): string
    {
        $message = (string) ($data['msg'] ?? $data['message'] ?? $data['error'] ?? '');

        return $message !== '' ? $message : $default;
    }

    private function parseExpiry(mixed $value): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw new VentasUsuarioApiException('La API no devolvio la fecha de expiracion del token.', 502);
        }

        try {
            return Carbon::parse(trim($value))->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            throw new VentasUsuarioApiException('No se pudo interpretar la fecha de expiracion del token.', 502);
        }
    }
}
