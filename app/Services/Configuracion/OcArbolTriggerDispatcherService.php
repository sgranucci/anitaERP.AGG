<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Configuracion\OcArbolTriggerCatalog;
use App\Support\Configuracion\OcArbolTriggerEvaluators\OcArbolTriggerEvaluatorRegistry;

final class OcArbolTriggerDispatcherService
{
    public const CONTEXTO_ALTA = 'ALTA';

    public const CONTEXTO_ACTUALIZACION = 'ACTUALIZACION';

    public const CONTEXTO_CAMBIO_SECTOR = 'CAMBIO_SECTOR';

    public function __construct(
        private ArbolaprobacionService $arbolaprobacionService,
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private OcArbolTriggerEvaluatorRegistry $evaluatorRegistry,
    ) {}

    public function dispararPorAlta(int $ordencompraId, ?string $observacionEnvio = null): void
    {
        $this->disparar($ordencompraId, self::CONTEXTO_ALTA, [], $observacionEnvio);
    }

    public function dispararPorActualizacion(int $ordencompraId, ?string $observacionEnvio = null): void
    {
        $this->disparar($ordencompraId, self::CONTEXTO_ACTUALIZACION, [], $observacionEnvio);
    }

    public function dispararPorCambioSector(
        int $ordencompraId,
        ?int $sectorAnteriorId,
        int $sectorNuevoId,
        ?string $observacionEnvio = null
    ): void {
        $this->disparar($ordencompraId, self::CONTEXTO_CAMBIO_SECTOR, [
            'sector_anterior_id' => $sectorAnteriorId,
            'sector_nuevo_id' => $sectorNuevoId,
        ], $observacionEnvio);
    }

    /**
     * @param  array{sector_anterior_id?: int|null, sector_nuevo_id?: int}  $contexto
     */
    private function disparar(
        int $ordencompraId,
        string $contextoEvento,
        array $contexto,
        ?string $observacionEnvio = null
    ): void {
        if ($ordencompraId <= 0) {
            return;
        }

        $oc = $this->ordencompraRepository->find($ordencompraId);
        if (! $oc) {
            return;
        }

        $arbol = $this->arbolaprobacionService->arbolOrdencompraActivoParaEmpresa((int) $oc->empresa_id);
        if (! $arbol) {
            return;
        }

        $opcionesArbol = $this->opcionesConObservacionEnvio([], $observacionEnvio);

        $triggers = Arbolaprobacion_OcTrigger::query()
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('activo', 'S')
            ->orderBy('prioridad')
            ->orderBy('id')
            ->get();

        if ($triggers->isEmpty()) {
            $this->dispararLegacy($arbol, $ordencompraId, $contextoEvento, $contexto, $oc, $observacionEnvio);

            return;
        }

        $trigger = $this->resolverTrigger($triggers, $oc, $contextoEvento, $contexto);
        if (! $trigger) {
            return;
        }

        $this->arbolaprobacionService->anulaMovimientosArbolPendientesOrdencompraTrigger(
            $ordencompraId,
            (int) $trigger->id,
            'Reinicio del circuito trigger OC #'.$trigger->id
        );

        $this->arbolaprobacionService->procesaArbolaprobacion('OC', $ordencompraId, 'insert', array_merge(
            $opcionesArbol,
            ['oc_trigger_id' => (int) $trigger->id]
        ));
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function opcionesConObservacionEnvio(array $base, ?string $observacionEnvio): array
    {
        $obs = $this->arbolaprobacionService->normalizarObservacionEnvio($observacionEnvio);
        if ($obs !== '') {
            $base['observacion_envio'] = $obs;
        }

        return $base;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Arbolaprobacion_OcTrigger>  $triggers
     * @param  array{sector_anterior_id?: int|null, sector_nuevo_id?: int}  $contexto
     */
    private function resolverTrigger(
        $triggers,
        $ordencompra,
        string $contextoEvento,
        array $contexto
    ): ?Arbolaprobacion_OcTrigger {
        foreach ($triggers as $trigger) {
            if ($this->triggerCoincide($trigger, $ordencompra, $contextoEvento, $contexto)) {
                return $trigger;
            }
        }

        return null;
    }

    /**
     * @param  array{sector_anterior_id?: int|null, sector_nuevo_id?: int}  $contexto
     */
    private function triggerCoincide(
        Arbolaprobacion_OcTrigger $trigger,
        $ordencompra,
        string $contextoEvento,
        array $contexto
    ): bool {
        if ($trigger->tipo === OcArbolTriggerCatalog::TIPO_EVENTO) {
            if ($contextoEvento === self::CONTEXTO_ALTA && $trigger->evento === OcArbolTriggerCatalog::EVENTO_ALTA) {
                return true;
            }

            if ($contextoEvento === self::CONTEXTO_CAMBIO_SECTOR
                && $trigger->evento === OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR) {
                return $this->evaluatorRegistry->aplicaEventoCambioSector(
                    $trigger,
                    $contexto['sector_anterior_id'] ?? null,
                    (int) ($contexto['sector_nuevo_id'] ?? 0)
                );
            }

            return false;
        }

        if ($trigger->tipo !== OcArbolTriggerCatalog::TIPO_CONDICION) {
            return false;
        }

        if ($contextoEvento === self::CONTEXTO_ALTA) {
            return $this->evaluatorRegistry->aplicaCondicion($ordencompra, $trigger);
        }

        if ($contextoEvento === self::CONTEXTO_ACTUALIZACION
            && strtoupper((string) ($trigger->reevaluar_en_actualizacion ?? 'N')) === 'S') {
            return $this->evaluatorRegistry->aplicaCondicion($ordencompra, $trigger);
        }

        return false;
    }

    /**
     * @param  array{sector_anterior_id?: int|null, sector_nuevo_id?: int}  $contexto
     */
    private function dispararLegacy(
        Arbolaprobacion $arbol,
        int $ordencompraId,
        string $contextoEvento,
        array $contexto,
        $oc,
        ?string $observacionEnvio = null
    ): void {
        $opciones = $this->opcionesConObservacionEnvio([], $observacionEnvio);

        if ($contextoEvento === self::CONTEXTO_ALTA) {
            if ($this->arbolaprobacionService->ocDispararArbolAlAlta($arbol)) {
                $this->arbolaprobacionService->procesaArbolaprobacion('OC', $ordencompraId, 'insert', $opciones);
            }

            return;
        }

        if ($contextoEvento === self::CONTEXTO_CAMBIO_SECTOR) {
            $sectorNuevo = (int) ($contexto['sector_nuevo_id'] ?? 0);
            $this->arbolaprobacionService->dispararArbolOrdencompraAlCambiarSector(
                $ordencompraId,
                $sectorNuevo,
                $observacionEnvio
            );

            return;
        }

        if ($contextoEvento === self::CONTEXTO_ACTUALIZACION
            && ($oc->estadoordencompra ?? '') === OrdencompraEstados::PENDIENTE
            && $this->arbolaprobacionService->ocDispararArbolAlAlta($arbol)) {
            $this->arbolaprobacionService->procesaArbolaprobacion('OC', $ordencompraId, 'insert', $opciones);
        }
    }
}
