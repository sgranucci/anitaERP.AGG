<?php

namespace App\Http\Requests;

use App\Support\Ventas\ArticuloListaprecioLineaVentasSupport;
use App\Support\Ventas\ClienteEntregaPedidoSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionRemito extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fecha' => 'required',
            'fechaentrega' => 'required',
            'cliente_id' => 'required|integer',
            'vendedor_id' => 'required|integer',
            'cliente_entrega_id' => 'nullable|integer',
            'lugarentrega' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:255',
            'descuento' => 'sometimes|numeric|min:0|max:100',
            'descuentointegrado' => 'sometimes|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $clienteId = (int) $this->input('cliente_id', 0);
            if ($clienteId <= 0) {
                return;
            }

            $error = ClienteEntregaPedidoSupport::validarSeleccionParaCliente(
                $clienteId,
                (int) $this->input('cliente_entrega_id', 0) ?: null,
                $this->input('lugarentrega')
            );

            if ($error !== null) {
                $validator->errors()->add('cliente_entrega_id', $error['error']);
            }

            $errorListaprecio = ArticuloListaprecioLineaVentasSupport::validarLineas(
                $this->input('articulo_ids'),
                $this->input('listasprecios_id'),
                $this->input('codigoarticulos'),
            );

            if ($errorListaprecio !== null) {
                $validator->errors()->add('articulo_ids', $errorListaprecio['error']);
            }
        });
    }
}
