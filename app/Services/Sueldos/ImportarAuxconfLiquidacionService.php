<?php

namespace App\Services\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Importacion_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Sueldos\AnitaAuxLiquidacionSupport;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use App\Support\Sueldos\LiquidacionDetalleTotalesSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importa auxconf/auxconfh a recibos/detalles de una corrida ERP existente.
 */
class ImportarAuxconfLiquidacionService
{
    public function __construct(
        private AnitaAuxLiquidacionSupport $aux,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analizar(
        Liquidacion_Sueldos $liquidacion,
        string $fuente = 'auto',
        ?int $empresaAnita = null,
        bool $eliminarAusentes = false
    ): array {
        $this->validarCorrida($liquidacion);

        $empresaAnita = $empresaAnita ?: SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $liquidacion->empresa_id);
        $liqAnita = (int) $liquidacion->numero;

        $lectura = $this->aux->filasConfidencialesLiquidacion($empresaAnita, $liqAnita, $fuente);
        $filas = $lectura['filas'];
        $errores = $lectura['errores'];

        if ($lectura['fuente'] === 'conflicto' || $lectura['fuente'] === 'ninguna') {
            return $this->planBase($liquidacion, $empresaAnita, $liqAnita, $lectura['fuente'], [], $errores, true);
        }

        $porLegajo = [];
        $codigos = [];
        $filasAmbiguas = 0;
        $filasVacias = 0;
        foreach ($filas as $f) {
            $haberes = (float) $f['haberes'];
            $deduc = (float) $f['deduc'];
            if (abs($haberes) > 0.0001 && abs($deduc) > 0.0001) {
                $filasAmbiguas++;

                continue;
            }
            if (abs($haberes) < 0.0001 && abs($deduc) < 0.0001
                && abs((float) $f['cantidad']) < 0.0001
                && abs((float) $f['valor']) < 0.0001) {
                $filasVacias++;

                continue;
            }
            $legajo = (int) $f['legajo'];
            $porLegajo[$legajo][] = $f;
            $codigos[(int) $f['codigo']] = (int) $f['codigo'];
        }

        $empleados = Empleado_Sueldos::query()
            ->where('empresa_id', (int) $liquidacion->empresa_id)
            ->whereIn('legajo', array_keys($porLegajo))
            ->get()
            ->keyBy(fn ($e) => (int) $e->legajo);

        $faltanEmpleados = array_values(array_diff(array_keys($porLegajo), $empleados->keys()->all()));

        $conceptos = Concepto_Sueldos::query()
            ->whereIn('codigo', array_values($codigos))
            ->get()
            ->keyBy(fn ($c) => (int) $c->codigo);
        $faltanConceptos = array_values(array_diff(array_values($codigos), $conceptos->keys()->all()));

        $recibosExistentes = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacion->id)
            ->whereIn('empleado_id', $empleados->pluck('id')->all())
            ->get()
            ->keyBy('empleado_id');

        $crear = 0;
        $actualizar = 0;
        $iguales = 0;
        $conflictosMotor = 0;
        $empleadosMarcar = 0;
        $detallePlan = [];
        $bloqueantes = $errores;

        if ($filasAmbiguas > 0) {
            $bloqueantes[] = "Hay {$filasAmbiguas} fila(s) con haberes y deducciones simultáneos.";
        }
        if ($faltanEmpleados !== []) {
            $bloqueantes[] = 'Faltan empleados ERP para legajos: '.implode(', ', array_slice($faltanEmpleados, 0, 20));
        }
        if ($faltanConceptos !== []) {
            $bloqueantes[] = 'Faltan conceptos ERP para códigos: '.implode(', ', array_slice($faltanConceptos, 0, 30));
        }

