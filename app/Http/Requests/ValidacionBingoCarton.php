<?php

namespace App\Http\Requests;

use App\Models\Caja\Bingo\BingoCarton;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionBingoCarton extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|exists:empresa,id',
            'codigo' => [
                'required',
                'max:20',
                Rule::unique('bingo_carton', 'codigo')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'codigo_anita' => 'nullable|integer|min:0',
            'nombre' => 'required|max:255',
            'precio_unitario' => 'numeric|min:0',
            'lineas' => ['required', 'integer', Rule::in([3, 4, 5])],
            'es_azar' => 'boolean',
            'orden' => 'integer|min:0',
            'estado' => ['required', Rule::in([
                BingoCarton::ESTADO_ACTIVO,
                BingoCarton::ESTADO_SUSPENDIDO,
                BingoCarton::ESTADO_ANULADO,
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
        });
    }

    public function messages()
    {
        return [
            'codigo.unique' => 'El código ya está en uso para esta empresa.',
        ];
    }
}
