<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;

/**
 * Control de comprobantes vs recepción en la cuenta de anticipos (OC anticipada).
 *
 * Por OC: suma de asientos con comprobante (cualquier modo) + asientos de
 * recepción en `pago.anticipo_proveedor`. Neto negativo = la recepción
 * acreditó de más (desvío). Neto positivo = pendiente de recepción (normal).
 * Incluye ASIGNA_RECEPCION que también pasó por anticipos (legado Anita).
 *
 * Si FA y COM se pueden expresar en la misma ME (nativa o implícita pesos÷cot)
 * el desvío/pendiente se decide por el neto en ME. Diferencia solo en pesos por
 * cotización = info, sin alerta.
 */
final class ProveedorFacturaAnticipadaRecepcionSupport
{
    /**
     * @return array{
     *   tolerancia: float,
     *   empresas: list<array<string, mixed>>,
     *   ordenes: list<array<string, mixed>>,
     *   desvios: list<array<string, mixed>>,
     *   pendientes: list<array<string, mixed>>,
     *   diff_cotizacion: list<array<string, mixed>>,
     *   ordenes_total: int,
     *   desvios_total: int,
     *   pendientes_total: int,
     *   diff_cotizacion_total: int,
     *   requiere_alerta: bool
     * }
     */
    public function auditar(float $tolerancia, ?int $empresaId = null): array
    {
        $tolerancia = abs($tolerancia);
        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $anticipoPorEmpresa = $this->cuentasAnticipoPorEmpresa($empresaId);
        $cuentaIds = array_values(array_unique(array_filter(
            array_map('intval', $anticipoPorEmpresa),
            static fn (int $id): bool => $id > 0
        )));
        if ($cuentaIds === []) {
            return [
                'tolerancia' => $tolerancia,
                'empresas' => [],
                'ordenes' => [],
                'desvios' => [],
                'pendientes' => [],
                'diff_cotizacion' => [],
                'ordenes_total' => 0,
                'desvios_total' => 0,
                'pendientes_total' => 0,
                'diff_cotizacion_total' => 0,
                'requiere_alerta' => false,
            ];
        }

        $facturado = $this->montosAnticipoPorOrden(
            $this->queryComprobanteAnticipo($cuentaIds, $empresaId),
            $monedaLocalId
        );
        $recepciones = $this->montosAnticipoPorOrden(
            $this->queryRecepcionAnticipo($cuentaIds, $empresaId),
            $monedaLocalId
        );
        $claves = array_unique(array_merge(array_keys($facturado), array_keys($recepciones)));
        $meta = $this->metaOrdenesYCuentas($claves, $anticipoPorEmpresa);

        $ordenes = [];
        $desvios = [];
        $pendientes = [];
        $diffCotizacion = [];
        $porEmpresa = [];

        foreach ($claves as $clave) {
            $partes = explode('|', (string) $clave);
            $empId = (int) ($partes[0] ?? 0);
            $cuentaId = (int) ($partes[1] ?? 0);
            $ocId = (int) ($partes[2] ?? 0);
            if ($empId <= 0 || $cuentaId <= 0 || $ocId <= 0) {
                continue;
            }
            if (($anticipoPorEmpresa[$empId] ?? 0) !== $cuentaId) {
                continue;
            }

            $factBag = $facturado[$clave] ?? ['local' => 0.0, 'monedas' => [], 'me_implicita' => 0.0];
            $recBag = $recepciones[$clave] ?? ['local' => 0.0, 'monedas' => [], 'me_implicita' => 0.0];
            $factura = round((float) $factBag['local'], 2);
            $recepcion = round((float) $recBag['local'], 2);
            $neto = round($factura + $recepcion, 2);
            $excesoPesos = $neto < -$tolerancia ? round(-$neto, 2) : 0.0;
            $pendientePesos = $neto > $tolerancia ? $neto : 0.0;

            $baseMe = $this->baseComparableMe($factBag, $recBag, $monedaLocalId);
            $exceso = $excesoPesos;
            $pendiente = $pendientePesos;
            $esDiffCotizacion = false;
            $esDesvio = false;
            $esPendiente = false;

            if ($baseMe !== null) {
                $netoMe = (float) $baseMe['neto_me'];
                if (abs($netoMe) <= $tolerancia) {
                    // Cierra en ME: solo info si en pesos la recepción “pasa” (cotización).
                    $esDiffCotizacion = $excesoPesos > $tolerancia;
                    $exceso = 0.0;
                    $pendiente = 0.0;
                } elseif ($netoMe < -$tolerancia) {
                    $esDesvio = true;
                    $exceso = $excesoPesos > 0 ? $excesoPesos : round(-$netoMe, 2);
                    $pendiente = 0.0;
                } else {
                    $esPendiente = true;
                    $pendiente = $pendientePesos > 0 ? $pendientePesos : round($netoMe, 2);
                    $exceso = 0.0;
                }
            } else {
                $esDesvio = $excesoPesos >= $tolerancia && $excesoPesos > 0;
                $esPendiente = $pendientePesos > $tolerancia;
            }

            $info = $meta[$clave] ?? [];
            $fila = [
                'empresa_id' => $empId,
                'empresa_nombre' => (string) ($info['empresa_nombre'] ?? ''),
                'cuentacontable_id' => $cuentaId,
                'cuenta_codigo' => (string) ($info['cuenta_codigo'] ?? ''),
                'cuenta_nombre' => (string) ($info['cuenta_nombre'] ?? ''),
                'ordencompra_id' => $ocId,
                'numeroordencompra' => (string) ($info['numeroordencompra'] ?? ''),
                'tratamiento' => (string) ($info['tratamiento'] ?? ''),
                'factura' => $factura,
                'recepcion' => $recepcion,
                'neto' => $neto,
                'exceso_recepcion' => $exceso,
                'exceso_pesos' => $excesoPesos,
                'pendiente_recepcion' => $pendiente,
                'es_desvio' => $esDesvio,
                'es_pendiente' => $esPendiente,
                'es_diff_cotizacion' => $esDiffCotizacion,
                'moneda_id_me' => $baseMe['moneda_id'] ?? null,
                'factura_me' => $baseMe['factura_me'] ?? null,
                'recepcion_me' => $baseMe['recepcion_me'] ?? null,
                'neto_me' => $baseMe['neto_me'] ?? null,
                'me_origen' => $baseMe['origen'] ?? null,
            ];
            $ordenes[] = $fila;
            if ($fila['es_desvio']) {
                $desvios[] = $fila;
            } elseif ($fila['es_diff_cotizacion']) {
                $diffCotizacion[] = $fila;
            } elseif ($fila['es_pendiente']) {
                $pendientes[] = $fila;
            }

            if (! isset($porEmpresa[$empId])) {
                $porEmpresa[$empId] = [
                    'empresa_id' => $empId,
                    'empresa_nombre' => $fila['empresa_nombre'],
                    'ordenes' => 0,
                    'desvios' => 0,
                    'pendientes' => 0,
                    'diff_cotizacion' => 0,
                    'exceso_recepcion' => 0.0,
                    'pendiente_recepcion' => 0.0,
                ];
            }
            $porEmpresa[$empId]['ordenes']++;
            if ($fila['es_desvio']) {
                $porEmpresa[$empId]['desvios']++;
                $porEmpresa[$empId]['exceso_recepcion'] = round(
                    $porEmpresa[$empId]['exceso_recepcion'] + $exceso,
                    2
                );
            }
            if ($fila['es_diff_cotizacion']) {
                $porEmpresa[$empId]['diff_cotizacion']++;
            }
            if ($fila['es_pendiente']) {
                $porEmpresa[$empId]['pendientes']++;
                $porEmpresa[$empId]['pendiente_recepcion'] = round(
                    $porEmpresa[$empId]['pendiente_recepcion'] + $pendiente,
                    2
                );
            }
        }

        usort($desvios, static fn (array $a, array $b): int => $b['exceso_recepcion'] <=> $a['exceso_recepcion']);
        usort($pendientes, static fn (array $a, array $b): int => $b['pendiente_recepcion'] <=> $a['pendiente_recepcion']);
        usort($diffCotizacion, static fn (array $a, array $b): int => $b['exceso_pesos'] <=> $a['exceso_pesos']);
        usort($ordenes, static fn (array $a, array $b): int => abs($b['neto']) <=> abs($a['neto']));

        return [
            'tolerancia' => $tolerancia,
            'empresas' => array_values($porEmpresa),
            'ordenes' => $ordenes,
            'desvios' => $desvios,
            'pendientes' => $pendientes,
            'diff_cotizacion' => $diffCotizacion,
            'ordenes_total' => count($ordenes),
            'desvios_total' => count($desvios),
            'pendientes_total' => count($pendientes),
            'diff_cotizacion_total' => count($diffCotizacion),
            'requiere_alerta' => $desvios !== [],
        ];
    }

