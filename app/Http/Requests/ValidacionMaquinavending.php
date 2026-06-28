<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionMaquinavending extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|exists:empresa,id',
            'nombre' => 'required|string|max:255',
            'puntoventa_id' => [
                'required',
                Rule::exists('puntoventa', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)->where('estado', 'A')),
            ],
            'ubicacion_id' => [
                'required',
                Rule::exists('ubicaciones_gastronomia', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'deposito_id' => 'required|integer|exists:depmae,id',
            'listaprecio_id' => 'required|integer|exists:listaprecio,id',
            'codigo_arca' => 'nullable|string|max:20',
            'numero_serie' => 'nullable|string|max:50',
            'numero_rulo' => 'nullable|array',
            'numero_rulo.*' => 'nullable|integer|min:1',
            'articulo_ids' => 'nullable|array',
            'articulo_ids.*' => 'nullable|integer|exists:articulo,id',
            'precio_lista' => 'nullable|array',
            'precio_lista.*' => 'nullable|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'puntoventa_id.exists' => 'El punto de venta no existe, no está activo o no pertenece a la empresa.',
            'ubicacion_id.exists' => 'La ubicación no existe o no pertenece a la empresa seleccionada.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $empresaId = (int) $this->input('empresa_id', 0);
            $depositoId = (int) $this->input('deposito_id', 0);

            if ($depositoId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
                $validator->errors()->add('deposito_id', 'El depósito no está autorizado para su usuario o no pertenece a la empresa.');
            }

            $numeros = $this->input('numero_rulo', []);
            $articuloIds = $this->input('articulo_ids', []);
            if (! is_array($numeros) || ! is_array($articuloIds)) {
                return;
            }

            $rulosUsados = [];
            $total = max(count($numeros), count($articuloIds));

            for ($i = 0; $i < $total; $i++) {
                $numero = (int) ($numeros[$i] ?? 0);
                $articuloId = (int) ($articuloIds[$i] ?? 0);

                if ($numero <= 0 && $articuloId <= 0) {
                    continue;
                }

                if ($numero <= 0) {
                    $validator->errors()->add('numero_rulo.'.$i, 'Indique el número de rulo/ubicación en la fila '.($i + 1).'.');
                    continue;
                }

                if ($articuloId <= 0) {
                    $validator->errors()->add('articulo_ids.'.$i, 'Seleccione un artículo en la fila '.($i + 1).'.');
                    continue;
                }

                if (isset($rulosUsados[$numero])) {
                    $validator->errors()->add('numero_rulo.'.$i, 'El rulo '.$numero.' está repetido.');
                }
                $rulosUsados[$numero] = true;
            }
        });
    }
}
