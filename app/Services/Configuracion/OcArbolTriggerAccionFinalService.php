<?php

namespace App\Services\Configuracion;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Configuracion\OcArbolTriggerCatalog;
use Carbon\Carbon;
use RuntimeException;

final class OcArbolTriggerAccionFinalService
{
    public function ejecutar(Arbolaprobacion_OcTrigger $trigger, int $ordencompraId, int $usuarioHistoriaId): void
    {
        $accion = strtoupper(trim((string) ($trigger->accion_final ?? OcArbolTriggerCatalog::ACCION_NINGUNA)));
        if ($accion === OcArbolTriggerCatalog::ACCION_NINGUNA || $accion === '') {
            return;
        }

        if ($accion === OcArbolTriggerCatalog::ACCION_CAMBIAR_SECTOR) {
            $this->cambiarSector($trigger, $ordencompraId, $usuarioHistoriaId);

            return;
        }

        if ($accion === OcArbolTriggerCatalog::ACCION_CAMBIAR_ESTADO) {
            $estado = trim((string) ($trigger->accion_final_estado ?? ''));
            if ($estado !== '' && OrdencompraEstados::esNombreValido($estado)) {
                app(ArbolaprobacionService::class)->aplicarEstadoOrdencompraPublico(
                    $ordencompraId,
                    $estado,
                    'Acción final del trigger OC: '.$trigger->nombre,
                    $usuarioHistoriaId
                );
            }
        }
    }

    private function cambiarSector(Arbolaprobacion_OcTrigger $trigger, int $ordencompraId, int $usuarioHistoriaId): void
    {
        $sectorDestinoId = (int) ($trigger->accion_final_sector_id ?? 0);
        if ($sectorDestinoId <= 0) {
            $sectorDestinoId = (int) Sector_Legajocompra::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', ['CUENTAS A PAGAR'])
                ->value('id');
        }
        if ($sectorDestinoId <= 0) {
            return;
        }

        $oc = Ordencompra::query()->find($ordencompraId);
        if (! $oc || (int) ($oc->sector_legajocompra_id ?? 0) === $sectorDestinoId) {
            return;
        }

        if (OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorDestinoId)) {
            $gate = OrdencompraEnvioCuentasAPagarGateSupport::evaluar($oc);
            if (! $gate['ok']) {
                throw new RuntimeException(
                    'No se puede enviar el legajo a Cuentas a pagar (trigger OC): '.implode(' ', $gate['errores'])
                );
            }
        }

        Ordencompra::query()->whereKey($ordencompraId)->update(['sector_legajocompra_id' => $sectorDestinoId]);
        Ordencompra_Historia::create([
            'ordencompra_id' => $ordencompraId,
            'sector_legajocompra_id' => $sectorDestinoId,
            'fecha' => Carbon::now(),
            'observacion' => 'Traslado automático tras aprobación (trigger OC)',
            'leyenda' => $trigger->nombre ?: 'Acción final trigger OC',
            'creousuario_id' => $usuarioHistoriaId,
        ]);
    }
}
