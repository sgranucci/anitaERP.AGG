<?php

namespace App\Http\Requests;

use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEstacionamientoDescuento extends FormRequest
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
            'codigo' => 'nullable|max:50|unique:descuento_estacionamiento,codigo,'.$id,
            'tipovalor' => ['required', Rule::in([
                DescuentoEstacionamiento::TIPO_PORCENTAJE,
                DescuentoEstacionamiento::TIPO_IMPORTE,
                DescuentoEstacionamiento::TIPO_APLICA,
            ])],
            'valor' => 'required|numeric',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
        ];
    }
}