        foreach ($porLegajo as $legajo => $lineasAnita) {
            $emp = $empleados->get($legajo);
            if (! $emp) {
                continue;
            }
            $lineasDto = [];
            $firmas = [];
            $anomalias = [];
            foreach ($lineasAnita as $f) {
                $concepto = $conceptos->get((int) $f['codigo']);
                if (! $concepto) {
                    continue;
                }
                $importe = abs((float) $f['haberes']) > 0.0001 ? (float) $f['haberes'] : (float) $f['deduc'];
                $tipo = (string) ($concepto->tipo ?? 'remunerativo');
                $usaHaberes = abs((float) $f['haberes']) > 0.0001;
                if ($usaHaberes && in_array($tipo, ['descuento', 'aporte', 'retencion'], true)) {
                    $anomalias[] = 'código '.$f['codigo'].' (descuento en haberes)';
                }
                if (! $usaHaberes && in_array($tipo, ['remunerativo', 'no_remunerativo', 'asignacion'], true)) {
                    $anomalias[] = 'código '.$f['codigo'].' (haber en deducciones)';
                }
                $clave = LiquidacionDetalleTotalesSupport::claveOrigenDetalle(
                    $empresaAnita,
                    $liqAnita,
                    (int) $f['legajo'],
                    (int) $f['codigo'],
                    (int) $f['nro_interno']
                );
                $firmas[] = hash('sha256', implode('|', [
                    $clave,
                    number_format((float) $f['cantidad'], 4, '.', ''),
                    number_format((float) $f['valor'], 4, '.', ''),
                    number_format($importe, 2, '.', ''),
                    $tipo,
                    (int) ($concepto->va_recibo ?? true),
                ]));
                $lineasDto[] = [
                    'concepto_id' => $concepto->id,
                    'concepto_codigo' => (int) $concepto->codigo,
                    'concepto_descripcion' => $f['descripcion'] !== '' ? $f['descripcion'] : (string) $concepto->descripcion,
                    'tipo' => $tipo,
                    'columna' => LiquidacionDetalleTotalesSupport::columnaParaTipo($tipo),
                    'cantidad' => (float) $f['cantidad'],
                    'valor' => (float) $f['valor'],
                    'importe' => $importe,
                    'remunerativo' => $tipo === 'remunerativo',
                    'va_recibo' => (bool) ($concepto->va_recibo ?? true),
                    'concepto_afip' => $concepto->concepto_afip ?? null,
                    'leyenda' => null,
                    'origen_tabla' => $f['tabla'],
                    'origen_serial' => (int) $f['serial'],
                    'origen_nro_interno' => (int) $f['nro_interno'],
                    'origen_clave' => $clave,
                ];
            }
            if ($anomalias !== []) {
                $bloqueantes[] = 'Legajo '.$legajo.': incoherencias tipo/campo — '.implode('; ', array_slice($anomalias, 0, 5));
            }

            $fp = LiquidacionDetalleTotalesSupport::fingerprintRecibo($firmas);
            $existente = $recibosExistentes->get($emp->id);
            $accion = 'crear';
            if ($existente) {
                $origen = (string) ($existente->origen ?? Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR);
                if ($origen !== Liquidacion_Recibo_Sueldos::ORIGEN_AUXCONF && $origen !== '') {
                    if ($origen === Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR || $origen === 'motor_erp') {
                        $conflictosMotor++;
                        $bloqueantes[] = 'Legajo '.$legajo.': ya tiene recibo del motor ERP';
                        $accion = 'conflicto';
                    }
                }
                if ($accion !== 'conflicto') {
                    if ((string) $existente->origen_fingerprint === $fp) {
                        $accion = 'igual';
                        $iguales++;
                    } else {
                        $accion = 'actualizar';
                        $actualizar++;
                    }
                }
            } else {
                $crear++;
            }

            if (! (bool) ($emp->confidencial ?? false)) {
                $empleadosMarcar++;
            }

            $tot = LiquidacionDetalleTotalesSupport::totalesRecibo($lineasDto);
            $detallePlan[] = [
                'legajo' => $legajo,
                'empleado_id' => $emp->id,
                'accion' => $accion,
                'lineas' => count($lineasDto),
                'neto' => $tot['neto'],
                'fingerprint' => $fp,
                'lineas_dto' => $lineasDto,
                'totales' => $tot,
            ];
        }

