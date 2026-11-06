<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownCierreEvento;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownComprobanteLinea;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trae las cajas abiertas y cerradas de un día desde cncadmin.
 * a-transfefe.c graba efectivo, rendmbill (origen C), fichacmov, rendgastro,
 * ciefrend, asigcaja y aprobrend. tesmov y monextr no tienen tabla en este módulo.
 */
class TesoreriaCrownCajaAnitaImportService
{
    /** @var array<string, string> */
    private const MONEDA = ['1' => 'ARS', '2' => 'USD', '3' => 'EUR'];

    /**
     * @param  list<int>  $excepto
     * @return array{cajas: list<array<string, mixed>>, fuera: list<string>, excluidas: list<int>}
     */
    public function plan(string $fechaYmd, array $excepto): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de cajas solo corre en Crown.');
        }
        $fechaInt = (int) str_replace('-', '', $fechaYmd);
        $jornada = TesoreriaCrownJornada::query()->whereDate('fecha', $fechaYmd)->first();
        if ($jornada === null) {
            throw new RuntimeException('No hay jornada del '.$fechaYmd.'.');
        }
        $excepto = array_fill_keys($excepto, true);
        $efectivo = $this->consultar(
            'efe_nro_oper,efe_tipo_oper,efe_caja,efe_cajero,efe_hora,efe_total_x,efe_fallo_mozo,efe_nro_z,efe_ult_ticket,efe_invitacion,efe_total_z,efe_tot_tarj,efe_nro_z_cash,efe_total_z_cash,efe_tot_parc_cash,efe_venta_online,efe_pago_online,efe_tarjeta_debito,efe_pago_voucher,efe_recargo_fin,efe_total_qr,efe_vta_apu_dep,efe_pago_apu_dep,efe_tk_inicial,efe_tk_final,efe_tot_carga,efe_tot_descarga,efe_cotiz_dolar,efe_cotiz_euro',
            'SELECT efe_nro_oper, efe_tipo_oper, efe_caja, efe_cajero, efe_hora, efe_total_x, efe_fallo_mozo, efe_nro_z, efe_ult_ticket, efe_invitacion, efe_total_z, efe_tot_tarj, efe_nro_z_cash, efe_total_z_cash, efe_tot_parc_cash, efe_venta_online, efe_pago_online, efe_tarjeta_debito, efe_pago_voucher, efe_recargo_fin, efe_total_qr, efe_vta_apu_dep, efe_pago_apu_dep, efe_tk_inicial, efe_tk_final, efe_tot_carga, efe_tot_descarga, efe_cotiz_dolar, efe_cotiz_euro'
            .' FROM efectivo WHERE efe_fecha = '.$fechaInt.' AND efe_tipo_oper IN ("I","F") ORDER BY efe_caja, efe_nro_oper'
        );
        $porCaja = [];
        foreach ($efectivo as $fila) {
            $porCaja[(int) $fila->efe_caja][] = $fila;
        }
        $asignadas = [];
        foreach ($this->consultar(
            'asigc_caja,asigc_cajero,asigc_tipo_caja,asigc_turno,asigc_estado',
            'SELECT asigc_caja, asigc_cajero, asigc_tipo_caja, asigc_turno, asigc_estado FROM asigcaja WHERE asigc_fecha = '.$fechaInt
        ) as $fila) {
            $asignadas[(int) $fila->asigc_caja] = $fila;
        }

        $entrar = [];
        $fuera = [];
        foreach ($porCaja as $caja => $filas) {
            if (isset($excepto[$caja])) {
                continue;
            }
            $tipos = [];
            foreach ($filas as $fila) {
                $tipos[trim((string) $fila->efe_tipo_oper)] = true;
            }
            if (! isset($tipos['F'])) {
                $fuera[] = 'Caja '.$caja.' no tiene rendición ese día.';

                continue;
            }
            $entrar[$caja] = $filas;
        }
        if ($entrar === []) {
            throw new RuntimeException('No hay cajas para importar.');
        }

        $numeros = [];
        foreach ($entrar as $filas) {
            foreach ($filas as $fila) {
                $numeros[] = (int) $fila->efe_nro_oper;
            }
        }
        $billetes = $this->porOperacion($this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'renmb_nro_oper,renmb_valor,renmb_cantidad,renmb_cod_mon',
                "SELECT renmb_nro_oper, renmb_valor, renmb_cantidad, renmb_cod_mon FROM rendmbill WHERE renmb_origen = 'C' AND renmb_nro_oper IN ($lista)"
            );
        }), 'renmb_nro_oper');
        $fichas = $this->porOperacion($this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'fichcv_nro_oper,fichcv_tipo_oper,fichcv_codigo,fichcv_cantidad',
                "SELECT fichcv_nro_oper, fichcv_tipo_oper, fichcv_codigo, fichcv_cantidad FROM fichacmov WHERE fichcv_tipo_oper IN ('H','P') AND fichcv_nro_oper IN ($lista)"
            );
        }), 'fichcv_nro_oper');
        $gastro = [];
        foreach ($this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'rendg_nro_oper,rendg_tot_nc,rendg_iva_fac,rendg_iva_nc,rendg_total_x,rendg_total_z,rendg_invitacion,rendg_fallo_mozo,rendg_ult_ticket,rendg_nro_z',
                "SELECT rendg_nro_oper, rendg_tot_nc, rendg_iva_fac, rendg_iva_nc, rendg_total_x, rendg_total_z, rendg_invitacion, rendg_fallo_mozo, rendg_ult_ticket, rendg_nro_z FROM rendgastro WHERE rendg_nro_oper IN ($lista)"
            );
        }) as $fila) {
            $gastro[(int) $fila->rendg_nro_oper] = $fila;
        }
        $fiscales = $this->porOperacion($this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'ciefr_nro_oper,ciefr_sucursal,ciefr_nro_x,ciefr_nro_z,ciefr_hora',
                "SELECT ciefr_nro_oper, ciefr_sucursal, ciefr_nro_x, ciefr_nro_z, ciefr_hora FROM ciefrend WHERE ciefr_nro_oper IN ($lista)"
            );
        }), 'ciefr_nro_oper');
        $aprobacion = $this->ultimaAprobacion($numeros);
        $fichaValor = $this->fichasPorCodigo((int) $jornada->sala_id);
        $billeteValor = $this->billetesPorClave((int) $jornada->sala_id);
        $depositos = TesoreriaCrownDeposito::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereHas('tipoDeposito', fn ($q) => $q->where('codigo', 'CAJA'))
            ->get()
            ->keyBy(fn (TesoreriaCrownDeposito $deposito) => (int) $deposito->codigo);
        $detalleFiscal = $this->cierresFiscales($fechaInt);

        $cajas = [];
        foreach ($entrar as $caja => $filas) {
            $deposito = $depositos->get($caja);
            if (! $deposito instanceof TesoreriaCrownDeposito) {
                throw new RuntimeException('No está el depósito de la caja '.$caja.'.');
            }
            $asig = $asignadas[$caja] ?? null;
            $cajeroCodigo = (int) ($asig->asigc_cajero ?? $filas[0]->efe_cajero);
            $cajero = CajeroCrown::query()
                ->where('sala_id', $jornada->sala_id)
                ->where('codigo', $cajeroCodigo)
                ->first();
            if ($cajero === null) {
                throw new RuntimeException('No está el cajero '.$cajeroCodigo.' de la caja '.$caja.'.');
            }
            $tarea = strtoupper(trim((string) ($asig->asigc_tipo_caja ?? 'T')));
            $turno = strtoupper(trim((string) ($asig->asigc_turno ?? 'M')));
            $documentos = [];
            foreach ($filas as $fila) {
                $numero = (int) $fila->efe_nro_oper;
                $tipo = trim((string) $fila->efe_tipo_oper) === 'I'
                    ? TesoreriaCrownComprobanteSupport::HABILITACION
                    : TesoreriaCrownComprobanteSupport::RENDICION;
                $lineas = $this->lineasDe(
                    $billetes[$numero] ?? [],
                    $fichas[$numero] ?? [],
                    $tipo,
                    $billeteValor,
                    $fichaValor,
                    $numero
                );
                $g = $gastro[$numero] ?? null;
                $documentos[] = [
                    'numero' => $numero,
                    'tipo' => $tipo,
                    'hora' => $this->hora((string) $fila->efe_hora),
                    'lineas' => $lineas,
                    'importes' => $this->importes($fila, $g, $tarea),
                    'fiscales' => $this->fiscalesDe($fiscales[$numero] ?? [], $detalleFiscal, $fechaYmd),
                    'ya' => TesoreriaCrownComprobante::query()
                        ->where('sala_id', $jornada->sala_id)
                        ->where('tipo', $tipo)
                        ->where('numero', $numero)
                        ->exists(),
                ];
            }
            $ultimoCierre = null;
            foreach ($documentos as $doc) {
                if ($doc['tipo'] === TesoreriaCrownComprobanteSupport::RENDICION) {
                    $ultimoCierre = $aprobacion[$doc['numero']] ?? $ultimoCierre;
                }
            }
            $cajas[] = [
                'caja' => $caja,
                'deposito_id' => (int) $deposito->id,
                'deposito' => trim($deposito->codigo.' '.$deposito->nombre),
                'cajero_id' => (int) $cajero->id,
                'cajero' => $cajeroCodigo.' '.trim((string) $cajero->nombre),
                'tarea' => $tarea,
                'turno' => $turno !== '' ? $turno : 'M',
                'estado_anita' => trim((string) ($asig->asigc_estado ?? '')),
                'aprobacion' => $ultimoCierre,
                'documentos' => $documentos,
                'jornada_id' => (int) $jornada->id,
                'sala_id' => (int) $jornada->sala_id,
                'empresa_id' => (int) $jornada->empresa_id,
                'fecha' => $fechaYmd,
            ];
        }

        return [
            'cajas' => $cajas,
            'fuera' => $fuera,
            'excluidas' => array_map('intval', array_keys($excepto)),
        ];
    }

    /**
     * @param  array{cajas: list<array<string, mixed>>}  $plan
     */
    public function aplicar(array $plan): int
    {
        $altas = 0;
        DB::transaction(function () use ($plan, &$altas) {
            foreach ($plan['cajas'] as $caja) {
                $asignacion = TesoreriaCrownAsignacion::query()->firstOrCreate(
                    [
                        'jornada_id' => $caja['jornada_id'],
                        'deposito_id' => $caja['deposito_id'],
                    ],
                    [
                        'sala_id' => $caja['sala_id'],
                        'cajero_crown_id' => $caja['cajero_id'],
                        'tipo_tarea' => $caja['tarea'],
                        'turno' => $caja['turno'],
                    ]
                );
                foreach ($caja['documentos'] as $doc) {
                    if ($doc['ya']) {
                        continue;
                    }
                    $cuando = $caja['fecha'].' '.$doc['hora'].':00';
                    $comprobante = TesoreriaCrownComprobante::query()->create(array_merge($doc['importes'], [
                        'sala_id' => $caja['sala_id'],
                        'empresa_id' => $caja['empresa_id'],
                        'jornada_id' => $caja['jornada_id'],
                        'asignacion_id' => $asignacion->id,
                        'tipo' => $doc['tipo'],
                        'numero' => $doc['numero'],
                        'estado' => TesoreriaCrownComprobanteSupport::CONFORME,
                        'entrega_at' => $cuando,
                        'recibe_at' => $cuando,
                    ]));
                    foreach ($doc['lineas'] as $linea) {
                        TesoreriaCrownComprobanteLinea::query()->create([
                            'comprobante_id' => $comprobante->id,
                            'valor_id' => $linea['valor_id'],
                            'cantidad' => $linea['cantidad'],
                        ]);
                    }
                    foreach ($doc['fiscales'] as $fiscal) {
                        TesoreriaCrownCierreFiscal::query()->create(array_merge($fiscal, [
                            'comprobante_id' => $comprobante->id,
                        ]));
                    }
                    TesoreriaCrownBovedaSupport::asentarCajero($comprobante);
                    $altas++;
                }
                $this->cerrar($asignacion, $caja);
            }
        });

        return $altas;
    }

    /**
     * @param  array<string, mixed>  $caja
     */
    private function cerrar(TesoreriaCrownAsignacion $asignacion, array $caja): void
    {
        $asignacion->load(['comprobantes.lineas.valor.tipoValor', 'jornada', 'cierre']);
        if ($asignacion->cierre !== null) {
            return;
        }
        $calculo = TesoreriaCrownCierreCalculo::de($asignacion);
        if (! $calculo['puede']) {
            throw new RuntimeException('La caja '.$caja['caja'].' no cierra: '.$calculo['motivo']);
        }
        $estado = $caja['aprobacion'] ?: ($calculo['supera']
            ? TesoreriaCrownCierreSupport::AUDITORIA
            : TesoreriaCrownCierreSupport::CONFORME);
        $cierre = TesoreriaCrownCierre::query()->create([
            'sala_id' => $caja['sala_id'],
            'empresa_id' => $caja['empresa_id'],
            'jornada_id' => $caja['jornada_id'],
            'asignacion_id' => $asignacion->id,
            'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $caja['sala_id'], 'C'),
            'efectivo_habilitado' => $calculo['habilitado'],
            'valores_rendidos' => $calculo['rendido'],
            'resultado' => $calculo['resultado'],
            'diferencia' => $calculo['diferencia'],
            'componentes' => $calculo['componentes'],
            'estado' => $estado,
            'observacion' => 'Importado de Anita',
            'tesoreria_at' => $caja['fecha'].' 23:59:00',
            'cajero_at' => $caja['fecha'].' 23:59:00',
        ]);
        TesoreriaCrownCierreEvento::query()->create([
            'cierre_id' => $cierre->id,
            'estado' => $estado,
            'detalle' => 'Importado de Anita',
        ]);
    }

    /**
     * @param  list<object>  $billetes
     * @param  list<object>  $fichas
     * @param  array<string, TesoreriaCrownValor>  $billeteValor
     * @param  array<int, int>  $fichaValor
     * @return list<array{valor_id: int, cantidad: float}>
     */
    private function lineasDe(array $billetes, array $fichas, string $tipo, array $billeteValor, array $fichaValor, int $numero): array
    {
        $suma = [];
        foreach ($billetes as $linea) {
            if (abs((float) $linea->renmb_cantidad) < 0.0001) {
                continue;
            }
            $moneda = self::MONEDA[trim((string) $linea->renmb_cod_mon)] ?? '';
            $clave = $moneda.'|'.number_format((float) $linea->renmb_valor, 2, '.', '');
            $valor = $billeteValor[$clave] ?? null;
            if (! $valor instanceof TesoreriaCrownValor) {
                throw new RuntimeException('No está la denominación '.$clave.' (operación '.$numero.').');
            }
            $suma[(int) $valor->id] = ($suma[(int) $valor->id] ?? 0) + (float) $linea->renmb_cantidad;
        }
        $tipoFicha = $tipo === TesoreriaCrownComprobanteSupport::HABILITACION ? 'H' : 'P';
        foreach ($fichas as $linea) {
            if (trim((string) $linea->fichcv_tipo_oper) !== $tipoFicha) {
                continue;
            }
            if (abs((float) $linea->fichcv_cantidad) < 0.0001) {
                continue;
            }
            $codigo = (int) $linea->fichcv_codigo;
            $valorId = $fichaValor[$codigo] ?? null;
            if ($valorId === null) {
                throw new RuntimeException('No está la ficha '.$codigo.' (operación '.$numero.').');
            }
            $suma[$valorId] = ($suma[$valorId] ?? 0) + (float) $linea->fichcv_cantidad;
        }
        $out = [];
        foreach ($suma as $valorId => $cantidad) {
            $out[] = ['valor_id' => $valorId, 'cantidad' => round($cantidad, 2)];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function importes(object $fila, ?object $gastro, string $tarea): array
    {
        $totalX = $gastro ? (float) $gastro->rendg_total_x : (float) $fila->efe_total_x;
        $totalZ = $gastro ? (float) $gastro->rendg_total_z : (float) $fila->efe_total_z;
        $nroZ = $gastro ? (string) $gastro->rendg_nro_z : trim((string) $fila->efe_nro_z);
        $ult = $gastro ? (int) $gastro->rendg_ult_ticket : (int) $fila->efe_ult_ticket;

        return [
            'total_x' => round($totalX, 2),
            'invitacion' => round($gastro ? (float) $gastro->rendg_invitacion : (float) $fila->efe_invitacion, 2),
            'total_z' => round($totalZ, 2),
            'nro_z' => $nroZ !== '' && $nroZ !== '0' ? mb_substr($nroZ, 0, 15) : null,
            'ult_ticket' => $ult > 0 ? $ult : null,
            'total_nc' => round($gastro ? (float) $gastro->rendg_tot_nc : 0, 2),
            'fallo_mozo' => round($gastro ? (float) $gastro->rendg_fallo_mozo : (float) $fila->efe_fallo_mozo, 2),
            'iva_facturas' => round($gastro ? (float) $gastro->rendg_iva_fac : 0, 2),
            'iva_nc' => round($gastro ? (float) $gastro->rendg_iva_nc : 0, 2),
            'nro_z_cash' => ($nro = trim((string) $fila->efe_nro_z_cash)) !== '' ? mb_substr($nro, 0, 15) : null,
            'venta_cash' => round((float) $fila->efe_tk_inicial, 2),
            'pago_cash' => round((float) $fila->efe_total_z_cash, 2),
            'pago_parcial' => round((float) $fila->efe_tot_parc_cash, 2),
            'venta_online' => round((float) $fila->efe_venta_online, 2),
            'pago_online' => round((float) $fila->efe_pago_online, 2),
            'vta_apuesta' => round((float) $fila->efe_vta_apu_dep, 2),
            'pago_apuesta' => round((float) $fila->efe_pago_apu_dep, 2),
            'tarjeta_debito' => round((float) $fila->efe_tarjeta_debito, 2),
            'tarjeta_credito' => round((float) $fila->efe_tot_tarj, 2),
            'recargo_fin' => round((float) $fila->efe_recargo_fin, 2),
            'ctacte_hotel' => $tarea === 'R' ? 0 : round((float) $fila->efe_tk_final, 2),
            'transferencia_hotel' => $tarea === 'R' ? 0 : round((float) $fila->efe_tot_carga, 2),
            'venta_voucher' => $tarea === 'R' ? 0 : round((float) $fila->efe_tot_descarga, 2),
            'pago_voucher' => round((float) $fila->efe_pago_voucher, 2),
            'total_qr' => round((float) $fila->efe_total_qr, 2),
            'cotiz_dolar' => self::cotizacionOpcional($fila->efe_cotiz_dolar ?? null),
            'cotiz_euro' => self::cotizacionOpcional($fila->efe_cotiz_euro ?? null),
        ];
    }

    private static function cotizacionOpcional(mixed $valor): ?float
    {
        $tasa = round((float) $valor, 4);

        return $tasa > 0 ? $tasa : null;
    }

    /**
     * @param  list<object>  $filas
     * @param  array<string, object>  $detalle
     * @return list<array<string, mixed>>
     */
    private function fiscalesDe(array $filas, array $detalle, string $fechaYmd): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $sucursal = (int) $fila->ciefr_sucursal;
            $nroX = (int) $fila->ciefr_nro_x;
            $nroZ = (int) $fila->ciefr_nro_z;
            $hora = substr(trim((string) $fila->ciefr_hora), 0, 8);
            $clave = $sucursal.'|'.$nroX.'|'.$nroZ;
            $dato = $detalle[$clave] ?? null;
            $out[] = [
                'sucursal' => $sucursal,
                'fecha_jor' => $fechaYmd,
                'hora' => $dato->hora ?? ($hora !== '' ? $hora : '00:00:00'),
                'nro_x' => $nroX,
                'nro_z' => $nroZ,
                'tipo' => $nroZ > 0 ? 'Z' : 'X',
                'nombre_sucursal' => $dato->nombre ?? '',
                'tot_venta' => round((float) ($dato->tot_venta ?? 0), 2),
                'tot_cred' => round((float) ($dato->tot_cred ?? 0), 2),
                'tot_iva' => round((float) ($dato->tot_iva ?? 0), 2),
                'tot_iva_cred' => round((float) ($dato->tot_iva_cred ?? 0), 2),
                'ult_fact_b' => (int) ($dato->ult_fact_b ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, object>
     */
    private function cierresFiscales(int $fechaInt): array
    {
        try {
            $filas = $this->consultar(
                'sucursal,nro_x,nro_z,hora,tot_venta,tot_cred,tot_iva,tot_iva_cred,ult_fact_b',
                'SELECT cief_sucursal, cief_nro_x, cief_nro_z, cief_hora, cief_tot_venta, cief_tot_cred, cief_tot_iva, cief_tot_iva_cred, cief_ult_fact_b FROM cierrefiscal WHERE cief_fecha_jor = '.$fechaInt,
                'ventas'
            );
        } catch (RuntimeException) {
            return [];
        }
        $nombres = [];
        $out = [];
        foreach ($filas as $fila) {
            $sucursal = (int) $fila->sucursal;
            $fila->nombre = '';
            $nombres[$sucursal] = true;
            $out[$sucursal.'|'.(int) $fila->nro_x.'|'.(int) $fila->nro_z] = $fila;
        }
        if ($nombres === []) {
            return $out;
        }
        foreach ($this->consultar(
            'numero,empresa',
            'SELECT suc_numero, suc_empresa FROM sucursal WHERE suc_numero IN ('.implode(',', array_keys($nombres)).')',
            'ventas'
        ) as $suc) {
            foreach ($out as $fila) {
                if ((int) $fila->sucursal === (int) $suc->numero) {
                    $fila->nombre = trim((string) $suc->empresa);
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $numeros
     * @return array<int, string>
     */
    private function ultimaAprobacion(array $numeros): array
    {
        $filas = $this->enLotes($numeros, function (string $lista) {
            return $this->consultar(
                'aprobr_nro_oper,aprobr_estado,aprobr_fecha,aprobr_hora,aprobr_id',
                "SELECT aprobr_nro_oper, aprobr_estado, aprobr_fecha, aprobr_hora, aprobr_id FROM aprobrend WHERE aprobr_tipo_oper = 'F' AND aprobr_nro_oper IN ($lista)"
            );
        });
        $mejor = [];
        foreach ($filas as $fila) {
            $nro = (int) $fila->aprobr_nro_oper;
            $marca = sprintf('%08d%s%010d', (int) $fila->aprobr_fecha, trim((string) $fila->aprobr_hora), (int) $fila->aprobr_id);
            if (! isset($mejor[$nro]) || $marca > $mejor[$nro]['marca']) {
                $estado = strtoupper(trim((string) $fila->aprobr_estado));
                $mejor[$nro] = [
                    'marca' => $marca,
                    'estado' => $estado === 'J' ? TesoreriaCrownCierreSupport::AUDITORIA : $estado,
                ];
            }
        }
        $out = [];
        foreach ($mejor as $nro => $fila) {
            $out[$nro] = $fila['estado'];
        }

        return $out;
    }

    /**
     * @return array<string, TesoreriaCrownValor>
     */
    private function billetesPorClave(int $salaId): array
    {
        $out = [];
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->whereHas('tipoValor', fn ($q) => $q->whereIn('codigo', ['ARS', 'USD', 'EUR']))
            ->get();
        foreach ($valores as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            $out[$codigo.'|'.number_format((float) $valor->denominacion, 2, '.', '')] = $valor;
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function fichasPorCodigo(int $salaId): array
    {
        $porSerie = [];
        foreach (TesoreriaCrownValor::query()->with('tipoValor')->where('sala_id', $salaId)->get() as $valor) {
            $serie = strtoupper(trim((string) $valor->serie));
            if ($serie !== '') {
                $porSerie[$serie] = (int) $valor->id;
            }
        }
        $out = [];
        foreach ($this->consultar(
            'fichm_codigo,fichm_serie',
            'SELECT fichm_codigo, fichm_serie FROM fichamae'
        ) as $fila) {
            $serie = strtoupper(trim((string) $fila->fichm_serie));
            if ($serie !== '' && isset($porSerie[$serie])) {
                $out[(int) $fila->fichm_codigo] = $porSerie[$serie];
            }
        }

        return $out;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function porOperacion(array $filas, string $campo): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->{$campo}][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<int>  $numeros
     * @param  callable(string): list<object>  $consulta
     * @return list<object>
     */
    private function enLotes(array $numeros, callable $consulta): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($numeros)), 40) as $lote) {
            $lista = implode(',', array_map('intval', $lote));
            foreach ($consulta($lista) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    private function hora(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{2}:\d{2})/', $hora, $coincide) === 1) {
            return $coincide[1];
        }

        return '00:00';
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql, string $sistema = ''): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => $sistema !== '' ? $sistema : config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
