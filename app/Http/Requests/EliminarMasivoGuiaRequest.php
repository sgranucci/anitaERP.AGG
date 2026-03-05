<?php

namespace App\Http\Requests;

use App\Models\Stock\Condicionpago;
use Illuminate\Foundation\Http\FormRequest;

class EliminarMasivoGuiaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:guia,id',
        ];
    }
}