        $empleadoIdsFuente = $empleados->pluck('id')->map(fn ($id) => (int) $id)->all();
        $recibosAusentes = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacion->id)
            ->where('origen', Liquidacion_Recibo_Sueldos::ORIGEN_AUXCONF)
            ->when($empleadoIdsFuente !== [], fn ($q) => $q->whereNotIn('empleado_id', $empleadoIdsFuente))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $plan = [
            'liquidacion_id' => $liquidacion->id,
            'empresa_id' => (int) $liquidacion->empresa_id,
            'empresa_anita' => $empresaAnita,
            'liquidacion_anita' => $liqAnita,
            'fuente' => $lectura['fuente'],
            'filas_leidas' => count($filas),
            'filas_vacias' => $filasVacias,
            'filas_ambiguas' => $filasAmbiguas,
            'empleados' => count($porLegajo),
            'empleados_ok' => $empleados->count(),
            'faltan_empleados' => $faltanEmpleados,
            'faltan_conceptos' => $faltanConceptos,
            'recibos_crear' => $crear,
            'recibos_actualizar' => $actualizar,
            'recibos_iguales' => $iguales,
            'conflictos_motor' => $conflictosMotor,
            'empleados_marcar_confidencial' => $empleadosMarcar,
            'eliminar_ausentes' => $eliminarAusentes,
            'recibos_eliminar' => $eliminarAusentes ? count($recibosAusentes) : 0,
            'recibo_ids_eliminar' => $eliminarAusentes ? $recibosAusentes : [],
            'bloqueantes' => $bloqueantes,
            'puede_ejecutar' => $bloqueantes === []
                && ($crear + $actualizar + $empleadosMarcar + ($eliminarAusentes ? count($recibosAusentes) : 0)) > 0,
            'detalle' => $detallePlan,
        ];
        $plan['plan_hash'] = hash('sha256', json_encode([
            'liq' => $liquidacion->id,
            'fuente' => $plan['fuente'],
            'empresa_anita' => $empresaAnita,
            'liq_anita' => $liqAnita,
            'eliminar_ausentes' => $eliminarAusentes,
            'recibo_ids_eliminar' => $plan['recibo_ids_eliminar'],
            'detalle' => array_map(fn ($d) => [
                'legajo' => $d['legajo'],
                'accion' => $d['accion'],
                'fp' => $d['fingerprint'],
                'neto' => $d['neto'],
                'lineas' => $d['lineas'],
            ], $detallePlan),
        ], JSON_UNESCAPED_UNICODE));

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function ejecutar(Liquidacion_Sueldos $liquidacion, array $plan, Usuario $usuario, bool $eliminarAusentes = false): array
    {
        if (! LiquidacionConfidencialSeguridadSupport::usuarioPuedeImportar($usuario, (int) $liquidacion->empresa_id)) {
            throw new \RuntimeException('Sin permiso para importar nómina confidencial.');
        }
        if (empty($plan['puede_ejecutar'])) {
            throw new \RuntimeException('El plan tiene bloqueantes y no puede ejecutarse.');
        }
        $hashEsperado = (string) ($plan['plan_hash'] ?? '');
        if ((bool) ($plan['eliminar_ausentes'] ?? false) !== $eliminarAusentes) {
            throw new \RuntimeException('La opción de eliminar ausentes no coincide con el dry-run.');
        }
        $reanalisis = $this->analizar(
            $liquidacion,
            (string) ($plan['fuente'] ?? 'auto'),
            (int) ($plan['empresa_anita'] ?? 0) ?: null,
            $eliminarAusentes
        );
        if (($reanalisis['plan_hash'] ?? '') !== $hashEsperado) {
            throw new \RuntimeException('El plan cambió respecto del dry-run. Vuelva a analizar.');
        }
        if (empty($reanalisis['puede_ejecutar'])) {
            throw new \RuntimeException('El plan reanalizado tiene bloqueantes.');
        }

        return DB::transaction(function () use ($liquidacion, $reanalisis, $usuario, $eliminarAusentes) {
            $liq = Liquidacion_Sueldos::query()->whereKey($liquidacion->id)->lockForUpdate()->firstOrFail();
            $this->validarCorrida($liq);

            $creados = 0;
            $actualizados = 0;
            $iguales = 0;
            $marcados = 0;
            $empleadoIdsImportados = [];

            foreach ($reanalisis['detalle'] as $item) {
                if (($item['accion'] ?? '') === 'conflicto') {
                    continue;
                }
                $emp = Empleado_Sueldos::query()->findOrFail((int) $item['empleado_id']);
                $empleadoIdsImportados[] = $emp->id;
                $recibo = Liquidacion_Recibo_Sueldos::query()
                    ->where('liquidacion_id', $liq->id)
                    ->where('empleado_id', $emp->id)
                    ->first();

                if (($item['accion'] ?? '') === 'igual' && $recibo) {
                    $iguales++;
                } else {
                    if (! $recibo) {
                        $recibo = new Liquidacion_Recibo_Sueldos([
                            'liquidacion_id' => $liq->id,
                            'empleado_id' => $emp->id,
                            'legajo' => $emp->legajo,
                            'numero_recibo' => 0,
                        ]);
                        $creados++;
                    } else {
                        Liquidacion_Detalle_Sueldos::query()->where('recibo_id', $recibo->id)->delete();
                        $actualizados++;
                    }

                    $tot = $item['totales'];
                    $recibo->fill([
                        'legajo' => $emp->legajo,
                        'apellido_nombre' => $emp->nombre,
                        'cuil' => $emp->cuil,
                        'categoria_id' => $emp->categoria_id,
                        'categoria_desc' => optional($emp->categoria)->descripcion,
                        'agrupamiento_id' => $emp->agrupamiento_id,
                        'lugartrabajo_id' => $emp->lugartrabajo_id,
                        'obrasocial_id' => $emp->obrasocial_id,
                        'sindicato_id' => $emp->sindicato_id,
                        'fecha_ingreso' => $emp->fecha_ingreso,
                        'sueldo_basico' => null,
                        'total_remunerativo' => $tot['rem'],
                        'total_no_remunerativo' => $tot['norem'],
                        'total_bruto' => $tot['bruto'],
                        'total_descuentos' => $tot['desc'],
                        'total_aportes' => $tot['aportes'],
                        'total_contribuciones' => $tot['contrib'],
                        'total_asignaciones' => $tot['asig'],
                        'neto' => $tot['neto'],
                        'redondeo' => 0,
                        'neto_a_pagar' => $tot['neto'],
                        'estado' => 'calculado',
                        'origen' => Liquidacion_Recibo_Sueldos::ORIGEN_AUXCONF,
                        'confidencial' => true,
                        'origen_fingerprint' => $item['fingerprint'],
                    ]);
                    $recibo->save();

                    $nro = 0;
                    foreach ($item['lineas_dto'] as $l) {
                        $nro++;
                        Liquidacion_Detalle_Sueldos::create([
                            'recibo_id' => $recibo->id,
                            'liquidacion_id' => $liq->id,
                            'empleado_id' => $emp->id,
                            'concepto_id' => $l['concepto_id'],
                            'concepto_codigo' => $l['concepto_codigo'],
                            'concepto_descripcion' => $l['concepto_descripcion'],
                            'tipo' => $l['tipo'],
                            'nro_linea' => $nro,
                            'columna' => $l['columna'],
                            'cantidad' => $l['cantidad'],
                            'valor' => $l['valor'],
                            'base_calculo' => null,
                            'importe' => $l['importe'],
                            'remunerativo' => $l['remunerativo'],
                            'va_recibo' => $l['va_recibo'],
                            'concepto_afip' => $l['concepto_afip'],
                            'leyenda' => $l['leyenda'],
                            'origen_tabla' => $l['origen_tabla'],
                            'origen_serial' => $l['origen_serial'],
                            'origen_nro_interno' => $l['origen_nro_interno'],
                            'origen_clave' => $l['origen_clave'],
                        ]);
                    }
                }

                if (! (bool) ($emp->confidencial ?? false)) {
                    $emp->update(['confidencial' => true]);
                    $marcados++;
                }
            }

            if ($eliminarAusentes) {
                Liquidacion_Recibo_Sueldos::query()
                    ->where('liquidacion_id', $liq->id)
                    ->where('origen', Liquidacion_Recibo_Sueldos::ORIGEN_AUXCONF)
                    ->when($empleadoIdsImportados !== [], fn ($q) => $q->whereNotIn('empleado_id', $empleadoIdsImportados))
                    ->delete();
            }

            LiquidacionDetalleTotalesSupport::renumerarRecibos((int) $liq->id);
            LiquidacionDetalleTotalesSupport::recalcularCabecera($liq->fresh());

            if (! in_array($liq->estado, ['calculada', 'revisada'], true)) {
                $liq->update(['estado' => 'calculada', 'fecha_calculo' => now()]);
            }

            Liquidacion_Importacion_Sueldos::create([
                'liquidacion_id' => $liq->id,
                'usuario_id' => $usuario->id,
                'fuente' => $reanalisis['fuente'],
                'plan_hash' => $reanalisis['plan_hash'],
                'empresa_anita' => $reanalisis['empresa_anita'],
                'liquidacion_anita' => $reanalisis['liquidacion_anita'],
                'filas' => $reanalisis['filas_leidas'],
                'recibos_creados' => $creados,
                'recibos_actualizados' => $actualizados,
                'recibos_iguales' => $iguales,
                'empleados_marcados' => $marcados,
                'resumen' => [
                    'empleados' => $reanalisis['empleados'],
                    'faltan_empleados' => $reanalisis['faltan_empleados'],
                    'faltan_conceptos' => $reanalisis['faltan_conceptos'],
                ],
            ]);

            return [
                'recibos_creados' => $creados,
                'recibos_actualizados' => $actualizados,
                'recibos_iguales' => $iguales,
                'empleados_marcados' => $marcados,
                'plan_hash' => $reanalisis['plan_hash'],
            ];
        });
    }

    private function validarCorrida(Liquidacion_Sueldos $liq): void
    {
        if (in_array($liq->estado, ['anulada', 'contabilizada', 'pagada', 'cerrada'], true)) {
            throw new \RuntimeException('La corrida no admite importación en estado '.$liq->estado.'.');
        }
        if (! in_array($liq->estado, ['borrador', 'calculada', 'revisada'], true)) {
            throw new \RuntimeException('Estado de corrida no permitido para importar: '.$liq->estado);
        }
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function planBase(
        Liquidacion_Sueldos $liq,
        int $empresaAnita,
        int $liqAnita,
        string $fuente,
        array $detalle,
        array $errores,
        bool $bloquear
    ): array {
        $plan = [
            'liquidacion_id' => $liq->id,
            'empresa_id' => (int) $liq->empresa_id,
            'empresa_anita' => $empresaAnita,
            'liquidacion_anita' => $liqAnita,
            'fuente' => $fuente,
            'filas_leidas' => 0,
            'filas_vacias' => 0,
            'filas_ambiguas' => 0,
            'empleados' => 0,
            'empleados_ok' => 0,
            'faltan_empleados' => [],
            'faltan_conceptos' => [],
            'recibos_crear' => 0,
            'recibos_actualizar' => 0,
            'recibos_iguales' => 0,
            'conflictos_motor' => 0,
            'empleados_marcar_confidencial' => 0,
            'eliminar_ausentes' => false,
            'recibos_eliminar' => 0,
            'recibo_ids_eliminar' => [],
            'bloqueantes' => $bloquear ? ($errores ?: ['Sin filas confidenciales en Anita para esta liquidación.']) : $errores,
            'puede_ejecutar' => false,
            'detalle' => $detalle,
        ];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE));

        return $plan;
    }
}
