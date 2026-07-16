<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\CondicionIIBB;
use App\Models\Configuracion\Provincia;
use App\Services\Configuracion\IIBBService;
use App\Support\Compras\Retencion\RetencionIibbCalculoSupport;
use App\Support\Compras\Retencion\RetencionIibbInput;
use App\Support\Compras\Retencion\RetencionIibbResultado;

/**
 * Fachada de retención IIBB.
 *
 * Tasa: override del pago → padrón (tasaretencion) → fallback retencionIIBB_condicion.
 * Mínimos desde retencionIIBB_condicion (provincia × condición del proveedor).
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
     */
    public function calcularParaProveedor(
        Proveedor $proveedor,
        float $importeNetoPago,
        ?string $fecha = null,
        ?float $tasaOverride = null,
        ?int $provinciaIdOverride = null,
        ?int $condicionIibbIdOverride = null,
        ?bool $retieneOverride = null,
    ): RetencionIibbResultado {
        $condicionId = $condicionIibbIdOverride ?? ($proveedor->condicionIIBB_id
            ? (int) $proveedor->condicionIIBB_id
            : null);

        $condicion = $condicionId
            ? CondicionIIBB::query()->find($condicionId)
            : null;

        $retieneCatalogo = $condicion !== null
            && strtoupper((string) ($condicion->formacalculo ?? '')) !== 'N'
            && strtoupper((string) ($condicion->estado ?? 'A')) === 'A';

        $retiene = $retieneOverride ?? $retieneCatalogo;

        if (! $retiene) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE, [
                'condicion_iibb_id' => $condicionId,
            ]);
        }

        $provinciaId = $provinciaIdOverride;
        $parametrica = null;

        if ($provinciaId === null) {
            $parametrica = RetencionIIBB::query()
                ->with(['retencionIIBB_condiciones', 'provincias'])
                ->orderBy('id')
                ->first();
            $provinciaId = $parametrica?->provincia_id ? (int) $parametrica->provincia_id : null;
        } else {
            $parametrica = RetencionIIBB::query()
                ->with(['retencionIIBB_condiciones', 'provincias'])
                ->where('provincia_id', $provinciaId)
                ->first();
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
        ));
    }

    public function calcular(RetencionIibbInput $input): RetencionIibbResultado
    {
        return $this->calculoSupport->calcular($input);
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
