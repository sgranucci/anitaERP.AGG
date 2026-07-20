<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionArt_Sueldos extends FormRequest
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
                Rule::requiredIf(fn () => empty($id)),
                'nullable',
                'string',
                'max:15',
                Rule::unique('art_sueldos', 'codigo')->ignore($id),
            ],
            'nombre' => 'required|string|max:30',
        ];
    }

    public function attributes()
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
        ];
    }
}
