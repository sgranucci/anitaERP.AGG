<?php

namespace App\Http\Requests;

use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionTesoreriaCrownCajero extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $salaId = (int) $this->input('sala_id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'sala_id' => ['required', 'integer', 'exists:sala,id'],
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'codigo' => [
                'required',
                'integer',
                'min:1',
                'max:99999999',
                Rule::unique('cajero_crown', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'legajo' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'usuario_id' => ['nullable', 'integer', 'min:0'],
            'caja_codigo' => ['nullable', 'string', 'max:20'],
            'mozo_codigo' => ['nullable', 'string', 'max:20'],
            'clave' => ['nullable', 'string', 'min:4', 'max:20'],
            'activo' => ['required', 'in:0,1'],
            'externos' => ['nullable', 'array'],
            'externos.*.sistema' => ['nullable', 'string', Rule::in(CajeroCrownSistemaExternoSupport::sistemas())],
            'externos.*.codigo_externo' => ['nullable', 'string', 'max:30'],
            'externos.*.mozo_codigo' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sala_id' => 'sala',
            'empresa_id' => 'empresa',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'legajo' => 'legajo',
            'usuario_id' => 'usuario',
            'caja_codigo' => 'caja',
            'mozo_codigo' => 'mozo',
            'clave' => 'PIN / clave',
            'activo' => 'activo',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('activo')) {
            $this->merge(['activo' => '0']);
        }
    }
}
