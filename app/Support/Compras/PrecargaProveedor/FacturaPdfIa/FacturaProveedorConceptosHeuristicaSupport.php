<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Detecta líneas de conceptos IVA / percepciones / netos en facturas AR (sin ítems de artículos).
 */
final class FacturaProveedorConceptosHeuristicaSupport
{
    public function __construct(
        private FacturaProveedorImporteParserSupport $importeParser,
    ) {}

    /**
     * @return list<array{descripcion: string, importe: float, alicuota_iva: ?float, tipo: string}>
     */
    public function extraer(string $texto, ?float $totalFactura = null): array
    {
        $lineas = [];
        $textoNorm = str_replace(["\r\n", "\r"], "\n", $texto);
        $filas = preg_split('/\n/u', $textoNorm) ?: [];

        foreach ($filas as $fila) {
            $fila = trim($fila);
            if ($fila === '' || mb_strlen($fila) < 4) {
                continue;
            }

            $detectada = $this->detectarEnLinea($fila);
            if ($detectada !== null) {
                $lineas[] = $detectada;
            }
        }

        $lineas = $this->fusionarDuplicados($lineas);
        $lineas = $this->completarDesdeBloquesMultilinea($textoNorm, $lineas);

        if ($lineas === [] && $totalFactura !== null && $totalFactura > 0) {
            $lineas[] = [
                'descripcion' => 'Total factura (sin desglose detectado)',
                'importe' => $totalFactura,
                'alicuota_iva' => null,
                'tipo' => 'neto',
            ];
        }

        return $lineas;
    }

    /**
     * @return ?array{descripcion: string, importe: float, alicuota_iva: ?float, tipo: string}
     */
    private function detectarEnLinea(string $fila): ?array
    {
        $filaLower = mb_strtolower($fila);

        if ($this->esRuido($filaLower)) {
            return null;
        }

        $importe = $this->importeParser->importeAlFinalDeLinea($fila);
        if ($importe === null || abs($importe) < 0.01) {
            return null;
        }

        $definiciones = $this->definicionesConcepto();

        foreach ($definiciones as $def) {
            if (preg_match($def['patron'], $filaLower)) {
                $alicuota = $def['alicuota'] ?? $this->inferirAlicuotaEnTexto($fila);

                return [
                    'descripcion' => $this->limpiarDescripcion($fila),
                    'importe' => abs($importe),
                    'alicuota_iva' => $alicuota,
                    'tipo' => $def['tipo'],
                ];
            }
        }

        return null;
    }

