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
            'programacion_hora_hasta' => 'nullable|date_format:H:i',
            'programacion_dias' => 'nullable|array',
            'programacion_dias.*' => 'integer|between:1,7',
            'programacion_cada_n' => 'nullable|integer|min:1|max:52',
            'programacion_hasta' => 'nullable|date',
            'programacion_dia_mes' => 'nullable|integer|min:1|max:31',
            'programacion_skip_feriados' => 'nullable|boolean',
            'programacion_pausada' => 'nullable|boolean',
            'programacion_reintentos_max' => 'nullable|integer|min:0|max:96',
            'programacion_depende_de' => 'nullable|string|max:160',
            'programacion_depende_modo' => 'nullable|in:ok_hoy,ok_reciente,sin_fallo_hoy',
            'parametro_dias' => 'nullable|integer|min:0|max:365',
            'notificar_in_app' => 'nullable|boolean',
            'notificar_whatsapp' => 'nullable|boolean',
            'sla_horas' => 'nullable|integer|min:0|max:8760',
            'destinatarios' => 'nullable|array',
            'destinatarios.*.id' => 'nullable|integer|min:0',
            'destinatarios.*.email' => 'nullable|email|max:255',
            'destinatarios.*.usuario_id' => 'nullable|integer|min:1',
            'destinatarios.*.empresa_id' => 'nullable|integer|min:1',
            'destinatarios.*.centrocosto_id' => 'nullable|integer|min:1',
            'destinatarios.*.activo' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('programacion_habilitada')) {
                return;
            }
            $diaMes = $this->filled('programacion_dia_mes') ? (int) $this->input('programacion_dia_mes') : null;
            $dias = $this->input('programacion_dias', []);
            if ($diaMes === null && (! is_array($dias) || $dias === [])) {
                $validator->errors()->add(
                    'programacion_dias',
                    'Marcá al menos un día de la semana, o indicá un día del mes.'
                );
            }
        });
    }
}
