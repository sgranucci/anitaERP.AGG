<?php

namespace App\Http\Requests;

use App\Support\Database\SqlDialectSupport;
use App\Models\Ventas\Puntoventa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionPuntoventa extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $codigo = $this->input('codigo');
        if ($codigo === null || $codigo === '') {
            return;
        }

        $numerico = (int) preg_replace('/\D+/', '', (string) $codigo);
        if ($numerico > 0) {
            $this->merge(['codigo' => Puntoventa::normalizarCodigoArca((string) $codigo)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = (int) ($this->route('id') ?? 0);
        $empresaId = (int) $this->input('empresa_id');

        return [
            'nombre' => [
                'required',
                'max:255',
                Rule::unique('puntoventa', 'nombre')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)->whereNull('deleted_at'))
                    ->ignore($id > 0 ? $id : null),
            ],
            'codigo' => [
                'required',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) use ($id, $empresaId): void {
                    $codigoNorm = Puntoventa::normalizarCodigoArca((string) $value);
                    if ($codigoNorm === null) {
                        $fail('El código de punto de venta no es válido.');

                        return;
                    }

                    $numeroPv = (int) preg_replace('/\D+/', '', $codigoNorm);

                    $query = Puntoventa::query()
                        ->where('empresa_id', $empresaId)
                        ->where(function ($q) use ($codigoNorm, $numeroPv): void {
                            $q->where('codigo', $codigoNorm);
                            if ($numeroPv > 0) {
                                $q->orWhereRaw(SqlDialectSupport::castEntero('TRIM(codigo)').' = ?', [$numeroPv]);
                            }
                        });

                    if ($id > 0) {
                        $query->where('id', '!=', $id);
                    }

                    $existente = $query->first(['id', 'nombre', 'codigo']);
                    if ($existente !== null) {
                        $fail(
                            'El código de punto de venta ya existe para esta empresa '
                            ."(registro #{$existente->id}: {$existente->nombre}, código {$existente->codigo})."
                        );
                    }
                },
            ],
            'empresa_id' => 'required|integer|exists:empresa,id',
            'webservice' => ['nullable', Rule::in(array_keys(Puntoventa::$enumWebservice))],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'El nombre ya existe para esta empresa.',
            'empresa_id.required' => 'Debe seleccionar una empresa.',
        ];
    }
}
