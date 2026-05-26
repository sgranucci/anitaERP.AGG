<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionConfiguracionPrestamo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enviar_aprobacion' => 'sometimes|boolean',
            'enviar_recordatorios' => 'sometimes|boolean',
            'dias_antes_devolucion_aviso' => 'required|integer|min:0|max:60',
            'dias_repeticion_vencido' => 'required|integer|min:1|max:60',
            'horas_validez_token' => 'required|integer|min:1|max:8760',
            'mail_asunto_aprobacion' => 'required|string|max:255',
            'mail_asunto_recordatorio' => 'required|string|max:255',
            'mail_asunto_devolucion_vencida' => 'required|string|max:255',
            'mail_asunto_aprobado_solicitante' => 'required|string|max:255',
            'mail_asunto_rechazado_solicitante' => 'required|string|max:255',
            'mail_remitente' => 'nullable|email|max:255',
            'mail_copia_a' => 'nullable|string|max:1000',
            'mail_texto_aprobacion' => 'nullable|string|max:5000',
            'mail_texto_recordatorio' => 'nullable|string|max:5000',
            'mail_texto_devolucion_vencida' => 'nullable|string|max:5000',
        ];
    }
}
