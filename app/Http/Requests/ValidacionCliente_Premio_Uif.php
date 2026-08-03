<?php

namespace App\Http\Requests;

use App\Support\Uif\ClienteUifArchivoStorage;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionCliente_Premio_Uif extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'cliente_uif_id' => 'required|integer|exists:cliente_uif,id',
            'sala_id' => [
                'required',
                'integer',
                'exists:sala,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $esperada = $this->salaEsperadaDesdeCliente();
                    if ($esperada === null) {
                        $fail('No se pudo determinar la sala del cliente UIF.');

                        return;
                    }
                    if ((int) $value !== $esperada) {
                        $fail('La sala del premio debe coincidir con el origen del cliente (sala_id '.$esperada.').');
                    }
                },
            ],
            'juego_uif_id' => 'required|integer|exists:juego_uif,id',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'monto' => 'required|numeric|min:0.01',
            'fechaentrega' => 'required|date',
            'formapago_id' => 'nullable|integer|exists:formapago,id',
            'fechatito' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'cliente_uif_id.required' => 'Falta el cliente UIF del premio.',
            'cliente_uif_id.exists' => 'El cliente UIF no existe.',
            'sala_id.required' => 'Debe seleccionar la sala.',
            'juego_uif_id.required' => 'Debe seleccionar el juego.',
            'moneda_id.required' => 'Debe seleccionar la moneda.',
            'monto.required' => 'Debe ingresar el monto del premio.',
            'monto.min' => 'El monto del premio debe ser mayor a cero.',
            'fechaentrega.required' => 'Debe ingresar la fecha de entrega del premio.',
            'fechaentrega.date' => 'La fecha de entrega no es válida.',
        ];
    }

    protected function prepareForValidation()
    {
        $fechatito = $this->input('fechatito');
        if ($fechatito === '' || $fechatito === null) {
            $this->merge(['fechatito' => null]);
        }

        $sala = $this->salaEsperadaDesdeCliente();
        if ($sala !== null) {
            $this->merge(['sala_id' => $sala]);
        }
    }

    private function salaEsperadaDesdeCliente(): ?int
    {
        $clienteId = (int) $this->input('cliente_uif_id', 0);
        $origen = ClienteUifOrigenPcSupport::origenDeClienteId($clienteId);
        if ($origen !== null) {
            return ClienteUifArchivoStorage::salaId($origen);
        }

        try {
            return ClienteUifOrigenPcSupport::resolverParaEscritura(
                $this,
                (int) $this->input('empresa_id', 0) ?: null
            )['sala_id'];
        } catch (\Throwable) {
            return null;
        }
    }
}
