<?php

namespace App\Http\Requests;

use App\Models\Configuracion\Sala;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionRequisicion extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $salaId = (int) $this->input('sala_id', 0);
        if ($salaId <= 0) {
            return;
        }

        $empresaId = (int) (Sala::query()->whereKey($salaId)->value('empresa_id') ?? 0);
        if ($empresaId > 0) {
            $this->merge(['empresa_id' => $empresaId]);
        }
    }

    public function rules()
    {
        $rules = [
            'empresa_id' => 'required|integer',
            'fecha' => 'required|date',
            'fechaentrega' => 'required|date',
            'centrocosto_id' => 'required|integer',
            'centrocostodestino_arbol_id' => 'nullable|integer|exists:centrocosto,id',
            'oficinacompra_id' => 'nullable|integer',
            'comentario' => 'nullable|string|max:255',
            'detalle' => 'nullable|string',
            'detalle_articulos' => 'nullable|array',
            'detalle_articulos.*' => 'nullable|string|max:2000',
            'tratamiento' => 'required|string|max:50',
            'motivotratamiento' => 'nullable|string|max:255',
            'contrataciondirecta' => 'nullable|string|max:50',
        ];

        if (EntornoEmpresaSupport::esCrown()) {
            $rules['sala_id'] = 'required|integer|exists:sala,id';
        } else {
            $rules['sala_id'] = 'nullable|integer|exists:sala,id';
        }

        return $rules;
    }
}
