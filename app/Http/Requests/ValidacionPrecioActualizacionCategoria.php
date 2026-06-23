<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionPrecioActualizacionCategoria extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'categoria_id' => 'required|integer|exists:categoria,id',
      'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
      'fecha_referencia' => 'required|date_format:Y-m-d',
      'nueva_fechavigencia' => 'required|date_format:Y-m-d|after_or_equal:fecha_referencia',
      'porcentaje' => 'required|numeric|between:-99.99,999.99',
    ];
  }

  public function messages(): array
  {
    return [
      'nueva_fechavigencia.after_or_equal' => 'La nueva vigencia debe ser igual o posterior a la fecha de referencia de precios.',
    ];
  }
}
