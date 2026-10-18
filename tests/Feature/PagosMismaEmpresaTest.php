<?php

use App\Models\ExternalApiToken;
use App\Models\PagoMismaEmpresa;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ventas_usuario', [
        'token_url' => 'https://api.test/api/v1/token-path',
        'ventas_url' => 'https://api.test/api/V1/ventas-path',
        'pagos_url' => 'https://api.test/api/V1/pagos-path',
        'codigo' => '08',
        'header_cc' => 'cc-value',
        'header_vb' => 'vb-value',
        'timeout' => 5,
    ]);

    $this->actingAs(User::factory()->create());
});

function pagosApiRows(): array
{
    return [
        ['fecha' => '2026-10-14', 'consorcio_id' => 8, 'monto' => 3000, 'pagado_agencia_id' => '08125009', 'agencia_id' => '08125009', 'producto_id' => 0, 'descripcion' => 'LOTEDOM 10PM QUINIELA', 'plataforma_pago' => 'LOTOBET'],
        ['fecha' => '2026-10-14', 'consorcio_id' => 8, 'monto' => 100.5, 'pagado_agencia_id' => '08125014', 'agencia_id' => '08125014', 'producto_id' => 0, 'descripcion' => 'LOTEDOM 10PM QUINIELA', 'plataforma_pago' => 'LOTOBET'],
    ];
}

test('guests cannot open the pagos misma empresa module', function () {
    auth()->logout();

    $this->get(route('pagos-misma-empresa.index'))->assertRedirect();
});

test('the pagos page renders with the shared token status', function () {
    ExternalApiToken::factory()->create();

    $this->get(route('pagos-misma-empresa.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos-misma-empresa/index')
            ->where('tokenValid', true));
});

test('pagos are consulted by date using the shared token', function () {
    ExternalApiToken::factory()->create(['token' => 'tok-1']);
    Http::fake(['api.test/*' => Http::response(['code' => 0, 'Content' => pagosApiRows()])]);

    $this->getJson(route('pagos-misma-empresa.consultar', ['fecha' => '2026-10-14']))
        ->assertOk()
        ->assertJsonCount(2, 'pagos');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.test/api/V1/pagos-path/tok-1/2026-10-14/08');
});

test('consulting pagos without a valid token is rejected', function () {
    $this->getJson(route('pagos-misma-empresa.consultar', ['fecha' => '2026-10-14']))->assertStatus(404);

    ExternalApiToken::factory()->expired()->create();

    $this->getJson(route('pagos-misma-empresa.consultar', ['fecha' => '2026-10-14']))->assertStatus(401);
});

test('pagos are saved with the selected date', function () {
    ExternalApiToken::factory()->create();
    Http::fake(['api.test/*' => Http::response(['code' => 0, 'Content' => pagosApiRows()])]);

    $this->postJson(route('pagos-misma-empresa.guardar'), ['fecha' => '2026-10-14'])
        ->assertOk()
        ->assertJsonPath('total', 2);

    expect(PagoMismaEmpresa::count())->toBe(2)
        ->and(PagoMismaEmpresa::where('agencia_id', '08125009')->first())
        ->consorcio_id->toBe('8')
        ->plataforma_pago->toBe('LOTOBET')
        ->fecha->toDateString()->toBe('2026-10-14');
});

test('saving a date that already has pagos is rejected without calling the api', function () {
    ExternalApiToken::factory()->create();
    PagoMismaEmpresa::factory()->create(['fecha' => '2026-10-14']);
    Http::fake();

    $this->postJson(route('pagos-misma-empresa.guardar'), ['fecha' => '2026-10-14'])->assertStatus(409);

    Http::assertNothingSent();
    expect(PagoMismaEmpresa::count())->toBe(1);
});

test('pagos of one date can be deleted without touching other dates', function () {
    PagoMismaEmpresa::factory()->count(2)->create(['fecha' => '2026-10-14']);
    PagoMismaEmpresa::factory()->create(['fecha' => '2026-10-15']);

    $this->deleteJson(route('pagos-misma-empresa.eliminar', ['fecha' => '2026-10-14']))
        ->assertOk()
        ->assertJsonPath('total', 2);

    expect(PagoMismaEmpresa::count())->toBe(1);
});
