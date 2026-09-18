<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\Provincia;
use App\Services\Configuracion\IIBBService;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Compras\Retencion\RetencionIibbCalculoSupport;
use App\Support\Compras\Retencion\RetencionIibbElegibilidadSupport;
use App\Support\Compras\Retencion\RetencionIibbInput;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use App\Support\Configuracion\EmpresaJurisdiccionIibbSupport;

/**
 * Fachada de retención IIBB.
 *
 * Quién retiene: matriz empresa × jurisdicción (es_agente_retencion).
 * Catálogo de alícuotas/mínimos: retencionIIBB (legado Anita).
 * Tasa: override del pago → padrón (tasaretencion) → fallback retencionIIBB_condicion.
 */
class RetencionIibbCalculator
{
    /** Alícuota supletoria ARBA si no figura en padrón (régimen general). */
    public const TASA_FALLBACK_ARBA = 4.0;

    public function __construct(
        private RetencionIibbCalculoSupport $calculoSupport,
        private IIBBService $iibbService,
    ) {
    }

    /**
     * @param  float|null  $tasaOverride  Alícuota forzada en el pago
     * @param  int|null  $provinciaIdOverride  Provincia agente / del pago
     * @param  int|null  $condicionIibbIdOverride  Condición IIBB del pago/comprobante
     * @param  int|null  $empresaId  Empresa del pago (sucursal / jurídica)
     */
    public function calcularParaProveedor(
        Proveedor $proveedor,
        float $importeNetoPago,
        ?string $fecha = null,
        ?float $tasaOverride = null,
        ?int $provinciaIdOverride = null,
        ?int $condicionIibbIdOverride = null,
        ?bool $retieneOverride = null,
        ?int $empresaId = null,
    ): RetencionIibbResultado {
        if ($importeNetoPago <= 0) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_SIN_BASE_FACTURA, [
                'importe_neto_pago' => $importeNetoPago,
            ]);
        }

        $condicionId = $condicionIibbIdOverride ?? ($proveedor->condicionIIBB_id
            ? (int) $proveedor->condicionIIBB_id
            : null);

        if (! RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor, $condicionIibbIdOverride)) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE, [
                'proveedor_id' => (int) ($proveedor->id ?? 0),
                'condicion_iibb_id' => $condicionId,
            ]);
        }

        if ($retieneOverride === false) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE, [
                'condicion_iibb_id' => $condicionId,
            ]);
        }

        $provinciaId = $provinciaIdOverride;
        $parametrica = null;

        if ($provinciaId === null) {
            $parametrica = $this->resolverCatalogoAgente($empresaId);
            $provinciaId = $parametrica?->provincia_id
                ? (int) $parametrica->provincia_id
                : $this->provinciaIdAgentePreferida($empresaId);
        } else {
            $parametrica = RetencionIIBB::query()
                ->with(['retencionIIBB_condiciones', 'provincias'])
                ->where('provincia_id', $provinciaId)
                ->first();
        }

        if (! EmpresaJurisdiccionIibbSupport::esAgenteRetencion($empresaId, $provinciaId)) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_AGENTE, [
                'empresa_id' => $empresaId,
                'provincia_id' => $provinciaId,
                'condicion_iibb_id' => $condicionId,
            ]);
        }

        $provincia = $provinciaId
            ? ($parametrica?->provincias ?? Provincia::query()->find($provinciaId))
            : null;

        $jurisdiccion = $provincia?->jurisdiccion !== null
            ? (string) $provincia->jurisdiccion
            : null;

        $filaCondicion = $this->resolverFilaCondicion($parametrica, $condicionId);

        $minimoImponible = (float) ($filaCondicion->minimoimponible ?? 0);
        $minimoRetencion = (float) ($filaCondicion->minimoretencion ?? 0);
        $tasaFallback = $filaCondicion !== null
            ? (float) $filaCondicion->porcentajeretencion
            : ((int) $jurisdiccion === 902 ? self::TASA_FALLBACK_ARBA : 0.0);

        $origen = 'fallback';
        $tasa = $tasaFallback;

        if ($tasaOverride !== null) {
            $tasa = (float) $tasaOverride;
            $origen = 'override';
        } elseif ($jurisdiccion !== null && $proveedor->nroinscripcion) {
            $padron = $this->iibbService->leeTasaRetencion(
                (string) $proveedor->nroinscripcion,
                $jurisdiccion,
                $fecha,
            );
            if ($padron !== null && $padron['tasa'] !== null) {
                $tasa = (float) $padron['tasa'];
                $origen = 'padron';
            }
        }

        return $this->calculoSupport->calcular(new RetencionIibbInput(
            $importeNetoPago,
            $tasa,
            true,
            $minimoImponible,
            $minimoRetencion,
            $origen,
            $jurisdiccion,
            $provinciaId,
            $condicionId,
            $provincia?->nombre !== null ? (string) $provincia->nombre : null,
        ));
    }

    public function calcular(RetencionIibbInput $input): RetencionIibbResultado
    {
        return $this->calculoSupport->calcular($input);
    }

    /**
     * Catálogo del agente: prioriza Buenos Aires (902), que es la única
     * jurisdicción con base recortada en la OP. Sin eso, orderBy(id) tomaba
     * Capital (CABA) y retenía AGIP sobre un neto ARBA o sobre un anticipo.
     */
    private function resolverCatalogoAgente(?int $empresaId): ?RetencionIIBB
    {
        $query = RetencionIIBB::query()
            ->with(['retencionIIBB_condiciones', 'provincias']);

        $agenteIds = EmpresaJurisdiccionIibbSupport::provinciaIdsRetencion($empresaId);
        $baId = ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID;
        $puedeArba = $agenteIds === [] || in_array($baId, $agenteIds, true);
        if ($puedeArba) {
            $arba = (clone $query)->where('provincia_id', $baId)->first();
            if ($arba instanceof RetencionIIBB) {
                return $arba;
            }
        }

        if ($agenteIds !== []) {
            $query->whereIn('provincia_id', $agenteIds);
        }

        return $query->orderBy('id')->first();
    }

    private function provinciaIdAgentePreferida(?int $empresaId): ?int
    {
        $ids = EmpresaJurisdiccionIibbSupport::provinciaIdsRetencion($empresaId);
        $baId = ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID;
        if ($ids === [] || in_array($baId, $ids, true)) {
            return $baId;
        }

        return $ids[0] ?? null;
    }

    private function resolverFilaCondicion(?RetencionIIBB $parametrica, ?int $condicionId): ?RetencionIIBB_Condicion
    {
        if ($parametrica === null || $condicionId === null) {
            return null;
        }

        foreach ($parametrica->retencionIIBB_condiciones as $fila) {
            if ((int) $fila->condicionIIBB_id === $condicionId) {
                return $fila;
            }
        }

        return null;
    }
}
