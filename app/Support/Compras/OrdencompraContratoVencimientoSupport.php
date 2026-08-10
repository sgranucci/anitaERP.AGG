<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra_Contrato_Aviso;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de contratos / OC abiertas: cálculo de vencimientos, preaviso de no renovación
 * y consumo del monto contratado.
 *
 * Vigila dos ejes en paralelo, igual que los acuerdos marco de SAP / los purchase
 * agreements de Oracle: el tiempo (fin de vigencia y fecha límite de preaviso) y el
 * consumo (recibido/facturado contra el tope contratado).
 */
final class OrdencompraContratoVencimientoSupport
{
    /** @var list<int> */
    public const UMBRALES_DIAS_DEFAULT = [60, 30, 15];

    /** @var list<int> */
    public const UMBRALES_CONSUMO_DEFAULT = [80, 100];

    /**
     * Estados de OC en los que el contrato sigue vivo y vale la pena avisar.
     * CERRADA y SUSPENDIDA quedan fuera: ya no admiten movimiento.
     *
     * @return list<string>
     */
    public static function estadosVigilados(): array
    {
        return [
            OrdencompraEstados::APROBADA,
            OrdencompraEstados::CUMPLIDA,
        ];
    }

    /**
     * Estados de factura de proveedor que consumen el monto contratado.
     * Se excluyen BORRADOR y ANULADO por no representar compromiso firme.
     *
     * @return list<string>
     */
    public static function estadosFacturadoComputable(): array
    {
        return [
            ComprobanteProveedorEstados::PENDIENTE_REVISION,
            ComprobanteProveedorEstados::PENDIENTE_APROBACION,
            ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
            ComprobanteProveedorEstados::APROBADO,
            ComprobanteProveedorEstados::CONTABILIZADO,
            ComprobanteProveedorEstados::ERROR_SYNC,
        ];
    }

    /**
     * Tipos de recepción que consumen el monto contratado: la devolución resta.
     *
     * @return list<string>
     */
    public static function tiposRecepcionComputable(): array
    {
        return [
            Recepcion_Proveedor::TIPO_RECEPCION,
            Recepcion_Proveedor::TIPO_DEVOLUCION,
        ];
    }

    /**
     * Umbrales en días. El contrato puede sobreescribir el default de configuración.
     *
     * @return list<int>
     */
    public static function umbralesDias(?string $override = null): array
    {
        $umbrales = self::parsearEnteros((string) $override);

        if ($umbrales === []) {
            $umbrales = self::parsearEnteros(
                (string) config('compras.contratos_vencimiento.dias_aviso', '60,30,15')
            );
        }

        if ($umbrales === []) {
            $umbrales = self::UMBRALES_DIAS_DEFAULT;
        }

        rsort($umbrales);

        return $umbrales;
    }

    /** @return list<int> */
    public static function umbralesConsumo(): array
    {
        $umbrales = self::parsearEnteros(
            (string) config('compras.contratos_vencimiento.porcentajes_consumo', '80,100')
        );

        if ($umbrales === []) {
            $umbrales = self::UMBRALES_CONSUMO_DEFAULT;
        }

        sort($umbrales);

        return $umbrales;
    }

    public static function diasRepeticionVencido(): int
    {
        return max(1, (int) config('compras.contratos_vencimiento.dias_repeticion_vencido', 7));
    }

    /**
     * Contratos vigilados con sus métricas de tiempo y consumo, sin evaluar umbrales.
     *
     * @return list<array<string, mixed>>
     */
    public static function recopilar(?int $empresaId = null, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->startOfDay();
        $filas = self::consultarContratos($empresaId);

        if ($filas->isEmpty()) {
            return [];
        }

        $consumo = self::consumoPorContrato(
            $filas->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );

        $contratos = [];
        foreach ($filas as $fila) {
            $contratos[] = self::mapearContrato($fila, $hoy, $consumo);
        }

        return $contratos;
    }

