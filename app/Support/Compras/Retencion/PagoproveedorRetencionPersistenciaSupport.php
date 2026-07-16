<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use App\Support\Compras\Retencion\RetencionesPagoResultado;

/**
 * Persiste el resultado del orquestador en pagoproveedor_retencion (fuente SICORE ERP)
 * y asigna nro_certificado vía numeradores Anita (retgan/retiva/retsmov/retibr.fc).
 */
final class PagoproveedorRetencionPersistenciaSupport
{
    /**
     * @return list<Pagoproveedor_Retencion>
     */
    public static function reemplazarDesdeResultado(
        Pagoproveedor $pago,
        RetencionesPagoResultado $resultado,
        int $monedaId,
        float $cotizacion = 1.0,
        bool $asignarCertificadosAnita = true,
    ): array {
        Pagoproveedor_Retencion::query()
            ->where('pagoproveedor_id', $pago->id)
            ->delete();

        $creadas = [];
        $empresaId = (int) $pago->empresa_id;

        // IVA / SUSS: un certificado por pago (Anita RETI_fl_primera / RETS_fl_primera).
        $nroIva = null;
        $nroSuss = null;

        if ($resultado->ganancias->aplica && $resultado->ganancias->importeRetencion > 0) {
            // Ganancias: un número por régimen con importe (cada graba_retmov).
            $nroGan = $asignarCertificadosAnita
                ? PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock(
                    Pagoproveedor_Retencion::TIPO_GANANCIAS,
                    $empresaId
                )
                : null;
            $creadas[] = self::crear($pago, Pagoproveedor_Retencion::TIPO_GANANCIAS, $resultado->ganancias, $monedaId, $cotizacion, [
                'retencionganancia_id' => $resultado->ganancias->detalle['regimen_id'] ?? null,
                'codigo_regimen' => (string) ($resultado->ganancias->detalle['regimen'] ?? ''),
                'codigo_retencion' => (string) ($resultado->ganancias->detalle['codigo'] ?? ''),
                'nro_certificado' => $nroGan !== null ? (string) $nroGan : null,
            ]);
        }

        if ($resultado->iva->aplica && $resultado->iva->importeRetencion > 0) {
            if ($asignarCertificadosAnita) {
                $nroIva = PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock(
                    Pagoproveedor_Retencion::TIPO_IVA,
                    $empresaId
                );
            }
            $creadas[] = self::crear($pago, Pagoproveedor_Retencion::TIPO_IVA, $resultado->iva, $monedaId, $cotizacion, [
                'codigo_regimen' => (string) ($resultado->iva->detalle['regimen'] ?? ''),
                'codigo_retencion' => (string) ($resultado->iva->detalle['codigo'] ?? ''),
                'nro_certificado' => $nroIva !== null ? (string) $nroIva : null,
            ]);
        }

        if ($resultado->suss->aplica && $resultado->suss->importeRetencion > 0) {
            if ($asignarCertificadosAnita) {
                $nroSuss = PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock(
                    Pagoproveedor_Retencion::TIPO_SUSS,
                    $empresaId
                );
            }
            $creadas[] = self::crear($pago, Pagoproveedor_Retencion::TIPO_SUSS, $resultado->suss, $monedaId, $cotizacion, [
                'codigo_regimen' => (string) ($resultado->suss->detalle['regimen'] ?? ''),
                'codigo_retencion' => (string) ($resultado->suss->detalle['codigo'] ?? ''),
                'nro_certificado' => $nroSuss !== null ? (string) $nroSuss : null,
            ]);
        }

        if ($resultado->iibb->aplica && $resultado->iibb->importeRetencion > 0) {
            // IIBB: un número por provincia; el orquestador actual emite un agregado.
            // Si detalle trae 'por_provincia', numera cada una; si no, un certificado.
            $porProvincia = $resultado->iibb->detalle['por_provincia'] ?? null;
            if (is_array($porProvincia) && $porProvincia !== []) {
                $provinciaAnterior = null;
                $nroIibb = null;
                foreach ($porProvincia as $fila) {
                    $importe = (float) ($fila['importe'] ?? $fila['retencion'] ?? 0);
                    if ($importe <= 0) {
                        continue;
                    }
                    $provId = (int) ($fila['provincia_id'] ?? 0);
                    if ($asignarCertificadosAnita && ($nroIibb === null || $provId !== $provinciaAnterior)) {
                        $nroIibb = PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock(
                            Pagoproveedor_Retencion::TIPO_IIBB,
                            $empresaId
                        );
                        $provinciaAnterior = $provId;
                    }
                    $parcial = (object) [
                        'importeRetencion' => $importe,
                        'baseRetenible' => (float) ($fila['base'] ?? $fila['base_calculo'] ?? 0),
                        'alicuotaAplicada' => (float) ($fila['alicuota'] ?? $resultado->iibb->alicuotaAplicada),
                        'motivo' => (string) ($fila['motivo'] ?? $resultado->iibb->motivo),
                        'detalle' => $fila,
                    ];
                    $creadas[] = self::crear($pago, Pagoproveedor_Retencion::TIPO_IIBB, $parcial, $monedaId, $cotizacion, [
                        'provincia_id' => $provId ?: null,
                        'codigo_regimen' => (string) ($fila['jurisdiccion'] ?? ''),
                        'nro_certificado' => $nroIibb !== null ? (string) $nroIibb : null,
                    ]);
                }
            } else {
                $nroIibb = $asignarCertificadosAnita
                    ? PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock(
                        Pagoproveedor_Retencion::TIPO_IIBB,
                        $empresaId
                    )
                    : null;
                $creadas[] = self::crear($pago, Pagoproveedor_Retencion::TIPO_IIBB, $resultado->iibb, $monedaId, $cotizacion, [
                    'provincia_id' => $resultado->iibb->detalle['provincia_id'] ?? null,
                    'codigo_regimen' => (string) ($resultado->iibb->detalle['jurisdiccion'] ?? ''),
                    'nro_certificado' => $nroIibb !== null ? (string) $nroIibb : null,
                ]);
            }
        }

        return $creadas;
    }

    /**
     * @param  object{
     *   importeRetencion: float,
     *   baseCalculo?: float,
     *   baseRetenible?: float,
     *   alicuotaAplicada: float,
     *   motivo: string,
     *   detalle: array<string, mixed>
     * }  $parcial
     * @param  array<string, mixed>  $extra
     */
    private static function crear(
        Pagoproveedor $pago,
        string $tipo,
        object $parcial,
        int $monedaId,
        float $cotizacion,
        array $extra,
    ): Pagoproveedor_Retencion {
        $base = (float) ($parcial->baseRetenible ?? $parcial->baseCalculo ?? 0);

        return Pagoproveedor_Retencion::query()->create(array_merge([
            'pagoproveedor_id' => $pago->id,
            'tiporetencion' => $tipo,
            'base_calculo' => $base,
            'alicuota' => (float) $parcial->alicuotaAplicada,
            'importe' => (float) $parcial->importeRetencion,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'detalle_calculo' => $parcial->detalle,
            'motivo' => $parcial->motivo,
        ], $extra));
    }
}
