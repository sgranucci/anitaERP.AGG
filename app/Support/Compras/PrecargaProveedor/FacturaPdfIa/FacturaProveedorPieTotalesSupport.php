<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Extrae el bloque de totales del pie AFIP.
 *
 * Estrategias (en orden):
 * 1) Tabla horizontal: rótulos en una fila, importes en la siguiente (YAFEMA).
 * 2) Pares rótulo→valor: detecta etiquetas (Neto, IVA, Perc., TOTAL) y asigna
 *    el importe de la misma línea o de las siguientes (Los Cinco Hispanos).
 * 3) Vertical apilado: todos los rótulos y después todos los importes.
 *
 * Nunca usa el número de una RG (5329, etc.) ni una alícuota (3%, 21%) como importe.
 */
final class FacturaProveedorPieTotalesSupport
{
    /** Números de resoluciones frecuentes: no son montos. */
    private const NUMEROS_RG = [5329, 3337, 2408, 2126];

    public function __construct(
        private FacturaProveedorImporteParserSupport $importeParser,
    ) {}

    /**
     * @return array{
     *   subtotal: ?float,
     *   iva: ?float,
     *   impuestos_internos: ?float,
     *   percepcion_iva: ?float,
     *   percepcion_iibb: ?float,
     *   total: ?float,
     *   lineas: list<array{descripcion: string, importe: float, alicuota_iva: ?float, tipo: string}>,
     *   origen: ?string
     * }
     */
    public function extraer(string $texto): array
    {
        $vacio = [
            'subtotal' => null,
            'iva' => null,
            'impuestos_internos' => null,
            'percepcion_iva' => null,
            'percepcion_iibb' => null,
            'total' => null,
            'lineas' => [],
            'origen' => null,
        ];

        foreach ([
            'pie_horizontal' => fn () => $this->extraerHorizontal($texto),
            'pie_pares' => fn () => $this->extraerPorPares($texto),
            'pie_vertical' => fn () => $this->extraerVertical($texto),
        ] as $origen => $resolver) {
            $vals = $resolver();
            if ($vals === null) {
                continue;
            }
            $vals = $this->completarTotalSiFalta($vals);
            if (! $this->esCoherente($vals)) {
                continue;
            }

            return $this->conLineas($vals, $origen);
        }

        return $vacio;
    }

