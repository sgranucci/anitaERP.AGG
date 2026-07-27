<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicionSalaEdicionMenor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_entrega' => 'required|date',
            'zona_sala_id' => 'nullable|integer|exists:zona_sala,id',
            'prioridad_sala_id' => 'nullable|integer|exists:prioridad_sala,id',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'nullable|string',
            'requisicion_sala_articulo_ids' => 'nullable|array',
            'requisicion_sala_articulo_ids.*' => 'nullable|integer|exists:requisicion_sala_articulo,id',
            'detalle_articulos' => 'nullable|array',
            'detalle_articulos.*' => 'nullable|string|max:2000',
            'uids' => 'nullable|array',
            'uids.*' => 'nullable|string|max:50',
            'numeropartes' => 'nullable|array',
            'numeropartes.*' => 'nullable|string|max:50',
        ];
    }
}