    /**
     * Resumen listo para mostrar en la cabecera de la OC.
     *
     * @return array<string, string>|null
     */
    public static function resumenParaFormulario(int $ordencompraId): ?array
    {
        if ($ordencompraId <= 0) {
            return null;
        }

        $fila = self::consultarContratos(null, $ordencompraId)->first();
        if ($fila === null) {
            return null;
        }

        $contrato = self::mapearContrato(
            $fila,
            Carbon::today()->startOfDay(),
            self::consumoPorContrato([$ordencompraId])
        );

        return [
            'consumido_texto' => self::fmtNumero((float) $contrato['monto_consumido']),
            'recibido_texto' => self::fmtNumero((float) $contrato['monto_recibido']),
            'facturado_texto' => self::fmtNumero((float) $contrato['monto_facturado']),
            'origen_texto' => (string) $contrato['origen_consumo'],
            'tope_texto' => $contrato['monto_tope'] > 0 ? self::fmtNumero((float) $contrato['monto_tope']) : '',
            'porcentaje_texto' => self::fmtNumero((float) $contrato['porcentaje_consumido'], 1),
            'vence_texto' => $contrato['vigencia_hasta'] instanceof Carbon
                ? self::fmtFecha($contrato['vigencia_hasta'])
                : '',
            'dias_texto' => ((int) $contrato['dias_para_vencer']) < 0
                ? 'vencido hace '.abs((int) $contrato['dias_para_vencer']).' día/s'
                : 'faltan '.(int) $contrato['dias_para_vencer'].' día/s',
        ];
    }

    /**
     * Contratos con umbrales cruzados que todavía no se avisaron.
     *
     * @return array{
     *   fecha: string,
     *   preventivos: list<array<string, mixed>>,
     *   vencidos: list<array<string, mixed>>,
     *   total_preventivos: int,
     *   total_vencidos: int,
     * }
     */
    public static function novedades(?int $empresaId = null, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->startOfDay();
        $contratos = self::recopilar($empresaId, $hoy);

        $preventivos = [];
        $vencidos = [];

        if ($contratos !== []) {
            $enviadas = self::clavesYaEnviadas(array_column($contratos, 'id'));

            foreach ($contratos as $contrato) {
                $pendientes = array_values(array_filter(
                    self::avisosCandidatos($contrato, $hoy),
                    static fn (array $aviso) => ! isset($enviadas[$contrato['id'].'|'.$aviso['clave']])
                ));

                if ($pendientes === []) {
                    continue;
                }

                $deVencido = array_values(array_filter(
                    $pendientes,
                    static fn (array $a) => $a['tipo'] === Ordencompra_Contrato_Aviso::TIPO_VENCIDO
                ));
                $dePrevios = array_values(array_filter(
                    $pendientes,
                    static fn (array $a) => $a['tipo'] !== Ordencompra_Contrato_Aviso::TIPO_VENCIDO
                ));

                if ($dePrevios !== []) {
                    $preventivos[] = self::conAvisos($contrato, $dePrevios);
                }
                if ($deVencido !== []) {
                    $vencidos[] = self::conAvisos($contrato, $deVencido);
                }
            }
        }

        return [
            'fecha' => $hoy->format('d/m/Y'),
            'preventivos' => $preventivos,
            'vencidos' => $vencidos,
            'total_preventivos' => count($preventivos),
            'total_vencidos' => count($vencidos),
        ];
    }

    /**
     * Umbrales cruzados hoy por un contrato, sin mirar el log de enviados.
     *
     * @param  array<string, mixed>  $contrato
     * @return list<array<string, mixed>>
     */
    public static function avisosCandidatos(array $contrato, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? Carbon::today())->startOfDay();
        $avisos = [];

        $vigenciaHasta = $contrato['vigencia_hasta'] ?? null;
        $refVigencia = $vigenciaHasta instanceof Carbon ? $vigenciaHasta->toDateString() : 'sin_vigencia';
        $umbralesDias = self::umbralesDias($contrato['dias_aviso_override'] ?? null);

