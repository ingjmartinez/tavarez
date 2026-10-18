<?php

use App\Models\ExternalApiToken;
use App\Models\User;
use App\Models\VentaUsuario;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ventas_usuario', [
        'token_url' => 'https://api.test/api/v1/token-path',
        'ventas_url' => 'https://api.test/api/V1/ventas-path',
        'codigo' => '08',
        'header_cc' => 'cc-value',
        'header_vb' => 'vb-value',
        'timeout' => 5,
    ]);

    $this->actingAs(User::factory()->create());
});

function ventasApiRows(): array
{
    return [
        ['consorcio_id' => '01', 'agencia_id' => '1001', 'producto_id' => '5', 'cedula' => '001-1234567-8', 'descripcion' => 'Quiniela', 'tipo' => 'VENTA', 'monto' => 150.5],
        ['consorcio_id' => '01', 'agencia_id' => '1002', 'producto_id' => '6', 'cedula' => '000-0000000-0', 'descripcion' => 'Pale', 'tipo' => 'VENTA', 'monto' => 20],
    ];
}

test('guests cannot open the ventas usuario module', function () {
    auth()->logout();

    $this->get(route('ventas-usuario.index'))->assertRedirect();
});

test('the ventas usuario page renders with the token status', function () {
    ExternalApiToken::factory()->create();

    $this->get(route('ventas-usuario.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ventas-usuario/index')
            ->where('tokenValid', true));
});

test('a token is generated and stored with its expiration', function () {
    Http::fake([
        'api.test/api/v1/token-path' => Http::response([
            'Content' => ['Token' => 'abc-123', 'DateExpire' => now()->addHour()->toIso8601String()],
        ]),
    ]);

    $this->postJson(route('ventas-usuario.token'))->assertOk()->assertJsonPath('message', 'Token generado y guardado correctamente.');

    $token = ExternalApiToken::query()->where('name', 'ventas_usuario')->first();
    expect($token->token)->toBe('abc-123')->and($token->isValid())->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->hasHeader('AhfCC', 'cc-value') && $request->hasHeader('AhfVB', 'vb-value'));
});

test('generating a token reports api failures', function () {
    Http::fake(['api.test/*' => Http::response(['msg' => 'Credenciales invalidas'], 401)]);

    $this->postJson(route('ventas-usuario.token'))->assertStatus(401)->assertJsonPath('message', 'Credenciales invalidas');

    expect(ExternalApiToken::count())->toBe(0);
});

test('consulting without a valid token is rejected', function () {
    $this->getJson(route('ventas-usuario.consultar', ['fecha' => '2026-10-14']))->assertStatus(404);

    ExternalApiToken::factory()->expired()->create();

    $this->getJson(route('ventas-usuario.consultar', ['fecha' => '2026-10-14']))
        ->assertStatus(401)
        ->assertJsonPath('message', 'El token ha expirado, genere uno nuevo.');
});

test('ventas are consulted by date using the stored token', function () {
    ExternalApiToken::factory()->create(['token' => 'tok-1']);
    Http::fake(['api.test/*' => Http::response(['code' => 0, 'Content' => ventasApiRows()])]);

    $this->getJson(route('ventas-usuario.consultar', ['fecha' => '2026-10-14']))
        ->assertOk()
        ->assertJsonCount(2, 'ventas');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.test/api/V1/ventas-path/tok-1/2026-10-14/08');
});

test('consulting validates the date', function () {
    $this->getJson(route('ventas-usuario.consultar'))->assertStatus(422)->assertJsonValidationErrors('fecha');
    $this->getJson(route('ventas-usuario.consultar', ['fecha' => '14/10/2026']))->assertStatus(422);
});

test('ventas are saved with normalized cedulas', function () {
    ExternalApiToken::factory()->create();
    Http::fake(['api.test/*' => Http::response(['code' => 0, 'Content' => ventasApiRows()])]);

    $this->postJson(route('ventas-usuario.guardar'), ['fecha' => '2026-10-14'])
        ->assertOk()
        ->assertJsonPath('total', 2);

    expect(VentaUsuario::count())->toBe(2)
        ->and(VentaUsuario::where('agencia_id', '1001')->value('cedula'))->toBe('00112345678')
        ->and(VentaUsuario::where('agencia_id', '1002')->value('cedula'))->toBeNull();
});

test('saving a date that already has data is rejected without calling the api', function () {
    ExternalApiToken::factory()->create();
    VentaUsuario::factory()->create(['fecha' => '2026-10-14']);
    Http::fake();

    $this->postJson(route('ventas-usuario.guardar'), ['fecha' => '2026-10-14'])->assertStatus(409);

    Http::assertNothingSent();
    expect(VentaUsuario::count())->toBe(1);
});

test('ventas of one date can be deleted without touching other dates', function () {
    VentaUsuario::factory()->count(2)->create(['fecha' => '2026-10-14']);
    VentaUsuario::factory()->create(['fecha' => '2026-10-15']);

    $this->deleteJson(route('ventas-usuario.eliminar', ['fecha' => '2026-10-14']))
        ->assertOk()
        ->assertJsonPath('total', 2);

    expect(VentaUsuario::count())->toBe(1);
});
