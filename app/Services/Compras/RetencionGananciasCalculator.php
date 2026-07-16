<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionganancia;
use App\Support\Compras\Retencion\RetencionGananciasCalculoSupport;
use App\Support\Compras\Retencion\RetencionGananciasInput;
use App\Support\Compras\Retencion\RetencionGananciasRegimen;
use App\Support\Compras\Retencion\RetencionGananciasResultado;
use App\Support\Compras\Retencion\RetencionRegimenResolver;

/**
 * Fachada de retención de ganancias para el futuro módulo de pagos.
 *
 * Régimen efectivo: override del pago → default comprobante → default proveedor.
 */
class RetencionGananciasCalculator
{
    public function __construct(
        private RetencionGananciasCalculoSupport $calculoSupport,
    ) {
    }

    /**
     * @param  float  $importeNetoPago  Neto sin IVA del pago actual
     * @param  Retencionganancia|int|null  $regimenOverride  Régimen elegido en el pago
     * @param  int|null  $regimenComprobanteId  Default grabado en la factura/comprobante
     */
    public function calcularParaProveedor(
        Proveedor $proveedor,
        float $importeNetoPago,
        float $netoAcumuladoPeriodo = 0.0,
        float $retenidoAcumuladoPeriodo = 0.0,
        ?float $retencionManual = null,
        Retencionganancia|int|null $regimenOverride = null,
        ?int $regimenComprobanteId = null,
        ?bool $retieneOverride = null,
        ?bool $inscriptoOverride = null,
    ): RetencionGananciasResultado {
        $defaults = RetencionRegimenResolver::defaultsDesdeProveedor($proveedor);
        $retiene = $retieneOverride ?? $defaults['retieneganancia'];
        $inscripto = $inscriptoOverride ?? $this->esInscripto($proveedor);

        $regimenId = $this->resolverRegimenId(
            $regimenOverride,
            $regimenComprobanteId,
            $defaults['retencionganancia_id'],
        );
        $regimenModelo = $this->cargarRegimen($regimenOverride, $regimenId);

        if ($regimenModelo === null) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_SIN_REGIMEN, [
                'proveedor_id' => $proveedor->id,
                'retencionganancia_id' => $regimenId,
            ]);
        }

        if (! $regimenModelo->relationLoaded('retencionganancia_escalas')) {
            $regimenModelo->load('retencionganancia_escalas');
        }

        return $this->calculoSupport->calcular(new RetencionGananciasInput(
            RetencionGananciasRegimen::desdeModelo($regimenModelo),
            $importeNetoPago,
            $retiene,
            $inscripto,
            $netoAcumuladoPeriodo,
            $retenidoAcumuladoPeriodo,
            $retencionManual,
        ));
    }

    public function calcular(RetencionGananciasInput $input): RetencionGananciasResultado
    {
        return $this->calculoSupport->calcular($input);
    }

    private function esInscripto(Proveedor $proveedor): bool
    {
        $condicion = strtoupper(trim((string) ($proveedor->condicionganancia ?? '')));

        return ! in_array($condicion, ['N', 'NO', ''], true);
    }

    private function resolverRegimenId(
        Retencionganancia|int|null $regimenOverride,
        ?int $regimenComprobanteId,
        ?int $regimenProveedorId,
    ): ?int {
        if ($regimenOverride instanceof Retencionganancia) {
            return (int) $regimenOverride->id;
        }

        $overrideId = is_int($regimenOverride) ? $regimenOverride : null;

        return RetencionRegimenResolver::resolverId($overrideId, $regimenComprobanteId, $regimenProveedorId);
    }

    private function cargarRegimen(
        Retencionganancia|int|null $regimenOverride,
        ?int $regimenId,
    ): ?Retencionganancia {
        if ($regimenOverride instanceof Retencionganancia) {
            return $regimenOverride;
        }

        if ($regimenId === null) {
            return null;
        }

        return Retencionganancia::query()
            ->with('retencionganancia_escalas')
            ->find($regimenId);
    }
}
