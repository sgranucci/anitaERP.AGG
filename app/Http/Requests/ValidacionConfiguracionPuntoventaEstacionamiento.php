<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionPuntoventaEstacionamiento extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('tipotransaccion_nota_credito_id') === ''
            || $this->input('tipotransaccion_nota_credito_id') === '0') {
            $this->merge(['tipotransaccion_nota_credito_id' => null]);
        }
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'identificador_pc' => [
                'required',
                'max:100',
                Rule::unique('configuracion_puntoventa_estacionamiento', 'identificador_pc')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'empresa_id' => 'required|exists:empresa,id',
            'caja_id' => 'required|exists:caja,id',
            'puntoventa_cae_id' => [
                'required',
                Rule::exists('puntoventa', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId)
                        ->where('estado', 'A')
                        ->whereIn('modofacturacion', ['C', 'E'])
                        ->whereNull('deleted_at');
                }),
            ],
            'puntoventa_caea_id' => [
                'required',
                Rule::exists('puntoventa', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId)
                        ->where('estado', 'A')
                        ->where('modofacturacion', 'A')
                        ->whereNull('deleted_at');
                }),
            ],
            'salida_factura_id' => 'required|exists:salida,id',
            'tipotransaccion_id' => 'required|exists:tipotransaccion,id',
            'tipotransaccion_nota_credito_id' => 'nullable|exists:tipotransaccion,id',
            'tipotransaccion_caja_id' => 'required|exists:tipotransaccion_caja,id',
        ];
    }

    public function attributes()
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'caja_id' => 'caja de recepción',
            'puntoventa_cae_id' => 'punto de venta CAE',
            'puntoventa_caea_id' => 'punto de venta CAEA',
            'salida_factura_id' => 'salida de factura electrónica',
            'tipotransaccion_id' => 'tipo de transacción (factura)',
            'tipotransaccion_nota_credito_id' => 'tipo de transacción (nota de crédito)',
            'tipotransaccion_caja_id' => 'tipo de transacción de caja (cobranza)',
        ];
    }
}
