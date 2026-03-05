<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EliminarMasivoUsuario_CuentacontableRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
        ];
    }
}

