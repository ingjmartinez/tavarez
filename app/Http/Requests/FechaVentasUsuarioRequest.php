<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FechaVentasUsuarioRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Debe indicar una fecha valida.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
        ];
    }
}
