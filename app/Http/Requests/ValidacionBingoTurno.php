<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionBingoTurno extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('codigo') === '') {
            $this->merge(['codigo' => null]);
        }

        $this->merge([
            'activo' => $this->boolean('activo'),
            'orden' => (int) ($this->input('orden') ?? 0),
        ]);
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'nombre' => [
                'required',
                'max:255',
                Rule::unique('turno_bingo', 'nombre')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'codigo' => [
                'nullable',
                'max:50',
                Rule::unique('turno_bingo', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'empresa_id' => 'required|exists:empresa,id',
            'hora_desde' => 'nullable|date_format:H:i',
            'hora_hasta' => 'nullable|date_format:H:i',
            'orden' => 'nullable|integer|min:0|max:9999',
            'activo' => 'boolean',
        ];
    }
}
