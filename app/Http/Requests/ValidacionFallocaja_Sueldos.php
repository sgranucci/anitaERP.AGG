<?php

namespace App\Http\Requests;

use App\Support\Sueldos\FalloCajaTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionFallocaja_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'tipo' => ['required', Rule::in(FalloCajaTipo::OPCIONES)],
            'orden' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('fallocaja_sueldos', 'orden')
                    ->where(fn ($q) => $q->where('tipo', $this->input('tipo')))
                    ->ignore($id),
            ],
            'desde' => 'required|numeric|min:0',
            'hasta' => 'required|numeric|min:0|gte:desde',
            'sancion' => 'required|string|max:40',
        ];
    }

    public function attributes()
    {
        return [
            'tipo' => 'tipo',
            'orden' => 'orden',
            'desde' => 'desde',
            'hasta' => 'hasta',
            'sancion' => 'sanción',
        ];
    }

    public function messages()
    {
        return [
            'orden.unique' => 'Ya existe un fallo con ese orden para el tipo seleccionado.',
            'hasta.gte' => 'El «hasta» debe ser mayor o igual que el «desde».',
        ];
    }
}
