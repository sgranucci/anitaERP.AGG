<?php

namespace App\Http\Requests;

use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionConfiguracionPuntoventaBingo extends FormRequest
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
            'identificador_pc' => [
                'required',
                'max:100',
                Rule::unique('configuracion_puntoventa_bingo', 'identificador_pc')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId))
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|max:255',
            'empresa_id' => 'required|exists:empresa,id',
            'caja_id' => 'required|exists:caja,id',
            'cuentacaja_id' => 'nullable|exists:cuentacaja,id',
        ];
    }

    public function attributes()
    {
        return [
            'identificador_pc' => 'identificador de PC',
            'caja_id' => 'caja de recepción',
            'empresa_id' => 'empresa',
            'cuentacaja_id' => 'cuenta de caja',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId <= 0) {
                return;
            }

            if (! app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene acceso a la empresa seleccionada.');
            }

            $cuentacajaId = (int) $this->input('cuentacaja_id');
            if ($cuentacajaId > 0 && ! Cuentacaja::existeParaEmpresa($cuentacajaId, $empresaId)) {
                $validator->errors()->add('cuentacaja_id', 'La cuenta de caja no pertenece a la empresa seleccionada.');
            }
        });
    }
}
