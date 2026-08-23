<?php

namespace App\Http\Requests;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionReglaClave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionProgramaImpresion extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id', 0);

        return [
            'codigo' => [
                'required',
                'max:40',
                Rule::unique('comprobante_impresion_programa', 'codigo')
                    ->ignore($id)
                    ->where(function ($query) use ($empresaId) {
                        if ($empresaId > 0) {
                            $query->where('empresa_id', $empresaId);
                        } else {
                            $query->whereNull('empresa_id');
                        }
                    }),
            ],
            'nombre' => 'required|max:120',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'permite_disparo_al_grabar' => 'nullable|boolean',
            'formularios' => 'required|array|min:1',
            'formularios.*.id' => 'nullable|integer',
            'formularios.*.orden' => 'nullable|integer|min:1',
            'formularios.*.formulario' => ['required', Rule::in(ComprobanteImpresionFormulario::todos())],
            'formularios.*.copias' => 'required|array|min:1',
            'formularios.*.copias.*.id' => 'nullable|integer',
            'formularios.*.copias.*.codigo' => 'required|max:20',
            'formularios.*.copias.*.leyenda' => 'required|max:60',
            'formularios.*.copias.*.destinatario' => 'nullable|max:80',
            'formularios.*.copias.*.salida_id' => 'nullable|integer|exists:salida,id',
            'formularios.*.copias.*.incluir_en_pdf_sesion' => 'nullable|boolean',
            'reglas' => 'nullable|array',
            'reglas.*.id' => 'nullable|integer',
            'reglas.*.clave' => ['required', Rule::in(array_keys(ComprobanteImpresionReglaClave::etiquetas()))],
            'reglas.*.valor_id' => 'nullable|integer',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'empresa_id' => 'empresa',
            'formularios' => 'formularios',
            'formularios.*.formulario' => 'formulario',
            'formularios.*.copias.*.leyenda' => 'leyenda de copia',
            'reglas.*.clave' => 'tipo de regla',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipos = collect($this->input('formularios', []))->pluck('formulario')->filter();
            if ($tipos->count() !== $tipos->unique()->count()) {
                $validator->errors()->add(
                    'formularios',
                    'Cada comprobante (Factura, Remito, Pedido, Envío) puede ir una sola vez en la ruta.'
                );
            }
            $empresaId = (int) $this->input('empresa_id', 0);
            if ($empresaId > 0 && ! app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene asignada esa empresa.');
            }
        });
    }
}
