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
        $usuarios = $this->usuariosSectores([
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS,
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR,
        ], (int) $oc->empresa_id);
        $emails = $this->emailsDesdeUsuarios($usuarios);
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

        try {
            app(\App\Services\Configuracion\AnitaNotificacionService::class)->avisarSistemaAUsuarios(
                $usuarios->pluck('id')->all(),
                'Legajo OC '.$oc->numeroordencompra.' autorizado',
                $firmante !== '' ? "Firmante: {$firmante}" : 'El legajo quedó autorizado.',
                $datos['url'],
                ['origen' => 'oc_legajo_autorizado', 'ordencompra_id' => (int) $oc->id]
            );
        } catch (\Throwable) {
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    public function notificarRecordatorio(Ordencompra $oc, int $dias, string $referente = ''): array
    {
        $oc->loadMissing(['proveedores', 'empresas']);
        $usuarios = $this->usuariosSectores([
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS,
        ], (int) $oc->empresa_id);
        $emails = $this->emailsDesdeUsuarios($usuarios);
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

        try {
            app(\App\Services\Configuracion\AnitaNotificacionService::class)->avisarSistemaAUsuarios(
                $usuarios->pluck('id')->all(),
                'Recordatorio legajo OC '.$oc->numeroordencompra,
                "Lleva {$dias} día(s) en Gastronomía.",
                $datos['url'],
                ['origen' => 'oc_legajo_recordatorio', 'ordencompra_id' => (int) $oc->id, 'dias' => $dias]
            );
        } catch (\Throwable) {
        }

        return $emails;
    }

    /**
     * @param  list<string>  $nombresSector
     * @return \Illuminate\Support\Collection<int, \App\Models\Seguridad\Usuario>
     */
    private function usuariosSectores(array $nombresSector, int $empresaId): \Illuminate\Support\Collection
    {
        $coleccion = collect();
        foreach ($nombresSector as $nombreSector) {
            $sectorId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre($nombreSector);
            if ($sectorId <= 0) {
                continue;
            }
            $coleccion = $coleccion->merge(UsuarioOperativoSupport::listadoParaSelector(
                empresaId: $empresaId > 0 ? $empresaId : null,
                soloConEmail: true,
                sectorLegajocompraId: $sectorId,
            ));
        }

        return $coleccion->unique('id')->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $usuarios
     * @return list<string>
     */
    private function emailsDesdeUsuarios(\Illuminate\Support\Collection $usuarios): array
    {
        return $usuarios
            ->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
