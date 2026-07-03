<?php

namespace App\Http\Requests;

use App\Support\Ventas\ViandaDiaSemanaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionViandaTipoMenu extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'nombre' => 'required|string|max:255',
            'estado' => 'required|in:A,I',
            'articulo_por_dia' => 'nullable|array',
        ];

        foreach (ViandaDiaSemanaSupport::diasValidos() as $dia) {
            $rules['articulo_por_dia.'.$dia] = 'nullable|array';
            $rules['articulo_por_dia.'.$dia.'.*'] = 'nullable|integer|exists:articulo,id';
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $porDia = $this->input('articulo_por_dia', []);
            if (! is_array($porDia)) {
                return;
            }

            foreach (ViandaDiaSemanaSupport::diasValidos() as $dia) {
                $ids = $porDia[$dia] ?? $porDia[(string) $dia] ?? [];
                if (! is_array($ids)) {
                    continue;
                }

                $vistos = [];
                foreach ($ids as $idx => $articuloId) {
                    $articuloId = (int) $articuloId;
                    if ($articuloId <= 0) {
                        continue;
                    }
                    if (isset($vistos[$articuloId])) {
                        $validator->errors()->add(
                            'articulo_por_dia.'.$dia.'.'.$idx,
                            'El artículo está repetido en '.ViandaDiaSemanaSupport::etiqueta($dia).'.'
                        );
                    }
                    $vistos[$articuloId] = true;
                }
            }
        });
    }
}
