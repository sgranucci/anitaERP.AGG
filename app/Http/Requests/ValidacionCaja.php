<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionCaja extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $salaId = $this->input('sala_id');
        $salaId = ($salaId === '' || $salaId === null) ? null : (int) $salaId;

        return [
            'nombre' => 'required|max:255',
            'codigo' => [
                'nullable',
                'max:30',
                Rule::unique('caja', 'codigo')
                    ->where(fn ($q) => $salaId === null
                        ? $q->whereNull('sala_id')
                        : $q->where('sala_id', $salaId))
                    ->ignore($id),
            ],
            'sala_id' => 'nullable|integer|exists:sala,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sala_id') && $this->input('sala_id') === '') {
            $this->merge(['sala_id' => null]);
        }
        if ($this->has('codigo') && trim((string) $this->input('codigo')) === '') {
            $this->merge(['codigo' => null]);
        }
    }
}
