<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Agrupa líneas del mayor por concepto para pantalla/export (asiento + cuenta + D/H + moneda).
 *
 * No altera {@see MayorConceptoPeriodoProcesador} ni {@code secciones}: la auditoría sigue
 * usando el detalle completo con disp por cuenta de disponibilidad.
 */
class MayorConceptoLineasVisualConsolidacionSupport
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public function consolidarLineasDetalle(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        /** @var array<string, array{base: array<string, mixed>, debe: float, haber: float, count: int}> $grupos */
        $grupos = [];

        foreach ($lineas as $linea) {
            $clave = $this->claveConsolidacion($linea);
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'base' => $linea,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'count' => 0,
                ];
            }

            $grupos[$clave]['debe'] += (float) ($linea['debe'] ?? 0);
            $grupos[$clave]['haber'] += (float) ($linea['haber'] ?? 0);
            $grupos[$clave]['count']++;
        }

        $consolidadas = [];
        foreach ($grupos as $grupo) {
            $consolidadas[] = $this->armarLineaConsolidada($grupo);
        }

        usort(
            $consolidadas,
            static function (array $a, array $b): int {
                $fechaA = (int) ($a['fecha'] ?? 0);
                $fechaB = (int) ($b['fecha'] ?? 0);
                if ($fechaA !== $fechaB) {
                    return $fechaA <=> $fechaB;
                }

                $nroA = (int) ($a['nro_asiento'] ?? 0);
                $nroB = (int) ($b['nro_asiento'] ?? 0);
                if ($nroA !== $nroB) {
                    return $nroA <=> $nroB;
                }

                return strcmp((string) ($a['comprobante'] ?? ''), (string) ($b['comprobante'] ?? ''));
            },
        );

        return $consolidadas;
    }

    /**
     * @param  array{base: array<string, mixed>, debe: float, haber: float, count: int}  $grupo
     * @return array<string, mixed>
     */
    private function armarLineaConsolidada(array $grupo): array
    {
        $linea = $grupo['base'];
        $linea['debe'] = round($grupo['debe'], 2);
        $linea['haber'] = round($grupo['haber'], 2);
        $linea['lineas_consolidadas'] = $grupo['count'];

        if ($grupo['count'] > 1) {
            $linea['cuenta_disponibilidad'] = 0;
            $linea['cuenta_disponibilidad_codigo'] = '';
            $linea['disp_debe'] = 0.0;
            $linea['disp_haber'] = 0.0;
            $linea['consolidada_visual'] = true;
        }

        return $linea;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function claveConsolidacion(array $linea): string
    {
        $debe = round((float) ($linea['debe'] ?? 0), 2);
        $haber = round((float) ($linea['haber'] ?? 0), 2);
        $lado = match (true) {
            $debe > 0 && $haber > 0 => 'DH',
            $debe > 0 => 'D',
            $haber > 0 => 'H',
            default => '0',
        };

        return implode("\0", [
            (int) ($linea['concepto_id'] ?? 0),
            (int) ($linea['nro_asiento'] ?? 0),
            (int) ($linea['cuenta'] ?? 0),
            $lado,
            strtoupper(trim((string) ($linea['moneda_abrev'] ?? ''))),
        ]);
    }
}
