<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaUsuario extends Model
{
    use HasFactory;

    protected $table = 'ventas_usuarios';

    protected $fillable = [
        'consorcio_id',
        'agencia_id',
        'cedula',
        'tipo',
        'producto_id',
        'descripcion',
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
