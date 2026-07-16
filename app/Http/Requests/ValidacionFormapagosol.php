<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionFormapagosol extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|string|max:40',
        ];
    }

    public function attributes()
    {
        return [
            'nombre' => 'nombre',
        ];
    }
}
