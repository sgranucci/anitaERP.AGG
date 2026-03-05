<?php

namespace App\Http\Requests;

use App\Models\Stock\Modulo;
use Illuminate\Foundation\Http\FormRequest;

class EliminarMasivoModuloRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:modulo,id',
        ];
    }
}

