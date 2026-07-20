<?php

namespace App\Http\Requests;

use App\Support\Sueldos\CategoriaOrigenBases;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCategoria_Sueldos extends FormRequest
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
                Rule::unique('categoria_sueldos', 'codigo')->ignore($id),
            ],
            'descripcion' => 'required|string|max:30',
            'origen_bases' => ['required', Rule::in(array_keys(CategoriaOrigenBases::LABELS))],
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'origen_bases' => 'origen de las bases',
        ];
    }
}
