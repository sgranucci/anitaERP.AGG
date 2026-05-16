<?php

namespace App\Http\Requests;

use App\Models\Stock\DescuentoGastronomia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDescuentoGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:255',
            'codigo' => 'nullable|max:50|unique:descuento_gastronomia,codigo,'.$id,
            'tipovalor' => ['required', Rule::in([
                DescuentoGastronomia::TIPO_PORCENTAJE,
                DescuentoGastronomia::TIPO_IMPORTE,
                DescuentoGastronomia::TIPO_APLICA,
            ])],
            'valor' => 'required|numeric',
        ];
    }
}
