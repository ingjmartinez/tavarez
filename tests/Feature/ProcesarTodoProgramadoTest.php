<?php

use App\Mail\ResultadoProcesarTodo;
use App\Models\ExternalApiToken;
use App\Models\User;
use App\Services\ProcesarTodoProgramadoService;
use App\Services\VentasUsuario\VentasUsuarioApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\mock;

test('administrator saves the daily time, recipient and processing day', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->putJson(route('procesar-todo.configurar'), ['hora' => '02:30', 'correo' => 'destino@example.com', 'dia' => 'hoy'])
        ->assertOk()->assertJsonPath('hora', '02:30')->assertJsonPath('correo', 'destino@example.com')->assertJsonPath('dia', 'hoy');

    $this->assertDatabaseHas('procesar_todo_configuraciones', ['id' => 1, 'hora' => '02:30', 'correo' => 'destino@example.com', 'dia' => 'hoy']);
    $this->get(route('procesar-todo.index'))
        ->assertInertia(fn ($page) => $page->component('procesar-todo/index')->where('horaProgramada', '02:30')->where('correoProgramado', 'destino@example.com')->where('diaProgramado', 'hoy'));
});

test('invalid time does not replace the saved time', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->putJson(route('procesar-todo.configurar'), ['hora' => '02:30', 'correo' => 'destino@example.com', 'dia' => 'ayer'])->assertOk();

    $this->putJson(route('procesar-todo.configurar'), ['hora' => '25:99', 'correo' => 'invalid', 'dia' => 'mañana'])
        ->assertUnprocessable()->assertJsonValidationErrors('hora');
    $this->putJson(route('procesar-todo.configurar'), ['hora' => '02:30', 'correo' => 'invalid', 'dia' => 'mañana'])
        ->assertUnprocessable()->assertJsonValidationErrors(['correo', 'dia']);

    $this->assertDatabaseHas('procesar_todo_configuraciones', ['id' => 1, 'hora' => '02:30', 'correo' => 'destino@example.com', 'dia' => 'ayer']);
});

test('unauthorized user cannot change the daily time', function () {
    $this->actingAs(User::factory()->create(['role' => 'otro']));

    $this->putJson(route('procesar-todo.configurar'), ['hora' => '02:30', 'correo' => 'destino@example.com', 'dia' => 'hoy'])->assertForbidden();

    $this->assertDatabaseCount('procesar_todo_configuraciones', 0);
});

test('scheduled command processes the previous day in Santo Domingo', function () {
    Mail::fake();
    config()->set('mail.procesar_todo_recipient', 'info@helpprocompany.com');
    $this->travelTo(Carbon::parse('2026-10-25 03:00:00', 'America/Santo_Domingo'));
    mock(ProcesarTodoProgramadoService::class)
        ->shouldReceive('procesar')
        ->once()
        ->with('2026-10-24')
        ->andReturn(['ventas-usuario' => ['estado' => 'ok', 'filas_guardadas' => 2]]);

    $this->artisan('app:procesar-todo-programado')->assertSuccessful();

    Mail::assertSent(ResultadoProcesarTodo::class, function (ResultadoProcesarTodo $mail): bool {
        return $mail->hasTo('info@helpprocompany.com')
            && $mail->fecha === '2026-10-24'
            && $mail->resultados === ['ventas-usuario' => ['estado' => 'ok', 'filas_guardadas' => 2]];
    });
});

test('scheduled command processes the same day and emails the configured recipient', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-25 03:00:00', 'America/Santo_Domingo'));
    DB::table('procesar_todo_configuraciones')->insert([
        'id' => 1, 'hora' => '03:00', 'correo' => 'destino@example.com', 'dia' => 'hoy',
    ]);
    mock(ProcesarTodoProgramadoService::class)
        ->shouldReceive('procesar')
        ->once()
        ->with('2026-10-25')
        ->andReturn(['ventas-usuario' => ['estado' => 'ok', 'filas_guardadas' => 2]]);

    $this->artisan('app:procesar-todo-programado')->assertSuccessful();

    Mail::assertSent(ResultadoProcesarTodo::class, fn (ResultadoProcesarTodo $mail): bool => $mail->hasTo('destino@example.com') && $mail->fecha === '2026-10-25');
});

test('result email displays saved row counts for each module', function () {
    $mail = new ResultadoProcesarTodo('2026-10-24', [
        'ventas-usuario' => ['estado' => 'ok', 'filas_guardadas' => 12],
        'pagos-misma-empresa' => ['estado' => 'omitido', 'filas_guardadas' => 0],
        'pagos-otra-empresa' => ['estado' => 'ok', 'filas_guardadas' => 3],
        'ventas-raza' => ['estado' => 'error', 'filas_guardadas' => 0],
    ]);

    $mail->assertSeeInHtml('Ventas por usuario')
        ->assertSeeInHtml('Pagos misma empresa')
        ->assertSeeInHtml('Pagos a otra empresa')
        ->assertSeeInHtml('Ventas raza')
        ->assertSeeInHtml('12')
        ->assertSeeInHtml('3')
        ->assertSeeInHtml('Omitido')
        ->assertSeeInHtml('Error')
        ->assertSeeInText('12 filas guardadas');

    expect($mail->envelope()->subject)->toContain('incompleto');
});

test('processing reports only rows committed for each api', function () {
    $api = Mockery::mock(VentasUsuarioApiService::class)->makePartial();
    $api->shouldReceive('currentToken')->once()->andReturn(null);
    $api->shouldReceive('generateToken')->once()->andReturn(new ExternalApiToken);
    $api->shouldReceive('fetchVentas')->once()->with('2026-10-24')->andReturn([['monto' => 10], ['monto' => 20]]);
    $api->shouldReceive('fetchPagos')->once()->andReturn([]);
    $api->shouldReceive('fetchPagosOtraEmpresa')->once()->andReturn([]);
    $api->shouldReceive('fetchVentasRaza')->once()->andReturn([]);

    $resultados = (new ProcesarTodoProgramadoService($api))->procesar('2026-10-24');

    expect($resultados['ventas-usuario'])->toBe(['estado' => 'ok', 'filas_guardadas' => 2])
        ->and($resultados['pagos-misma-empresa'])->toBe(['estado' => 'ok', 'filas_guardadas' => 0]);
    $this->assertDatabaseCount('ventas_usuarios', 2);
});

test('scheduled command emails an incomplete result when a module fails', function () {
    Mail::fake();
    config()->set('mail.procesar_todo_recipient', 'info@helpprocompany.com');
    mock(ProcesarTodoProgramadoService::class)
        ->shouldReceive('procesar')
        ->once()
        ->andReturn(['ventas-usuario' => ['estado' => 'error', 'filas_guardadas' => 0]]);

    $this->artisan('app:procesar-todo-programado')->assertFailed();

    Mail::assertSent(ResultadoProcesarTodo::class, function (ResultadoProcesarTodo $mail): bool {
        return $mail->hasTo('info@helpprocompany.com')
            && $mail->resultados === ['ventas-usuario' => ['estado' => 'error', 'filas_guardadas' => 0]];
    });
});
