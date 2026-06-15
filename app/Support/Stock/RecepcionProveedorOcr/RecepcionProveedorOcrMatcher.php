<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Cruza líneas OCR con la precarga de la OC y devuelve la grilla actualizada.
 */
class RecepcionProveedorOcrMatcher
{
    /**
     * @param  list<array<string, mixed>>  $lineasOc
     * @param  list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float}>  $lineasOcr
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   resumen: array{emparejadas: int, sin_match: int, sin_ocr: int, detalle: list<string>}
     * }
     */
    public function aplicar(array $lineasOc, array $lineasOcr): array
    {
        if ($lineasOc === []) {
            return [
                'lineas' => [],
                'resumen' => [
                    'emparejadas' => 0,
                    'sin_match' => count($lineasOcr),
                    'sin_ocr' => 0,
                    'detalle' => ['La OC no tiene ítems para cruzar con el OCR.'],
                ],
            ];
        }

        $ocrPendientes = $lineasOcr;
        $emparejadas = 0;
        $detalle = [];
        $lineas = $lineasOc;

        foreach ($lineas as $idx => $linea) {
            $matchIdx = $this->buscarMejorMatch($linea, $ocrPendientes);
            if ($matchIdx === null) {
                continue;
            }

            $ocr = $ocrPendientes[$matchIdx];
            unset($ocrPendientes[$matchIdx]);
            $ocrPendientes = array_values($ocrPendientes);

            $cantidad = RecepcionProveedorOcrCantidadSupport::resolver($linea, $ocr);
            $lineas[$idx]['cantidad'] = $cantidad;
            if ((float) ($ocr['precio'] ?? 0) > 0) {
                $lineas[$idx]['precio'] = $ocr['precio'];
            }
            $lineas[$idx]['cantidad_stock'] = round(
                (float) $cantidad * (float) ($linea['coeficienteconversion'] ?? 1),
                6
            );
            if (! empty($ocr['codigo'])) {
                $lineas[$idx]['ocr_codigo_proveedor'] = $ocr['codigo'];
            }
            if (! empty($ocr['descripcion'])) {
                $lineas[$idx]['ocr_descripcion_proveedor'] = $ocr['descripcion'];
            }
            if (! empty($ocr['codigobarra'])) {
                $lineas[$idx]['ocr_codigobarra'] = $ocr['codigobarra'];
            }
            if (! empty($ocr['unidad_compra'])) {
                $lineas[$idx]['ocr_unidad_compra'] = $ocr['unidad_compra'];
            }
            $emparejadas++;
            $detalle[] = 'OC línea '.($idx + 1).' ← OCR '
                .($ocr['codigo'] ?: $ocr['descripcion'])
                .' (cant '.$cantidad.', precio '.($ocr['precio'] ?? 0).')';
        }

        $sinOcr = 0;
        foreach ($lineas as $linea) {
            if (! isset($linea['cantidad']) || (float) $linea['cantidad'] <= 0) {
                $sinOcr++;
            }
        }

        if ($ocrPendientes !== []) {
            foreach ($ocrPendientes as $ocr) {
                $detalle[] = 'OCR sin match en OC: '
                    .($ocr['codigo'] ? $ocr['codigo'].' ' : '')
                    .$ocr['descripcion'];
            }
        }

        return [
            'lineas' => $lineas,
            'resumen' => [
                'emparejadas' => $emparejadas,
                'sin_match' => count($ocrPendientes),
                'sin_ocr' => $sinOcr,
                'detalle' => $detalle,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $lineaOc
     * @param  list<array{codigo: ?string, descripcion: string, cantidad: float, precio: float}>  $ocrPendientes
     */
    private function buscarMejorMatch(array $lineaOc, array $ocrPendientes): ?int
    {
        $skuOc = RecepcionProveedorOcrNumeroSupport::normalizarSku((string) ($lineaOc['sku'] ?? ''));
        $skuAlt = RecepcionProveedorOcrNumeroSupport::normalizarSku((string) ($lineaOc['skualternativo'] ?? ''));
        $descOc = mb_strtoupper(trim((string) ($lineaOc['descripcion'] ?? '')));

        $mejorIdx = null;
        $mejorPuntaje = 0;

        foreach ($ocrPendientes as $idx => $ocr) {
            $puntaje = 0;
            $codigo = RecepcionProveedorOcrNumeroSupport::normalizarSku($ocr['codigo'] ?? '');

            if ($codigo !== '' && ($codigo === $skuOc || $codigo === $skuAlt)) {
                $puntaje = 100;
            } elseif ($codigo !== '' && $skuOc !== '' && (str_contains($codigo, $skuOc) || str_contains($skuOc, $codigo))) {
                $puntaje = 85;
            } elseif ($descOc !== '' && $this->descripcionCoincide($descOc, $ocr['descripcion'])) {
                $puntaje = 70;
            } elseif ($descOc !== '' && $this->textoSimilar($descOc, $ocr['descripcion'])) {
                $puntaje = 55;
            }

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejorIdx = $idx;
            }
        }

        return $mejorPuntaje >= 55 ? $mejorIdx : null;
    }

    private function descripcionCoincide(string $descOc, string $descOcr): bool
    {
        $a = mb_strtoupper(trim($descOc));
        $b = mb_strtoupper(trim($descOcr));
        if ($a === '' || $b === '') {
            return false;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    private function textoSimilar(string $descOc, string $descOcr): bool
    {
        $tokensOc = $this->tokensSignificativos($descOc);
        $tokensOcr = $this->tokensSignificativos($descOcr);
        if ($tokensOc === [] || $tokensOcr === []) {
            return false;
        }

        $coincidencias = 0;
        foreach ($tokensOcr as $token) {
            foreach ($tokensOc as $base) {
                if ($this->tokenSimilar($token, $base)) {
                    $coincidencias++;
                    break;
                }
            }
        }

        return $coincidencias >= min(2, count($tokensOcr));
    }

    private function tokenSimilar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (mb_strlen($a) < 4 || mb_strlen($b) < 4) {
            return false;
        }

        $prefA = mb_substr($a, 0, 3);
        $prefB = mb_substr($b, 0, 3);
        $sufA = mb_substr($a, -4);
        $sufB = mb_substr($b, -4);

        return $prefA === $prefB && $sufA === $sufB;
    }

    /** @return list<string> */
    private function tokensSignificativos(string $texto): array
    {
        $texto = mb_strtoupper($texto);
        $partes = preg_split('/[^A-Z0-9ÁÉÍÓÚÑ]+/u', $texto) ?: [];

        return array_values(array_filter($partes, static fn (string $t): bool => mb_strlen($t) >= 4));
    }
}
