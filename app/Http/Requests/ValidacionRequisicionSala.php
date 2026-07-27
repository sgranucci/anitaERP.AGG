<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicionSala extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required|date',
            'fecha_entrega' => 'required|date',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'deposito_id' => 'required|integer|exists:depmae,id',
            'zona_sala_id' => 'nullable|integer|exists:zona_sala,id',
            'prioridad_sala_id' => 'nullable|integer|exists:prioridad_sala,id',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'nullable|string',
            'articulo_ids' => 'required|array|min:1',
            'articulo_ids.*' => 'required|integer|exists:articulo,id',
            'cantidades' => 'required|array|min:1',
            'cantidades.*' => 'required|numeric|min:0.0001',
            'destinos' => 'nullable|array',
            'destinos.*' => 'nullable|string|max:1',
            'fueradeservicios' => 'nullable|array',
            'fueradeservicios.*' => 'nullable|in:S,N',
            'uids' => 'nullable|array',
            'uids.*' => 'nullable|string|max:50',
            'numeropartes' => 'nullable|array',
            'numeropartes.*' => 'nullable|string|max:50',
            'detalle_articulos' => 'nullable|array',
            'detalle_articulos.*' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'articulo_ids.required' => 'Debe cargar al menos un artículo.',
            'articulo_ids.min' => 'Debe cargar al menos un artículo.',
            'uids.*.required' => 'Debe ingresar el UID cuando el ítem está fuera de servicio (F/S = S).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fueraDeServicio = $this->input('fueradeservicios', []);
            $uids = $this->input('uids', []);
            if (! is_array($fueraDeServicio)) {
                return;
            }
            foreach ($fueraDeServicio as $i => $valor) {
                if ($valor !== 'S') {
                    continue;
                }
                $uid = trim((string) ($uids[$i] ?? ''));
                if ($uid === '') {
                    $validator->errors()->add(
                        'uids.'.$i,
                        'Debe ingresar el UID cuando el ítem está fuera de servicio (F/S = S).'
                    );
                }
            }
        });
    }
}
