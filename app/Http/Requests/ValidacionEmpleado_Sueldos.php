<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEmpleado_Sueldos extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id', 0);

        return [
            'empresa_id' => [$id ? 'nullable' : 'required', 'integer', 'exists:empresa,id'],
            'legajo' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('empleado_sueldos', 'legajo')
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId ?: 0))
                    ->ignore($id),
            ],
            'nombre' => 'required|string|max:80',
            'domicilio' => 'nullable|string|max:80',
            'entre_calles' => 'nullable|string|max:80',
            'localidad' => 'nullable|string|max:60',
            'codigo_postal' => 'nullable|string|max:12',
            'provincia' => 'nullable|string|max:40',
            'pais_id' => 'nullable|integer|exists:pais,id',
            'provincia_id' => 'nullable|integer|exists:provincia,id',
            'localidad_id' => 'nullable|integer|exists:localidad,id',
            'desc_provincia' => 'nullable|string|max:40',
            'desc_localidad' => 'nullable|string|max:60',
            'telefono' => 'nullable|string|max:40',
            'telefono_emergencia' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:120',
            'nacionalidad' => 'nullable|string|max:40',
            'pais_nacimiento_id' => 'nullable|integer|exists:pais,id',
            'documento' => 'nullable|string|max:30',
            'fecha_nacimiento' => 'nullable|date',
            'cuil' => 'nullable|string|max:15',
            'sexo' => 'nullable|in:1,2',
            'estado_civil' => 'nullable|integer|min:1|max:5',
            'confidencial' => 'nullable|boolean',
            'fecha_ingreso' => 'nullable|date',
            'categoria_id' => 'nullable|integer|exists:categoria_sueldos,id',
            'agrupamiento_id' => 'nullable|integer|exists:agrupamiento_sueldos,id',
            'lugartrabajo_id' => 'nullable|integer|exists:lugartrabajo_sueldos,id',
            'centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'obrasocial_id' => 'nullable|integer|exists:obrasocial_sueldos,id',
            'afiliacion_os' => 'nullable|string|max:30',
            'sindicato_id' => 'nullable|integer|exists:sindicato_sueldos,id',
            'vacacion_id' => 'nullable|integer|exists:vacacion_sueldos,id',
            'art_id' => 'nullable|integer|exists:art_sueldos,id',
            'sueldo_basico' => 'nullable|numeric',
            'jornal_dia' => 'nullable|numeric',
            'jornal_hora' => 'nullable|numeric',
            'codigo_liquidacion' => 'nullable|string|max:20',
            'antiguedad_anterior' => 'nullable|string|max:12',
            'cbu' => 'nullable|string|max:30',
            'cuenta_bancaria' => 'nullable|string|max:30',
            'banco_codigo' => 'nullable|integer',
            'mano_obra' => 'nullable|in:D,I,N',
            'personal_contratado' => 'nullable|in:S,N',
            'codigo_afjp' => 'nullable|string|max:20',
            'situacion_sijp' => 'nullable|string|max:4',
            'condicion_sijp' => 'nullable|string|max:4',
            'modalidad_sijp' => 'nullable|string|max:6',
            'siniestrado_sijp' => 'nullable|string|max:4',
            'marca_reduccion_sijp' => 'nullable|string|max:1',
            'tipo_empresa_sijp' => 'nullable|string|max:1',
            'regimen_sijp' => 'nullable|string|max:1',
            'actividad_sijp' => 'nullable|string|max:3',
            'localidad_afip' => 'nullable|string|max:2',
            'cuit_agencia_eventual' => 'nullable|string|max:13',
            'lsd_legajo_principal' => 'nullable|boolean',
            'lsd_cct' => 'nullable|boolean',
            'lsd_scvo' => 'nullable|boolean',
            'lsd_revista' => 'nullable|array|max:3',
            'lsd_revista.*.id' => 'nullable|integer',
            'lsd_revista.*.periodo' => 'nullable|integer|min:200001|max:209912',
            'lsd_revista.*.situacion' => 'nullable|string|max:2',
            'lsd_revista.*.dia_inicio' => 'nullable|integer|min:1|max:31',
            'a_cargo_de' => 'nullable|string|max:80',
            'puesto_jefe' => 'nullable|string|max:80',
            'clave_alta_temprana' => 'nullable|string|max:40',
            'leyendas' => 'nullable|string',
            'nombrearchivos' => 'nullable|array',
            'nombrearchivos.*' => 'nullable|file|max:10240',
            'nombresanteriores' => 'nullable|array',
            'nombresanteriores.*' => 'nullable|string|max:255',
            'foto_archivo' => 'nullable|image|max:5120',
        ];
    }

    public function attributes()
    {
        return [
            'empresa_id' => 'empresa',
            'legajo' => 'legajo',
            'nombre' => 'nombre',
            'cuil' => 'CUIL',
            'fecha_ingreso' => 'fecha de ingreso',
            'categoria_id' => 'categoría',
            'centrocosto_id' => 'centro de costo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confidencial' => $this->boolean('confidencial'),
            'lsd_legajo_principal' => $this->boolean('lsd_legajo_principal'),
            'lsd_cct' => $this->boolean('lsd_cct'),
            'lsd_scvo' => $this->boolean('lsd_scvo'),
            'cuil' => preg_replace('/\D+/', '', (string) $this->input('cuil', '')) ?: null,
        ]);
    }
}
