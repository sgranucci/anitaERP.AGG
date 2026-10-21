<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionChequera extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fecha = trim((string) $this->input('fechauso', ''));
        $this->merge([
            'fechauso' => $fecha === '' ? null : $fecha,
            'codigo' => trim((string) $this->input('codigo', '')),
            'desdenumerocheque' => preg_replace('/\D+/', '', (string) $this->input('desdenumerocheque', '')) ?? '',
            'hastanumerocheque' => preg_replace('/\D+/', '', (string) $this->input('hastanumerocheque', '')) ?? '',
        ]);
    }

    public function rules()
    {
        $alta = $this->route('id') === null;

        return [
            'tipochequera' => ['required', Rule::in(['F', 'E'])],
            'tipocheque' => ['required', Rule::in(['N', 'D'])],
            'codigo' => [$alta ? 'nullable' : 'required', 'max:10'],
            'cuentacaja_id' => ['required', 'integer', 'exists:cuentacaja,id'],
            'desdenumerocheque' => ['required', 'regex:/^\d+$/', 'max:50'],
            'hastanumerocheque' => ['required', 'regex:/^\d+$/', 'max:50'],
            'fechauso' => ['nullable', 'date'],
            'estado' => ['required', Rule::in(['A', 'T'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $desde = (string) $this->input('desdenumerocheque', '');
            $hasta = (string) $this->input('hastanumerocheque', '');
            if ($desde === '' || $hasta === '' || ! ctype_digit($desde) || ! ctype_digit($hasta)) {
                return;
            }
            if (strlen($hasta) < strlen($desde) || (strlen($hasta) === strlen($desde) && strcmp($hasta, $desde) < 0)) {
                $validator->errors()->add('hastanumerocheque', 'El número hasta tiene que ser mayor o igual que el desde.');
            }
        });
    }

    public function attributes()
    {
        return [
            'tipochequera' => 'tipo de chequera',
            'tipocheque' => 'tipo de cheque',
            'codigo' => 'código de chequera',
            'cuentacaja_id' => 'cuenta de caja',
            'desdenumerocheque' => 'desde nro. de cheque',
            'hastanumerocheque' => 'hasta nro. de cheque',
            'fechauso' => 'fecha de uso',
            'estado' => 'estado',
        ];
    }
}
