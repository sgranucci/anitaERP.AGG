<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\ConceptoVentaTagSupport;
use App\Support\Ventas\GtinEan13Support;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionConcepto_Venta extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('concepto_venta', 'codigo')->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['required', 'string', 'max:255'],
            'codigo_gtin' => ['nullable', 'string', 'max:13'],
            'unidades_mtx' => ['required', 'integer', 'min:1', 'max:999'],
            'impuesto_id' => ['nullable', 'integer', 'exists:impuesto,id'],
            'unidadmedida_id' => ['nullable', 'integer', 'exists:unidadmedida,id'],
            'activo' => ['nullable', 'boolean'],
            'codigo_anita' => ['nullable', 'integer', 'min:1'],
            'empresa_ids' => ['nullable', 'array'],
            'empresa_ids.*' => ['nullable', 'integer', 'exists:empresa,id'],
            'cuentacontable_ids' => ['nullable', 'array'],
            'cuentacontable_ids.*' => ['nullable', 'integer'],
            'tipotransaccion_ids' => ['nullable', 'array'],
            'tipotransaccion_ids.*' => ['nullable', 'integer', 'exists:tipotransaccion,id'],
            'vigencia_desde' => ['nullable', 'array'],
            'vigencia_desde.*' => ['nullable', 'date'],
            'vigencia_hasta' => ['nullable', 'array'],
            'vigencia_hasta.*' => ['nullable', 'date'],
            'centrocosto_ids' => ['nullable', 'array'],
            'centrocosto_ids.*' => ['nullable', 'integer', 'exists:centrocosto,id'],
            'creousuario_cuentacontable_ids' => ['nullable', 'array'],
            'creousuario_cuentacontable_ids.*' => ['nullable', 'integer'],
            'precios' => ['nullable', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0'],
            'precio_vigencia_desde' => ['nullable', 'array'],
            'precio_vigencia_desde.*' => ['nullable', 'date'],
            'precio_vigencia_hasta' => ['nullable', 'array'],
            'precio_vigencia_hasta.*' => ['nullable', 'date'],
            'creousuario_precio_ids' => ['nullable', 'array'],
            'creousuario_precio_ids.*' => ['nullable', 'integer'],
            'tag_claves' => ['nullable', 'array'],
            'tag_claves.*' => ['nullable', 'string', 'max:40'],
            'tag_etiquetas' => ['nullable', 'array'],
            'tag_etiquetas.*' => ['nullable', 'string', 'max:80'],
            'tag_tipos' => ['nullable', 'array'],
            'tag_tipos.*' => ['nullable', 'string', 'max:20'],
            'tag_origenes' => ['nullable', 'array'],
            'tag_origenes.*' => ['nullable', 'string', 'max:20'],
            'tag_obligatorios' => ['nullable', 'array'],
            'tag_obligatorios.*' => ['nullable'],
            'tag_ordenes' => ['nullable', 'array'],
            'tag_ordenes.*' => ['nullable', 'integer', 'min:1', 'max:999'],
            'tag_largo_max' => ['nullable', 'array'],
            'tag_largo_max.*' => ['nullable', 'integer', 'min:1', 'max:255'],
            'tag_opciones' => ['nullable', 'array'],
            'tag_opciones.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'descripcion' => 'descripción ARCA',
            'codigo_gtin' => 'código GTIN',
            'unidades_mtx' => 'unidades MTX',
            'impuesto_id' => 'alícuota IVA',
            'unidadmedida_id' => 'unidad de medida',
            'activo' => 'activo',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaRepository = app(EmpresaRepositoryInterface::class);
            $gtin = $this->input('codigo_gtin');
            if ($gtin !== null && $gtin !== '' && ! GtinEan13Support::esAceptable($gtin)) {
                $validator->errors()->add(
                    'codigo_gtin',
                    'El GTIN debe tener 13 dígitos y dígito verificador GS1, o el placeholder MTXCA 7790000000000.'
                );
            }

            foreach ((array) $this->input('empresa_ids', []) as $empresaId) {
                $empresaId = (int) $empresaId;
                if ($empresaId <= 0) {
                    continue;
                }
                if (! $empresaRepository->empresaIdPermitida($empresaId)) {
                    $validator->errors()->add(
                        'empresa_ids',
                        'No tiene acceso a una de las empresas seleccionadas.'
                    );
                    break;
                }
            }

            $desdeCuentas = (array) $this->input('vigencia_desde', []);
            $hastaCuentas = (array) $this->input('vigencia_hasta', []);
            foreach ($desdeCuentas as $i => $desde) {
                $hasta = $hastaCuentas[$i] ?? null;
                if ($desde && $hasta && (string) $hasta < (string) $desde) {
                    $validator->errors()->add('vigencia_hasta.'.$i, 'La vigencia hasta no puede ser anterior al desde.');
                }
            }

            $desdePrecios = (array) $this->input('precio_vigencia_desde', []);
            $hastaPrecios = (array) $this->input('precio_vigencia_hasta', []);
            foreach ($desdePrecios as $i => $desde) {
                $hasta = $hastaPrecios[$i] ?? null;
                if ($desde && $hasta && (string) $hasta < (string) $desde) {
                    $validator->errors()->add('precio_vigencia_hasta.'.$i, 'La vigencia hasta no puede ser anterior al desde.');
                }
            }

            $clavesForm = [];
            foreach ((array) $this->input('tag_claves', []) as $i => $claveRaw) {
                $clave = ConceptoVentaTagSupport::normalizarClave((string) $claveRaw);
                if ($clave === '') {
                    continue;
                }
                if (! ConceptoVentaTagSupport::esClaveValida($clave)) {
                    $validator->errors()->add(
                        'tag_claves.'.$i,
                        'La clave del tag debe empezar con letra y solo usar a-z, 0-9 y _ (máx. 40).'
                    );
                    continue;
                }
                if (isset($clavesForm[$clave])) {
                    $validator->errors()->add('tag_claves.'.$i, 'La clave @'.$clave.'@ está duplicada.');
                    continue;
                }
                $clavesForm[$clave] = true;
                $tipo = ConceptoVentaTagSupport::normalizarTipo((string) ($this->input('tag_tipos.'.$i) ?? ''));
                if (! in_array($tipo, ConceptoVentaTagSupport::TIPOS, true)) {
                    $validator->errors()->add('tag_tipos.'.$i, 'Tipo de tag no soportado.');
                }
            }

            $msgPlantilla = ConceptoVentaTagSupport::mensajePlantillaSinDefinicion(
                (string) $this->input('descripcion', ''),
                array_keys($clavesForm)
            );
            if ($msgPlantilla !== null) {
                $validator->errors()->add('descripcion', $msgPlantilla);
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => strtoupper(trim((string) $this->input('codigo', ''))),
            'activo' => $this->boolean('activo'),
            'codigo_gtin' => preg_replace('/\D+/', '', (string) $this->input('codigo_gtin', '')) ?: null,
        ]);
    }
}
