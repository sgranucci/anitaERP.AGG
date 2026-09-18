<?php

namespace App\Http\Requests;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Sala\RequisicionSalaArticuloCatalogoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
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
            'cantidades.*' => 'required|integer|min:1',
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
            $empresaId = (int) $this->input('empresa_id', 0);
            $depositoId = (int) $this->input('deposito_id', 0);

            if ($depositoId > 0 && $empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
                $validator->errors()->add(
                    'deposito_id',
                    'El depósito no pertenece a la empresa seleccionada o no está autorizado para su usuario.'
                );
            }

            if (UsuarioDepositoAutorizado::tieneRestriccion()) {
                $articuloIds = array_values(array_unique(array_filter(array_map(
                    'intval',
                    is_array($this->input('articulo_ids')) ? $this->input('articulo_ids') : []
                ))));

                if ($articuloIds !== []) {
                    $depositosPorArticulo = Articulo::query()
                        ->whereIn('id', $articuloIds)
                        ->pluck('depositoentrega_id', 'id');

                    foreach ($articuloIds as $articuloId) {
                        $depositoEntregaId = (int) ($depositosPorArticulo[$articuloId] ?? 0);
                        if (! RequisicionSalaArticuloCatalogoSupport::articuloPermitido($depositoEntregaId)) {
                            $validator->errors()->add(
                                'articulo_ids',
                                'Hay artículos cuyo depósito de entrega no está permitido para requisición de sala.'
                            );
                            break;
                        }
                    }
                }
            }

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
