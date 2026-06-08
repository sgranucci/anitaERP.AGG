<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionPrestamo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_prometida' => 'required|date|after_or_equal:fecha_prestamo',
            'deposito_origen_id' => 'required|integer|exists:depmae,id|different:deposito_destino_id',
            'deposito_destino_id' => 'required|integer|exists:depmae,id',
            'observaciones' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.observaciones' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) $this->input('empresa_id', 0);
            $origenId = (int) $this->input('deposito_origen_id', 0);
            $destinoId = (int) $this->input('deposito_destino_id', 0);

            if ($empresaId <= 0) {
                return;
            }

            if ($origenId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($origenId, $empresaId)) {
                $validator->errors()->add('deposito_origen_id', 'El depósito origen no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if ($destinoId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($destinoId, $empresaId)) {
                $validator->errors()->add('deposito_destino_id', 'El depósito destino no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'deposito_origen_id.different' => 'El depósito origen y destino deben ser distintos.',
            'fecha_devolucion_prometida.after_or_equal' => 'La fecha prometida de devolución no puede ser anterior a la fecha del préstamo.',
            'items.required' => 'Debe cargar al menos un ítem en el préstamo.',
            'items.*.cantidad.gt' => 'La cantidad de cada ítem debe ser mayor a 0.',
        ];
    }
}