    /**
     * Rótulo en una línea + importe en la misma o en las siguientes.
     * Sirve cuando el pie está en columna derecha mezclado con texto legal.
     *
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }|null
     */
    private function extraerPorPares(string $texto): ?array
    {
        $filas = preg_split('/\R/u', $texto) ?: [];
        /** @var array<string, array{importe: float, score: int}> $mejores */
        $mejores = [];

        foreach ($filas as $i => $fila) {
            $clave = $this->detectarClaveEnTexto($fila);
            if ($clave === null) {
                continue;
            }

            $candidato = $this->mejorImporteEnContexto($filas, (int) $i, $clave);
            if ($candidato === null) {
                continue;
            }

            if (! isset($mejores[$clave])) {
                $mejores[$clave] = $candidato;
                continue;
            }

            // TOTAL / Neto: el mayor monto gana (columna TOTAL de ítems vs pie).
            if (in_array($clave, ['total', 'subtotal'], true)) {
                if ($candidato['importe'] > $mejores[$clave]['importe']
                    || ($candidato['importe'] === $mejores[$clave]['importe']
                        && $candidato['score'] > $mejores[$clave]['score'])) {
                    $mejores[$clave] = $candidato;
                }
                continue;
            }

            // IVA / percepciones: score manda (evitar 536.267 OCR pegado vs 36.267).
            if ($candidato['score'] > $mejores[$clave]['score']
                || ($candidato['score'] === $mejores[$clave]['score']
                    && $this->preferirImporteImpuesto($clave, $candidato['importe'], $mejores[$clave]['importe']))) {
                $mejores[$clave] = $candidato;
            }
        }

        if (! isset($mejores['total']) && ! isset($mejores['subtotal'])) {
            return null;
        }

        $out = [
            'subtotal' => $mejores['subtotal']['importe'] ?? 0.0,
            'iva' => $mejores['iva']['importe'] ?? 0.0,
            'impuestos_internos' => $mejores['impuestos_internos']['importe'] ?? 0.0,
            'percepcion_iva' => $mejores['percepcion_iva']['importe'] ?? 0.0,
            'percepcion_iibb' => $mejores['percepcion_iibb']['importe'] ?? 0.0,
            'total' => $mejores['total']['importe'] ?? 0.0,
        ];

        // Si una percepción quedó ~neto o ~IVA (OCR pegó dígitos), buscar alternativa coherente.
        $out = $this->corregirImpuestosVsBase($out, $filas);

        // Coherencia final: si internos == perc IVA, internos es ruido OCR.
        if ($out['impuestos_internos'] > 0
            && $out['percepcion_iva'] > 0
            && abs($out['impuestos_internos'] - $out['percepcion_iva']) < 0.05) {
            $out['impuestos_internos'] = 0.0;
        }

        // Si suma de componentes supera el TOTAL por un renglón duplicado, anular internos.
        if ($out['total'] > 0) {
            $suma = $out['subtotal'] + $out['iva'] + $out['impuestos_internos']
                + $out['percepcion_iva'] + $out['percepcion_iibb'];
            if ($suma > $out['total'] + 1.0 && $out['impuestos_internos'] > 0) {
                $sumaSin = $suma - $out['impuestos_internos'];
                if (abs($sumaSin - $out['total']) <= max(1.0, $out['total'] * 0.02)) {
                    $out['impuestos_internos'] = 0.0;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $filas
     * @return array{importe: float, score: int}|null
     */
    private function mejorImporteEnContexto(array $filas, int $indice, string $clave): ?array
    {
        $candidatos = [];

        for ($offset = 0; $offset <= 3; $offset++) {
            $idx = $indice + $offset;
            if (! isset($filas[$idx])) {
                break;
            }
            $lineaOriginal = $filas[$idx];

            // No cruzar a otro rótulo de pie (Imp.Int → Per.IVA).
            if ($offset > 0) {
                $otra = $this->detectarClaveEnTexto($lineaOriginal);
                if ($otra !== null && $otra !== $clave) {
                    break;
                }
            }

            $linea = $offset === 0 ? $this->textoTrasRotulo($lineaOriginal, $clave) : $lineaOriginal;

            foreach ($this->importesDeLinea($linea) as $importe) {
                if (! $this->esImportePlausible($importe, $clave)) {
                    continue;
                }
                $score = 10 - $offset;
                if (str_contains($lineaOriginal, '$') || preg_match('/\bS\s+\d/u', $lineaOriginal)) {
                    $score += 8;
                }
                // Preferir montos "de factura" (miles), no alícuotas.
                if ($importe >= 100) {
                    $score += 3;
                }
                if ($clave === 'total' && $importe >= 1000) {
                    $score += 5;
                }
                // RG 5329: el número de resolución ya se filtró; preferir montos con decimales típicos.
                if (in_array($clave, ['percepcion_iva', 'percepcion_iibb'], true) && $importe >= 100 && $importe < 500000) {
                    $score += 2;
                }
                // Fragmentos de neto (108932 / 208932) cerca de RG no son percepción.
                if ($clave === 'percepcion_iva' && preg_match('/^208?932|^108932/u', (string) (int) $importe)) {
                    $score -= 20;
                }
                $candidatos[] = ['importe' => $importe, 'score' => $score];
            }
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, function (array $a, array $b) use ($clave): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if (in_array($clave, ['total', 'subtotal'], true)) {
                return $b['importe'] <=> $a['importe'];
            }

            // Empate: preferir el menor en impuestos (OCR suele pegar dígitos de más).
            return $a['importe'] <=> $b['importe'];
        });

        return $candidatos[0];
    }

    private function preferirImporteImpuesto(string $clave, float $nuevo, float $actual): bool
    {
        // Ante mismo score, el menor suele ser el correcto (36.267 vs 536.267).
        return $nuevo < $actual;
    }

    /**
     * @param  array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }  $out
     * @param  list<string>  $filas
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }
     */
    private function corregirImpuestosVsBase(array $out, array $filas): array
    {
        $base = $out['subtotal'] > 0 ? $out['subtotal'] : null;
        if ($base === null && $out['total'] > 0) {
            $base = $out['total'] / 1.3; // aproximación gruesa solo para rangos
        }
        if ($base === null || $base <= 0) {
            return $out;
        }

        // IVA debe acercarse a 10.5% / 21% / 27% del neto (no fragmentos del neto).
        if ($out['iva'] > 0) {
            $ratio = $out['iva'] / $base;
            $cercaTasa = false;
            foreach ([0.105, 0.21, 0.27] as $tasa) {
                if (abs($ratio - $tasa) <= 0.025) {
                    $cercaTasa = true;
                    break;
                }
            }
            if (! $cercaTasa) {
                $alt = $this->buscarImporteCercanoA($filas, $base * 0.21, 'iva');
                if ($alt !== null) {
                    $out['iva'] = $alt;
                }
            }
        }

        // Perc. IVA típica 1–5% del neto.
        if ($out['percepcion_iva'] > 0) {
            $ratio = $out['percepcion_iva'] / $base;
            if ($ratio < 0.002 || $ratio > 0.12 || abs($out['percepcion_iva'] - $base) < 1) {
                $alt = $this->buscarImporteCercanoA($filas, $base * 0.03, 'percepcion_iva');
                if ($alt !== null) {
                    $out['percepcion_iva'] = $alt;
                }
            }
        }

        // Perc. IIBB típica 1–8% del neto.
        if ($out['percepcion_iibb'] > 0) {
            $ratio = $out['percepcion_iibb'] / $base;
            if ($ratio < 0.002 || $ratio > 0.15 || abs($out['percepcion_iibb'] - $base) < 1) {
                $alt = $this->buscarImporteCercanoA($filas, $base * 0.06, 'percepcion_iibb');
                if ($alt !== null) {
                    $out['percepcion_iibb'] = $alt;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $filas
     */
    private function buscarImporteCercanoA(array $filas, float $objetivo, string $clave): ?float
    {
        $mejor = null;
        $mejorDist = PHP_FLOAT_MAX;
        foreach ($filas as $fila) {
            foreach ($this->importesDeLinea($fila) as $importe) {
                if (! $this->esImportePlausible($importe, $clave)) {
                    continue;
                }
                $dist = abs($importe - $objetivo);
                $tol = max(50.0, $objetivo * 0.35);
                if ($dist <= $tol && $dist < $mejorDist) {
                    $mejorDist = $dist;
                    $mejor = $importe;
                }
            }
        }

        return $mejor;
    }

    private function textoTrasRotulo(string $linea, string $clave): string
    {
        $patrones = match ($clave) {
            'total' => '/\btotal(?:es)?\b/iu',
            'subtotal' => '/(?:sub\s*totals?\s*\d*|neto(?:\s+gravado)?)\b/iu',
            'iva' => '/\b[il1]\.?\s*v\.?\s*a\.?v?(?:\s*inscripto)?\b/iu',
            'impuestos_internos' => '/imp(?:uestos?)?\s*\.?\s*int(?:ernos)?\b/iu',
            'percepcion_iva' => '/(?:per\.?\s*[il1]\.?\s*v[,.\s]*a\.?|rg\s*5329|percepcion\s+iva)\b/iu',
            'percepcion_iibb' => '/per\.?\s*[il1]+\.?\s*b+\.?|percepcion\s+iibb|percep\s*ii\.?\s*bb/iu',
            default => null,
        };
        if ($patrones === null) {
            return $linea;
        }
        if (preg_match($patrones, $linea, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($linea, $pos) ?: '';
        }

        return $linea;
    }

    /** @return list<float> */
    private function importesDeLinea(string $linea): array
    {
        $out = [];
        // OCR: $253_875.82 · 253.875.X2 (X≈8) · 36.267_97
        $linea = str_replace('_', '', $linea);
        $linea = preg_replace('/(?<=\d)[Xx](?=\d)/u', '8', $linea) ?? $linea;
        // $ 1.571.612,21 | 1.208.932,47 | 72535.95 | 36,267.97
        if (! preg_match_all('/(?:\$|S\s)?\s*(-?\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})|-?\d+[.,]\d{2})/u', $linea, $m)) {
            return [];
        }
        foreach ($m[1] as $raw) {
            $v = $this->importeParser->parsear($raw);
            if ($v !== null) {
                $out[] = $v;
            }
        }

        return $out;
    }

    private function esImportePlausible(float $importe, string $clave): bool
    {
        if ($importe < 0) {
            return false;
        }
        // Ceros válidos solo para internos / subtotal 2.
        if ($importe == 0.0) {
            return in_array($clave, ['impuestos_internos', 'subtotal'], true);
        }
        // Número de resolución RG, no monto.
        foreach (self::NUMEROS_RG as $rg) {
            if (abs($importe - $rg) < 0.001) {
                return false;
            }
        }
        // Alícuotas típicas tomadas como importe (3, 6, 10.5, 21, 27).
        if (in_array(round($importe, 2), [3.0, 6.0, 10.5, 21.0, 27.0], true)) {
            return false;
        }
        // Para TOTAL/neto/IVA exigir montos de factura reales.
        if (in_array($clave, ['total', 'subtotal', 'iva'], true) && $importe < 50) {
            return false;
        }

        return true;
    }

    private function detectarClaveEnTexto(string $linea): ?string
    {
        $l = mb_strtolower($linea);
        // Orden importa: Per.IVA / RG antes que IVA genérico; TOTAL antes que Subtotal.
        if (preg_match('/\btotal(?:es)?\b/iu', $l) && ! preg_match('/sub\s*total|precio|unitario|bultos/iu', $l)) {
            return 'total';
        }
        // OCR confunde I/l/1: Per.IIBB / Per.llBB / Per.ilBB
        if (preg_match('/per\.?\s*[il1]+\.?\s*b{1,2}|percepcion\s+iibb|percep\s*ii\.?\s*bb/iu', $l)) {
            return 'percepcion_iibb';
        }
        if (preg_match('/per\.?\s*[il1]\.?\s*v[,.\s]*a|rg\s*5329|percepcion\s+iva/iu', $l)) {
            return 'percepcion_iva';
        }
        // Imp. Int no debe “robar” el monto de Per.IVA en la línea siguiente.
        if (preg_match('/imp(?:uestos?)?\s*\.?\s*int(?:ernos)?/iu', $l)
            && ! preg_match('/per\.?\s*[il1]/iu', $l)) {
            return 'impuestos_internos';
        }
        if (preg_match('/\b[il1]\.?\s*v\.?\s*a\.?v?(?:\s*inscripto)?\b/iu', $l)
            && ! preg_match('/responsable|condicion|cuit/iu', $l)) {
            return 'iva';
        }
        if (preg_match('/\bneto(?:\s+gravado)?\b|sub\s*totals?\b/iu', $l)) {
            return 'subtotal';
        }

        return null;
    }

    /**
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }|null
     */
    private function extraerHorizontal(string $texto): ?array
    {
        $patronLabels = '/SUB\s*TOTAL\s+'
            .'I\.?\s*V\.?\s*A\.?\s*INSCRIPTO\s+'
            .'IMPUESTOS?\s*INTERNOS\s+'
            .'RG\s*5329\s+'
            .'PERCEP\s*I+\.?\s*B+\.?\s+'
            .'TOTAL\s*[\r\n]+\s*'
            .'(-?[\d.,]+)\s+(-?[\d.,]+)\s+(-?[\d.,]+)\s+(-?[\d.,]+)\s+(-?[\d.,]+)\s+(-?[\d.,]+)/iu';

        if (! preg_match($patronLabels, $texto, $m)) {
            if (! preg_match(
                '/SUB\s*TOTAL[^\n]{0,120}TOTAL\s*[\r\n]+\s*(-?[\d.,]+(?:\s+-?[\d.,]+){5})/iu',
                $texto,
                $m2
            )) {
                return null;
            }
            $parts = preg_split('/\s+/', trim($m2[1])) ?: [];
            if (count($parts) < 6) {
                return null;
            }
            $vals = [];
            foreach (array_slice($parts, 0, 6) as $p) {
                $v = $this->importeParser->parsear($p);
                if ($v === null) {
                    return null;
                }
                $vals[] = $v;
            }
        } else {
            $vals = [];
            for ($i = 1; $i <= 6; $i++) {
                $v = $this->importeParser->parsear($m[$i]);
                if ($v === null) {
                    return null;
                }
                $vals[] = $v;
            }
        }

        return $this->mapearVals($vals);
    }

    /**
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }|null
     */
    private function extraerVertical(string $texto): ?array
    {
        $filas = preg_split('/\R/u', $texto) ?: [];
        $n = count($filas);

        for ($i = 0; $i < $n; $i++) {
            if (! preg_match('/^\s*SUB\s*TOTAL\s*$/iu', $filas[$i])) {
                continue;
            }

            $labels = [];
            $j = $i;
            while ($j < $n && count($labels) < 8) {
                $l = trim($filas[$j]);
                if ($l === '') {
                    $j++;
                    continue;
                }
                if ($this->esLabelPie($l)) {
                    $labels[] = $this->normalizarLabel($l);
                    $j++;
                    continue;
                }
                break;
            }

            if (! in_array('subtotal', $labels, true) || ! in_array('total', $labels, true)) {
                continue;
            }

            $vals = [];
            while ($j < $n && count($vals) < count($labels) + 1) {
                $l = trim($filas[$j]);
                if ($l === '') {
                    $j++;
                    continue;
                }
                if ($this->esLabelPie($l) || preg_match('/motivo|cae|controle|factura/iu', $l)) {
                    break;
                }
                $v = $this->importeParser->parsear($l);
                if ($v === null || ! $this->esImportePlausible($v, 'subtotal')) {
                    if ($v !== null && abs($v) < 0.001) {
                        $vals[] = 0.0;
                        $j++;
                        continue;
                    }
                    break;
                }
                $vals[] = $v;
                $j++;
            }

            $alineados = $this->alinearVertical($labels, $vals);
            if ($alineados !== null) {
                return $alineados;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $vals
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }|null
     */
    private function alinearVertical(array $labels, array $vals): ?array
    {
        if ($vals === []) {
            return null;
        }

        $total = $vals[array_key_last($vals)];
        if (count($vals) === count($labels)) {
            return $this->mapearPorLabels($labels, $vals);
        }

        if (count($labels) === count($vals) + 1 && in_array('impuestos_internos', $labels, true)) {
            $idxInternos = array_search('impuestos_internos', $labels, true);
            array_splice($vals, (int) $idxInternos, 0, [0.0]);
            if (count($vals) === count($labels)) {
                return $this->mapearPorLabels($labels, $vals);
            }
        }

        $subtotal = $vals[0];
        if ($total > $subtotal + 0.05) {
            return [
                'subtotal' => $subtotal,
                'iva' => $vals[1] ?? 0.0,
                'impuestos_internos' => 0.0,
                'percepcion_iva' => $vals[count($vals) - 3] ?? 0.0,
                'percepcion_iibb' => $vals[count($vals) - 2] ?? 0.0,
                'total' => $total,
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $vals
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }|null
     */
    private function mapearPorLabels(array $labels, array $vals): ?array
    {
        $out = [
            'subtotal' => 0.0,
            'iva' => 0.0,
            'impuestos_internos' => 0.0,
            'percepcion_iva' => 0.0,
            'percepcion_iibb' => 0.0,
            'total' => 0.0,
        ];
        foreach ($labels as $i => $label) {
            if (! array_key_exists($label, $out)) {
                continue;
            }
            $out[$label] = $vals[$i];
        }
        if ($out['total'] <= 0 || $out['subtotal'] <= 0) {
            return null;
        }
        if ($out['total'] + 0.05 < $out['subtotal']) {
            return null;
        }

        return $out;
    }

    /**
     * @param  list<float>  $vals
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }
     */
    private function mapearVals(array $vals): array
    {
        return [
            'subtotal' => $vals[0],
            'iva' => $vals[1],
            'impuestos_internos' => $vals[2],
            'percepcion_iva' => $vals[3],
            'percepcion_iibb' => $vals[4],
            'total' => $vals[5],
        ];
    }

    /**
     * @param  array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }  $vals
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }
     */
    private function completarTotalSiFalta(array $vals): array
    {
        $suma = round(
            $vals['subtotal'] + $vals['iva'] + $vals['impuestos_internos']
            + $vals['percepcion_iva'] + $vals['percepcion_iibb'],
            2
        );
        if ($vals['total'] <= 0 && $suma > 0) {
            $vals['total'] = $suma;
        }
        // Si "total" quedó igual al neto pero hay IVA/percs, reconstruir.
        if ($vals['subtotal'] > 0
            && abs($vals['total'] - $vals['subtotal']) <= 0.05
            && ($vals['iva'] + $vals['percepcion_iva'] + $vals['percepcion_iibb']) > 0.05) {
            $vals['total'] = $suma;
        }

        return $vals;
    }

    /**
     * @param  array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }  $vals
     */
    private function esCoherente(array $vals): bool
    {
        if ($vals['total'] <= 0) {
            return false;
        }
        // Debe haber al menos neto o algún componente impositivo.
        if ($vals['subtotal'] <= 0 && $vals['iva'] <= 0) {
            return false;
        }
        if ($vals['subtotal'] > 0 && $vals['total'] + 0.05 < $vals['subtotal']) {
            return false;
        }
        $suma = round(
            $vals['subtotal'] + $vals['iva'] + $vals['impuestos_internos']
            + $vals['percepcion_iva'] + $vals['percepcion_iibb'],
            2
        );
        // Si hay varios componentes, la suma debe acercarse al total.
        $componentes = ($vals['subtotal'] > 0 ? 1 : 0)
            + ($vals['iva'] > 0 ? 1 : 0)
            + ($vals['percepcion_iva'] > 0 ? 1 : 0)
            + ($vals['percepcion_iibb'] > 0 ? 1 : 0);
        if ($componentes >= 2 && $suma > 0 && abs($suma - $vals['total']) > max(1.0, $vals['total'] * 0.02)) {
            // Tolerar OCR parcial: si total > suma, igual sirve (faltó algún renglón).
            if ($vals['total'] + 0.05 < $suma) {
                return false;
            }
        }

        return true;
    }

    private function esLabelPie(string $linea): bool
    {
        return (bool) preg_match(
            '/^(SUB\s*TOTAL|I\.?\s*V\.?\s*A\.?\s*INSCRIPTO|IMPUESTOS?\s*INTERNOS|RG\s*5329|PERCEP\s*I+\.?\s*B+\.?|TOTAL)$/iu',
            trim($linea)
        );
    }

    private function normalizarLabel(string $linea): string
    {
        $l = mb_strtolower(trim($linea));
        $l = preg_replace('/\s+/', '', $l) ?? $l;
        $l = str_replace(['.', '-'], '', $l);

        return match (true) {
            str_contains($l, 'subtotal') => 'subtotal',
            str_contains($l, 'ivaincripto') || str_contains($l, 'ivainscripto') => 'iva',
            str_contains($l, 'impuestosinternos') || str_contains($l, 'impuestointerno') => 'impuestos_internos',
            str_contains($l, 'rg5329') => 'percepcion_iva',
            str_contains($l, 'percep') && str_contains($l, 'bb') => 'percepcion_iibb',
            $l === 'total' => 'total',
            default => $l,
        };
    }

    /**
     * @param  array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float
     * }  $vals
     * @return array{
     *   subtotal: float,
     *   iva: float,
     *   impuestos_internos: float,
     *   percepcion_iva: float,
     *   percepcion_iibb: float,
     *   total: float,
     *   lineas: list<array{descripcion: string, importe: float, alicuota_iva: ?float, tipo: string}>,
     *   origen: string
     * }
     */
    private function conLineas(array $vals, string $origen): array
    {
        $total = (float) ($vals['total'] ?? 0);
        $subtotal = (float) ($vals['subtotal'] ?? 0);
        $iva = (float) ($vals['iva'] ?? 0);
        $internos = (float) ($vals['impuestos_internos'] ?? 0);
        $percIva = (float) ($vals['percepcion_iva'] ?? 0);
        $percIibb = (float) ($vals['percepcion_iibb'] ?? 0);

        // NC/FC “rara”: solo TOTAL (o SUBTOTAL = TOTAL) sin IVA ni tributos → exento = total.
        $sinDesgloseIva = $total > 0
            && $iva <= 0.005
            && $internos <= 0.005
            && $percIva <= 0.005
            && $percIibb <= 0.005
            && ($subtotal <= 0.005 || abs($subtotal - $total) <= 0.05);

        if ($sinDesgloseIva) {
            $vals['subtotal'] = $total;

            return $vals + [
                'lineas' => [[
                    'descripcion' => 'Operaciones exentas (total sin desglose IVA)',
                    'importe' => $total,
                    'alicuota_iva' => 0.0,
                    'tipo' => 'exento',
                ]],
                'origen' => $origen,
            ];
        }

        $lineas = [];
        if ($subtotal > 0) {
            $lineas[] = [
                'descripcion' => 'Neto gravado',
                'importe' => $subtotal,
                'alicuota_iva' => null,
                'tipo' => 'neto',
            ];
        }
        if ($iva > 0) {
            $lineas[] = [
                'descripcion' => 'I.V.A. INSCRIPTO',
                'importe' => $iva,
                'alicuota_iva' => null,
                'tipo' => 'iva',
            ];
        }
        if ($internos > 0) {
            $lineas[] = [
                'descripcion' => 'IMPUESTOS INTERNOS',
                'importe' => $internos,
                'alicuota_iva' => null,
                'tipo' => 'interno',
            ];
        }
        if ($percIva > 0) {
            $lineas[] = [
                'descripcion' => 'Percepción IVA / RG 5329',
                'importe' => $percIva,
                'alicuota_iva' => 3.0,
                'tipo' => 'percepcion_iva',
            ];
        }
        if ($percIibb > 0) {
            $lineas[] = [
                'descripcion' => 'PERCEP II.BB.',
                'importe' => $percIibb,
                'alicuota_iva' => null,
                'tipo' => 'percepcion_iibb',
            ];
        }

        return $vals + ['lineas' => $lineas, 'origen' => $origen];
    }
}