        if ($vigenciaHasta instanceof Carbon) {
            $diasParaVencer = (int) $contrato['dias_para_vencer'];

            if ($diasParaVencer >= 0) {
                foreach ($umbralesDias as $umbral) {
                    if ($diasParaVencer <= $umbral) {
                        $avisos[] = self::aviso(
                            Ordencompra_Contrato_Aviso::TIPO_VIGENCIA,
                            $umbral,
                            $refVigencia,
                            $vigenciaHasta
                        );
                    }
                }
            } else {
                $bloque = intdiv(abs($diasParaVencer), self::diasRepeticionVencido());
                $avisos[] = self::aviso(
                    Ordencompra_Contrato_Aviso::TIPO_VENCIDO,
                    min(65535, abs($diasParaVencer)),
                    $refVigencia,
                    $vigenciaHasta,
                    sufijoClave: (string) $bloque
                );
            }
        }

        // Renovación automática: lo accionable es la fecha límite para avisar la NO renovación,
        // no el fin de vigencia. Pasada esa fecha el contrato se renueva solo.
        $limitePreaviso = $contrato['fecha_limite_preaviso'] ?? null;
        if ($limitePreaviso instanceof Carbon) {
            $diasParaPreaviso = (int) $contrato['dias_para_preaviso'];
            $umbralesPreaviso = array_values(array_unique(array_merge($umbralesDias, [0])));
            rsort($umbralesPreaviso);

            foreach ($umbralesPreaviso as $umbral) {
                if ($diasParaPreaviso <= $umbral) {
                    $avisos[] = self::aviso(
                        Ordencompra_Contrato_Aviso::TIPO_PREAVISO,
                        $umbral,
                        $limitePreaviso->toDateString(),
                        $limitePreaviso
                    );
                }
            }
        }

        $tope = (float) ($contrato['monto_tope'] ?? 0);
        if ($tope > 0) {
            $porcentaje = (float) $contrato['porcentaje_consumido'];
            foreach (self::umbralesConsumo() as $umbral) {
                if ($porcentaje >= $umbral) {
                    $aviso = self::aviso(
                        Ordencompra_Contrato_Aviso::TIPO_CONSUMO,
                        $umbral,
                        $refVigencia,
                        $vigenciaHasta instanceof Carbon ? $vigenciaHasta : null
                    );
                    $aviso['monto_consumido'] = (float) $contrato['monto_consumido'];
                    $aviso['porcentaje_consumido'] = $porcentaje;
                    $avisos[] = $aviso;
                }
            }
        }

