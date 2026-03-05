<?php

namespace App\Http\Requests;

use App\Models\Stock\Condicionpago;
use Illuminate\Foundation\Http\FormRequest;

class EliminarMasivoCondicionpagoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:condicionpago,id',
        ];
    }
}

