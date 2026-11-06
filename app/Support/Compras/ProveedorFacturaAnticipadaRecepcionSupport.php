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
     *   ordenes_total: int,
     *   desvios_total: int,
     *   pendientes_total: int,
     *   requiere_alerta: bool
     * }
     */
    public function auditar(float $tolerancia, ?int $empresaId = null): array
    {
        $tolerancia = abs($tolerancia);
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
                'ordenes_total' => 0,
                'desvios_total' => 0,
                'pendientes_total' => 0,
                'requiere_alerta' => false,
            ];
        }

        $facturado = $this->montoComprobanteEnAnticipoPorOrden($cuentaIds, $empresaId);
        $recepciones = $this->montoRecepcionPorOrden($cuentaIds, $empresaId);
        $claves = array_unique(array_merge(array_keys($facturado), array_keys($recepciones)));
        $meta = $this->metaOrdenesYCuentas($claves, $anticipoPorEmpresa);

        $ordenes = [];
        $desvios = [];
        $pendientes = [];
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

            $factura = round((float) ($facturado[$clave] ?? 0), 2);
            $recepcion = round((float) ($recepciones[$clave] ?? 0), 2);
            $neto = round($factura + $recepcion, 2);
            $exceso = $neto < -$tolerancia ? round(-$neto, 2) : 0.0;
            $pendiente = $neto > $tolerancia ? $neto : 0.0;
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
                'pendiente_recepcion' => $pendiente,
                'es_desvio' => $exceso >= $tolerancia && $exceso > 0,
                'es_pendiente' => $pendiente > $tolerancia,
            ];
            $ordenes[] = $fila;
            if ($fila['es_desvio']) {
                $desvios[] = $fila;
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
        usort($ordenes, static fn (array $a, array $b): int => abs($b['neto']) <=> abs($a['neto']));

        return [
            'tolerancia' => $tolerancia,
            'empresas' => array_values($porEmpresa),
            'ordenes' => $ordenes,
            'desvios' => $desvios,
            'pendientes' => $pendientes,
            'ordenes_total' => count($ordenes),
            'desvios_total' => count($desvios),
            'pendientes_total' => count($pendientes),
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
     * Todos los comprobantes de la OC que imputaron anticipos (FA, ASIGNA_RECEPCION, etc.).
     *
     * @param  list<int>  $cuentaIds
     * @return array<string, float> empresa|cuenta|ordencompra => monto local
     */
    private function montoComprobanteEnAnticipoPorOrden(array $cuentaIds, ?int $empresaId): array
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

        $filas = $query
            ->selectRaw('a.empresa_id, m.cuentacontable_id, oc.id as ordencompra_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('a.empresa_id', 'm.cuentacontable_id', 'oc.id')
            ->get();

        return $this->indexarMontoPorOrden($filas);
    }

    /**
     * Solo recepciones de OC anticipadas (mismo universo que la factura anticipada).
     *
     * @param  list<int>  $cuentaIds
     * @return array<string, float>
     */
    private function montoRecepcionPorOrden(array $cuentaIds, ?int $empresaId): array
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

        $filas = $query
            ->selectRaw('a.empresa_id, m.cuentacontable_id, r.ordencompra_id as ordencompra_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('a.empresa_id', 'm.cuentacontable_id', 'r.ordencompra_id')
            ->get();

        return $this->indexarMontoPorOrden($filas);
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return array<string, float>
     */
    private function indexarMontoPorOrden(iterable $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $key = ((int) $fila->empresa_id).'|'.((int) $fila->cuentacontable_id).'|'.((int) $fila->ordencompra_id);
            $out[$key] = round((float) $fila->saldo_local, 2);
        }

        return $out;
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
