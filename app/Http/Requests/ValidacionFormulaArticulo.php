<?php

namespace App\Http\Requests;

use App\Repositories\Stock\Formula_ArticuloRepositoryInterface;
use App\Support\Stock\FormulaArticuloGastronomia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionFormulaArticulo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpia = function ($arr) {
            if (! is_array($arr)) {
                return [];
            }

            return array_map(function ($v) {
                if ($v === '' || $v === null) {
                    return null;
                }

                return is_numeric($v) ? (int) $v : $v;
            }, $arr);
        };

        $articuloCabecera = $this->input('articulo_id');
        $articuloCabecera = ($articuloCabecera === '' || $articuloCabecera === null) ? null : (int) $articuloCabecera;

        $codigo = $this->input('codigo');
        $codigo = is_string($codigo) ? trim($codigo) : '';

        $this->merge([
            'articulo_id' => $articuloCabecera,
            'codigo' => $codigo,
            'articulo_ids' => $limpia($this->input('articulo_ids', [])),
            'formula_hija_ids' => $limpia($this->input('formula_hija_ids', [])),
            'deposito_ids' => $limpia($this->input('deposito_ids', [])),
            'formula_articulo_hijo_ids' => $limpia($this->input('formula_articulo_hijo_ids', [])),
            'ranuras' => $limpia($this->input('ranuras', [])),
            'ordenopcionales' => $limpia($this->input('ordenopcionales', [])),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'articulo_id' => 'nullable|integer|exists:articulo,id',
            'codigo' => 'nullable|string|max:50',
            'cantidadunidad' => 'required|numeric',
            'estado' => 'required|string|max:50',
            'detalle' => 'nullable|string',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'articulo_ids' => 'nullable|array',
            'articulo_ids.*' => 'nullable|integer|exists:articulo,id',
            'formula_hija_ids' => 'nullable|array',
            'formula_hija_ids.*' => 'nullable|integer|exists:formula_articulo,id',
            'cantidades' => 'nullable|array',
            'factorcostos' => 'nullable|array',
            'deposito_ids' => 'nullable|array',
            'ranuras' => 'nullable|array',
        ];
        if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
            $rules['esopcional'] = 'nullable|array';
            $rules['esopcional.*'] = 'nullable|in:0,1';
            $rules['ordenopcionales'] = 'nullable|array';
            $rules['ordenopcionales.*'] = 'nullable|integer|min:1|max:65535';
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = $this->route('id');
            if ($id) {
                try {
                    $existente = app(Formula_ArticuloRepositoryInterface::class)->find((int) $id);
                } catch (\Throwable $e) {
                    $existente = null;
                }
                if ($existente) {
                    $estadoNuevo = (string) ($this->input('estado') ?? '');
                    if (($existente->estado ?? '') !== $estadoNuevo) {
                        if (trim((string) $this->input('observacion_estado', '')) === '') {
                            $validator->errors()->add(
                                'observacion_estado',
                                'Indique la observación del cambio de estado (solapa Historia).'
                            );
                        }
                    }
                }
            }

            if (! FormulaArticuloGastronomia::opcionalesHabilitados()) {
                return;
            }
            $articulo_ids = $this->input('articulo_ids', []);
            $formula_hija_ids = $this->input('formula_hija_ids', []);
            $esopcional = $this->input('esopcional', []);
            $ordenopcionales = $this->input('ordenopcionales', []);
            if (! is_array($articulo_ids)) {
                return;
            }
            $n = count($articulo_ids);
            for ($i = 0; $i < $n; $i++) {
                $aid = $articulo_ids[$i] ?? null;
                $fid = $formula_hija_ids[$i] ?? null;
                if (($aid === null || $aid === '') && ($fid === null || $fid === '')) {
                    continue;
                }
                $esOp = isset($esopcional[$i]) && (string) $esopcional[$i] === '1';
                if (! $esOp) {
                    continue;
                }
                $ord = $ordenopcionales[$i] ?? null;
                if ($ord === null || $ord === '') {
                    $validator->errors()->add(
                        'ordenopcionales.'.$i,
                        'Si el ítem es opcional debe indicar el orden opcional (1 en adelante).'
                    );

                    continue;
                }
                $ord = (int) $ord;
                if ($ord < 1) {
                    $validator->errors()->add('ordenopcionales.'.$i, 'El orden opcional debe ser mayor o igual a 1.');
                }
            }
        });
    }
}
