<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;

/**
 * Extrae cabecera de factura de proveedor desde texto OCR.
 */
final class FacturaProveedorCabeceraHeuristicaSupport
{
    public function __construct(
        private FacturaProveedorImporteParserSupport $importeParser,
        private RecepcionProveedorOcrNumeroOcExtractor $numeroOcExtractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extraer(string $texto): array
    {
        $textoNorm = $this->normalizar($texto);
        $cuits = $this->extraerCuits($textoNorm);
        $comprobante = $this->extraerComprobante($textoNorm);
        $monedaCotiz = $this->extraerMonedaYCotizacion($textoNorm);
        $oc = $this->numeroOcExtractor->extraer($textoNorm);

        return [
            'cuit_destinatario' => $cuits['destinatario'],
            'cuit_proveedor' => $cuits['proveedor'],
            'numero_oc' => $oc['numero'] ? str_pad((string) $oc['numero'], 6, '0', STR_PAD_LEFT) : null,
            'numero_oc_origen' => $oc['origen'],
            'tipo_comprobante' => $this->inferirTipoComprobante($textoNorm),
            'letra' => $comprobante['letra'],
            'sucursal' => $comprobante['sucursal'],
            'numero_factura' => $comprobante['numero'],
            'fecha_factura' => $this->extraerFechaFactura($textoNorm),
            'numerocae' => $this->extraerCae($textoNorm),
            'fecha_vto_cai_cae' => $this->extraerFechaVtoCae($textoNorm),
            'subtotal' => $this->extraerSubtotal($textoNorm),
            'total' => $this->extraerTotal($textoNorm),
            'moneda' => $monedaCotiz['moneda'],
            'cotizacion' => $monedaCotiz['cotizacion'],
        ];
    }

    /** @return array{destinatario: ?string, proveedor: ?string} */
    private function extraerCuits(string $texto): array
    {
        $encontrados = [];
        if (preg_match_all('/\b(\d{2}[-\s]?\d{8}[-\s]?\d)\b/u', $texto, $matches)) {
            foreach ($matches[1] as $raw) {
                $fmt = $this->formatearCuit($raw);
                if ($fmt && ! in_array($fmt, $encontrados, true)) {
                    $encontrados[] = $fmt;
                }
            }
        }

        $destinatario = null;
        $proveedor = null;

        if (preg_match('/(?:señor(?:es)?|cliente|destinatario|raz[oó]n\s+social\s+del\s+comprador)[^\n]{0,120}?\b(\d{2}-\d{8}-\d)\b/iu', $texto, $m)) {
            $destinatario = $m[1];
        }
        if (preg_match('/(?:proveedor|vendedor|emisor)[^\n]{0,120}?\b(\d{2}-\d{8}-\d)\b/iu', $texto, $m)) {
            $proveedor = $m[1];
        }

        if ($destinatario === null && count($encontrados) >= 2) {
            $destinatario = $encontrados[1];
        }
        if ($proveedor === null && count($encontrados) >= 1) {
            $proveedor = $encontrados[0];
        }

        return ['destinatario' => $destinatario, 'proveedor' => $proveedor];
    }

    private function formatearCuit(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) !== 11) {
            return null;
        }

