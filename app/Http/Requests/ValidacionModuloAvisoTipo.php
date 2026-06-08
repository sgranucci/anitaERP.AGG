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
