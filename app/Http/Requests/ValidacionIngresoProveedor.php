<?php

namespace App\Http\Requests;

use App\Repositories\Configuracion\EmpresaRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidacionIngresoProveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'fecha' => 'required|date',
            'es_visitante' => 'nullable|boolean',
            'visitante_nombre' => 'nullable|string|max:180',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'ordencompra_id' => 'nullable|integer|exists:ordencompra,id',
            'motivo_id' => 'required|integer|exists:ingreso_proveedor_motivo,id',
            'punto_id' => 'required|integer|exists:ingreso_proveedor_punto,id',
            'area_id' => 'required|integer|exists:ingreso_proveedor_area,id',
            'sector_id' => 'required|integer|exists:ingreso_proveedor_sector,id',
            'patente' => 'nullable|string|max:20',
            'titulo' => 'nullable|string|max:180',
            'comentario' => 'nullable|string',
            'persona_nombres' => 'required|array|min:1',
            'persona_nombres.*' => 'nullable|string|max:160',
            'persona_documentos' => 'nullable|array',
            'persona_documentos.*' => 'nullable|string|max:20',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'nombresanteriores' => 'nullable|array',
            'nombresanteriores.*' => 'nullable|integer',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId > 0 && ! app(EmpresaRepository::class)->empresaIdPermitida($empresaId)) {
                $v->errors()->add('empresa_id', 'La empresa no está asignada al usuario.');
            }

            $hayPersona = false;
            foreach ((array) $this->input('persona_nombres', []) as $nombre) {
                if (trim((string) $nombre) !== '') {
                    $hayPersona = true;
                    break;
                }
            }
            if (! $hayPersona) {
                $v->errors()->add('persona_nombres', 'Indique al menos una persona que visita.');
            }

            $esVisitante = $this->boolean('es_visitante');
            if ($esVisitante) {
                if (trim((string) $this->input('visitante_nombre', '')) === '') {
                    $v->errors()->add('visitante_nombre', 'Indique el nombre de la empresa o persona que visita (no es proveedor).');
                }
            } elseif ((int) $this->input('proveedor_id') <= 0) {
                $v->errors()->add('proveedor_id', 'Seleccione un proveedor o marque que no es proveedor.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'proveedor_id' => 'proveedor',
            'visitante_nombre' => 'nombre del visitante',
            'motivo_id' => 'motivo de visita',
            'punto_id' => 'sala / punto de ingreso',
            'area_id' => 'área de destino',
            'sector_id' => 'sector',
            'persona_nombres' => 'personas que visitan',
        ];
    }
}
