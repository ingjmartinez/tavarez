<?php

use App\Http\Controllers\CentroCostoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PagosMismaEmpresaController;
use App\Http\Controllers\PagosOtraEmpresaController;
use App\Http\Controllers\ProcesarTodoController;
use App\Http\Controllers\ReportePeriodoController;
use App\Http\Controllers\ReporteSubgruposController;
use App\Http\Controllers\ReporteVentasController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\VelzonRoutesController;
use App\Http\Controllers\VentasRazaController;
use App\Http\Controllers\VentasUsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/auth/signin-basic', [VelzonRoutesController::class, 'auth_signin_basic'])
    ->middleware('guest')
    ->name('auth.signin-basic');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Route::prefix('contabilidad-centro-costo')->name('centro-costo.')->controller(CentroCostoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/consultar', 'consultar')->name('consultar');
        Route::post('/sincronizar', 'sincronizar')->name('sincronizar');
        Route::delete('/eliminar', 'eliminar')->name('eliminar')->middleware('can:eliminar-datos');
    });

    Route::prefix('reporte-ventas')->name('reporte-ventas.')->controller(ReporteVentasController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/filtros', 'filtros')->name('filtros');
        Route::get('/datos', 'datos')->name('datos');
    });

    Route::prefix('reporte-subgrupos')->name('reporte-subgrupos.')->controller(ReporteSubgruposController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/datos', 'datos')->name('datos');
    });

    Route::prefix('reporte-periodo')->name('reporte-periodo.')->controller(ReportePeriodoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/datos', 'datos')->name('datos');
    });

    Route::prefix('mantenimiento-terminal')->name('terminal.')->controller(TerminalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/categorias-reporte', 'categoriasReporte')->name('categorias-reporte')->middleware('can:configurar-reportes');
        Route::put('/categorias-reporte', 'guardarCategoriasReporte')->name('guardar-categorias-reporte')->middleware('can:configurar-reportes');
        Route::get('/resumen', 'resumen')->name('resumen');
        Route::get('/inactivas', 'inactivas')->name('inactivas');
        Route::get('/sin-venta-30-dias', 'sinVentaTreintaDias')->name('sin-venta-30-dias');
        Route::get('/inactivas-con-venta-30-dias', 'inactivasConVentaTreintaDias')->name('inactivas-con-venta-30-dias');
    });

    Route::get('/api-procesar-todo', [ProcesarTodoController::class, 'index'])->name('procesar-todo.index');

    Route::prefix('api-ventas-usuario')->name('ventas-usuario.')->controller(VentasUsuarioController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/token', 'generarToken')->name('token');
        Route::get('/consultar', 'consultar')->name('consultar');
        Route::post('/guardar', 'guardar')->name('guardar');
        Route::delete('/eliminar', 'eliminar')->name('eliminar')->middleware('can:eliminar-datos');
    });

    Route::prefix('api-pagos-misma-empresa')->name('pagos-misma-empresa.')->controller(PagosMismaEmpresaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/consultar', 'consultar')->name('consultar');
        Route::post('/guardar', 'guardar')->name('guardar');
        Route::delete('/eliminar', 'eliminar')->name('eliminar')->middleware('can:eliminar-datos');
    });

    Route::prefix('api-pagos-otra-empresa')->name('pagos-otra-empresa.')->controller(PagosOtraEmpresaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/consultar', 'consultar')->name('consultar');
        Route::post('/guardar', 'guardar')->name('guardar');
        Route::delete('/eliminar', 'eliminar')->name('eliminar')->middleware('can:eliminar-datos');
    });

    Route::prefix('api-ventas-raza')->name('ventas-raza.')->controller(VentasRazaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/consultar', 'consultar')->name('consultar');
        Route::post('/guardar', 'guardar')->name('guardar');
        Route::delete('/eliminar', 'eliminar')->name('eliminar')->middleware('can:eliminar-datos');
    });

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/datos', [DashboardController::class, 'datos'])->name('dashboard.datos');
});
