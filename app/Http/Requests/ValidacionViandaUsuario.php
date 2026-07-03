<?php

namespace App\Http\Requests;

use App\Support\Ventas\ViandaUsuarioTipoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionViandaUsuario extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = (int) $this->route('id');

        return [
            'codigo_usuario' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('vianda_usuario', 'codigo_usuario')->ignore($id > 0 ? $id : null),
            ],
            'nombre' => 'required|string|max:255',
            'password' => 'required|string|max:15',
            'centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'tipo_usuario' => ['required', Rule::in(ViandaUsuarioTipoSupport::tiposValidos())],
            'vianda_tipo_menu_id' => 'nullable|integer|exists:vianda_tipo_menu,id',
            'estado' => 'required|in:A,I',
        ];
    }

    public function messages()
    {
        return [
            'codigo_usuario.unique' => 'Ya existe un usuario de vianda con ese código.',
            'tipo_usuario.in' => 'El tipo de usuario no es válido.',
        ];
    }
}
