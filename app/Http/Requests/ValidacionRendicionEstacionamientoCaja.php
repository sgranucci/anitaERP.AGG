<?php

namespace App\Http\Requests;

use App\Models\Caja\RendicionEstacionamientoCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionRendicionEstacionamientoCaja extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('movimientos') || ! is_array($this->input('movimientos'))) {
            $this->merge(['movimientos' => []]);
        }
    }

    public function rules(): array
    {
        $rendicionId = (int) ($this->route('id') ?? 0);
        $tipo = (string) $this->input('tipo', RendicionEstacionamientoCaja::TIPO_TURNO);
        $esJornada = $tipo === RendicionEstacionamientoCaja::TIPO_JORNADA;

        $codigoRules = $rendicionId > 0
            ? ['required', 'string', 'max:50']
            : ['nullable', 'string', 'max:50'];

        if ($esJornada) {
            $codigoRules = ['required', 'string', 'max:80'];
        }

        return [
            'tipo' => ['required', Rule::in([RendicionEstacionamientoCaja::TIPO_TURNO, RendicionEstacionamientoCaja::TIPO_JORNADA])],
            'codigo' => $codigoRules,
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'puntoventa_cae_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'puntoventa_caea_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'caja_id' => ['required', 'integer', 'min:1', 'exists:caja,id'],
            'turno_operativo_estacionamiento_id' => $esJornada
                ? ['nullable', 'integer']
                : [
                    'required',
                    'integer',
                    'exists:turno_operativo_estacionamiento,id',
                    Rule::unique('rendicion_estacionamiento_caja', 'turno_operativo_estacionamiento_id')
                        ->ignore($rendicionId),
                ],
            'jornada_estacionamiento_id' => $esJornada
                ? [
                    'required',
                    'integer',
                    'exists:jornada_estacionamiento,id',
                    Rule::unique('rendicion_estacionamiento_caja', 'jornada_estacionamiento_id')
                        ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
                        ->ignore($rendicionId),
                ]
                : ['nullable', 'integer'],
            'iniciodelfondo' => ['required', 'numeric'],
            'totalfactura' => ['required', 'numeric'],
            'totalcobrado' => ['required', 'numeric'],
            'totalinvitacion' => ['required', 'numeric'],
            'totalnotacredito' => ['required', 'numeric', 'min:0'],
            'totalredondeo' => ['required', 'numeric'],
            'totalredondeoinvitacion' => ['required', 'numeric'],
            'sobrantefaltante' => ['required', 'numeric'],
            'observacion' => ['nullable', 'string', 'max:65535'],
            'movimientos' => ['present', 'array'],
            'movimientos.*.cuentacaja_id' => ['required', 'integer', 'exists:cuentacaja,id'],
            'movimientos.*.monto' => ['required', 'numeric'],
            'movimientos.*.cotizacion' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de rendición',
            'codigo' => 'ticket / código',
            'caja_id' => 'caja',
            'turno_operativo_estacionamiento_id' => 'cierre de turno',
            'jornada_estacionamiento_id' => 'cierre de jornada',
            'iniciodelfondo' => 'inicio del fondo',
            'totalredondeo' => 'redondeo rendición',
            'totalredondeoinvitacion' => 'redondeo invitaciones',
            'sobrantefaltante' => 'sobrante / faltante',
            'movimientos' => 'medios de pago rendidos',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_id.min' => 'Debe tener una caja asignada para registrar la rendición (ingrese desde Movimientos de caja).',
            'movimientos.present' => 'Debe cargar el cierre de estacionamiento (Consultar) antes de guardar la rendición.',
            'caja_id.exists' => 'La caja indicada no existe o no está habilitada.',
            'jornada_estacionamiento_id.unique' => 'Esta jornada ya fue presentada en caja.',
            'turno_operativo_estacionamiento_id.unique' => 'Este cierre de turno ya fue rendido en caja.',
        ];
    }
}
