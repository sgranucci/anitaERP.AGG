<?php

namespace App\Http\Requests;

use App\Models\Caja\AperturaGasto;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionAperturaGasto extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $exceptoId = (int) $this->route('id');

        return [
            'codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('apertura_gasto', 'codigo')->ignore($exceptoId),
            ],
            'nombre' => 'required|max:40',
            'estado' => ['required', Rule::in([
                AperturaGasto::ESTADO_ACTIVO,
                AperturaGasto::ESTADO_SUSPENDIDO,
            ])],
            'empresa_ids' => 'required|array|min:1',
            'empresa_ids.*' => 'nullable|integer|exists:empresa,id',
            'cuentacontable_ids' => 'required|array|min:1',
            'cuentacontable_ids.*' => 'nullable|integer|exists:cuentacontable,id',
            'cuentacontable_contrapartida_ids' => 'nullable|array',
            'cuentacontable_contrapartida_ids.*' => 'nullable|integer|exists:cuentacontable,id',
            'centrocosto_ids' => 'nullable|array',
            'centrocosto_ids.*' => 'nullable|integer|exists:centrocosto,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaIds = array_values((array) $this->input('empresa_ids', []));
            $cuentaIds = array_values((array) $this->input('cuentacontable_ids', []));
            $n = max(count($empresaIds), count($cuentaIds));

            $lineasValidas = 0;
            $empresasVistas = [];
            $empresaRepository = app(EmpresaRepositoryInterface::class);

            for ($i = 0; $i < $n; $i++) {
                $empresaId = (int) ($empresaIds[$i] ?? 0);
                $cuentaId = (int) ($cuentaIds[$i] ?? 0);

                if ($empresaId <= 0 && $cuentaId <= 0) {
                    continue;
                }

                if ($empresaId <= 0 || $cuentaId <= 0) {
                    $validator->errors()->add(
                        'empresa_ids',
                        'Cada renglón debe tener empresa y cuenta contable.'
                    );

                    continue;
                }

                if (isset($empresasVistas[$empresaId])) {
                    $validator->errors()->add(
                        'empresa_ids',
                        'No puede repetir la misma empresa en la grilla.'
                    );
                }
                $empresasVistas[$empresaId] = true;

                if (! $empresaRepository->empresaIdPermitida($empresaId)) {
                    $validator->errors()->add(
                        'empresa_ids',
                        'No tiene acceso a una de las empresas seleccionadas.'
                    );
                }

                $lineasValidas++;
            }

            if ($lineasValidas === 0) {
                $validator->errors()->add(
                    'empresa_ids',
                    'Debe cargar al menos una cuenta por empresa.'
                );
            }
        });
    }

    public function messages()
    {
        return [
            'empresa_ids.required' => 'Debe cargar al menos una cuenta por empresa.',
            'cuentacontable_ids.required' => 'Debe indicar la cuenta contable por empresa.',
        ];
    }
}
