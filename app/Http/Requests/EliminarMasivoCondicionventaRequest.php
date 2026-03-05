<?php

namespace App\Http\Requests;

use App\Models\Stock\Condicionventa;
use Illuminate\Foundation\Http\FormRequest;

class EliminarMasivoCondicionventaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:condicionventa,id',
        ];
    }
}