    /** @return list<array{patron: string, tipo: string, alicuota: ?float}> */
    private function definicionesConcepto(): array
    {
        $ali = '(?:21|10[,.]5|27|5|2[,.]5|0)';

        return [
            ['patron' => '/\biva\s+(?:inscripto\s+)?'.$ali.'\s*%/u', 'tipo' => 'iva', 'alicuota' => null],
            ['patron' => '/\bi\.?\s*v\.?\s*a\.?\s*(?:\s*'.$ali.')?\s*%/u', 'tipo' => 'iva', 'alicuota' => null],
            ['patron' => '/\bi\.?\s*v\.?\s*a\.?\s*inscripto/u', 'tipo' => 'iva', 'alicuota' => null],
            ['patron' => '/\biva\s+inscripto/u', 'tipo' => 'iva', 'alicuota' => null],
            ['patron' => '/\biva\s+discriminado/u', 'tipo' => 'iva', 'alicuota' => null],
            ['patron' => '/\bneto\s+gravado\s+'.$ali.'/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\bimporte\s+neto\s+gravado/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\bgravado\s+'.$ali.'\s*%/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\bneto\s+'.$ali.'\s*%/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\boperaciones\s+exentas/u', 'tipo' => 'exento', 'alicuota' => 0.0],
            ['patron' => '/\bimporte\s+exento/u', 'tipo' => 'exento', 'alicuota' => 0.0],
            ['patron' => '/\bno\s+gravado/u', 'tipo' => 'no_gravado', 'alicuota' => 0.0],
            // RG 5329 / Perc. IVA 3% (nombre_ia del concepto 103).
            ['patron' => '/\brg\s*5329\b/u', 'tipo' => 'percepcion_iva', 'alicuota' => 3.0],
            ['patron' => '/\bpercep(?:ci[oó]n)?\s+(?:iva|i\.v\.a)/u', 'tipo' => 'percepcion_iva', 'alicuota' => null],
            ['patron' => '/\bpercep(?:\.|\s)*(?:ii\.?\s*bb\.?|iibb|i\.i\.b\.b|ingresos\s+brutos)/u', 'tipo' => 'percepcion_iibb', 'alicuota' => null],
            ['patron' => '/\bpercep(?:ci[oó]n)?\s+(?:ganancias|gan\.)/u', 'tipo' => 'percepcion_ganancias', 'alicuota' => null],
            ['patron' => '/\bimpuestos?\s+internos/u', 'tipo' => 'interno', 'alicuota' => null],
            ['patron' => '/\bimp\.?\s*internos/u', 'tipo' => 'interno', 'alicuota' => null],
            ['patron' => '/\bsub\s*total\b/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\bimporte\s+neto\b/u', 'tipo' => 'neto', 'alicuota' => null],
            ['patron' => '/\botros?\s+tributos/u', 'tipo' => 'otro_tributo', 'alicuota' => null],
            ['patron' => '/\bretenc(?:i[oó]n)?\s+iva/u', 'tipo' => 'retencion_iva', 'alicuota' => null],
            ['patron' => '/\bretenc(?:i[oó]n)?\s+iibb/u', 'tipo' => 'retencion_iibb', 'alicuota' => null],
        ];
    }

    private function esRuido(string $filaLower): bool
    {
        if (preg_match('/\b(?:cantidad|codigo|c[oó]digo|sku|art[ií]culo|descripci[oó]n|precio\s+unit)\b/u', $filaLower)) {
            return true;
        }
        if (preg_match('/\btotal\s+(?:general|a\s+pagar)\b/u', $filaLower) && ! preg_match('/\biva\b/u', $filaLower)) {
            return true;
        }

        return false;
    }

