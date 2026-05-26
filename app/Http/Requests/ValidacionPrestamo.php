<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPrestamo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_prometida' => 'required|date|after_or_equal:fecha_prestamo',
            'deposito_origen_id' => 'required|integer|different:deposito_destino_id',
            'deposito_destino_id' => 'required|integer',
            'observaciones' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.observaciones' => 'nullable|string|max:255',
        ];
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
