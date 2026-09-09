<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Compras\Retencion\ProveedorExclusionRetencionSupport;
use App\Support\Compras\Retencion\RetencionGananciasAcumuladoMesSupport;
use App\Support\Compras\Retencion\RetencionesPagoBasesDesdeConceptosSupport;
use App\Support\Compras\Retencion\RetencionesPagoBasesResultado;
use App\Support\Compras\Retencion\RetencionesPagoInput;

/**
 * Arma RetencionesPagoInput con bases por concepto + acumulados mensuales Ganancias.
 */
class RetencionesPagoContextoBuilder
{
    public function __construct(
        private RetencionesPagoBasesDesdeConceptosSupport $basesSupport,
        private RetencionGananciasAcumuladoMesSupport $acumuladoGananciasSupport,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $aplicaciones
     * @param  array<string, mixed>  $overrides  Campos opcionales del request/form
     */
    public function armarInput(
        Proveedor $proveedor,
        array $aplicaciones,
        ?string $fecha,
        ?int $empresaId = null,
        int $monedaPagoId = 1,
        ?float $cotizacionPago = null,
        ?int $excluirPagoproveedorId = null,
        array $overrides = [],
        ?float $importeNetoFallback = null,
        ?float $importeIvaFallback = null,
    ): array {
        $bases = $aplicaciones !== []
            ? $this->basesSupport->desdeAplicaciones($aplicaciones, $monedaPagoId, $cotizacionPago)
            : null;

        if ($bases === null || ($bases->origen === 'fallback_bruto' && $bases->brutoAplicado <= 0 && $importeNetoFallback !== null)) {
            $netoFb = round((float) ($importeNetoFallback ?? 0), 2);
            $ivaFb = round((float) ($importeIvaFallback ?? 0), 2);
            $bases = new RetencionesPagoBasesResultado(
                netoGanancias: $netoFb,
                netoIibb: $netoFb,
                netoGravado: $netoFb,
                netoExento: 0.0,
                netoNogravado: 0.0,
                importeIva: $ivaFb,
                brutoAplicado: $netoFb + $ivaFb,
                origen: 'request_fallback',
                detalle: [],
            );
        }

        $regimenGanId = $this->acumuladoGananciasSupport->regimenIdDesdeProveedor(
            isset($overrides['retencionganancia_id']) ? (int) $overrides['retencionganancia_id'] : null,
            $proveedor->retencionganancia_id ? (int) $proveedor->retencionganancia_id : null,
        );

        $netoAcum = 0.0;
        $retAcum = 0.0;
        $acumMeta = null;
        if ($fecha && $this->acumuladoGananciasSupport->regimenTomaAcumulados($regimenGanId)) {
            $acumMeta = $this->acumuladoGananciasSupport->acumular(
                (int) $proveedor->id,
                $fecha,
                $empresaId,
                $regimenGanId,
                $excluirPagoproveedorId,
            );
            $netoAcum = $acumMeta['neto'];
            $retAcum = $acumMeta['retenido'];
        }

        // IVA y SUSS: neto gravado (+ exento no gravado documental para SUSS = gravado).
        $netoIvaSuss = $bases->netoGravado > 0
            ? $bases->netoGravado
            : $bases->netoDocumental();

        $exclusiones = ProveedorExclusionRetencionSupport::mapaVigentes((int) $proveedor->id, $fecha);
        $pctG = (float) ($exclusiones[ProveedorExclusionRetencionSupport::TIPO_GANANCIAS]['porcentaje'] ?? 0);
        $pctI = (float) ($exclusiones[ProveedorExclusionRetencionSupport::TIPO_IVA]['porcentaje'] ?? 0);
        $pctS = (float) ($exclusiones[ProveedorExclusionRetencionSupport::TIPO_SUSS]['porcentaje'] ?? 0);
        $pctB = (float) ($exclusiones[ProveedorExclusionRetencionSupport::TIPO_IIBB]['porcentaje'] ?? 0);

        $input = new RetencionesPagoInput(
            proveedor: $proveedor,
            importeNetoPago: $netoIvaSuss,
            importeIvaPago: $bases->importeIva,
            fecha: $fecha,
            retenciongananciaIdPago: isset($overrides['retencionganancia_id']) ? (int) $overrides['retencionganancia_id'] : null,
            retencionivaIdPago: isset($overrides['retencioniva_id']) ? (int) $overrides['retencioniva_id'] : null,
            retencionsussIdPago: isset($overrides['retencionsuss_id']) ? (int) $overrides['retencionsuss_id'] : null,
            gananciasNetoAcumulado: $netoAcum,
            gananciasRetenidoAcumulado: $retAcum,
            iibbProvinciaId: isset($overrides['iibb_provincia_id']) ? (int) $overrides['iibb_provincia_id'] : null,
            iibbTasaOverride: isset($overrides['iibb_tasa']) ? (float) $overrides['iibb_tasa'] : null,
            calcularGanancias: ! array_key_exists('calcular_ganancias', $overrides) || (bool) $overrides['calcular_ganancias'],
            calcularIva: ! array_key_exists('calcular_iva', $overrides) || (bool) $overrides['calcular_iva'],
            calcularSuss: ! array_key_exists('calcular_suss', $overrides) || (bool) $overrides['calcular_suss'],
            calcularIibb: ! array_key_exists('calcular_iibb', $overrides) || (bool) $overrides['calcular_iibb'],
            empresaId: $empresaId,
            importeNetoGanancias: $bases->netoGanancias,
            importeNetoIibb: $bases->netoIibb,
            importeNetoSuss: $netoIvaSuss,
            ivaExcluido: $pctI >= 100.0,
            porcentajeExclusionIva: $pctI,
            porcentajeExclusionSuss: $pctS,
            porcentajeExclusionIibb: $pctB,
            porcentajeExclusionGanancias: $pctG,
            exclusionesVigentes: $exclusiones,
        );

        return [
            'input' => $input,
            'bases' => $bases,
            'acumulado_ganancias' => $acumMeta,
            'exclusiones' => $exclusiones,
        ];
    }
}
