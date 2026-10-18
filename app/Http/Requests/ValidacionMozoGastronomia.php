<?php

namespace App\Http\Requests;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionMozoGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');
        $esAlta = ! $id;

        $rules = [
            'nombre' => 'required|max:255',
            'codigo' => [
                'nullable',
                'max:50',
                Rule::unique('mozo_gastronomia', 'codigo')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'clave' => ($esAlta ? 'required' : 'nullable').'|string|min:4|max:60',
        ];

        if (EntornoEmpresaSupport::esCrown()) {
            $rules['sala_id'] = 'required|exists:sala,id';
        } else {
            $rules['sala_id'] = 'nullable|exists:sala,id';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
            'clave.required' => 'Indique la clave POS para el facturador de canjes marketing.',
            'sala_id.required' => 'Debe seleccionar la sala.',
        ];
    }
}
