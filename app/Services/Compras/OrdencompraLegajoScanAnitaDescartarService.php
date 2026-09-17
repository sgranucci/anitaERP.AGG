<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Legajo_Scan_Anita_Descartado;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Al borrar una precarga nacida de Scan Anita, la saca del legajo (ERP + best-effort Anita)
 * para que no se rematerialice ni reaparezca en Asignar COM / bandeja.
 */
class OrdencompraLegajoScanAnitaDescartarService
{
    public function descartarSiCorresponde(Precarga_Comprobante_Proveedor $precarga): void
    {
        if ((string) ($precarga->origen_entrada ?? '') !== PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return;
        }

        $oc = $this->resolverOrdencompra($precarga);
        if ($oc === null) {
            Log::info('bandeja.scan_anita_descartar.sin_oc', [
                'precarga_id' => (int) $precarga->id,
                'numeroordencompra' => (string) ($precarga->numeroordencompra ?? ''),
            ]);

            return;
        }

        $documentoId = $this->resolverDocumentoId($oc, $precarga);
        if ($documentoId <= 0) {
            Log::info('bandeja.scan_anita_descartar.sin_documento', [
                'precarga_id' => (int) $precarga->id,
                'ordencompra_id' => (int) $oc->id,
                'letra' => (string) ($precarga->letra ?? ''),
                'sucursal' => (int) ($precarga->sucursal ?? 0),
                'numero' => (int) ($precarga->numerocomprobante ?? 0),
            ]);

            return;
        }

        $this->registrarDescarte($oc, $precarga, $documentoId);
        $this->desvincularEnAnita($oc, $documentoId);
        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();
    }

    /**
     * @param  list<int>  $ordencompraIds
     * @return array<int, array<int, true>> mapa ocId => [documentoId => true]
     */
    public function documentoIdsDescartadosPorOcIds(array $ordencompraIds): array
    {
        $ordencompraIds = array_values(array_filter(array_map('intval', $ordencompraIds), static fn (int $id) => $id > 0));
        if ($ordencompraIds === []) {
            return [];
        }

        $out = [];
        foreach ($ordencompraIds as $ocId) {
            $out[$ocId] = [];
        }

        $rows = Ordencompra_Legajo_Scan_Anita_Descartado::query()
            ->whereIn('ordencompra_id', $ordencompraIds)
            ->get(['ordencompra_id', 'documento_id']);

        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            $docId = (int) $row->documento_id;
            if ($ocId > 0 && $docId > 0) {
                $out[$ocId][$docId] = true;
            }
        }

        return $out;
    }

    public function estaDescartado(Ordencompra $oc, int $documentoId): bool
    {
        if ($documentoId <= 0 || (int) $oc->id <= 0) {
            return false;
        }

        return Ordencompra_Legajo_Scan_Anita_Descartado::query()
            ->where('ordencompra_id', (int) $oc->id)
            ->where('documento_id', $documentoId)
            ->exists();
    }

    private function resolverOrdencompra(Precarga_Comprobante_Proveedor $precarga): ?Ordencompra
    {
        $nro = trim((string) ($precarga->numeroordencompra ?? ''));
        if ($nro === '') {
            return null;
        }

        $query = Ordencompra::query()->where('numeroordencompra', $nro);
        $empresaId = (int) ($precarga->empresa_id ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->with('empresas:id,codigo,nombre')->first();
    }

    private function resolverDocumentoId(Ordencompra $oc, Precarga_Comprobante_Proveedor $precarga): int
    {
        $scans = OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOcIncluyendoDescartados($oc);
        $docId = OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible(
            $scans,
            (string) ($precarga->letra ?? ''),
            (int) ($precarga->sucursal ?? 0),
            (int) ($precarga->numerocomprobante ?? 0),
            '',
            0
        );

        return (int) ($docId ?? 0);
    }

    private function registrarDescarte(
        Ordencompra $oc,
        Precarga_Comprobante_Proveedor $precarga,
        int $documentoId,
    ): void {
        Ordencompra_Legajo_Scan_Anita_Descartado::query()->updateOrCreate(
            [
                'ordencompra_id' => (int) $oc->id,
                'documento_id' => $documentoId,
            ],
            [
                'empresa_id' => (int) ($precarga->empresa_id ?: $oc->empresa_id),
                'numeroordencompra' => (string) $oc->numeroordencompra,
                'letra' => strtoupper(trim((string) ($precarga->letra ?? ''))) ?: null,
                'sucursal' => (int) ($precarga->sucursal ?? 0) ?: null,
                'numerocomprobante' => (int) ($precarga->numerocomprobante ?? 0) ?: null,
                'precarga_id_origen' => (int) $precarga->id,
                'user_id' => Auth::id() ? (int) Auth::id() : null,
            ]
        );
    }

    /**
     * Best-effort: corta el vínculo scanfactura → OC en Anita (iotid = 0).
     * El descarte ERP alcanza para no rematerializar aunque esto falle.
     */
    private function desvincularEnAnita(Ordencompra $oc, int $documentoId): void
    {
        $nroOc = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
        if ($documentoId <= 0 || $nroOc <= 0) {
            return;
        }

        try {
            (new ApiAnita)->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'scanfactura',
                'sistema' => 'base_admin',
                'valores' => " iotid = '0' ",
                'whereArmado' => ' WHERE idocumentoid = '.(int) $documentoId
                    .' AND iotid = '.(int) $nroOc.' ',
            ], 'scanfactura desvincular OC', 'bandeja.scan_anita_descartar.anita', true);
        } catch (\Throwable $e) {
            Log::warning('bandeja.scan_anita_descartar.anita_fallo', [
                'ordencompra_id' => (int) $oc->id,
                'documento_id' => $documentoId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
