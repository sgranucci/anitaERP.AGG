<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionConfiguracionAsientoContable extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enviar_mail_aprobacion' => ['nullable', 'boolean'],
            'mail_aprobador' => ['nullable', 'email', 'max:255'],
            'mail_copia_a' => ['nullable', 'string', 'max:255'],
            'horas_validez_token' => ['required', 'integer', 'min:1', 'max:8760'],
            'mail_asunto_aprobacion' => ['required', 'string', 'max:255'],
            'mail_texto_aprobacion' => ['nullable', 'string'],
            'mail_asunto_aprobado_solicitante' => ['required', 'string', 'max:255'],
            'mail_asunto_rechazado_solicitante' => ['required', 'string', 'max:255'],
        ];
    }
}
