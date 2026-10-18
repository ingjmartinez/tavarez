<?php

namespace App\Http\Controllers;

use App\Services\VentasUsuario\VentasUsuarioApiService;
use Inertia\Inertia;
use Inertia\Response;

class ProcesarTodoController extends Controller
{
    public function __construct(private VentasUsuarioApiService $service) {}

    public function index(): Response
    {
        $token = $this->service->currentToken();

        return Inertia::render('procesar-todo/index', [
            'tokenExpiresAt' => $token?->expires_at?->toIso8601String(),
            'tokenValid' => (bool) $token?->isValid(),
        ]);
    }
}