        return $avisos;
    }

    /**
     * Aviso más urgente de la lista: el que se muestra como motivo en el mail.
     *
     * @param  list<array<string, mixed>>  $avisos
     * @return array<string, mixed>|null
     */
    public static function avisoPrincipal(array $avisos): ?array
    {
        if ($avisos === []) {
            return null;
        }

        $prioridad = [
            Ordencompra_Contrato_Aviso::TIPO_VENCIDO => 0,
            Ordencompra_Contrato_Aviso::TIPO_PREAVISO => 1,
            Ordencompra_Contrato_Aviso::TIPO_CONSUMO => 2,
            Ordencompra_Contrato_Aviso::TIPO_VIGENCIA => 3,
        ];

        usort($avisos, static function (array $a, array $b) use ($prioridad) {
            $pa = $prioridad[$a['tipo']] ?? 9;
            $pb = $prioridad[$b['tipo']] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            // Dentro del mismo tipo: días → el umbral más chico es el más urgente;
            // consumo → el porcentaje más alto.
            return $a['tipo'] === Ordencompra_Contrato_Aviso::TIPO_CONSUMO
                ? $b['umbral'] <=> $a['umbral']
                : $a['umbral'] <=> $b['umbral'];
        });

        return $avisos[0];
    }

    /**
     * Texto del motivo para el mail y la pantalla.
     *
     * @param  array<string, mixed>  $contrato
     */
    public static function motivo(array $contrato): string
    {
        $aviso = self::avisoPrincipal($contrato['avisos'] ?? []);
        if ($aviso === null) {
            return '—';
        }

        $dias = (int) $contrato['dias_para_vencer'];

        return match ($aviso['tipo']) {
            Ordencompra_Contrato_Aviso::TIPO_VENCIDO => sprintf(
                'VENCIDO hace %d día(s) el %s',
                abs($dias),
                self::fmtFecha($contrato['vigencia_hasta'])
            ),
            Ordencompra_Contrato_Aviso::TIPO_PREAVISO => sprintf(
                'Renovación automática: %s (límite %s)',
                self::textoPreaviso((int) $contrato['dias_para_preaviso']),
                self::fmtFecha($contrato['fecha_limite_preaviso'])
            ),
            Ordencompra_Contrato_Aviso::TIPO_CONSUMO => sprintf(
                'Consumió %s%% del monto contratado (%s de %s, según %s)',
                self::fmtNumero((float) $contrato['porcentaje_consumido'], 1),
                self::fmtNumero((float) $contrato['monto_consumido']),
                self::fmtNumero((float) $contrato['monto_tope']),
                mb_strtolower((string) ($contrato['origen_consumo'] ?? 'factura'))
            ),
            default => sprintf(
                'Vence en %d día(s) el %s',
                max(0, $dias),
                self::fmtFecha($contrato['vigencia_hasta'])
            ),
        };
    }

    private static function textoPreaviso(int $diasParaPreaviso): string
    {
        if ($diasParaPreaviso > 0) {
            return 'quedan '.$diasParaPreaviso.' día(s) para avisar la no renovación';
        }

        if ($diasParaPreaviso === 0) {
            return 'HOY vence el plazo para avisar la no renovación';
        }

        return 'el plazo para avisar la no renovación venció hace '.abs($diasParaPreaviso)
            .' día(s): si no se notificó, el contrato se renueva solo';
    }

    /**
     * Renglones de texto plano para el cuerpo del mail.
     *
     * @param  list<array<string, mixed>>  $contratos
     */
    public static function formatearLista(array $contratos, ?int $totalReal = null): string
    {
        if ($contratos === []) {
            return '(ninguno)';
        }

        $lineas = [];
        foreach ($contratos as $contrato) {
            $lineas[] = sprintf(
                'OC %s | %s | %s | Vigencia %s a %s | %s | Responsable: %s',
                $contrato['numero'],
                $contrato['empresa'],
                $contrato['proveedor'],
                self::fmtFecha($contrato['vigencia_desde']),
                self::fmtFecha($contrato['vigencia_hasta']),
                self::motivo($contrato),
                $contrato['responsable'] !== '' ? $contrato['responsable'] : 'sin asignar'
            );
        }

        $texto = implode("\n", $lineas);
        $total = $totalReal ?? count($contratos);
        if ($total > count($contratos)) {
            $texto .= "\n… y ".($total - count($contratos)).' más';
        }

        return $texto;
    }

    /**
     * @param  list<int>  $contratoIds
     * @return array<string, true>
     */
    public static function clavesYaEnviadas(array $contratoIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $contratoIds)));
        if ($ids === []) {
            return [];
        }

        $mapa = [];
        Ordencompra_Contrato_Aviso::query()
            ->whereIn('ordencompra_id', $ids)
            ->select(['ordencompra_id', 'clave'])
            ->get()
            ->each(function ($fila) use (&$mapa) {
                $mapa[$fila->ordencompra_id.'|'.$fila->clave] = true;
            });

        return $mapa;
    }

    public static function fmtFecha(mixed $fecha): string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->format('d/m/Y');
        }

        if ($fecha === null || $fecha === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    public static function fmtNumero(float $valor, int $decimales = 2): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $contrato
     * @param  list<array<string, mixed>>  $avisos
     * @return array<string, mixed>
     */
    private static function conAvisos(array $contrato, array $avisos): array
    {
        $contrato['avisos'] = $avisos;
        $contrato['aviso_principal'] = self::avisoPrincipal($avisos);
        $contrato['motivo'] = self::motivo($contrato);

        return $contrato;
    }

    /**
     * @return array<string, mixed>
     */
    private static function aviso(
        string $tipo,
        int $umbral,
        string $referencia,
        ?Carbon $fechaReferencia,
        string $sufijoClave = '',
    ): array {
        $clave = $tipo.'|'.$referencia.'|'.$umbral;
        if ($sufijoClave !== '') {
            $clave .= '|'.$sufijoClave;
        }

        return [
            'tipo' => $tipo,
            'umbral' => $umbral,
            'clave' => $clave,
            'fecha_referencia' => $fechaReferencia?->toDateString(),
            'monto_consumido' => null,
            'porcentaje_consumido' => null,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultarContratos(?int $empresaId, ?int $ordencompraId = null)
    {
        return DB::table('ordencompra as oc')
            ->join('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'oc.contrato_responsable_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'oc.contrato_moneda_id')
            ->where('oc.es_contrato', true)
            ->whereIn('oc.estadoordencompra', self::estadosVigilados())
            ->where(function ($q) {
                $q->whereNotNull('oc.contrato_vigencia_hasta')
                    ->orWhere('oc.contrato_monto_tope', '>', 0);
            })
            ->when($empresaId !== null && $empresaId > 0, function ($q) use ($empresaId) {
                $q->where('oc.empresa_id', $empresaId);
            })
            ->when($ordencompraId !== null && $ordencompraId > 0, function ($q) use ($ordencompraId) {
                $q->where('oc.id', $ordencompraId);
            })
            ->orderBy('oc.contrato_vigencia_hasta')
            ->orderBy('oc.numeroordencompra')
            ->get([
                'oc.id',
                'oc.numeroordencompra',
                'oc.empresa_id',
                'oc.estadoordencompra',
                'oc.contrato_vigencia_desde',
                'oc.contrato_vigencia_hasta',
                'oc.contrato_monto_tope',
                'oc.contrato_moneda_id',
                'oc.contrato_auto_renovable',
                'oc.contrato_dias_preaviso',
                'oc.contrato_dias_aviso',
                'oc.contrato_responsable_id',
                'p.nombre as proveedor_nombre',
                'e.nombre as empresa_nombre',
                'u.nombre as responsable_nombre',
                'u.email as responsable_email',
                'm.nombre as moneda_nombre',
            ]);
    }

    /**
     * Consumo del monto contratado por contrato.
     *
     * La recepción confirmada es la fuente primaria: marca el consumo real apenas entra
     * el bien o servicio, sin esperar a que el proveedor facture. La factura funciona
     * como respaldo para lo que no pasa por recepción (abonos, honorarios) y como piso
     * cuando la recepción quedó sin valorizar o la factura terminó siendo mayor.
     *
     *   consumido = max(facturado_total, recibido + facturado_sin_recepcion)
     *
     * El término de la derecha suma recepciones y solo las facturas que no están
     * vinculadas a ninguna recepción, para no contar dos veces la misma compra.
     *
     * @param  list<int>  $contratoIds
     * @return array<int, array{recibido: float, facturado: float, facturado_sin_recepcion: float, consumido: float, origen: string}>
     */
    private static function consumoPorContrato(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        $recibido = self::recibidoPorContrato($contratoIds);
        $facturado = self::facturadoPorContrato($contratoIds);

        $mapa = [];
        foreach ($contratoIds as $contratoId) {
            $id = (int) $contratoId;
            $montoRecibido = (float) ($recibido[$id] ?? 0.0);
            $montoFacturado = (float) ($facturado[$id]['facturado'] ?? 0.0);
            $montoSinRecepcion = (float) ($facturado[$id]['facturado_sin_recepcion'] ?? 0.0);

            $porRecepcion = $montoRecibido + $montoSinRecepcion;
            $consumido = max($montoFacturado, $porRecepcion);

            $mapa[$id] = [
                'recibido' => $montoRecibido,
                'facturado' => $montoFacturado,
                'facturado_sin_recepcion' => $montoSinRecepcion,
                'consumido' => $consumido,
                'origen' => self::origenConsumo($montoRecibido, $montoSinRecepcion, $montoFacturado, $consumido),
            ];
        }

        return $mapa;
    }

    private static function origenConsumo(
        float $recibido,
        float $facturadoSinRecepcion,
        float $facturadoTotal,
        float $consumido,
    ): string {
        if ($consumido <= 0) {
            return 'Sin consumo';
        }

        if ($recibido + $facturadoSinRecepcion < $facturadoTotal) {
            return 'Factura';
        }

        if ($recibido > 0 && $facturadoSinRecepcion > 0) {
            return 'Recepción + factura';
        }

        return $recibido > 0 ? 'Recepción' : 'Factura';
    }

    /**
     * Recibido por contrato, valorizado con el precio de la línea de recepción.
     *
     * Con moneda de tope definida se suman solo las líneas de esa misma moneda;
     * sin moneda se suma el equivalente en moneda local (importe × cotización
     * solo si la línea está en divisa; pesos no se multiplican).
     *
     * @param  list<int>  $contratoIds
     * @return array<int, float>
     */
    private static function recibidoPorContrato(array $contratoIds): array
    {
        $importe = RecepcionProveedorConversionSupport::expresionSqlImporteLinea('rpa');
        $monedaLinea = 'COALESCE(NULLIF(rpa.moneda_id, 0), NULLIF(rp.moneda_id, 0), 1)';
        $cotizacion = 'COALESCE(NULLIF(rpa.cotizacion, 0), NULLIF(rp.cotizacion, 0), 1)';
        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        $filas = DB::table('recepcion_proveedor as rp')
            ->join('recepcion_proveedor_articulo as rpa', 'rpa.recepcion_proveedor_id', '=', 'rp.id')
            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->whereIn('rp.ordencompra_id', $contratoIds)
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->whereIn('rp.tipo', self::tiposRecepcionComputable())
            ->where(function ($q) use ($monedaLinea) {
                $q->whereNull('oc.contrato_moneda_id')
                    ->orWhereRaw($monedaLinea.' = oc.contrato_moneda_id');
            })
            ->groupBy('rp.ordencompra_id')
            ->selectRaw('rp.ordencompra_id as contrato_id')
            ->selectRaw(
                'SUM('
                .' (CASE WHEN rp.tipo = ? THEN -1 ELSE 1 END)'
                .' * '.$importe
                .' * (CASE'
                .' WHEN oc.contrato_moneda_id IS NULL AND ('.$monedaLinea.') <> '.$monedaLocalId
                .' THEN '.$cotizacion
                .' ELSE 1 END)'
                .') as recibido',
                [Recepcion_Proveedor::TIPO_DEVOLUCION]
            )
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->contrato_id] = round((float) $fila->recibido, 2);
        }

        return $mapa;
    }

    /**
     * Facturado por contrato: total y porción sin recepción vinculada (respaldo).
     *
     * Con moneda de tope definida se suman solo las facturas de esa misma moneda;
     * sin moneda se suma el equivalente en moneda local (total × cotización
     * solo si el comprobante está en divisa; pesos no se multiplican).
     *
     * @param  list<int>  $contratoIds
     * @return array<int, array{facturado: float, facturado_sin_recepcion: float}>
     */
    private static function facturadoPorContrato(array $contratoIds): array
    {
        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $monto = 'CASE'
            .' WHEN oc.contrato_moneda_id IS NULL'
            .' AND COALESCE(NULLIF(cp.moneda_id, 0), 1) <> '.$monedaLocalId
            .' THEN cp.total * COALESCE(NULLIF(cp.cotizacion, 0), 1)'
            .' ELSE cp.total END';

        $sinRecepcion = 'NOT EXISTS ('
            .'SELECT 1 FROM comprobante_proveedor_recepcion cpr'
            .' WHERE cpr.comprobante_proveedor_id = cp.id'
            .')';

        $filas = DB::table('comprobante_proveedor as cp')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->whereIn('cp.ordencompra_id', $contratoIds)
            ->whereNull('cp.deleted_at')
            ->whereIn('cp.estado', self::estadosFacturadoComputable())
            ->where(function ($q) {
                $q->whereNull('oc.contrato_moneda_id')
                    ->orWhereColumn('cp.moneda_id', 'oc.contrato_moneda_id');
            })
            ->groupBy('cp.ordencompra_id', 'oc.contrato_moneda_id')
            ->selectRaw('cp.ordencompra_id as contrato_id')
            ->selectRaw('SUM('.$monto.') as facturado')
            ->selectRaw('SUM(CASE WHEN '.$sinRecepcion.' THEN '.$monto.' ELSE 0 END) as facturado_sin_recepcion')
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->contrato_id] = [
                'facturado' => round((float) $fila->facturado, 2),
                'facturado_sin_recepcion' => round((float) $fila->facturado_sin_recepcion, 2),
            ];
        }

        return $mapa;
    }

    /**
     * @param  array<int, array<string, mixed>>  $consumo
     * @return array<string, mixed>
     */
    private static function mapearContrato(object $fila, Carbon $hoy, array $consumo): array
    {
        $id = (int) $fila->id;
        $vigenciaHasta = self::aCarbon($fila->contrato_vigencia_hasta ?? null);
        $vigenciaDesde = self::aCarbon($fila->contrato_vigencia_desde ?? null);

        $autoRenovable = (bool) ($fila->contrato_auto_renovable ?? false);
        $diasPreaviso = (int) ($fila->contrato_dias_preaviso ?? 0);
        $limitePreaviso = null;
        if ($autoRenovable && $diasPreaviso > 0 && $vigenciaHasta instanceof Carbon) {
            $limitePreaviso = $vigenciaHasta->copy()->subDays($diasPreaviso)->startOfDay();
        }

        $tope = (float) ($fila->contrato_monto_tope ?? 0);
        $consumoContrato = $consumo[$id] ?? [];
        $montoRecibido = (float) ($consumoContrato['recibido'] ?? 0);
        $montoFacturado = (float) ($consumoContrato['facturado'] ?? 0);
        $montoConsumido = (float) ($consumoContrato['consumido'] ?? 0);

        return [
            'id' => $id,
            'numero' => (string) ((int) $fila->numeroordencompra),
            'empresa_id' => (int) $fila->empresa_id,
            'empresa' => (string) ($fila->empresa_nombre ?? ''),
            'proveedor' => (string) ($fila->proveedor_nombre ?? ''),
            'estado' => (string) ($fila->estadoordencompra ?? ''),
            'vigencia_desde' => $vigenciaDesde,
            'vigencia_hasta' => $vigenciaHasta,
            'dias_para_vencer' => $vigenciaHasta instanceof Carbon
                ? (int) $hoy->diffInDays($vigenciaHasta, false)
                : 0,
            'auto_renovable' => $autoRenovable,
            'dias_preaviso' => $diasPreaviso,
            'fecha_limite_preaviso' => $limitePreaviso,
            'dias_para_preaviso' => $limitePreaviso instanceof Carbon
                ? (int) $hoy->diffInDays($limitePreaviso, false)
                : 0,
            'monto_tope' => $tope,
            'moneda' => (string) ($fila->moneda_nombre ?? ''),
            'monto_recibido' => $montoRecibido,
            'monto_facturado' => $montoFacturado,
            'monto_facturado_sin_recepcion' => (float) ($consumoContrato['facturado_sin_recepcion'] ?? 0),
            'monto_consumido' => $montoConsumido,
            'origen_consumo' => (string) ($consumoContrato['origen'] ?? 'Sin consumo'),
            'monto_disponible' => $tope > 0 ? max(0.0, $tope - $montoConsumido) : 0.0,
            'porcentaje_consumido' => $tope > 0 ? ($montoConsumido / $tope) * 100 : 0.0,
            'dias_aviso_override' => $fila->contrato_dias_aviso ?? null,
            'responsable_id' => (int) ($fila->contrato_responsable_id ?? 0),
            'responsable' => (string) ($fila->responsable_nombre ?? ''),
            'responsable_email' => (string) ($fila->responsable_email ?? ''),
            'avisos' => [],
            'aviso_principal' => null,
            'motivo' => '',
        ];
    }

    private static function aCarbon(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<int> */
    private static function parsearEnteros(string $valor): array
    {
        $partes = preg_split('/[^0-9]+/', $valor) ?: [];

        return array_values(array_unique(array_filter(
            array_map('intval', $partes),
            static fn (int $n) => $n > 0
        )));
    }
}
