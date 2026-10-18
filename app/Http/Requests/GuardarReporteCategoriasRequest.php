<?php

namespace App\Http\Requests;

use App\Services\Reporte\ReporteCategoriasService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarReporteCategoriasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configurar-reportes') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activas' => ['present', 'array'],
            'activas.*' => ['required', 'array:dimension,valor'],
            'activas.*.dimension' => ['required', 'string', Rule::in(array_keys(ReporteCategoriasService::DIMENSIONES))],
            'activas.*.valor' => ['required', 'string', 'max:255'],
        ];
    }
}
