<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTecnico_Ticket  extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->route('id');

        return [
            'nombre' => 'required|max:255|unique:tecnico_ticket,nombre,' . $id,
            'areadestino_id' => 'required',
            'usuario_id' => [
                'required',
                Rule::unique('tecnico_ticket', 'usuario_id')
                    ->where(fn ($q) => $q->where('areadestino_id', (int) $this->input('areadestino_id')))
                    ->ignore($id),
            ],
        ];
    }

    public function messages()
    {
        return [
            'usuario_id.unique' => 'Ese usuario ya está vinculado a otra ficha de técnico en la misma área. Cada técnico debe tener su propio usuario ERP para poder usar Tomar en la bandeja.',
        ];
    }
}
