<?php

namespace App\Http\Requests;

use App\Models\Caja\RendicionGastronomiaCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionRendicionGastronomiaCaja extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipo = (string) $this->input('tipo', RendicionGastronomiaCaja::TIPO_TURNO);
        if ($tipo === RendicionGastronomiaCaja::TIPO_JORNADA && ! $this->has('movimientos')) {
            $this->merge(['movimientos' => []]);
        }
    }

    public function rules(): array
    {
        $rendicionId = (int) ($this->route('id') ?? 0);
        $tipo = (string) $this->input('tipo', RendicionGastronomiaCaja::TIPO_TURNO);
        $esJornada = $tipo === RendicionGastronomiaCaja::TIPO_JORNADA;

        $codigoRules = $rendicionId > 0
            ? ['required', 'string', 'max:50']
            : ['nullable', 'string', 'max:50'];

        if ($esJornada) {
            $codigoRules = ['required', 'string', 'max:80'];
        }

        return [
            'tipo' => ['required', Rule::in([RendicionGastronomiaCaja::TIPO_TURNO, RendicionGastronomiaCaja::TIPO_JORNADA])],
            'codigo' => $codigoRules,
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'puntoventa_cae_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'puntoventa_caea_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'caja_id' => ['required', 'integer', 'min:1', 'exists:caja,id'],
            'fecharendicion' => ['required', 'date'],
            'turno_operativo_gastronomia_id' => $esJornada
                ? ['nullable', 'integer']
                : [
                    'required',
                    'integer',
                    'exists:turno_operativo_gastronomia,id',
                    Rule::unique('rendicion_gastronomia_caja', 'turno_operativo_gastronomia_id')
                        ->ignore($rendicionId),
                ],
            'jornada_gastronomia_id' => $esJornada
                ? [
                    'required',
                    'integer',
                    'exists:jornada_gastronomia,id',
                    Rule::unique('rendicion_gastronomia_caja', 'jornada_gastronomia_id')
                        ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
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
            'movimientos' => $esJornada
                ? ['nullable', 'array']
                : ['required', 'array', 'min:1'],
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
            'turno_operativo_gastronomia_id' => 'cierre de turno',
            'jornada_gastronomia_id' => 'cierre de jornada',
            'iniciodelfondo' => 'inicio del fondo',
            'totalredondeo' => 'redondeo rendición',
            'totalredondeoinvitacion' => 'redondeo invitaciones',
            'sobrantefaltante' => 'sobrante / faltante',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_id.min' => 'Debe tener una caja asignada para registrar la rendición (ingrese desde Movimientos de caja).',
            'caja_id.exists' => 'La caja indicada no existe o no está habilitada.',
            'jornada_gastronomia_id.unique' => 'Esta jornada ya fue presentada en caja.',
            'turno_operativo_gastronomia_id.unique' => 'Este cierre de turno ya fue rendido en caja.',
        ];
    }
}
