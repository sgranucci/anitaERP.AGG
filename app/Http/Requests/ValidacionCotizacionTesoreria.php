<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCotizacionTesoreria extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        $rules = [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'fecha' => [
                'required',
                'date',
                Rule::unique('cotizacion_tesoreria', 'fecha')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
        ];

        foreach ([2, 3, 4, 5, 6, 7, 8, 9] as $codigo) {
            $rules['cambio_compra_'.$codigo] = 'nullable|numeric|min:0';
            $rules['cambio_venta_'.$codigo] = 'nullable|numeric|min:0';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attrs = [
            'empresa_id' => 'empresa',
            'fecha' => 'fecha',
        ];
        foreach ([2, 3, 4, 5, 6, 7, 8, 9] as $codigo) {
            $attrs['cambio_compra_'.$codigo] = 'compra moneda '.$codigo;
            $attrs['cambio_venta_'.$codigo] = 'venta moneda '.$codigo;
        }

        return $attrs;
    }
}