    /**
     * @return array<int, int> empresa_id => cuentacontable_id
     */
    private function cuentasAnticipoPorEmpresa(?int $empresaId): array
    {
        $out = [];
        $query = Empresa::query()->orderBy('id');
        if ($empresaId !== null && $empresaId > 0) {
            $query->whereKey($empresaId);
        }
        foreach ($query->pluck('id') as $id) {
            $cuentaId = (int) (ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId((int) $id) ?? 0);
            if ($cuentaId > 0) {
                $out[(int) $id] = $cuentaId;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return \Illuminate\Database\Query\Builder
     */
    private function queryComprobanteAnticipo(array $cuentaIds, ?int $empresaId)
    {
        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $query = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'a.comprobante_proveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->whereRaw(SqlDialectSupport::lower('oc.tratamiento').' in (?, ?, ?)', ['anticipada', '2', 's'])
            ->where(function ($q) {
                $q->whereNull('oc.es_contrato')->orWhere('oc.es_contrato', 0);
            });
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('a.empresa_id', $empresaId);
        }

        return $query
            ->selectRaw('a.empresa_id, m.cuentacontable_id, oc.id as ordencompra_id')
            ->selectRaw('COALESCE(m.moneda_id, ?) as moneda_id', [$monedaLocalId])
            ->selectRaw('SUM(m.monto) as saldo_moneda')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) <= ? AND COALESCE(m.cotizacion, 0) > 1.0001 THEN m.monto / m.cotizacion ELSE 0 END) as saldo_me_implicita',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupByRaw('a.empresa_id, m.cuentacontable_id, oc.id, COALESCE(m.moneda_id, ?)', [$monedaLocalId]);
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return \Illuminate\Database\Query\Builder
     */
    private function queryRecepcionAnticipo(array $cuentaIds, ?int $empresaId)
    {
        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $query = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('recepcion_proveedor as r', 'r.id', '=', 'a.recepcionproveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'r.ordencompra_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->whereRaw(SqlDialectSupport::lower('oc.tratamiento').' in (?, ?, ?)', ['anticipada', '2', 's'])
            ->where(function ($q) {
                $q->whereNull('oc.es_contrato')->orWhere('oc.es_contrato', 0);
            });
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('a.empresa_id', $empresaId);
        }

        return $query
            ->selectRaw('a.empresa_id, m.cuentacontable_id, r.ordencompra_id as ordencompra_id')
            ->selectRaw('COALESCE(m.moneda_id, ?) as moneda_id', [$monedaLocalId])
            ->selectRaw('SUM(m.monto) as saldo_moneda')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) <= ? AND COALESCE(m.cotizacion, 0) > 1.0001 THEN m.monto / m.cotizacion ELSE 0 END) as saldo_me_implicita',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupByRaw('a.empresa_id, m.cuentacontable_id, r.ordencompra_id, COALESCE(m.moneda_id, ?)', [$monedaLocalId]);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array<string, array{local: float, monedas: array<int, float>, me_implicita: float}>
     */
    private function montosAnticipoPorOrden($query, int $monedaLocalId): array
    {
        $out = [];
        foreach ($query->get() as $fila) {
            $key = ((int) $fila->empresa_id).'|'.((int) $fila->cuentacontable_id).'|'.((int) $fila->ordencompra_id);
            if (! isset($out[$key])) {
                $out[$key] = ['local' => 0.0, 'monedas' => [], 'me_implicita' => 0.0];
            }
            $monedaId = (int) ($fila->moneda_id ?? $monedaLocalId);
            if ($monedaId <= 0) {
                $monedaId = $monedaLocalId;
            }
            $out[$key]['local'] = round($out[$key]['local'] + (float) $fila->saldo_local, 4);
            $out[$key]['monedas'][$monedaId] = round(
                ((float) ($out[$key]['monedas'][$monedaId] ?? 0)) + (float) $fila->saldo_moneda,
                4
            );
            $out[$key]['me_implicita'] = round(
                $out[$key]['me_implicita'] + (float) ($fila->saldo_me_implicita ?? 0),
                6
            );
        }
        foreach ($out as $key => $bag) {
            $out[$key]['local'] = round((float) $bag['local'], 2);
            $out[$key]['me_implicita'] = round((float) $bag['me_implicita'], 4);
            foreach ($bag['monedas'] as $monedaId => $monto) {
                $out[$key]['monedas'][(int) $monedaId] = round((float) $monto, 2);
            }
        }

        return $out;
    }

