<?php

namespace App\Http\Controllers;

use App\Services\VentasUsuario\VentasUsuarioApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProcesarTodoController extends Controller
{
    public function __construct(private VentasUsuarioApiService $service) {}

    public function index(): Response
    {
        $token = $this->service->currentToken();

        $configuracion = DB::table('procesar_todo_configuraciones')->where('id', 1)->first();

        return Inertia::render('procesar-todo/index', [
            'tokenExpiresAt' => $token?->expires_at?->toIso8601String(),
            'tokenValid' => (bool) $token?->isValid(),
            'horaProgramada' => $configuracion?->hora,
            'correoProgramado' => $configuracion?->correo ?? config('mail.procesar_todo_recipient'),
            'diaProgramado' => $configuracion?->dia ?? 'ayer',
            'puedeConfigurar' => Gate::allows('configurar-reportes'),
        ]);
    }

    public function configurar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hora' => ['required', 'date_format:H:i'],
            'correo' => ['required', 'email', 'max:255'],
            'dia' => ['required', 'in:hoy,ayer'],
        ]);

        DB::table('procesar_todo_configuraciones')->updateOrInsert(
            ['id' => 1],
            ['hora' => $data['hora'], 'correo' => $data['correo'], 'dia' => $data['dia'], 'updated_at' => now(), 'created_at' => now()],
        );

        return response()->json(['message' => 'Configuración programada guardada.', ...$data]);
    }
}
