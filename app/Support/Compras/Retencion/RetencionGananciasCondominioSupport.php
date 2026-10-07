<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Integrante;
use App\Services\Compras\RetencionGananciasCalculator;

/**
 * RG 830 art. 8 y 25: pago global a un condominio, retención por integrante.
 * Cada uno toma su porcentaje del neto y su propio mínimo no sujeto.
 * IIBB, IVA y SUSS no pasan por acá.
 */
final class RetencionGananciasCondominioSupport
{
    public function __construct(
        private readonly RetencionGananciasCalculator $calculator,
        private readonly RetencionGananciasAcumuladoMesSupport $acumulado,
    ) {
    }

    public function aplica(Proveedor $proveedor): bool
    {
        if (strtoupper(trim((string) ($proveedor->condicionganancia ?? ''))) !== 'C') {
            return false;
        }

        if (! $proveedor->relationLoaded('proveedor_integrantes')) {
            $proveedor->load('proveedor_integrantes');
        }

        return $proveedor->proveedor_integrantes->contains(
            static fn (Proveedor_Integrante $i): bool => (float) $i->porcentaje > 0.009
        );
    }

    public function calcular(RetencionesPagoInput $input): RetencionGananciasResultado
    {
        $proveedor = $input->proveedor;
        if (! $proveedor->relationLoaded('proveedor_integrantes')) {
            $proveedor->load('proveedor_integrantes');
        }

        $integrantes = $proveedor->proveedor_integrantes
            ->filter(static fn (Proveedor_Integrante $i): bool => (float) $i->porcentaje > 0.009)
            ->sortBy(static fn (Proveedor_Integrante $i): array => [(int) $i->orden, (int) $i->id])
            ->values();

        $neto = round($input->netoGanancias(), 2);
        $porcentajes = $integrantes->map(static fn (Proveedor_Integrante $i): float => (float) $i->porcentaje)->all();
        $partes = self::repartir($neto, $porcentajes);

        $regimenId = $this->acumulado->regimenIdDesdeProveedor(
            $input->retenciongananciaIdPago,
            $proveedor->retencionganancia_id ? (int) $proveedor->retencionganancia_id : null,
        );

        $lineas = [];
        $suma = 0.0;
        $sumaSujeto = 0.0;
        $alicuota = null;
        $motivo = RetencionGananciasResultado::MOTIVO_NO_RETIENE;
        $detalleComun = [];

        foreach ($integrantes as $idx => $integrante) {
            $parte = (float) ($partes[$idx] ?? 0);
            $cuit = Proveedor_Integrante::digitosCuit((string) $integrante->cuit);
            $acum = ['neto' => 0.0, 'retenido' => 0.0];
            if ($input->fecha && $this->acumulado->regimenTomaAcumulados($regimenId)) {
                $acum = $this->acumulado->acumular(
                    (int) $proveedor->id,
                    $input->fecha,
                    $input->empresaId,
                    $regimenId,
                    $input->excluirPagoproveedorId,
                    null,
                    $cuit,
                    (float) $integrante->porcentaje,
                );
            }

            $resultado = $this->calculator->calcularParaProveedor(
                $proveedor,
                $parte,
                (float) ($acum['neto'] ?? 0),
                (float) ($acum['retenido'] ?? 0),
                $input->gananciasManual,
                $input->retenciongananciaIdPago,
                $input->retenciongananciaIdComprobante,
                $input->retieneGanancias,
                $integrante->estaInscripto(),
            );

            if ($detalleComun === []) {
                $detalleComun = $resultado->detalle;
            }
            if ($resultado->aplica && $resultado->importeRetencion > 0) {
                $motivo = $resultado->motivo;
                $alicuota = $alicuota === null ? $resultado->alicuotaAplicada : $alicuota;
            }

            $importe = $resultado->aplica ? $resultado->importeRetencion : 0.0;
            $suma = round($suma + $importe, 2);
            $sumaSujeto = round($sumaSujeto + ($resultado->aplica ? $resultado->baseRetenible : 0.0), 2);

            $lineas[] = array_merge($resultado->detalle, [
                'aplica' => $resultado->aplica && $importe > 0,
                'importe' => $importe,
                'alicuota' => $resultado->alicuotaAplicada,
                'motivo' => $resultado->motivo,
                'base_calculo' => $resultado->baseCalculo,
                'base_retenible' => $resultado->baseRetenible,
                'neto_pago' => $parte,
                'neto_gravado_comun' => $neto,
                'integrante_id' => (int) $integrante->id,
                'integrante_nombre' => (string) $integrante->nombre,
                'integrante_cuit' => Proveedor_Integrante::formatearCuit((string) $integrante->cuit),
                'integrante_porcentaje' => (float) $integrante->porcentaje,
                'integrante_inscripto' => $integrante->estaInscripto(),
                'regimen_id' => $resultado->detalle['regimen_id'] ?? $regimenId,
                'regimen' => $resultado->detalle['regimen'] ?? '',
                'codigo' => $resultado->detalle['codigo'] ?? '',
            ]);
        }

        return new RetencionGananciasResultado(
            $suma > 0,
            $suma,
            $neto,
            $sumaSujeto,
            $alicuota ?? 0.0,
            $suma > 0 ? $motivo : ($lineas[0]['motivo'] ?? RetencionGananciasResultado::MOTIVO_NO_RETIENE),
            array_merge($detalleComun, [
                'condominio' => true,
                'modo' => 'condominio',
                'neto_pago' => $neto,
                'neto_gravado_comun' => $neto,
                'base_retenible' => $sumaSujeto,
                'integrantes' => $lineas,
            ]),
        );
    }

    /**
     * Reparte el neto según porcentajes. El último renglón absorbe el redondeo.
     *
     * @param  list<float>  $porcentajes
     * @return list<float>
     */
    public static function repartir(float $neto, array $porcentajes): array
    {
        $n = count($porcentajes);
        if ($n === 0) {
            return [];
        }

        $out = [];
        $asignado = 0.0;
        foreach ($porcentajes as $i => $pct) {
            if ($i === $n - 1) {
                $out[] = round($neto - $asignado, 2);
                break;
            }
            $parte = round($neto * ((float) $pct) / 100, 2);
            $asignado = round($asignado + $parte, 2);
            $out[] = $parte;
        }

        return $out;
    }
}
