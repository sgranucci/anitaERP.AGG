<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionRendicionGastronomiaCaja extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rendicionId = (int) ($this->route('id') ?? 0);

        return [
            'codigo' => $rendicionId > 0
                ? ['required', 'string', 'max:50']
                : ['nullable', 'string', 'max:50'],
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'puntoventa_cae_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'puntoventa_caea_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'caja_id' => ['required', 'integer', 'exists:caja,id'],
            'fecharendicion' => ['required', 'date'],
            'turno_operativo_gastronomia_id' => [
                'required',
                'integer',
                'exists:turno_operativo_gastronomia,id',
                Rule::unique('rendicion_gastronomia_caja', 'turno_operativo_gastronomia_id')
                    ->ignore($rendicionId),
            ],
            'iniciodelfondo' => ['required', 'numeric'],
            'totalfactura' => ['required', 'numeric'],
            'totalcobrado' => ['required', 'numeric'],
            'totalinvitacion' => ['required', 'numeric'],
            'totalnotacredito' => ['required', 'numeric', 'min:0'],
            'totalredondeo' => ['required', 'numeric'],
            'totalredondeoinvitacion' => ['required', 'numeric'],
            'sobrantefaltante' => ['required', 'numeric'],
            'observacion' => ['nullable', 'string', 'max:65535'],
            'movimientos' => ['required', 'array', 'min:1'],
            'movimientos.*.cuentacaja_id' => ['required', 'integer', 'exists:cuentacaja,id'],
            'movimientos.*.monto' => ['required', 'numeric'],
            'movimientos.*.cotizacion' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'ticket / código',
            'turno_operativo_gastronomia_id' => 'cierre de turno',
            'iniciodelfondo' => 'inicio del fondo',
            'totalredondeo' => 'redondeo rendición',
            'totalredondeoinvitacion' => 'redondeo invitaciones',
            'sobrantefaltante' => 'sobrante / faltante',
        ];
    }
}