        return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1);
    }

    /** @return array{letra: ?string, sucursal: ?int, numero: ?int} */
    private function extraerComprobante(string $texto): array
    {
        $letra = null;
        if (preg_match('/\bFACTURA\s+([ABC])\b/iu', $texto, $m)) {
            $letra = strtoupper($m[1]);
        } elseif (preg_match('/\bCOD\.?\s*0?1\b/iu', $texto)) {
            $letra = 'A';
        } elseif (preg_match('/\bCOD\.?\s*0?6\b/iu', $texto)) {
            $letra = 'B';
        } elseif (preg_match('/\bCOD\.?\s*0?11\b/iu', $texto)) {
            $letra = 'C';
        }

        $sucursal = null;
        $numero = null;

        if (preg_match('/\b(\d{1,5})\s*[-–]\s*(\d{4,8})\b/u', $texto, $m)) {
            $sucursal = (int) ltrim($m[1], '0');
            $numero = (int) ltrim($m[2], '0');
        }

        if ($sucursal === null && preg_match('/punto\s+de\s+venta[:\s]*(\d+)/iu', $texto, $m)) {
            $sucursal = (int) ltrim($m[1], '0');
        }
        if ($numero === null && preg_match('/(?:comp(?:robante)?\.?\s*nro|n[°ºo]\s*comprobante)[:\s]*(\d+)/iu', $texto, $m)) {
            $numero = (int) ltrim($m[1], '0');
        }

        return [
            'letra' => $letra ?? 'A',
            'sucursal' => $sucursal,
            'numero' => $numero,
        ];
    }

    private function inferirTipoComprobante(string $texto): string
    {
        // Código de comprobante AFIP en cabecera (COD. 003, Código 008, etc.)
        if (preg_match('/(?:cod(?:igo|\.)?|c[oó]digo)\s*(?:de\s+)?(?:comp(?:robante)?\.?)?\s*[:#]?\s*0*(\d{1,3})\b/iu', $texto, $m)) {
            $codigo = (int) $m[1];
            if (in_array($codigo, [2, 7, 12, 52, 54], true)) {
                return 'ND';
            }
            if (in_array($codigo, [3, 8, 13, 53, 55], true)) {
                return 'NC';
            }
            if (in_array($codigo, [1, 6, 11, 51], true)) {
                return 'FC';
            }
        }

        if (preg_match('/\bNOTA\s+DE\s+(?:D[EÉ]BITO|DEBITO)\b|\bN\.?\s*D[EÉ]BITO\b|\bN\/D\b/iu', $texto)) {
            return 'ND';
        }
        if (preg_match('/\bNOTA\s+DE\s+CR[EÉ]DITO\b|\bN\.?\s*CR[EÉ]DITO\b|\bN\/C\b/iu', $texto)) {
            return 'NC';
        }
        // Etiqueta corta en título (evitar confundir con "NC" de otras palabras)
        if (preg_match('/^\s*N\.?\s*D\.?\s*$/imu', $texto) || preg_match('/\bCOMPROBANTE\s*:\s*ND\b/iu', $texto)) {
            return 'ND';
        }
        if (preg_match('/^\s*N\.?\s*C\.?\s*$/imu', $texto) || preg_match('/\bCOMPROBANTE\s*:\s*NC\b/iu', $texto)) {
            return 'NC';
        }

        return 'FC';
    }

    private function extraerFechaFactura(string $texto): ?string
    {
        if (preg_match('/fecha\s+(?:de\s+)?(?:emisi[oó]n|factura|comprobante)[:\s]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/iu', $texto, $m)) {
            return $this->isoFecha($m[1], $m[2], $m[3]);
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})\b/u', $texto, $m)) {
            return $this->isoFecha($m[1], $m[2], $m[3]);
        }

        return null;
    }

    private function isoFecha(string $d, string $m, string $y): string
    {
        $year = (int) $y;
        if ($year < 100) {
            $year += 2000;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $m, (int) $d);
    }

    private function extraerCae(string $texto): ?string
    {
        // C.A.E.: / C-A-E-i / CAE N° — OCR suele separar las letras.
        $patrones = [
            '/C[\.\-\s]*A[\.\-\s]*E[\.\-\s]*(?:N[°ºo]\.?)?[:\s]*(\d{10,20})/iu',
            '/\bCAI\s*(?:N[°ºo]\.?)?[:\s]*(\d{10,20})/iu',
        ];
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function extraerFechaVtoCae(string $texto): ?string
    {
        if (preg_match('/vto\.?\s*(?:C[\.\-\s]*A[\.\-\s]*E|CAI)\.?\s*:?\s*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/iu', $texto, $m)) {
            return $this->isoFecha($m[1], $m[2], $m[3]);
        }

        return null;
    }

    private function extraerSubtotal(string $texto): ?float
    {
        if (preg_match(
            '/SUB\s*TOTAL[^\n]{0,160}TOTAL\s*[\r\n]+\s*(-?[\d.,]+)/iu',
            $texto,
            $m
        )) {
            $v = $this->importeParser->parsear($m[1]);
            if ($v !== null) {
                return $v;
            }
        }

        $patrones = [
            '/sub\s*total[^\S\n]*(-?\d[\d.,]+)/iu',
            '/importe\s+neto\s+gravado[^\S\n]*(-?\d[\d.,]+)/iu',
            '/neto\s+gravado[^\S\n]*(-?\d[\d.,]+)/iu',
        ];
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $m)) {
                $v = $this->importeParser->parsear($m[1]);
                if ($v !== null) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function extraerTotal(string $texto): ?float
    {
        // Preferir "TOTAL $ 1.571.612,21" explícito (no Subtotal/Neto).
        $filas = preg_split('/\R/u', $texto) ?: [];
        $mejor = null;
        foreach ($filas as $fila) {
            if (preg_match('/sub\s*total|\bneto\b|precio|unitario|bultos/iu', $fila)) {
                continue;
            }
            if (! preg_match('/\btotal(?:es)?\b/iu', $fila)) {
                continue;
            }
            if (preg_match_all('/(?:\$|S\s)?\s*(-?\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})|-?\d+[.,]\d{2})/u', $fila, $m)) {
                foreach ($m[1] as $raw) {
                    $v = $this->importeParser->parsear($raw);
                    if ($v !== null && $v >= 50 && ($mejor === null || $v > $mejor)) {
                        $mejor = $v;
                    }
                }
            }
        }
        if ($mejor !== null) {
            return $mejor;
        }

        // Pie en tabla (rótulos arriba / importes abajo): el TOTAL es el ÚLTIMO importe.
        if (preg_match(
            '/SUB\s*TOTAL[^\n]{0,160}TOTAL\s*[\r\n]+\s*((?:-?[\d.,]+)(?:\s+-?[\d.,]+){2,})/iu',
            $texto,
            $m
        )) {
            $parts = preg_split('/\s+/', trim($m[1])) ?: [];
            $ultimo = end($parts);
            if (is_string($ultimo)) {
                $v = $this->importeParser->parsear($ultimo);
                if ($v !== null && $v > 0) {
                    return $v;
                }
            }
        }

        return null;
    }

    /** @return array{moneda: string, cotizacion: float} */
    private function extraerMonedaYCotizacion(string $texto): array
    {
        $moneda = 'PESOS';
        if (preg_match('/\b(?:U\$S|USD|DOLAR(?:ES)?|DÓLAR(?:ES)?)\b/iu', $texto)) {
            $moneda = 'DOLARES';
        } elseif (preg_match('/\b(?:EUR|EURO(?:S)?)\b/iu', $texto)) {
            $moneda = 'EUROS';
        }

        $cotizacion = 1.0;
        if (preg_match('/(?:tipo\s+de\s+cambio|cotiz(?:aci[oó]n)?|t\.?\s*c\.?)[:\s]*(\d[\d.,]+)/iu', $texto, $m)) {
            $parsed = $this->importeParser->parsear($m[1]);
            if ($parsed !== null && $parsed > 0) {
                $cotizacion = $parsed;
            }
        }

        return ['moneda' => $moneda, 'cotizacion' => $cotizacion];
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = str_replace(['—', '–', '−'], '-', $texto);

        return $texto;
    }
}