    /**
     * Expresa FA y COM en una base ME comparable (nativa o pesos÷cotización).
     *
     * @param  array{local: float, monedas: array<int, float>, me_implicita: float}  $factBag
     * @param  array{local: float, monedas: array<int, float>, me_implicita: float}  $recBag
     * @return array{moneda_id: int|null, factura_me: float, recepcion_me: float, neto_me: float, origen: string}|null
     */
    private function baseComparableMe(array $factBag, array $recBag, int $monedaLocalId): ?array
    {
        $fact = $this->resolverMontoMe($factBag, $monedaLocalId);
        $rec = $this->resolverMontoMe($recBag, $monedaLocalId);
        if ($fact === null || $rec === null) {
            return null;
        }
        // Implícita vs implícita (ambos en pesos÷cot) reintroduce ruido de cotización: usar pesos.
        if ($fact['origen'] === 'implicita' && $rec['origen'] === 'implicita') {
            return null;
        }
        // Dos ME nativas distintas no son comparables.
        if ($fact['moneda_id'] !== null && $rec['moneda_id'] !== null && $fact['moneda_id'] !== $rec['moneda_id']) {
            return null;
        }

        $facturaMe = round((float) $fact['monto'], 2);
        $recepcionMe = round((float) $rec['monto'], 2);

        return [
            'moneda_id' => $fact['moneda_id'] ?? $rec['moneda_id'],
            'factura_me' => $facturaMe,
            'recepcion_me' => $recepcionMe,
            'neto_me' => round($facturaMe + $recepcionMe, 2),
            'origen' => ($fact['origen'] === 'implicita' || $rec['origen'] === 'implicita')
                ? 'implicita'
                : 'nativa',
        ];
    }

