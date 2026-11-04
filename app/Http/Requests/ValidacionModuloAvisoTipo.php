<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionModuloAvisoTipo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activo' => 'nullable|boolean',
            'mail_asunto' => 'required|string|max:255',
            'mail_texto' => 'nullable|string|max:8000',
            'mail_remitente' => 'nullable|email|max:255',
            'adjuntar_pdf' => 'nullable|boolean',
            'incluir_link_consulta' => 'nullable|boolean',
            'programacion_habilitada' => 'nullable|boolean',
            'programacion_hora' => 'required_if:programacion_habilitada,1|nullable|date_format:H:i',
            'programacion_dias' => 'required_if:programacion_habilitada,1|nullable|array|min:1',
            'programacion_dias.*' => 'integer|between:1,7',
            'destinatarios' => 'nullable|array',
            'destinatarios.*.id' => 'nullable|integer|min:0',
            'destinatarios.*.email' => 'nullable|email|max:255',
            'destinatarios.*.usuario_id' => 'nullable|integer|min:1',
            'destinatarios.*.empresa_id' => 'nullable|integer|min:1',
            'destinatarios.*.centrocosto_id' => 'nullable|integer|min:1',
            'destinatarios.*.activo' => 'nullable|boolean',
        ];
    }
}
