<?php

namespace App\Http\Requests;

use App\Models\Uif\Cliente_Premio_Uif;
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
        $salasUif = $this->salaIdsUifPermitidas();

        return [
            'cliente_uif_id' => 'required|integer|exists:cliente_uif,id',
            'sala_id' => [
                'required',
                'integer',
                'exists:sala,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($salasUif) {
                    if ($salasUif !== [] && ! in_array((int) $value, $salasUif, true)) {
                        $fail('La sala del premio debe ser una sala UIF (BSA/KSA/RSA).');
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

        // Edición: conservar la sala ya grabada (cliente multi-sala).
        $premioId = (int) $this->route('id', 0);
        if ($premioId > 0) {
            $salaExistente = (int) (Cliente_Premio_Uif::query()->whereKey($premioId)->value('sala_id') ?? 0);
            if ($salaExistente > 0) {
                $this->merge(['sala_id' => $salaExistente]);

                return;
            }
        }

        // Alta: sala del contexto de escritura (PC / empresa), no forzar origen de la ficha.
        $sala = $this->salaDesdeContextoEscritura();
        if ($sala !== null) {
            $this->merge(['sala_id' => $sala]);
        }
    }

    private function salaDesdeContextoEscritura(): ?int
    {
        try {
            return ClienteUifOrigenPcSupport::resolverParaEscritura(
                $this,
                (int) $this->input('empresa_id', 0) ?: null
            )['sala_id'];
        } catch (\Throwable) {
            // fallback: origen de carga del cliente
        }

        $clienteId = (int) $this->input('cliente_uif_id', 0);
        $origen = ClienteUifOrigenPcSupport::origenDeClienteId($clienteId);
        if ($origen !== null) {
            return ClienteUifArchivoStorage::salaId($origen);
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function salaIdsUifPermitidas(): array
    {
        $ids = [];
        foreach (config('uif.anita_origenes', []) as $cfg) {
            $sid = (int) ($cfg['sala_id'] ?? 0);
            if ($sid > 0) {
                $ids[] = $sid;
            }
        }

        return array_values(array_unique($ids));
    }
}
