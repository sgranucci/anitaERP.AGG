<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Familiar_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Lsd_Empleado_Revista_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Registro_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Sueldos;
use App\Models\Sueldos\Lsd_Recibo_Base_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\Formula\ParametroSueldosResolver;
use App\Support\Sueldos\Lsd\LsdAnsiSupport;
use App\Support\Sueldos\Lsd\LsdBases04Support;
use App\Support\Sueldos\Lsd\LsdBasesImponiblesSupport;
use App\Support\Sueldos\Lsd\LsdDetraccionSupport;
use App\Support\Sueldos\Lsd\LsdConceptoAfipAsignacionSupport;
use App\Support\Sueldos\Lsd\LsdConceptoAfipCatalogo;
use App\Support\Sueldos\Lsd\LsdPeriodoWizardSupport;
use App\Support\Sueldos\Lsd\LsdRegistroSupport;
use App\Support\Sueldos\Lsd\LsdTipoLiquidacionSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LsdGeneradorPresentacionService
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function generar(array $datos, ?int $usuarioId): Lsd_Presentacion_Sueldos
    {
        $liq = Liquidacion_Sueldos::query()->with([
            'empresa',
            'recibos.detalles.concepto',
            'recibos.empleado.familiares',
            'recibos.empleado.lugartrabajo',
            'recibos.empleado.obrasocial',
            'recibos.empleado.categoria',
            'recibos.lsdBase',
        ])->findOrFail((int) $datos['liquidacion_id']);
        if (! $liq->estaCerrada()) {
            throw new RuntimeException('Solo se pueden exportar liquidaciones cerradas, contabilizadas o pagadas.');
        }

        $ident = strtoupper((string) ($datos['identificacion'] ?? 'SJ'));
        if (! in_array($ident, ['SJ', 'RE'], true)) {
            $ident = 'SJ';
        }
        $rectificativa = $ident === 'RE' || ! empty($datos['es_rectificativa']);
        if ($rectificativa) {
            $ident = 'RE';
        }

        $periodo = (int) ($liq->periodo_anio * 100 + $liq->periodo_mes);
        $nroAfip = (int) ($datos['nro_liquidacion_afip'] ?? 0);
        if ($nroAfip <= 0 || $nroAfip > 99999) {
            $nroAfip = $this->proximoNroAfip((int) $liq->empresa_id, $periodo);
        }

        $tipoAfip = LsdTipoLiquidacionSupport::desdeTipoErp($liq->tipo);
        if (! $rectificativa) {
            $yaPresentada = Lsd_Presentacion_Sueldos::query()
                ->where('liquidacion_id', $liq->id)
                ->where('identificacion', 'SJ')
                ->where('estado', 'presentada')
                ->exists();
            if ($yaPresentada) {
                throw new RuntimeException('Esta liquidación ya fue marcada presentada en ARCA. Use una rectificativa (RE).');
            }
            if (in_array($tipoAfip, ['M', 'Q'], true)) {
                $wizard = LsdPeriodoWizardSupport::para((int) $liq->empresa_id, $periodo);
                if ($wizard['bloquea_mensual']) {
                    throw new RuntimeException(
                        'ARCA exige importar primero las liquidaciones especiales (E) del período: '
                        .implode(', ', $wizard['e_pendientes'])
                    );
                }
            }
        }
        $fechaPago = $datos['fecha_pago'] ?? optional($liq->fecha_pago)->format('Y-m-d');
        $fechaRubrica = $datos['fecha_rubrica'] ?? $fechaPago;
        $incluirLicencias = ! empty($datos['incluir_licencias_sin_recibo']);

        return DB::transaction(function () use ($liq, $ident, $rectificativa, $periodo, $nroAfip, $tipoAfip, $fechaPago, $fechaRubrica, $incluirLicencias, $datos, $usuarioId) {
            $armado = $this->armarLineas($liq, $ident, $rectificativa, $nroAfip, $tipoAfip, $fechaPago, $fechaRubrica, $incluirLicencias);

            $presentacion = Lsd_Presentacion_Sueldos::create([
                'empresa_id' => (int) $liq->empresa_id,
                'periodo' => $periodo,
                'liquidacion_id' => (int) $liq->id,
                'nro_liquidacion_afip' => $nroAfip,
                'identificacion' => $ident,
                'tipo_liquidacion' => $rectificativa ? null : $tipoAfip,
                'dias_base' => $rectificativa ? 0 : 30,
                'fecha_pago' => $fechaPago,
                'fecha_rubrica' => $fechaRubrica,
                'estado' => 'generada',
                'es_rectificativa' => $rectificativa,
                'presentacion_orig_id' => ! empty($datos['presentacion_orig_id']) ? (int) $datos['presentacion_orig_id'] : null,
                'cantidad_registros_04' => $armado['cantidad_04'],
                'cantidad_trabajadores' => $armado['trabajadores'],
                'validaciones_json' => $armado['validaciones'],
                'observacion' => $datos['observacion'] ?? null,
                'usuario_id' => $usuarioId,
                'generado_at' => now(),
            ]);

            $nro = 1;
            foreach ($armado['registros'] as $reg) {
                Lsd_Presentacion_Registro_Sueldos::create([
                    'presentacion_id' => $presentacion->id,
                    'tipo_registro' => $reg['tipo'],
                    'nro_linea' => $nro++,
                    'cuil' => $reg['cuil'] ?? null,
                    'contenido' => $reg['contenido'],
                    'estado_linea' => $reg['estado'],
                    'mensaje' => $reg['mensaje'] ?? null,
                ]);
            }

            $txt = $this->contenidoDesdePresentacion($presentacion->fresh('registros'));
            $presentacion->update([
                'archivo_hash' => hash('sha256', $txt),
                'archivo_nombre' => sprintf('LSD_%s_%05d.txt', $periodo, $nroAfip),
                'archivo_bytes' => strlen($txt),
            ]);

            return $presentacion->fresh(['registros', 'liquidacion', 'empresa']);
        });
    }

    public function contenidoDesdePresentacion(Lsd_Presentacion_Sueldos $p): string
    {
        $lineas = $p->registros->map(fn ($r) => $r->lineaEfectiva())->all();

        return LsdAnsiSupport::archivo($lineas);
    }

    public function proximoNroAfip(int $empresaId, int $periodo): int
    {
        $max = (int) Lsd_Presentacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo)
            ->max('nro_liquidacion_afip');

        return min(99999, $max + 1);
    }

    /**
     * Snapshot de bases al cerrar la liquidación.
     */
    public function persistirBasesCierre(Liquidacion_Sueldos $liq): int
    {
        $liq->loadMissing(['recibos.detalles.concepto', 'recibos.empleado.familiares', 'recibos.empleado.revistasLsd']);
        $parametros = new ParametroSueldosResolver((int) $liq->empresa_id, optional($liq->fecha_pago)->format('Y-m-d') ?: date('Y-m-d'));
        $periodo = (int) ($liq->periodo_anio * 100 + $liq->periodo_mes);
        $acumuladoPrevio = $this->basesAcumuladasPeriodo((int) $liq->empresa_id, $periodo, (int) $liq->id);
        $n = 0;

        foreach ($liq->recibos as $recibo) {
            $bases = $this->resolverBasesRecibo($recibo, $parametros, $periodo, $acumuladoPrevio[(int) $recibo->empleado_id] ?? null);
            Lsd_Recibo_Base_Sueldos::query()->updateOrCreate(
                ['recibo_id' => $recibo->id],
                array_merge($bases, [
                    'liquidacion_id' => (int) $liq->id,
                    'empleado_id' => (int) $recibo->empleado_id,
                ])
            );
            $n++;
        }

        return $n;
    }

    /**
     * @return array{registros: list<array<string,mixed>>, cantidad_04: int, trabajadores: int, validaciones: list<array{nivel:string,mensaje:string}>}
     */
    private function armarLineas(
        Liquidacion_Sueldos $liq,
        string $ident,
        bool $rectificativa,
        int $nroAfip,
        string $tipoAfip,
        ?string $fechaPago,
        ?string $fechaRubrica,
        bool $incluirLicencias,
    ): array {
        $validaciones = [];
        $registros = [];
        $cuit = preg_replace('/\D+/', '', (string) ($liq->empresa->nroinscripcion ?? '')) ?? '';
        if (strlen($cuit) !== 11) {
            $validaciones[] = ['nivel' => 'error', 'mensaje' => 'La empresa no tiene CUIT (nro. inscripción) de 11 dígitos.'];
        }

        $parametros = new ParametroSueldosResolver((int) $liq->empresa_id, $fechaPago ?: date('Y-m-d'));
        $periodo = (int) ($liq->periodo_anio * 100 + $liq->periodo_mes);
        $acumuladoPrevio = $this->basesAcumuladasPeriodo((int) $liq->empresa_id, $periodo, (int) $liq->id);

        $cuils04 = [];
        $trabajadores = [];

        foreach ($liq->recibos as $recibo) {
            $emp = $recibo->empleado;
            $cuil = LsdAnsiSupport::cuil11($recibo->cuil ?: ($emp->cuil ?? ''));
            if (strlen(preg_replace('/\D+/', '', $cuil) ?? '') !== 11) {
                $validaciones[] = ['nivel' => 'error', 'mensaje' => 'Recibo '.$recibo->legajo.': CUIL inválido.'];
            }
            $trabajadores[$cuil] = true;

            $bases = $this->resolverBasesRecibo(
                $recibo,
                $parametros,
                $periodo,
                $acumuladoPrevio[(int) $recibo->empleado_id] ?? null
            );

            if (($bases['rem_bruta'] ?? 0) <= 0) {
                $validaciones[] = ['nivel' => 'warning', 'mensaje' => 'Recibo '.$recibo->legajo.': remuneración bruta 0 en registro 04.'];
            }

            $principal = $emp ? (bool) $emp->lsd_legajo_principal : true;
            if (isset($cuils04[$cuil]) && $principal) {
                $validaciones[] = ['nivel' => 'warning', 'mensaje' => 'CUIL '.$cuil.' en más de un legajo. El 04 se informa una sola vez (legajo principal).'];
                $principal = false;
            }

            if (! $rectificativa) {
                $registros[] = $this->linea02($recibo, $emp, $cuil, $bases, $fechaPago, $fechaRubrica);
                foreach ($this->lineas03($recibo, $cuil, $validaciones) as $l03) {
                    $registros[] = $l03;
                }
            }

            if ($principal && ! isset($cuils04[$cuil])) {
                $registros[] = $this->linea04($recibo, $emp, $cuil, $bases, $periodo);
                $cuils04[$cuil] = true;
                if ($this->esEventual($emp)) {
                    $registros[] = $this->linea05($recibo, $emp, $cuil, $bases, $liq, $validaciones);
                }
            }

            if (! $rectificativa) {
                $obs = trim((string) ($recibo->observacion ?? ''));
                if ($obs !== '') {
                    $registros[] = [
                        'tipo' => '06',
                        'cuil' => $cuil,
                        'contenido' => LsdRegistroSupport::registro06(['cuil' => $cuil, 'observacion' => $obs]),
                        'estado' => 'ok',
                    ];
                }
            }
        }

        if ($incluirLicencias) {
            foreach ($this->empleadosLicenciaSinRecibo($liq, array_keys($trabajadores)) as $empLic) {
                $cuil = LsdAnsiSupport::cuil11($empLic->cuil);
                $basesVacias = LsdBasesImponiblesSupport::calcular([], $parametros, 0, (string) $empLic->condicion_sijp);
                $fakeRecibo = new Liquidacion_Recibo_Sueldos([
                    'empleado_id' => $empLic->id,
                    'cuil' => $empLic->cuil,
                    'legajo' => $empLic->legajo,
                ]);
                $fakeRecibo->setRelation('empleado', $empLic);
                $registros[] = $this->linea04($fakeRecibo, $empLic, $cuil, $basesVacias, $periodo);
                $cuils04[$cuil] = true;
                $validaciones[] = ['nivel' => 'info', 'mensaje' => 'CUIL '.$cuil.': licencia sin recibo (solo registro 04).'];
            }
        }

        $cantidad04 = count($cuils04);
        array_unshift($registros, [
            'tipo' => '01',
            'cuil' => null,
            'contenido' => LsdRegistroSupport::registro01([
                'cuit' => $cuit,
                'identificacion' => $ident,
                'periodo' => $periodo,
                'tipo_liquidacion' => $tipoAfip,
                'nro_liquidacion' => $nroAfip,
                'dias_base' => 30,
                'cantidad_04' => $cantidad04,
            ]),
            'estado' => $cantidad04 > 0 ? 'ok' : 'error',
            'mensaje' => $cantidad04 > 0 ? null : 'No hay registros 04.',
        ]);

        if ($cantidad04 === 0) {
            $validaciones[] = ['nivel' => 'error', 'mensaje' => 'La liquidación no tiene trabajadores para el registro 04.'];
        }
        if (! $rectificativa && in_array($tipoAfip, ['M', 'Q'], true)) {
            $validaciones[] = [
                'nivel' => 'info',
                'mensaje' => 'Tipo AFIP '.$tipoAfip.'. Si hubo vacaciones/SAC/final en el mismo período, ARCA exige importarlas antes (número de liquidación cronológico).',
            ];
        }

        return [
            'registros' => $registros,
            'cantidad_04' => $cantidad04,
            'trabajadores' => count($trabajadores),
            'validaciones' => $validaciones,
        ];
    }

    /**
     * @param  array<string, float|int>  $bases
     * @return array<string, mixed>
     */
    private function linea02(Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp, string $cuil, array $bases, ?string $fechaPago, ?string $fechaRubrica): array
    {
        $cbu = preg_replace('/\D+/', '', (string) ($emp->cbu ?? '')) ?? '';
        $forma = strlen($cbu) === 22 ? '3' : '1';
        $dep = trim((string) (optional($emp?->lugartrabajo)->nombre ?? optional($emp?->lugartrabajo)->descripcion ?? ''));

        return [
            'tipo' => '02',
            'cuil' => $cuil,
            'contenido' => LsdRegistroSupport::registro02([
                'cuil' => $cuil,
                'legajo' => $recibo->legajo,
                'dependencia' => $dep,
                'cbu' => $cbu,
                'dias_tope' => $bases['dias_tope'] ?? 0,
                'fecha_pago' => $fechaPago,
                'fecha_rubrica' => $fechaRubrica,
                'forma_pago' => $forma,
            ]),
            'estado' => 'ok',
        ];
    }

    /**
     * @param  list<array{nivel:string,mensaje:string}>  $validaciones
     * @return list<array<string, mixed>>
     */
    private function lineas03(Liquidacion_Recibo_Sueldos $recibo, string $cuil, array &$validaciones): array
    {
        $out = [];
        foreach ($recibo->detalles as $det) {
            if (LsdBasesImponiblesSupport::excluyeDelLibro((string) $det->tipo)) {
                continue;
            }
            $afip = LsdConceptoAfipCatalogo::normalizarCodigo($det->concepto_afip);
            $concepto = $det->concepto;
            if ($afip === null) {
                $afip = LsdConceptoAfipCatalogo::normalizarCodigo($concepto->concepto_afip ?? null);
            }
            if ($afip === null) {
                $soloBases = LsdBases04Support::tieneMapeo(is_array(optional($concepto)->lsd_bases) ? $concepto->lsd_bases : null)
                    || LsdConceptoAfipAsignacionSupport::debeOmitir(
                        (int) $det->concepto_codigo,
                        (string) (optional($concepto)->descripcion ?? $det->concepto_descripcion ?? ''),
                        (string) $det->tipo
                    );
                if ($soloBases) {
                    continue;
                }
                $validaciones[] = [
                    'nivel' => 'error',
                    'mensaje' => 'Legajo '.$recibo->legajo.': concepto '.$det->concepto_codigo.' sin mapeo AFIP.',
                ];

                continue;
            }
            $codEmp = trim((string) ($concepto->codigo_lsd_empleador ?? ''));
            if ($codEmp === '') {
                $codEmp = LsdConceptoAfipCatalogo::codigoEmpleadorDesdeInterno($det->concepto_codigo);
            }
            $unidad = strtoupper(substr(trim((string) ($concepto->unidad_medida ?? '')), 0, 1));
            $cantidad = (float) $det->cantidad;
            if (LsdConceptoAfipCatalogo::pideCantidad($afip) && $cantidad <= 0) {
                $validaciones[] = [
                    'nivel' => 'warning',
                    'mensaje' => 'Legajo '.$recibo->legajo.': concepto AFIP '.$afip.' requiere cantidad.',
                ];
            }
            $out[] = [
                'tipo' => '03',
                'cuil' => $cuil,
                'contenido' => LsdRegistroSupport::registro03([
                    'cuil' => $cuil,
                    'codigo_empleador' => $codEmp,
                    'cantidad' => $cantidad,
                    'unidad' => $unidad,
                    'importe' => abs((float) $det->importe),
                    'dh' => LsdRegistroSupport::debitoCredito((string) $det->tipo, (float) $det->importe),
                    'periodo_ajuste' => 0,
                ]),
                'estado' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float|int>  $bases
     * @return array<string, mixed>
     */
    private function linea04(Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp, string $cuil, array $bases, int $periodo): array
    {
        $revistas = $this->revistasEmpleado($emp, $periodo);
        $conyuge = 0;
        $hijos = 0;
        if ($emp) {
            $emp->loadMissing('familiares');
            foreach ($emp->familiares as $fam) {
                if (! $fam->activo) {
                    continue;
                }
                if ($fam->tipo === Empleado_Familiar_Sueldos::TIPOS['CONYUGE'] || $fam->tipo === 'CONYUGE') {
                    $conyuge = 1;
                }
                if (in_array($fam->tipo, ['HIJOS', 'HIJOS_50', 'HIJO_INCAP'], true)) {
                    $hijos++;
                }
            }
        }

        return [
            'tipo' => '04',
            'cuil' => $cuil,
            'contenido' => LsdRegistroSupport::registro04(array_merge($bases, [
                'cuil' => $cuil,
                'conyuge' => $conyuge,
                'hijos' => min(99, $hijos),
                'cct' => $emp && $emp->lsd_cct ? 1 : 0,
                'scvo' => $emp && $emp->lsd_scvo === false ? 0 : 1,
                'reduccion' => $emp?->marca_reduccion_sijp ?? '0',
                'tipo_empresa' => $emp?->tipo_empresa_sijp ?? '0',
                'codigo_situacion' => $this->padCodigo($emp?->situacion_sijp ?? '01', 2),
                'codigo_condicion' => $this->padCodigo($emp?->condicion_sijp ?? '01', 2),
                'actividad' => $this->padCodigo($emp?->actividad_sijp ?? '000', 3),
                'modalidad' => $this->padCodigo($emp?->modalidad_sijp ?? '001', 3),
                'siniestrado' => $this->padCodigo($emp?->siniestrado_sijp ?? '00', 2),
                'localidad' => $this->padCodigo($emp?->localidad_afip ?? '00', 2),
                'situacion_1' => $revistas[0]['situacion'] ?? $this->padCodigo($emp?->situacion_sijp ?? '01', 2),
                'dia_inicio_1' => $revistas[0]['dia'] ?? 1,
                'situacion_2' => $revistas[1]['situacion'] ?? '',
                'dia_inicio_2' => $revistas[1]['dia'] ?? 0,
                'situacion_3' => $revistas[2]['situacion'] ?? '',
                'dia_inicio_3' => $revistas[2]['dia'] ?? 0,
                'codigo_os' => $this->codigoOs($emp),
                'dias_trabajados' => ($bases['horas_trabajadas'] ?? 0) > 0 ? 0 : ($bases['dias_trabajados'] ?? 0),
                'horas_trabajadas' => ($bases['dias_trabajados'] ?? 0) > 0 ? 0 : ($bases['horas_trabajadas'] ?? 0),
            ])),
            'estado' => 'ok',
        ];
    }

    /**
     * @param  array<string, float|int>  $bases
     * @param  list<array{nivel:string,mensaje:string}>  $validaciones
     * @return array<string, mixed>
     */
    private function linea05(Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp, string $cuil, array $bases, Liquidacion_Sueldos $liq, array &$validaciones): array
    {
        $cuitAg = preg_replace('/\D+/', '', (string) ($emp->cuit_agencia_eventual ?? '')) ?? '';
        if (strlen($cuitAg) !== 11) {
            $validaciones[] = ['nivel' => 'warning', 'mensaje' => 'CUIL '.$cuil.': eventual sin CUIT de agencia.'];
        }
        $desde = optional($liq->periodo_desde)->format('Y-m-d') ?: optional($emp->fecha_ingreso)->format('Y-m-d');
        $hasta = optional($liq->periodo_hasta)->format('Y-m-d') ?: optional($emp->fecha_egreso)->format('Y-m-d');

        return [
            'tipo' => '05',
            'cuil' => $cuil,
            'contenido' => LsdRegistroSupport::registro05([
                'cuil' => $cuil,
                'categoria' => optional($emp?->categoria)->codigo ?? 0,
                'puesto' => 0,
                'fecha_ingreso' => $desde,
                'fecha_egreso' => $hasta,
                'importe' => $bases['rem_bruta'] ?? 0,
                'cuit_agencia' => $cuitAg,
            ]),
            'estado' => strlen($cuitAg) === 11 ? 'ok' : 'warning',
            'mensaje' => strlen($cuitAg) === 11 ? null : 'Falta CUIT agencia eventual',
        ];
    }

    private function esEventual(?Empleado_Sueldos $emp): bool
    {
        if (! $emp) {
            return false;
        }
        $mod = $this->padCodigo($emp->modalidad_sijp ?? '', 3);

        return $mod === '102';
    }

    /**
     * @return list<array{situacion: string, dia: int}>
     */
    private function revistasEmpleado(?Empleado_Sueldos $emp, int $periodo): array
    {
        if (! $emp) {
            return [];
        }
        $filas = Lsd_Empleado_Revista_Sueldos::query()
            ->where('empleado_id', $emp->id)
            ->where(function ($q) use ($periodo) {
                $q->where('periodo', $periodo)->orWhereNull('periodo');
            })
            ->orderBy('nro')
            ->limit(3)
            ->get();
        $out = [];
        foreach ($filas as $f) {
            $out[] = [
                'situacion' => $this->padCodigo($f->situacion, 2),
                'dia' => (int) $f->dia_inicio,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float>|null  $previas
     * @return array<string, float|int>
     */
    private function resolverBasesRecibo(
        Liquidacion_Recibo_Sueldos $recibo,
        ParametroSueldosResolver $parametros,
        int $periodo,
        ?array $previas,
    ): array {
        $tieneMapeo = false;
        foreach ($recibo->detalles as $det) {
            if (LsdBases04Support::tieneMapeo(optional($det->concepto)->lsd_bases)) {
                $tieneMapeo = true;
                break;
            }
        }
        if ($recibo->lsdBase && ! $tieneMapeo) {
            return $this->basesDesdeSnapshot($recibo->lsdBase);
        }

        return $this->basesDeRecibo($recibo, $parametros, $periodo, $previas);
    }

    /**
     * @return array<string, float|int>
     */
    private function basesDeRecibo(
        Liquidacion_Recibo_Sueldos $recibo,
        ParametroSueldosResolver $parametros,
        int $periodo,
        ?array $previas,
    ): array {
        $lineas = [];
        foreach ($recibo->detalles as $det) {
            $lineas[] = [
                'tipo' => (string) $det->tipo,
                'concepto_afip' => $det->concepto_afip,
                'importe' => (float) $det->importe,
                'cantidad' => (float) $det->cantidad,
                'lsd_bases' => is_array(optional($det->concepto)->lsd_bases) ? $det->concepto->lsd_bases : null,
            ];
        }
        $dias = (int) round((float) ($recibo->dias_trabajados ?? 0));
        if ($dias <= 0) {
            $dias = 30;
        }
        $emp = $recibo->empleado;
        $ctxDetraccion = [
            'condicion_sijp' => (string) ($emp?->condicion_sijp ?? '01'),
            'modalidad_sijp' => (int) ($emp?->modalidad_sijp ?? 0),
            'dias' => $dias,
        ];
        $calc = LsdBasesImponiblesSupport::calcular(
            $lineas,
            $parametros,
            $dias,
            $ctxDetraccion['condicion_sijp'],
            $ctxDetraccion,
        );
        $usaAnita = false;
        foreach ($lineas as $l) {
            if (LsdBases04Support::tieneMapeo($l['lsd_bases'] ?? null)) {
                $usaAnita = true;
                break;
            }
        }
        $horas = (int) round((float) ($recibo->horas ?? 0));
        if (! $usaAnita && $horas > 0 && $dias >= 30) {
            $calc['horas_trabajadas'] = $horas;
            $calc['dias_trabajados'] = 0;
        }
        if (is_array($previas)) {
            foreach (LsdBases04Support::CLAVES_IMPORTE as $k) {
                $calc[$k] = round(((float) ($calc[$k] ?? 0)) + ((float) ($previas[$k] ?? 0)), 2);
            }
            $calc = LsdDetraccionSupport::limitarTopeMensual($calc, $parametros, $ctxDetraccion);
        }
        $rev = $this->revistasEmpleado($emp, $periodo);
        $calc['situacion_1'] = $rev[0]['situacion'] ?? $this->padCodigo($emp?->situacion_sijp ?? '01', 2);
        $calc['dia_inicio_1'] = $rev[0]['dia'] ?? 1;
        $calc['situacion_2'] = $rev[1]['situacion'] ?? null;
        $calc['dia_inicio_2'] = $rev[1]['dia'] ?? null;
        $calc['situacion_3'] = $rev[2]['situacion'] ?? null;
        $calc['dia_inicio_3'] = $rev[2]['dia'] ?? null;

        return $calc;
    }

    /**
     * @return array<string, float|int>
     */
    private function basesDesdeSnapshot(Lsd_Recibo_Base_Sueldos $s): array
    {
        return [
            'dias_tope' => (int) $s->dias_tope,
            'dias_trabajados' => (int) $s->dias_trabajados,
            'horas_trabajadas' => (int) $s->horas_trabajadas,
            'rem_bruta' => (float) $s->rem_bruta,
            'base_1' => (float) $s->base_1,
            'base_2' => (float) $s->base_2,
            'base_3' => (float) $s->base_3,
            'base_4' => (float) $s->base_4,
            'base_5' => (float) $s->base_5,
            'base_6' => (float) $s->base_6,
            'base_7' => (float) $s->base_7,
            'base_8' => (float) $s->base_8,
            'base_9' => (float) $s->base_9,
            'base_10' => (float) $s->base_10,
            'importe_detraer' => (float) $s->importe_detraer,
            'situacion_1' => $s->situacion_1,
            'dia_inicio_1' => $s->dia_inicio_1,
            'situacion_2' => $s->situacion_2,
            'dia_inicio_2' => $s->dia_inicio_2,
            'situacion_3' => $s->situacion_3,
            'dia_inicio_3' => $s->dia_inicio_3,
        ];
    }

    /**
     * @return array<int, array<string, float>>
     */
    private function basesAcumuladasPeriodo(int $empresaId, int $periodo, int $exceptoLiqId): array
    {
        $liqIds = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo_anio', intdiv($periodo, 100))
            ->where('periodo_mes', $periodo % 100)
            ->where('id', '!=', $exceptoLiqId)
            ->whereIn('estado', ['cerrada', 'contabilizada', 'pagada'])
            ->pluck('id');
        if ($liqIds->isEmpty()) {
            return [];
        }
        $out = [];
        $filas = Lsd_Recibo_Base_Sueldos::query()->whereIn('liquidacion_id', $liqIds)->get();
        foreach ($filas as $f) {
            $id = (int) $f->empleado_id;
            if (! isset($out[$id])) {
                $out[$id] = [];
            }
            foreach (['rem_bruta', 'base_1', 'base_2', 'base_3', 'base_4', 'base_5', 'base_6', 'base_7', 'base_8', 'base_9', 'base_10'] as $k) {
                $out[$id][$k] = round(((float) ($out[$id][$k] ?? 0)) + (float) $f->{$k}, 2);
            }
        }

        return $out;
    }

    /** @return \Illuminate\Support\Collection<int, Empleado_Sueldos> */
    private function empleadosLicenciaSinRecibo(Liquidacion_Sueldos $liq, array $cuilsYa): \Illuminate\Support\Collection
    {
        $situaciones = ['5', '05', '11', '51'];
        $cuilsNorm = [];
        foreach ($cuilsYa as $c) {
            $n = preg_replace('/\D+/', '', (string) $c) ?? '';
            if ($n !== '') {
                $cuilsNorm[] = $n;
            }
        }

        $q = Empleado_Sueldos::query()
            ->where('empresa_id', $liq->empresa_id)
            ->where('estado', '!=', EmpleadoEstados::BAJA)
            ->whereIn('situacion_sijp', $situaciones);
        if ($cuilsNorm !== []) {
            $q->whereNotIn(DB::raw("REPLACE(REPLACE(REPLACE(cuil,'-',''),' ',''),'.','')"), $cuilsNorm);
        }

        return $q->get();
    }

    private function codigoOs(?Empleado_Sueldos $emp): string
    {
        if (! $emp) {
            return '';
        }
        $emp->loadMissing('obrasocial');
        $cod = (string) (optional($emp->obrasocial)->codigo ?? '');

        return $this->padCodigo($cod, 6);
    }

    private function padCodigo(?string $valor, int $largo): string
    {
        $v = preg_replace('/\D+/', '', (string) $valor) ?? '';
        if ($v === '') {
            return str_repeat('0', $largo);
        }

        return str_pad(substr($v, -$largo), $largo, '0', STR_PAD_LEFT);
    }
}
