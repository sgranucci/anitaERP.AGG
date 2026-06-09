<?php

namespace App\Http\Requests;

use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionUsoSalidaImpresora extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:uso_salida_impresora,nombre,'.$this->route('id'),
            'descripcion' => 'nullable|max:2000',
            'programas_destino' => 'nullable|array',
            'programas_destino.*' => ['string', Rule::in(SeteoSalidaProgramaSupport::codigosPrograma())],
        ];
    }
}