    /**
     * Extrae alícuota explícita del texto de la línea.
     */
    private function inferirAlicuotaEnTexto(string $texto): ?float
    {
        if (preg_match('/\b(21|10[,.]5|27|5|2[,.]5|0)\s*%/u', mb_strtolower($texto), $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function limpiarDescripcion(string $fila): string
    {
        $desc = preg_replace('/\s+-?\d[\d.,\s]*$/u', '', $fila) ?? $fila;
        $desc = preg_replace('/\s+/', ' ', trim($desc)) ?? trim($desc);

        return mb_substr($desc, 0, 120);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function fusionarDuplicados(array $lineas): array
    {
        $mapa = [];
        foreach ($lineas as $linea) {
            $alic = $linea['alicuota_iva'] ?? $this->inferirAlicuotaEnTexto($linea['descripcion']);
            if ($alic !== null) {
                $linea['alicuota_iva'] = $alic;
            }

            $clave = ($linea['tipo'] ?? '').'|'.($linea['alicuota_iva'] ?? 'x').'|'.mb_strtolower($linea['descripcion']);
            if (! isset($mapa[$clave])) {
                $mapa[$clave] = $linea;
            } else {
                $mapa[$clave]['importe'] = round((float) $mapa[$clave]['importe'] + (float) $linea['importe'], 2);
            }
        }

        return array_values($mapa);
    }

    /**
     * Bloques AFIP típicos: "IVA 21% 1.234,56" en línea compacta del pie.
     *
     * @param  list<array<string, mixed>>  $existentes
     * @return list<array<string, mixed>>
     */
    private function completarDesdeBloquesMultilinea(string $texto, array $existentes): array
    {
        $tiposYa = array_column($existentes, 'tipo');
        $agregar = [];

        $bloques = [
            ['regex' => '/i\.?\s*v\.?\s*a\.?\s*inscripto[^0-9\n]{0,20}([\d.,]+)/iu', 'tipo' => 'iva', 'alicuota' => null, 'desc' => 'I.V.A. INSCRIPTO'],
            ['regex' => '/iva\s+(?:inscripto\s+)?21\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'iva', 'alicuota' => 21.0, 'desc' => 'IVA 21%'],
            ['regex' => '/iva\s+(?:inscripto\s+)?10[,.]5\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'iva', 'alicuota' => 10.5, 'desc' => 'IVA 10,5%'],
            ['regex' => '/iva\s+(?:inscripto\s+)?27\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'iva', 'alicuota' => 27.0, 'desc' => 'IVA 27%'],
            ['regex' => '/sub\s*total[^0-9\n]{0,20}([\d.,]+)/iu', 'tipo' => 'neto', 'alicuota' => null, 'desc' => 'SUBTOTAL'],
            ['regex' => '/neto\s+gravado\s+21\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'neto', 'alicuota' => 21.0, 'desc' => 'Neto gravado 21%'],
            ['regex' => '/neto\s+gravado\s+10[,.]5\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'neto', 'alicuota' => 10.5, 'desc' => 'Neto gravado 10,5%'],
            ['regex' => '/neto\s+gravado\s+27\s*%?\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'neto', 'alicuota' => 27.0, 'desc' => 'Neto gravado 27%'],
            ['regex' => '/operaciones\s+exentas\s*[:\s]*([\d.,]+)/iu', 'tipo' => 'exento', 'alicuota' => 0.0, 'desc' => 'Operaciones exentas'],
            ['regex' => '/rg\s*5329[^0-9\n]{0,80}?((?:\d{1,3}(?:[.,]\d{3})+|\d+)[.,]\d{2})/iu', 'tipo' => 'percepcion_iva', 'alicuota' => 3.0, 'desc' => 'RG 5329'],
            ['regex' => '/percep(?:\.|\s)*(?:ii\.?\s*bb\.?|iibb)[^0-9\n]{0,30}((?:\d{1,3}(?:[.,]\d{3})+|\d+)[.,]\d{2}|\$?\s*\d[\d.,]+)/iu', 'tipo' => 'percepcion_iibb', 'alicuota' => null, 'desc' => 'PERCEP II.BB.'],
            ['regex' => '/percep(?:ci[oó]n)?\s+iva[^0-9\n]{0,30}((?:\d{1,3}(?:[.,]\d{3})+|\d+)[.,]\d{2}|\$?\s*\d[\d.,]+)/iu', 'tipo' => 'percepcion_iva', 'alicuota' => null, 'desc' => 'Percepción IVA'],
            ['regex' => '/impuestos?\s+internos[^0-9\n]{0,20}((?:\d{1,3}(?:[.,]\d{3})+|\d+)[.,]\d{2}|\$?\s*\d[\d.,]+)/iu', 'tipo' => 'interno', 'alicuota' => null, 'desc' => 'IMPUESTOS INTERNOS'],
        ];

        foreach ($bloques as $bloque) {
            if (preg_match($bloque['regex'], $texto, $m)) {
                $importe = $this->importeParser->parsear($m[1]);
                if ($importe === null || $importe <= 0) {
                    continue;
                }
                $claveTipo = $bloque['tipo'].($bloque['alicuota'] ?? '');
                if (in_array($bloque['tipo'], $tiposYa, true)) {
                    continue;
                }
                $agregar[] = [
                    'descripcion' => $bloque['desc'],
                    'importe' => $importe,
                    'alicuota_iva' => $bloque['alicuota'],
                    'tipo' => $bloque['tipo'],
                ];
            }
        }

        return array_merge($existentes, $agregar);
    }
}
