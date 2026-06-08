<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDepmae extends FormRequest
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
            'codigo' => [
                'required',
                'max:20',
                Rule::unique('depmae', 'codigo')->ignore($id)->where(function ($query) {
                    return $query->where('empresa_id', $this->get('empresa_id'));
                }),
            ],
            'nombre' => 'required|max:50|unique:depmae,nombre,'.$id,
            'tipodeposito' => 'required',
            'empresa_id' => 'required|integer|exists:empresa,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'descripción',
        ];
    }
}
