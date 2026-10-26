Proceso automático de API para {{ $fecha }}

Estados por módulo y filas guardadas en esta ejecución:

@foreach ($resultados as $modulo => $resultado)
{{ $modulo }}: {{ $resultado['estado'] }} — {{ $resultado['filas_guardadas'] }} filas guardadas
@endforeach

@if (in_array('error', array_column($resultados, 'estado'), true))
El proceso terminó con errores. Revise los registros de la aplicación.
@else
El proceso terminó correctamente. Los módulos omitidos ya tenían datos para esta fecha y guardaron 0 filas en esta ejecución.
@endif
