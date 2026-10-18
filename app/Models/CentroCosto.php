<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    use HasFactory;

    protected $table = 'centros_costo';

    protected $fillable = [
        'empresa_id',
        'id_centro_costo',
        'company',
        'descripcion',
        'cuenta',
        'inactivo',
        'id_grupo',
        'id_sub_grupo',
        'id_division',
        'id_sociedad',
        'id_viejo',
        'id_centro_costo_resumir_en',
        'com_recarga',
        'gasto_vta_tradicional',
        'varios_locales',
        'aplica_para_ponderar',
        'valor_ponderar',
        'creado_por',
        'fecha_grabado',
        'modificado_por',
        'fecha_modificado',
        'atributos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inactivo' => 'boolean',
            'com_recarga' => 'boolean',
            'gasto_vta_tradicional' => 'boolean',
            'varios_locales' => 'boolean',
            'aplica_para_ponderar' => 'boolean',
            'valor_ponderar' => 'decimal:2',
            'fecha_grabado' => 'datetime',
            'fecha_modificado' => 'datetime',
            'atributos' => 'array',
        ];
    }
}
