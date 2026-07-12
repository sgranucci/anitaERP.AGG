<?php

namespace App\Http\Requests;

use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionBingoConceptoRendicion extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'empresa_id' => 'required|exists:empresa,id',
            'codigo' => 'nullable|max:20',
            'codigo_anita' => 'nullable|integer|min:0',
            'signo' => ['required', Rule::in([
                BingoConceptoRendicion::SIGNO_SUMA,
                BingoConceptoRendicion::SIGNO_RESTA,
            ])],
            'detalle' => 'required|max:255',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'base_calculo' => ['required', Rule::in([
                BingoConceptoRendicion::BASE_TOTAL_CARTONES,
                BingoConceptoRendicion::BASE_SALDO_ANTERIOR,
                BingoConceptoRendicion::BASE_MONTO_COMISION,
                BingoConceptoRendicion::BASE_MANUAL,
            ])],
            'monto_fijo' => 'nullable|numeric|min:0',
            'orden' => 'integer|min:0',
            'es_saldo_rendicion' => 'nullable|boolean',
            'estado' => ['required', Rule::in([
                BingoConceptoRendicion::ESTADO_ACTIVO,
                BingoConceptoRendicion::ESTADO_SUSPENDIDO,
                BingoConceptoRendicion::ESTADO_ANULADO,
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId <= 0) {
                return;
            }

            $empresaRepository = app(EmpresaRepositoryInterface::class);
            if (! $empresaRepository->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene acceso a la empresa seleccionada.');
            }

            if ($this->boolean('es_saldo_rendicion')) {
                $query = BingoConceptoRendicion::query()
                    ->where('empresa_id', $empresaId)
                    ->where('es_saldo_rendicion', true);

                $exceptoId = (int) $this->route('id');
                if ($exceptoId > 0) {
                    $query->where('id', '!=', $exceptoId);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'es_saldo_rendicion',
                        'Ya existe un concepto marcado como saldo de rendición para esta empresa.'
                    );
                }
            }
        });
    }
}
