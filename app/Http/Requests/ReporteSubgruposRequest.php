<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteSubgruposRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:'.now()->addDay()->toDateString()],
            'modo' => ['required', Rule::in(['subgrupo', 'sociedad'])],
            'categoria' => ['required_without:categorias', 'string', 'max:191'],
            'categorias' => ['sometimes', 'array', 'min:1'],
            'categorias.*' => ['required', 'string', 'max:191', 'distinct'],
            'por_terminal' => ['sometimes', 'boolean'],
        ];
    }
}
