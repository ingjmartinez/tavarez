<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultado del proceso automático</title>
</head>
<body style="font-family: Arial, sans-serif; color: #243247; margin: 24px;">
    <h2>Proceso automático de API para {{ $fecha }}</h2>
    <p>Estados por módulo y cantidad de filas guardadas en esta ejecución.</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 760px;" cellpadding="10">
        <thead>
            <tr style="background: #f3f4f6; text-align: left;">
                <th style="border: 1px solid #dfe3e8;">#</th>
                <th style="border: 1px solid #dfe3e8;">Módulo</th>
                <th style="border: 1px solid #dfe3e8;">Estado</th>
                <th style="border: 1px solid #dfe3e8;">Filas guardadas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultados as $modulo => $resultado)
                <tr>
                    <td style="border: 1px solid #dfe3e8;">{{ $loop->iteration }}</td>
                    <td style="border: 1px solid #dfe3e8;">{{ [
                        'ventas-usuario' => 'Ventas por usuario',
                        'pagos-misma-empresa' => 'Pagos misma empresa',
                        'pagos-otra-empresa' => 'Pagos a otra empresa',
                        'ventas-raza' => 'Ventas raza',
                        'token' => 'Autenticación API',
                    ][$modulo] ?? $modulo }}</td>
                    <td style="border: 1px solid #dfe3e8;">{{ ['ok' => 'Completado', 'omitido' => 'Omitido', 'error' => 'Error'][$resultado['estado']] ?? $resultado['estado'] }}</td>
                    <td style="border: 1px solid #dfe3e8;">{{ number_format($resultado['filas_guardadas']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (in_array('error', array_column($resultados, 'estado'), true))
        <p>El proceso terminó con errores. Revise los registros de la aplicación.</p>
    @else
        <p>El proceso terminó correctamente. Los módulos omitidos ya tenían datos para esta fecha y guardaron 0 filas en esta ejecución.</p>
    @endif
</body>
</html>
