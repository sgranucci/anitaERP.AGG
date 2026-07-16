<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionsuss;
use App\Support\Compras\Retencion\RetencionRegimenResolver;
use App\Support\Compras\Retencion\RetencionSussCalculoSupport;
use App\Support\Compras\Retencion\RetencionSussInput;
use App\Support\Compras\Retencion\RetencionSussRegimen;
use App\Support\Compras\Retencion\RetencionSussResultado;

/**
 * Fachada de retención SUSS.
 *
 * Régimen efectivo: override del pago → default comprobante → default proveedor.
 */
class RetencionSussCalculator
{
    public function __construct(
        private RetencionSussCalculoSupport $calculoSupport,
    ) {
    }

    /**
     * @param  float  $importeNetoPago  Neto gravado IVA del pago actual
     * @param  bool|null  $esSujetoPasible  Empleador + RI IVA; null = asumir true si retienesuss=S
     * @param  Retencionsuss|int|null  $regimenOverride  Régimen elegido en el pago
     */
    public function calcularParaProveedor(
        Proveedor $proveedor,
        float $importeNetoPago,
        float $netoAcumuladoPeriodo = 0.0,
        float $retenidoAcumuladoPeriodo = 0.0,
        ?float $retencionManual = null,
        ?bool $esSujetoPasible = null,
        Retencionsuss|int|null $regimenOverride = null,
        ?int $regimenComprobanteId = null,
        ?bool $retieneOverride = null,
    ): RetencionSussResultado {
        $defaults = RetencionRegimenResolver::defaultsDesdeProveedor($proveedor);
        $retiene = $retieneOverride ?? $defaults['retienesuss'];
        $sujeto = $esSujetoPasible ?? $retiene;

        $regimenId = $this->resolverRegimenId(
            $regimenOverride,
            $regimenComprobanteId,
            $defaults['retencionsuss_id'],
        );
        $regimenModelo = $this->cargarRegimen($regimenOverride, $regimenId);

        if ($regimenModelo === null) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_SIN_REGIMEN, [
                'proveedor_id' => $proveedor->id,
                'retencionsuss_id' => $regimenId,
            ]);
        }

        return $this->calculoSupport->calcular(new RetencionSussInput(
            RetencionSussRegimen::desdeModelo($regimenModelo),
            $importeNetoPago,
            $retiene,
            $sujeto,
            $netoAcumuladoPeriodo,
            $retenidoAcumuladoPeriodo,
            $retencionManual,
        ));
    }

    public function calcular(RetencionSussInput $input): RetencionSussResultado
    {
        return $this->calculoSupport->calcular($input);
    }

    private function resolverRegimenId(
        Retencionsuss|int|null $regimenOverride,
        ?int $regimenComprobanteId,
        ?int $regimenProveedorId,
    ): ?int {
        if ($regimenOverride instanceof Retencionsuss) {
            return (int) $regimenOverride->id;
        }

        $overrideId = is_int($regimenOverride) ? $regimenOverride : null;

        return RetencionRegimenResolver::resolverId($overrideId, $regimenComprobanteId, $regimenProveedorId);
    }

    private function cargarRegimen(Retencionsuss|int|null $regimenOverride, ?int $regimenId): ?Retencionsuss
    {
        if ($regimenOverride instanceof Retencionsuss) {
            return $regimenOverride;
        }

        if ($regimenId === null) {
            return null;
        }

        return Retencionsuss::query()->find($regimenId);
    }
}
