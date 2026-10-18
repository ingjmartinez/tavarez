<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaRaza extends Model
{
    use HasFactory;

    protected $table = 'ventas_raza';

    protected $fillable = [
        'consorcio_id',
        'agencia_id',
        'proveedor_id',
        'proveedor_nombre',
        'ventas',
        'premios_pagados',
        'premios',
        'fecha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ventas' => 'decimal:2',
            'premios_pagados' => 'decimal:2',
            'premios' => 'decimal:2',
            'fecha' => 'date',
        ];
    }
}
