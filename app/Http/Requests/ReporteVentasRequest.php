<?php

namespace App\Http\Requests;

use App\Services\Reporte\ReporteVentasService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:'.now()->addDay()->toDateString()],
            'agrupar' => ['required', 'array', 'min:1', 'max:'.count(ReporteVentasService::DIMENSIONES)],
            'agrupar.*' => ['string', 'distinct', Rule::in(array_keys(ReporteVentasService::DIMENSIONES))],
            'grupo' => ['nullable', 'string', 'max:150'],
            'subgrupo' => ['nullable', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:150'],
            'sociedad' => ['nullable', 'string', 'max:150'],
            'grupos' => ['sometimes', 'array', 'min:1'],
            'grupos.*' => ['required', 'string', 'max:150', 'distinct'],
            'subgrupos' => ['sometimes', 'array', 'min:1'],
            'subgrupos.*' => ['required', 'string', 'max:150', 'distinct'],
            'divisiones' => ['sometimes', 'array', 'min:1'],
            'divisiones.*' => ['required', 'string', 'max:150', 'distinct'],
            'sociedades' => ['sometimes', 'array', 'min:1'],
            'sociedades.*' => ['required', 'string', 'max:150', 'distinct'],
            'tipo' => ['nullable', 'string', Rule::in(ReporteVentasService::TIPOS_PRODUCTO)],
            'estatus' => ['nullable', Rule::in(['todos', 'activas', 'inactivas'])],
            'buscar' => ['nullable', 'string', 'max:60'],
            'raza' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.required' => 'Indique la fecha desde.',
            'hasta.required' => 'Indique la fecha hasta.',
            'hasta.after_or_equal' => 'La fecha hasta no puede ser menor que la fecha desde.',
            'agrupar.required' => 'Seleccione al menos una opcion para agrupar.',
            'agrupar.min' => 'Seleccione al menos una opcion para agrupar.',
        ];
    }
}
