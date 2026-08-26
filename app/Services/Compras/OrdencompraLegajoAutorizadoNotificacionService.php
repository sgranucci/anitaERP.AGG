<?php

namespace App\Services\Compras;

use App\Mail\Compras\OrdencompraLegajoAutorizadoMail;
use App\Mail\Compras\OrdencompraLegajoRecordatorioMail;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrdencompraLegajoAutorizadoNotificacionService
{
    /**
     * @return list<string>
     */
    public function notificarAutorizacion(Ordencompra $oc, string $firmante = ''): array
    {
        $oc->loadMissing(['proveedores', 'empresas']);
        $emails = array_values(array_unique(array_merge(
            $this->emailsSector(OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS, (int) $oc->empresa_id),
            $this->emailsSector(OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR, (int) $oc->empresa_id),
        )));
        if ($emails === []) {
            return [];
        }

        $datos = [
            'numero_oc' => $oc->numeroordencompra,
            'proveedor' => (string) ($oc->proveedores?->nombre ?? ''),
            'empresa' => (string) ($oc->empresas?->nombre ?? ''),
            'firmante' => $firmante,
            'url' => url('compras/ordencompra/'.$oc->id.'/editar'),
        ];

        try {
            Mail::to($emails)->send(new OrdencompraLegajoAutorizadoMail($datos));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar mail de legajo autorizado: '.$e->getMessage(), [
                'ordencompra_id' => $oc->id,
            ]);
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    public function notificarRecordatorio(Ordencompra $oc, int $dias, string $referente = ''): array
    {
        $oc->loadMissing(['proveedores', 'empresas']);
        $emails = $this->emailsSector(OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS, (int) $oc->empresa_id);
        if ($emails === []) {
            return [];
        }

        $datos = [
            'numero_oc' => $oc->numeroordencompra,
            'proveedor' => (string) ($oc->proveedores?->nombre ?? ''),
            'empresa' => (string) ($oc->empresas?->nombre ?? ''),
            'dias' => $dias,
            'referente' => $referente,
            'url' => url('compras/legajos?vista=estados&tab=gastronomia'),
        ];

        try {
            Mail::to($emails)->send(new OrdencompraLegajoRecordatorioMail($datos));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar recordatorio de legajo en Gastronomía: '.$e->getMessage(), [
                'ordencompra_id' => $oc->id,
            ]);
        }

        return $emails;
    }

    /** @return list<string> */
    private function emailsSector(string $nombreSector, int $empresaId): array
    {
        $sectorId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre($nombreSector);
        if ($sectorId <= 0) {
            return [];
        }

        return UsuarioOperativoSupport::listadoParaSelector(
            empresaId: $empresaId > 0 ? $empresaId : null,
            soloConEmail: true,
            sectorLegajocompraId: $sectorId,
        )
            ->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
