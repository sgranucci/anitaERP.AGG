<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionRendicionMaquinavendingCaja extends FormRequest
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

        return [
            'codigo' => ['required', 'string', 'max:50'],
            'maquinavending_rendicion_id' => [
                'required',
                'integer',
                'exists:maquinavending_rendicion,id',
                Rule::unique('rendicion_maquinavending_caja', 'maquinavending_rendicion_id')->ignore($rendicionId),
            ],
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'maquinavending_id' => ['required', 'integer', 'exists:maquinavending,id'],
            'puntoventa_cae_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'puntoventa_caea_id' => ['required', 'integer', 'exists:puntoventa,id'],
            'caja_id' => ['required', 'integer', 'min:1', 'exists:caja,id'],
            'fecharendicion' => ['required', 'date'],
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
}
