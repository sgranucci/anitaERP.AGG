<?php

namespace App\Http\Requests;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\DepmaeCanalSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionDepmae extends FormRequest
{
    public const CODIGO_REGEX = '/^[A-Za-z0-9._ -]+$/';

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $codigo = trim((string) $this->input('codigo', ''));

        if ($codigo !== '') {
            $this->merge(['codigo' => $codigo]);
        }

        if ($this->has('sala_id') && $this->input('sala_id') === '') {
            $this->merge(['sala_id' => null]);
        }

        if ($this->has('canal')) {
            $canal = strtoupper(trim((string) $this->input('canal')));
            if (array_key_exists($canal, DepmaeCanalSupport::opciones())) {
                $this->merge(['canal' => $canal]);
            }
        }
    }

    public function rules()
    {
        $id = $this->route('id');
        $esCrown = EntornoEmpresaSupport::esCrown();
        $salaId = $this->input('sala_id');
        $salaId = ($salaId === '' || $salaId === null) ? null : (int) $salaId;
        $empresaId = (int) $this->input('empresa_id');

        $codigoUnique = Rule::unique('depmae', 'codigo')->ignore($id)->where(function ($query) use ($empresaId, $salaId, $esCrown) {
            $query->where('empresa_id', $empresaId);
            if ($esCrown) {
                return $salaId === null
                    ? $query->whereNull('sala_id')
                    : $query->where('sala_id', $salaId);
            }

            return $query;
        });

        $nombreUnique = Rule::unique('depmae', 'nombre')->ignore($id)->where(function ($query) use ($empresaId, $salaId, $esCrown) {
            $query->where('empresa_id', $empresaId);
            if ($esCrown) {
                return $salaId === null
                    ? $query->whereNull('sala_id')
                    : $query->where('sala_id', $salaId);
            }

            return $query;
        });

        return [
            'codigo' => [
                'required',
                'max:10',
                'regex:'.self::CODIGO_REGEX,
                $codigoUnique,
            ],
            'nombre' => ['required', 'max:50', $nombreUnique],
            'tipodeposito' => 'required',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'sala_id' => $esCrown
                ? 'required|integer|exists:sala,id'
                : 'nullable|integer|exists:sala,id',
            'canal' => EntornoEmpresaSupport::esFerli()
                ? ['required', Rule::in(array_keys(DepmaeCanalSupport::opciones()))]
                : ['nullable', Rule::in(array_keys(DepmaeCanalSupport::opciones()))],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'descripción',
            'sala_id' => 'sala',
            'canal' => 'canal',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código admite letras, números, espacios, punto, guión y guión bajo (máx. 10 caracteres).',
            'codigo.max' => 'El código no puede superar 10 caracteres.',
            'sala_id.required' => 'La sala es obligatoria para el depósito.',
        ];
    }
}
