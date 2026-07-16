<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencioniva;
use App\Support\Compras\Retencion\RetencionIvaCalculoSupport;
use App\Support\Compras\Retencion\RetencionIvaInput;
use App\Support\Compras\Retencion\RetencionIvaRegimen;
use App\Support\Compras\Retencion\RetencionIvaResultado;
use App\Support\Compras\Retencion\RetencionRegimenResolver;

/**
 * Fachada de retención de IVA.
 *
 * El régimen efectivo puede venir del proveedor, del comprobante o override del pago.
 */
class RetencionIvaCalculator
{
    public function __construct(
        private RetencionIvaCalculoSupport $calculoSupport,
    ) {
    }

    /**
     * @param  Retencioniva|int|null  $regimenOverride  Régimen del pago/comprobante (gana sobre proveedor)
     * @param  float|null  $porcentajeOverride  REPROWEB 100% u otra alícuota sustitutiva
     */
    public function calcularParaProveedor(
        Proveedor $proveedor,
        float $importeNetoPago,
        float $importeIvaPago,
        float $netoAcumuladoPeriodo = 0.0,
        float $ivaAcumuladoPeriodo = 0.0,
        float $retenidoAcumuladoPeriodo = 0.0,
        Retencioniva|int|null $regimenOverride = null,
        ?int $regimenComprobanteId = null,
        ?float $porcentajeOverride = null,
        bool $excluido = false,
        ?bool $retieneOverride = null,
    ): RetencionIvaResultado {
        $defaults = RetencionRegimenResolver::defaultsDesdeProveedor($proveedor);
        $retiene = $retieneOverride ?? $defaults['retieneiva'];

        $regimenId = $this->resolverRegimenId($regimenOverride, $regimenComprobanteId, $defaults['retencioniva_id']);
        $regimenModelo = $this->cargarRegimen($regimenOverride, $regimenId);

        if ($regimenModelo === null) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_SIN_REGIMEN, [
                'proveedor_id' => $proveedor->id,
                'retencioniva_id' => $regimenId,
            ]);
        }

        return $this->calculoSupport->calcular(new RetencionIvaInput(
            RetencionIvaRegimen::desdeModelo($regimenModelo),
            $importeNetoPago,
            $importeIvaPago,
            $retiene,
            $netoAcumuladoPeriodo,
            $ivaAcumuladoPeriodo,
            $retenidoAcumuladoPeriodo,
            $porcentajeOverride,
            $excluido,
        ));
    }

    public function calcular(RetencionIvaInput $input): RetencionIvaResultado
    {
        return $this->calculoSupport->calcular($input);
    }

    private function resolverRegimenId(
        Retencioniva|int|null $regimenOverride,
        ?int $regimenComprobanteId,
        ?int $regimenProveedorId,
    ): ?int {
        if ($regimenOverride instanceof Retencioniva) {
            return (int) $regimenOverride->id;
        }

        $overrideId = is_int($regimenOverride) ? $regimenOverride : null;

        return RetencionRegimenResolver::resolverId($overrideId, $regimenComprobanteId, $regimenProveedorId);
    }

    private function cargarRegimen(Retencioniva|int|null $regimenOverride, ?int $regimenId): ?Retencioniva
    {
        if ($regimenOverride instanceof Retencioniva) {
            return $regimenOverride;
        }

        if ($regimenId === null) {
            return null;
        }

        return Retencioniva::query()->find($regimenId);
    }
}
