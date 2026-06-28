<?php

namespace App\Http\Requests;

use App\Models\Ventas\Maquinavending;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionMaquinavendingRendicion extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'maquinavending_id' => 'required|integer|exists:maquinavending,id',
            'fecha_rendicion' => 'required|date',
            'fecha_jornada' => 'nullable|date',
            'observacion' => 'nullable|string|max:65535',
            'articulos' => 'required|array|min:1',
            'articulos.*.numero_rulo' => 'required|integer|min:1',
            'articulos.*.articulo_id' => 'required|integer|exists:articulo,id',
            'articulos.*.cantidad' => 'nullable|numeric|min:0',
            'articulos.*.precio_lista' => 'nullable|numeric|min:0',
            'medios_pago' => 'required|array|min:1',
            'medios_pago.*.cuentacaja_id' => 'nullable|integer|exists:cuentacaja,id',
            'medios_pago.*.monto' => 'nullable|numeric',
            'medios_pago.*.cotizacion' => 'nullable|numeric|min:0.0001',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $empresaId = (int) $this->input('empresa_id', 0);
            $maquinaId = (int) $this->input('maquinavending_id', 0);

            if ($maquinaId > 0) {
                $ok = Maquinavending::query()
                    ->where('id', $maquinaId)
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (! $ok) {
                    $validator->errors()->add('maquinavending_id', 'La máquina no pertenece a la empresa seleccionada.');
                }
            }
        });
    }
}
