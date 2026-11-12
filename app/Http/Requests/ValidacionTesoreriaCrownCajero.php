<?php

namespace App\Http\Requests;

use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use App\Support\Caja\TesoreriaCrownTareaCatalogo;
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
            'tipo_tarea' => ['required', 'string', Rule::in(array_keys(TesoreriaCrownTareaCatalogo::TAREAS))],
            'permite_rendir_sin_habilitacion' => ['sometimes', 'boolean'],
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
            'tipo_tarea' => 'tarea',
            'permite_rendir_sin_habilitacion' => 'permite rendir sin habilitación',
            'clave' => 'PIN / clave',
            'activo' => 'activo',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('activo')) {
            $this->merge(['activo' => '0']);
        }
        $this->merge([
            'permite_rendir_sin_habilitacion' => $this->boolean('permite_rendir_sin_habilitacion'),
        ]);
    }
}
