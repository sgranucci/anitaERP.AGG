<?php

namespace App\Http\Requests;

use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaLayoutSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionGastroPlanoMesa extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['sala_id', 'codigo'] as $campo) {
            if ($this->input($campo) === '' || $this->input($campo) === '0') {
                $this->merge([$campo => null]);
            }
        }
        $this->merge([
            'activo' => $this->boolean('activo') ? 1 : 0,
            'orden' => (int) ($this->input('orden') ?: 0),
        ]);
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');
        $salaId = $this->input('sala_id') !== null ? (int) $this->input('sala_id') : null;

        return [
            'empresa_id' => 'required|exists:empresa,id',
            'sala_id' => 'nullable|exists:sala,id',
            'codigo' => 'nullable|max:20',
            'nombre' => [
                'required',
                'max:120',
                // Unicidad blanda por empresa + sala + nombre.
                Rule::unique('gastro_plano_mesa', 'nombre')
                    ->where(function ($q) use ($empresaId, $salaId) {
                        $q->where('empresa_id', $empresaId);

                        return $salaId === null
                            ? $q->whereNull('sala_id')
                            : $q->where('sala_id', $salaId);
                    })
                    ->ignore($id),
            ],
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0|max:9999',
            'layout_json' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }
                $decodificado = json_decode((string) $value, true);
                if (! is_array($decodificado)) {
                    $fail('El layout del plano no es un JSON válido.');

                    return;
                }
                $nodos = $decodificado['nodes'] ?? [];
                if (! is_array($nodos)) {
                    $fail('El layout del plano no tiene una lista de mesas válida.');

                    return;
                }
                if (count($nodos) > 400) {
                    $fail('El plano no puede tener más de 400 mesas.');
                }
                foreach ($nodos as $nodo) {
                    $shape = is_array($nodo) ? (string) ($nodo['shape'] ?? 'rect') : 'rect';
                    if (! in_array($shape, GastroPlanoMesaLayoutSupport::SHAPES, true)) {
                        $fail('Forma de mesa no soportada: '.$shape);

                        return;
                    }
                }
            }],
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'sala_id' => 'sala',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'orden' => 'orden',
            'layout_json' => 'diseño del plano',
        ];
    }

    public function messages()
    {
        return [
            'nombre.unique' => 'Ya existe un plano con ese nombre para la empresa y sala elegidas.',
        ];
    }
}
