<?php

namespace App\Http\Requests;

use App\Models\Caja\Flash\FlashParametro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionFlashParametro extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $periodo = FlashParametro::periodoDesdeInput($this->input('periodo'));
        if ($periodo !== '') {
            $this->merge(['periodo' => $periodo]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'empresa_id' => ['required', 'integer', 'min:1'],
            'periodo' => [
                'required',
                'regex:/^\d{6}$/',
                Rule::unique('flash_parametro', 'periodo')
                    ->where(fn ($q) => $q->where('empresa_id', (int) $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'budget_slot' => ['nullable', 'numeric', 'min:0'],
            'budget_rul' => ['nullable', 'numeric', 'min:0'],
            'budget_poker' => ['nullable', 'numeric', 'min:0'],
            'budget_bingo' => ['nullable', 'numeric', 'min:0'],
            'budget_f_b' => ['nullable', 'numeric', 'min:0'],
            'budget_pos' => ['nullable', 'integer', 'min:0'],
            'budget_estac' => ['nullable', 'numeric', 'min:0'],
            'indices' => ['nullable', 'array'],
            'indices.*.fecha' => ['required', 'date'],
            'indices.*.customer' => ['nullable', 'integer', 'min:0'],
            'indices.*.vehiculos' => ['nullable', 'integer', 'min:0'],
            'indices.*.season_index' => ['nullable', 'numeric', 'min:0'],
            'indices.*.sindex_bingo' => ['nullable', 'numeric', 'min:0'],
            'indices.*.sindex_slot' => ['nullable', 'numeric', 'min:0'],
            'indices.*.sindex_rul' => ['nullable', 'numeric', 'min:0'],
            'indices.*.sindex_poker' => ['nullable', 'numeric', 'min:0'],
            'indices.*.sindex_estac' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'periodo' => 'período',
            'budget_total' => 'budget total',
            'budget_slot' => 'budget slots',
            'budget_rul' => 'budget ruleta',
            'budget_poker' => 'budget poker',
            'budget_bingo' => 'budget bingo',
            'budget_f_b' => 'budget F&B',
            'budget_pos' => 'budget positions',
            'budget_estac' => 'budget estacionamiento',
            'indices.*.fecha' => 'fecha del índice',
        ];
    }
}
