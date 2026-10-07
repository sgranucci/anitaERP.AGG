<?php

namespace App\Http\Requests;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Integrante;
use App\Models\Compras\Tiposervicio_Proveedor;
use App\Models\Configuracion\Pais;
use App\Rules\Compras\RuleProveedor;
use App\Rules\EmailsMultiples;
use App\Support\Configuracion\LocalidadProvinciaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionProveedor extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $localidadId = LocalidadProvinciaSupport::idConFallback(
            $this->input('localidad_id'),
            $this->input('localidad_id_previa')
        );
        if ($localidadId !== null) {
            $this->merge(['localidad_id' => $localidadId]);
        }

        if (! config('proveedor.filtro_empresa')) {
            $this->request->remove('empresa_id');

            return;
        }

        $eid = $this->input('empresa_id');
        if ($eid === '' || $eid === null) {
            $this->merge(['empresa_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $nroInscripcionRules = ['required', new RuleProveedor('nroinscripcion')];
        if ($this->tipoServicioProveedorControlaUnicidadCuit() && ! $this->proveedorEsDelExterior()) {
            // Un CUIT repetido solo en un proveedor suspendido no bloquea el guardado.
            $nroInscripcionRules[] = Rule::unique('proveedor', 'nroinscripcion')
                ->ignore($this->route('id'))
                ->whereNull('deleted_at')
                ->whereNot('estado', Proveedor::$enumEstado['1']);
        }

        $rules = [
            'nombre' => 'required|max:255|',
            'domicilio' => 'required|max:255|',
            'localidad_id' => ['integer', 'nullable'],
            'provincia_id' => 'required',
            'pais_id' => 'required',
            'condicioniva_id' => ['integer', 'nullable'],
            'condicionpago_id' => ['integer', 'nullable'],
            'cuentacontable_id' => 'required',
            'cuentacontableme_id' => 'required',
            'nroinscripcion' => $nroInscripcionRules,
            'retieneiva' => ['required', new RuleProveedor('retieneiva')],
            'nroIIBB' => 'sometimes|max:100|',
            'email' => ['nullable', 'string', 'max:255', new EmailsMultiples],
            'emailoc' => ['nullable', 'string', 'max:255', new EmailsMultiples],
            'nombres' => 'nullable|array',
            'formapago_ids' => 'nullable|array',
            'tipocuentacaja_ids' => 'nullable|array',
            'moneda_ids' => 'nullable|array',
            'cbus' => 'nullable|array',
            'numerocuentas' => 'nullable|array',
            'nroinscripciones' => 'nullable|array',
            'banco_ids' => 'nullable|array',
            'mediopago_ids' => 'nullable|array',
            'emails' => 'nullable|array',
            'emails.*' => ['nullable', 'string', 'max:255', new EmailsMultiples],
            'servicios_clientes' => 'nullable|array',
            'servicios_detalles' => 'nullable|array',
            'servicios_empresa_ids' => 'nullable|array',
            'servicios_clientes.*' => 'nullable|string|max:255',
            'servicios_detalles.*' => 'nullable|string|max:255',
        ];

        if (config('proveedor.filtro_empresa')) {
            $rules['empresa_id'] = ['nullable', 'integer', 'exists:empresa,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nroinscripcion.unique' => $this->mensajeCuitYaUsado(),
        ];
    }

    public function attributes(): array
    {
        return [
            'nroinscripcion' => 'C.U.I.T.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarRenglonesFormapago($validator);
            $this->validarRenglonesExclusion($validator);
            $this->validarIntegrantesCondominio($validator);
        });
    }

    /**
     * Si el renglón tiene algún dato, exige nombre, formapago_id y moneda_id.
     * TC, CBU y resto de campos bancarios son opcionales (tipocuentacaja_id es nullable).
     */
    private function validarRenglonesFormapago(Validator $validator): void
    {
        $nombres = (array) $this->input('nombres', []);
        if ($nombres === []) {
            return;
        }

        $formapagoIds = (array) $this->input('formapago_ids', []);
        $tipoCuentaIds = (array) $this->input('tipocuentacaja_ids', []);
        $monedaIds = (array) $this->input('moneda_ids', []);
        $cbus = (array) $this->input('cbus', []);
        $numerocuentas = (array) $this->input('numerocuentas', []);
        $nroinscripciones = (array) $this->input('nroinscripciones', []);
        $bancoIds = (array) $this->input('banco_ids', []);
        $mediopagoIds = (array) $this->input('mediopago_ids', []);
        $emails = (array) $this->input('emails', []);

        $max = max(
            count($nombres),
            count($formapagoIds),
            count($tipoCuentaIds),
            count($monedaIds)
        );

        for ($i = 0; $i < $max; $i++) {
            $nro = $i + 1;
            $valores = [
                $nombres[$i] ?? '',
                $formapagoIds[$i] ?? '',
                $tipoCuentaIds[$i] ?? '',
                $monedaIds[$i] ?? '',
                $cbus[$i] ?? '',
                $numerocuentas[$i] ?? '',
                $nroinscripciones[$i] ?? '',
                $bancoIds[$i] ?? '',
                $mediopagoIds[$i] ?? '',
                $emails[$i] ?? '',
            ];

            $tieneDatos = false;
            foreach ($valores as $valor) {
                if (trim((string) $valor) !== '') {
                    $tieneDatos = true;
                    break;
                }
            }

            if (! $tieneDatos) {
                continue;
            }

            if (trim((string) ($nombres[$i] ?? '')) === '') {
                $validator->errors()->add('nombres.'.$i, "Formas de pago renglón {$nro}: el Nombre es obligatorio.");
            }
            $formapagoId = (int) trim((string) ($formapagoIds[$i] ?? ''));
            if ($formapagoId <= 0) {
                $validator->errors()->add('formapago_ids.'.$i, "Formas de pago renglón {$nro}: la Forma de pago es obligatoria.");
            }
            if (trim((string) ($monedaIds[$i] ?? '')) === '') {
                $validator->errors()->add('moneda_ids.'.$i, "Formas de pago renglón {$nro}: la Moneda es obligatoria.");
            }
        }
    }

    /**
     * Condominio (RG 830 art. 8): los porcentajes de los integrantes suman 100.
     */
    private function validarIntegrantesCondominio(Validator $validator): void
    {
        if (! $this->has('integrantes_presentes')) {
            return;
        }
        if (strtoupper(trim((string) $this->input('condicionganancia'))) !== 'C') {
            return;
        }

        $filas = \App\Support\Compras\ProveedorIntegranteSupport::filasValidas((array) $this->input('integrantes', []));
        if ($filas === []) {
            $validator->errors()->add('integrantes', 'El condominio necesita al menos un integrante.');

            return;
        }

        $suma = 0.0;
        $cuits = [];
        foreach ($filas as $i => $fila) {
            $nro = $i + 1;
            if ($fila['nombre'] === '') {
                $validator->errors()->add('integrantes.'.$i.'.nombre', "Integrante {$nro}: el nombre es obligatorio.");
            }
            $digitos = Proveedor_Integrante::digitosCuit($fila['cuit']);
            if (strlen($digitos) !== 11) {
                $validator->errors()->add('integrantes.'.$i.'.cuit', "Integrante {$nro}: el CUIT debe tener 11 dígitos.");
            } elseif (isset($cuits[$digitos])) {
                $validator->errors()->add('integrantes.'.$i.'.cuit', "Integrante {$nro}: el CUIT está repetido.");
            } else {
                $cuits[$digitos] = true;
            }
            if ($fila['porcentaje'] <= 0) {
                $validator->errors()->add('integrantes.'.$i.'.porcentaje', "Integrante {$nro}: el porcentaje tiene que ser mayor a cero.");
            }
            $suma += $fila['porcentaje'];
        }

        if (abs($suma - 100) > 0.01) {
            $validator->errors()->add('integrantes', 'Los porcentajes de los integrantes tienen que sumar 100. Ahora suman '.number_format($suma, 2, ',', '.').'.');
        }
    }

    /**
     * Un renglón de exclusión con algún dato exige fechas, tipo y porcentaje.
     * El comentario puede ir vacío. Un renglón en blanco no se valida.
     */
    private function validarRenglonesExclusion(Validator $validator): void
    {
        $desde = (array) $this->input('desdefechas', []);
        $hasta = (array) $this->input('hastafechas', []);
        $tipos = (array) $this->input('tiporetenciones', []);
        $porcentajes = (array) $this->input('porcentajeexclusiones', []);
        $comentarios = (array) $this->input('comentarios', []);

        $max = max(
            count($desde),
            count($hasta),
            count($tipos),
            count($porcentajes),
            count($comentarios)
        );

        $tiposValidos = ['G', 'I', 'S', 'B'];

        for ($i = 0; $i < $max; $i++) {
            $valores = [
                $desde[$i] ?? '',
                $hasta[$i] ?? '',
                $tipos[$i] ?? '',
                $porcentajes[$i] ?? '',
                $comentarios[$i] ?? '',
            ];

            $tieneDatos = false;
            foreach ($valores as $valor) {
                if (trim((string) $valor) !== '') {
                    $tieneDatos = true;
                    break;
                }
            }

            if (! $tieneDatos) {
                continue;
            }

            $nro = $i + 1;
            if (trim((string) ($desde[$i] ?? '')) === '') {
                $validator->errors()->add(
                    'desdefechas.'.$i,
                    "Exclusiones, renglón {$nro}: la fecha desde es obligatoria."
                );
            }
            if (trim((string) ($hasta[$i] ?? '')) === '') {
                $validator->errors()->add(
                    'hastafechas.'.$i,
                    "Exclusiones, renglón {$nro}: la fecha hasta es obligatoria."
                );
            }
            $tipo = trim((string) ($tipos[$i] ?? ''));
            if (! in_array($tipo, $tiposValidos, true)) {
                $validator->errors()->add(
                    'tiporetenciones.'.$i,
                    "Exclusiones, renglón {$nro}: el tipo de retención es obligatorio."
                );
            }
            if (trim((string) ($porcentajes[$i] ?? '')) === '') {
                $validator->errors()->add(
                    'porcentajeexclusiones.'.$i,
                    "Exclusiones, renglón {$nro}: el porcentaje de exclusión es obligatorio."
                );
            }
        }
    }

    /**
     * Si el tipo de servicio del proveedor está configurado como NO CONTROLA, no se aplica unicidad de CUIT.
     */
    private function tipoServicioProveedorControlaUnicidadCuit(): bool
    {
        $tipoId = $this->input('tiposervicio_proveedor_id');
        if ($tipoId === null || $tipoId === '') {
            return true;
        }

        $tipo = Tiposervicio_Proveedor::query()->find((int) $tipoId);
        if ($tipo === null) {
            return true;
        }

        return $tipo->controla_unicidad_cuit !== Tiposervicio_Proveedor::UNICIDAD_CUIT_NO_CONTROLA;
    }

    /**
     * País distinto de Argentina: el CUIT puede repetirse (CUIT país compartido o CUIT ARCA).
     */
    private function proveedorEsDelExterior(): bool
    {
        $paisId = (int) $this->input('pais_id');
        if ($paisId <= 0) {
            return false;
        }

        $pais = Pais::query()->find($paisId, ['id', 'nombre', 'codigo']);
        if ($pais === null) {
            return false;
        }

        return ! self::paisEsArgentina($pais->codigo, $pais->nombre);
    }

    private static function paisEsArgentina(?string $codigo, ?string $nombre): bool
    {
        if (trim((string) $codigo) === '200') {
            return true;
        }

        $nombreNorm = mb_strtoupper(trim((string) $nombre));
        $nombreNorm = preg_replace('/^\d+\s*-\s*/', '', $nombreNorm) ?? $nombreNorm;

        return $nombreNorm === 'ARGENTINA';
    }

    /**
     * El unique genérico solo dice «ya está en uso». Acá se nombra al proveedor que ya tiene el CUIT.
     */
    private function mensajeCuitYaUsado(): string
    {
        $cuit = trim((string) $this->input('nroinscripcion'));
        $generico = 'El C.U.I.T. ya está cargado en otro proveedor.';
        if ($cuit === '') {
            return $generico;
        }

        $idActual = $this->route('id');
        $otros = Proveedor::query()
            ->where('nroinscripcion', $cuit)
            ->where('estado', '!=', Proveedor::$enumEstado['1'])
            ->when($idActual !== null && $idActual !== '', function ($query) use ($idActual) {
                $query->where('id', '!=', $idActual);
            })
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'codigo', 'nombre', 'estado']);

        if ($otros->isEmpty()) {
            return $generico;
        }

        $partes = $otros->map(function (Proveedor $otro) {
            $nombre = trim((string) $otro->nombre);
            $texto = 'ID '.$otro->id;
            if ($nombre !== '') {
                $texto .= ' · '.$nombre;
            }
            $codigo = trim((string) $otro->codigo);
            $estadoGuardado = trim((string) $otro->estado);
            $estado = Proveedor::$enumEstado[$estadoGuardado] ?? $estadoGuardado;
            $detalle = [];
            if ($codigo !== '') {
                $detalle[] = 'código Anita '.$codigo;
            }
            if ($estado !== '') {
                $detalle[] = $estado;
            }
            if ($detalle !== []) {
                $texto .= ' ('.implode(', ', $detalle).')';
            }

            return $texto;
        })->all();

        $lista = implode('; ', $partes);
        if (count($partes) === 1) {
            return 'El C.U.I.T. '.$cuit.' ya lo tiene el proveedor '.$lista.'.';
        }

        return 'El C.U.I.T. '.$cuit.' ya lo tienen estos proveedores: '.$lista.'.';
    }
}