    /**
     * @param  array{local: float, monedas: array<int, float>, me_implicita: float}  $bag
     * @return array{monto: float, moneda_id: int|null, origen: string}|null
     */
    private function resolverMontoMe(array $bag, int $monedaLocalId): ?array
    {
        $meNativa = [];
        $local = 0.0;
        foreach ($bag['monedas'] as $monedaId => $monto) {
            $monedaId = (int) $monedaId;
            $monto = (float) $monto;
            if (abs($monto) < 0.01) {
                continue;
            }
            if ($monedaId > $monedaLocalId) {
                $meNativa[$monedaId] = round($monto, 2);
            } else {
                $local = round($local + $monto, 2);
            }
        }
        if (count($meNativa) === 1 && abs($local) < 0.01) {
            $monedaId = (int) array_key_first($meNativa);

            return [
                'monto' => $meNativa[$monedaId],
                'moneda_id' => $monedaId,
                'origen' => 'nativa',
            ];
        }
        if ($meNativa === [] && abs($local) >= 0.01) {
            $implicita = round((float) ($bag['me_implicita'] ?? 0), 4);
            if (abs($implicita) < 0.01) {
                return null;
            }

            return [
                'monto' => round($implicita, 2),
                'moneda_id' => null,
                'origen' => 'implicita',
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $claves
     * @param  array<int, int>  $anticipoPorEmpresa
     * @return array<string, array<string, mixed>>
     */
    private function metaOrdenesYCuentas(array $claves, array $anticipoPorEmpresa): array
    {
        $ocIds = [];
        $cuentaIds = [];
        $empresaIds = [];
        foreach ($claves as $clave) {
            $partes = explode('|', (string) $clave);
            $empresaIds[(int) ($partes[0] ?? 0)] = true;
            $cuentaIds[(int) ($partes[1] ?? 0)] = true;
            $ocIds[(int) ($partes[2] ?? 0)] = true;
        }
        unset($empresaIds[0], $cuentaIds[0], $ocIds[0]);

        $empresas = Empresa::query()
            ->whereIn('id', array_keys($empresaIds))
            ->get(['id', 'nombre'])
            ->keyBy('id');
        $cuentas = DB::table('cuentacontable')
            ->whereIn('id', array_keys($cuentaIds))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');
        $ocs = DB::table('ordencompra')
            ->whereIn('id', array_keys($ocIds))
            ->get(['id', 'numeroordencompra', 'tratamiento'])
            ->keyBy('id');

        $out = [];
        foreach ($claves as $clave) {
            $partes = explode('|', (string) $clave);
            $empId = (int) ($partes[0] ?? 0);
            $cuentaId = (int) ($partes[1] ?? 0);
            $ocId = (int) ($partes[2] ?? 0);
            $out[$clave] = [
                'empresa_nombre' => trim((string) ($empresas[$empId]->nombre ?? '')),
                'cuenta_codigo' => trim((string) ($cuentas[$cuentaId]->codigo ?? '')),
                'cuenta_nombre' => trim((string) ($cuentas[$cuentaId]->nombre ?? '')),
                'numeroordencompra' => trim((string) ($ocs[$ocId]->numeroordencompra ?? '')),
                'tratamiento' => trim((string) ($ocs[$ocId]->tratamiento ?? '')),
                'cuenta_configurada' => ($anticipoPorEmpresa[$empId] ?? 0) === $cuentaId,
            ];
        }

        return $out;
    }
}
