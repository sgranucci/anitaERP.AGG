<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionPuntoventaGastronomia extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('ubicacion_id') === '' || $this->input('ubicacion_id') === '0') {
            $this->merge(['ubicacion_id' => null]);
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
                Rule::unique('configuracion_puntoventa_gastronomia', 'identificador_pc')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'empresa_id' => 'required|exists:empresa,id',
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
            'ubicacion_id' => 'nullable|exists:ubicaciones_gastronomia,id',
            'salida_comanda_id' => 'required|exists:salida,id',
            'salida_factura_id' => 'required|exists:salida,id',
            'listaprecio_id' => 'required|exists:listaprecio,id',
            'deposito_venta_id' => [
                'required',
                Rule::exists('depmae', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId);
                }),
            ],
            'deposito_insumos_id' => [
                'required',
                Rule::exists('depmae', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('empresa_id', $empresaId);
                }),
            ],
            'tipotransaccion_id' => 'required|exists:tipotransaccion,id',
            'tipotransaccion_nota_credito_id' => 'nullable|exists:tipotransaccion,id',
            'tipotransaccion_caja_id' => 'required|exists:tipotransaccion_caja,id',
            'waitry_habilitado' => 'required|in:0,1',
        ];
    }

    public function attributes()
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'puntoventa_cae_id' => 'punto de venta CAE',
            'puntoventa_caea_id' => 'punto de venta CAEA',
            'ubicacion_id' => 'ubicación',
            'salida_comanda_id' => 'salida de comandas',
            'salida_factura_id' => 'salida de facturas',
            'listaprecio_id' => 'lista de precios',
            'deposito_venta_id' => 'depósito de artículos facturados',
            'deposito_insumos_id' => 'depósito de descuento de insumos',
            'tipotransaccion_id' => 'tipo de transacción (factura)',
            'tipotransaccion_nota_credito_id' => 'tipo de transacción (nota de crédito)',
            'tipotransaccion_caja_id' => 'tipo de transacción de caja (cobranza)',
            'waitry_habilitado' => 'integración Waitry',
        ];
    }
}
