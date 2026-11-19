<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use Illuminate\Support\Facades\DB;

/**
 * Corrige cuotas ANITA_IMPORT que quedaron en Efectivo por el default del import.
 *
 * Prioridad: OC no-Efectivo → proveedor no-Efectivo.
 * Efectivo en la OC no bloquea: se intenta el proveedor.
 * También corrige cuotas de OC en Efectivo cuando el proveedor tiene otro medio
 * (la proyección lee primero el medio de la OC vinculada).
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
     *   oc_candidatas: int,
     *   oc_a_actualizar: int,
     *   oc_actualizadas: int,
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
            'oc_candidatas' => 0,
            'oc_a_actualizar' => 0,
            'oc_actualizadas' => 0,
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
        /** @var array<int, array{proveedor_id:int, formapago_id:int}> $ocsACorregir */
        $ocsACorregir = [];

        foreach ($query->cursor() as $row) {
            $stats['candidatas']++;
            $proveedorId = (int) $row->proveedor_id;
            $ordencompraId = (int) ($row->ordencompra_id ?? 0) ?: null;

            $viaOc = ComprobanteProveedorAnitaImportFormapagoSupport::desdeOrdencompra($ordencompraId);
            $viaProv = ComprobanteProveedorAnitaImportFormapagoSupport::desdeProveedor($proveedorId);
            $elegido = ComprobanteProveedorAnitaImportFormapagoSupport::elegirNoEfectivo(
                $viaOc,
                $viaProv,
                $efectivoId
            );

            if ($elegido === null) {
                $stats['quedan_efectivo']++;

                continue;
            }

            $nuevo = $elegido['id'];
            $origen = $elegido['origen'];

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

            // Si la OC quedó en Efectivo y corregimos vía proveedor, hay que
            // alinear las cuotas de la OC (la proyección lee ese medio primero).
            if (
                $ordencompraId !== null
                && $origen === 'proveedor'
                && ($viaOc === null || $viaOc === $efectivoId)
            ) {
                $ocsACorregir[$ordencompraId] = [
                    'proveedor_id' => $proveedorId,
                    'formapago_id' => $nuevo,
                ];
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

        $ocStats = self::corregirCuotasOcEfectivo($ocsACorregir, $efectivoId, $dryRun);
        $stats['oc_candidatas'] = $ocStats['candidatas'];
        $stats['oc_a_actualizar'] = $ocStats['a_actualizar'];
        $stats['oc_actualizadas'] = $ocStats['actualizadas'];

        return $stats;
    }

    /**
     * @param  array<int, array{proveedor_id:int, formapago_id:int}>  $ocsACorregir
     * @return array{candidatas:int, a_actualizar:int, actualizadas:int}
     */
    private static function corregirCuotasOcEfectivo(array $ocsACorregir, int $efectivoId, bool $dryRun): array
    {
        $out = ['candidatas' => 0, 'a_actualizar' => 0, 'actualizadas' => 0];
        if ($ocsACorregir === []) {
            return $out;
        }

        $porDestino = [];
        foreach ($ocsACorregir as $ordencompraId => $meta) {
            $cuotaIds = DB::table('ordencompra_comprobante_cuota as occu')
                ->join('ordencompra_comprobante as occ', 'occ.id', '=', 'occu.ordencompra_comprobante_id')
                ->where('occ.ordencompra_id', $ordencompraId)
                ->where('occu.formapago_id', $efectivoId)
                ->pluck('occu.id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($cuotaIds === []) {
                continue;
            }

            $out['candidatas'] += count($cuotaIds);
            $out['a_actualizar'] += count($cuotaIds);
            $nuevo = (int) $meta['formapago_id'];
            $porDestino[$nuevo] = array_merge($porDestino[$nuevo] ?? [], $cuotaIds);
        }

        if ($dryRun) {
            return $out;
        }

        foreach ($porDestino as $formapagoId => $cuotaIds) {
            foreach (array_chunk($cuotaIds, 500) as $chunk) {
                $out['actualizadas'] += DB::table('ordencompra_comprobante_cuota')
                    ->whereIn('id', $chunk)
                    ->where('formapago_id', $efectivoId)
                    ->update([
                        'formapago_id' => (int) $formapagoId,
                        'updated_at' => now(),
                    ]);
            }
        }

        ComprobanteProveedorAnitaImportFormapagoSupport::resetCache();

        return $out;
    }
}
