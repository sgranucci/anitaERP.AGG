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

    protected function prepareForValidation(): void
    {
        $centrocostoId = $this->input('centrocosto_id');
        if ($centrocostoId === '' || $centrocostoId === null) {
            $this->merge(['centrocosto_id' => null]);
        }

        $tipoMenuId = $this->input('vianda_tipo_menu_id');
        if ($tipoMenuId === '' || $tipoMenuId === null) {
            $this->merge(['vianda_tipo_menu_id' => null]);
        }
    }

    public function rules()
    {
        $id = (int) $this->route('id');
        $empresaId = (int) $this->input('empresa_id');
        $esAlta = $id <= 0;

        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'codigo_usuario' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('vianda_usuario', 'codigo_usuario')
                    ->where(fn ($query) => $query->where('empresa_id', $empresaId))
                    ->ignore($id > 0 ? $id : null),
            ],
            'nombre' => 'required|string|max:255',
            // En edición la clave puede no viajar (modo consulta la oculta): sin valor se conserva la actual.
            'password' => ($esAlta ? 'required' : 'nullable').'|string|max:15',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'tipo_usuario' => ['required', Rule::in(ViandaUsuarioTipoSupport::tiposValidos())],
            'vianda_tipo_menu_id' => 'nullable|integer|exists:vianda_tipo_menu,id',
            'estado' => 'required|in:A,I',
        ];
    }

    public function messages()
    {
        return [
            'empresa_id.required' => 'Debe seleccionar la empresa del usuario de vianda.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'codigo_usuario.unique' => 'Ya existe un usuario de vianda con ese código en esa empresa.',
            'centrocosto_id.required' => 'Debe seleccionar el centro de costo del usuario de vianda.',
            'centrocosto_id.exists' => 'El centro de costo seleccionado no existe.',
            'tipo_usuario.in' => 'El tipo de usuario no es válido.',
        ];
    }
}
