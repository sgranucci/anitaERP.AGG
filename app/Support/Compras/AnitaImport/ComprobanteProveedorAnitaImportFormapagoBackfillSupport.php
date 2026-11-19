<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use Illuminate\Support\Facades\DB;

/**
 * Corrige cuotas ANITA_IMPORT que quedaron en Efectivo por el default del import.
 * Prioridad: OC → proveedor. No toca cuotas que ya no son Efectivo.
 */
final class ComprobanteProveedorAnitaImportFormapagoBackfillSupport
{
    /**
     * @return array{
     *   candidatas: int,
     *   a_actualizar: int,
     *   via_oc: int,
     *   via_proveedor: int,
     *   quedan_efectivo: int,
     *   actualizadas: int,
     *   por_destino: array<string, int>,
     *   muestra: list<array{cuota_id:int,cp_id:int,proveedor_id:int,ordencompra_id:?int,formapago_id:int,origen:string,etiqueta:string}>
     * }
     */
    public static function ejecutar(
        ?string $desde,
        ?string $hasta,
        ?int $empresaId,
        bool $dryRun,
        int $muestraMax = 25,
    ): array {
        ComprobanteProveedorAnitaImportFormapagoSupport::resetCache();
        $efectivoId = ComprobanteProveedorAnitaImportFormapagoSupport::idEfectivo();

        $stats = [
            'candidatas' => 0,
            'a_actualizar' => 0,
            'via_oc' => 0,
            'via_proveedor' => 0,
            'quedan_efectivo' => 0,
            'actualizadas' => 0,
            'por_destino' => [],
            'muestra' => [],
        ];

        $nombresFp = DB::table('formapago')->pluck('abreviatura', 'id')->map(
            static fn ($a) => (string) $a
        )->all();

        $query = DB::table('comprobante_proveedor_cuota as cpc')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpc.comprobante_proveedor_id')
            ->where('cp.origen_entrada', ComprobanteProveedorOrigenEntrada::ANITA_IMPORT)
            ->where('cpc.formapago_id', $efectivoId)
            ->select([
                'cpc.id as cuota_id',
                'cp.id as cp_id',
                'cp.proveedor_id',
                'cp.ordencompra_id',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'cp.fechacomprobante',
            ])
            ->orderBy('cpc.id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('cp.empresa_id', $empresaId);
        }
        if ($desde !== null && $desde !== '') {
            $query->whereDate('cp.fechacomprobante', '>=', $desde);
        }
        if ($hasta !== null && $hasta !== '') {
            $query->whereDate('cp.fechacomprobante', '<=', $hasta);
        }

        $actualizaciones = [];

        foreach ($query->cursor() as $row) {
            $stats['candidatas']++;
            $proveedorId = (int) $row->proveedor_id;
            $ordencompraId = (int) ($row->ordencompra_id ?? 0) ?: null;

            $viaOc = ComprobanteProveedorAnitaImportFormapagoSupport::desdeOrdencompra($ordencompraId);
            $viaProv = null;
            $origen = '';
            if ($viaOc !== null && $viaOc > 0) {
                $nuevo = $viaOc;
                $origen = 'oc';
            } else {
                $viaProv = ComprobanteProveedorAnitaImportFormapagoSupport::desdeProveedor($proveedorId);
                if ($viaProv !== null && $viaProv > 0) {
                    $nuevo = $viaProv;
                    $origen = 'proveedor';
                } else {
                    $stats['quedan_efectivo']++;

                    continue;
                }
            }

            if ($nuevo === $efectivoId) {
                // OC o proveedor confirman Efectivo: no es el default erróneo del import.
                $stats['quedan_efectivo']++;

                continue;
            }

            $stats['a_actualizar']++;
            if ($origen === 'oc') {
                $stats['via_oc']++;
            } else {
                $stats['via_proveedor']++;
            }

            $abrev = $nombresFp[$nuevo] ?? (string) $nuevo;
            $stats['por_destino'][$abrev] = ($stats['por_destino'][$abrev] ?? 0) + 1;

            if (count($stats['muestra']) < $muestraMax) {
                $stats['muestra'][] = [
                    'cuota_id' => (int) $row->cuota_id,
                    'cp_id' => (int) $row->cp_id,
                    'proveedor_id' => $proveedorId,
                    'ordencompra_id' => $ordencompraId,
                    'formapago_id' => $nuevo,
                    'origen' => $origen,
                    'etiqueta' => sprintf(
                        '%s-%04d-%s',
                        $row->letra ?? 'A',
                        (int) ($row->sucursal ?? 0),
                        $row->numerocomprobante ?? ''
                    ),
                ];
            }

            if (! $dryRun) {
                $actualizaciones[$nuevo][] = (int) $row->cuota_id;
            }
        }

        if (! $dryRun) {
            foreach ($actualizaciones as $formapagoId => $cuotaIds) {
                foreach (array_chunk($cuotaIds, 500) as $chunk) {
                    $stats['actualizadas'] += DB::table('comprobante_proveedor_cuota')
                        ->whereIn('id', $chunk)
                        ->where('formapago_id', $efectivoId)
                        ->update([
                            'formapago_id' => (int) $formapagoId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        return $stats;
    }
}
