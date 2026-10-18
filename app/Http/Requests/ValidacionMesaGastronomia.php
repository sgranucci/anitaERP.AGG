<?php

namespace App\Http\Requests;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionMesaGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $rules = [
            'nombre' => 'required|max:255',
            'ubicacion_id' => 'nullable|exists:ubicaciones_gastronomia,id',
            'numeromesa' => 'required|max:50',
            'codigo' => 'nullable|max:50|unique:mesa_gastronomia,codigo,'.$id,
            'empresa_id' => 'required|exists:empresa,id',
        ];

        if (EntornoEmpresaSupport::esCrown()) {
            $rules['sala_id'] = 'required|exists:sala,id';
        } else {
            $rules['sala_id'] = 'nullable|exists:sala,id';
        }

        return $rules;
    }
}
