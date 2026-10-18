<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoMismaEmpresa extends Model
{
    use HasFactory;

    protected $table = 'pagos_misma_empresa';

    protected $fillable = [
        'consorcio_id',
        'agencia_id',
        'pagado_agencia_id',
        'producto_id',
        'descripcion',
        'plataforma_pago',
        'monto',
        'fecha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }
}
