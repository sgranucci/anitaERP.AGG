<?php

namespace App\Http\Requests;

use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTipoSancion_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('tipo_sancion_sueldos', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:60',
            'clase' => ['required', 'string', Rule::in(array_keys(Tipo_Sancion_Sueldos::CLASES))],
            'requiere_dias' => 'nullable|boolean',
            'tope_dias' => 'nullable|integer|min:0|max:999',
            'tipo_dias' => ['required', 'string', Rule::in(array_keys(Tipo_Sancion_Sueldos::TIPOS_DIA))],
            'goza_sueldo' => 'nullable|boolean',
            'genera_novedad' => 'nullable|boolean',
            'concepto_id' => 'nullable|integer|exists:concepto_sueldos,id',
            'orden_progresivo' => 'nullable|integer|min:1|max:99',
            'plazo_descargo_dias' => 'nullable|integer|min:0|max:30',
            'plantilla_notificacion' => 'nullable|string|max:4000',
            'activo' => 'nullable|boolean',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'clase' => 'clase',
            'concepto_id' => 'concepto',
        ];
    }
}
