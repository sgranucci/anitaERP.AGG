<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionTerminalVianda extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('listaprecio_venta_id') === '' || $this->input('listaprecio_venta_id') === '0') {
            $this->merge(['listaprecio_venta_id' => null]);
        }

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
                Rule::unique('configuracion_terminal_vianda', 'identificador_pc')->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'ubicacion_id' => 'nullable|exists:ubicaciones_gastronomia,id',
            'empresa_id' => 'required|exists:empresa,id',
            'deposito_platos_id' => [
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
            'salida_voucher_id' => 'required|exists:salida,id',
            'listaprecio_venta_id' => 'nullable|exists:listaprecio,id',
            'tipotransaccion_stock_id' => [
                'required',
                Rule::exists('tipotransaccion_stock', 'id')->where(function ($query) {
                    $query->where('operacion', 'S')->where('estado', 'A');
                }),
            ],
            'estado' => 'required|in:A,I',
        ];
    }

    public function attributes()
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'deposito_platos_id' => 'depósito de descuento de platos',
            'deposito_insumos_id' => 'depósito de descuento de insumos',
            'salida_voucher_id' => 'impresora / salida del voucher',
            'listaprecio_venta_id' => 'lista de precios de venta',
            'tipotransaccion_stock_id' => 'tipo de transacción de stock (descuento)',
        ];
    }
}
