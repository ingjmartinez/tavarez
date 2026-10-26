<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $hora = Schema::hasTable('procesar_todo_configuraciones')
            ? DB::table('procesar_todo_configuraciones')->where('id', 1)->value('hora')
            : null;

        if ($hora !== null) {
            $schedule->command('app:procesar-todo-programado')
                ->dailyAt($hora)
                ->timezone('America/Santo_Domingo')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/procesar-todo.log'));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
