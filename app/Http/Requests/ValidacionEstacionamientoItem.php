<?php

namespace App\Http\Requests;

use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEstacionamientoItem extends FormRequest
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
            'empresa_id' => 'required|exists:empresa,id',
            'nombre' => [
                'required',
                'max:255',
                Rule::unique('item_estacionamiento', 'nombre')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'estado' => ['required', Rule::in([
                ItemEstacionamiento::ESTADO_ACTIVO,
                ItemEstacionamiento::ESTADO_SUSPENDIDO,
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId <= 0) {
                return;
            }

            $empresaRepository = app(EmpresaRepositoryInterface::class);
            if (! $empresaRepository->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene acceso a la empresa seleccionada.');
            }
        });
    }

    public function messages()
    {
        return [
            'nombre.unique' => 'El nombre ya está en uso para esta empresa.',
        ];
    }
}
