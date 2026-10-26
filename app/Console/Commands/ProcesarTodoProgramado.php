<?php

namespace App\Console\Commands;

use App\Mail\ResultadoProcesarTodo;
use App\Services\ProcesarTodoProgramadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcesarTodoProgramado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:procesar-todo-programado';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los cuatro modulos de la API para el dia configurado';

    /**
     * Execute the console command.
     */
    public function handle(ProcesarTodoProgramadoService $service): int
    {
        ini_set('memory_limit', '1024M');

        $configuracion = DB::table('procesar_todo_configuraciones')->where('id', 1)->first();
        $fecha = now('America/Santo_Domingo')
            ->subDays(($configuracion?->dia ?? 'ayer') === 'hoy' ? 0 : 1)
            ->toDateString();
        $resultados = $service->procesar($fecha);

        foreach ($resultados as $modulo => $resultado) {
            $this->line("{$modulo}: {$resultado['estado']} ({$resultado['filas_guardadas']} filas guardadas)");
        }

        try {
            $destinatarios = array_values(array_unique(array_filter([
                $configuracion?->correo ?? config('mail.procesar_todo_recipient'),
                $configuracion?->correo_secundario,
            ])));

            Mail::to($destinatarios)
                ->send(new ResultadoProcesarTodo($fecha, $resultados));
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar el correo del proceso automatico', ['fecha' => $fecha, 'exception' => $exception]);

            return self::FAILURE;
        }

        if (in_array('error', array_column($resultados, 'estado'), true)) {
            Log::error('Proceso automatico incompleto', ['fecha' => $fecha, 'resultados' => $resultados]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
