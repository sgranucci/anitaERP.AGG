<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use RuntimeException;

/**
 * Asigna importes extraídos por IA a conceptos IVA usando nombre_ia (alias separados por coma).
 */
final class ComprobanteProveedorPdfIaConceptoMatcherSupport
{
    /** Proveedor monotributista: el total sin IVA va a RESP. MONOTRIBUTO, no a un impuesto. */
    private bool $esMonotributo = false;

    /**
     * @param  list<array<string, mixed>>  $conceptosCandidatos
     * @param  list<array<string, mixed>>  $lineasIa
     * @param  array{es_monotributo?: bool}  $opciones
     * @return list<array{id_concepto: int|string, importe: float, descripcion_ia: string, concepto_nombre: string}>
     */
    public function matchear(array $conceptosCandidatos, array $lineasIa, array $opciones = []): array
    {
        $this->esMonotributo = (bool) ($opciones['es_monotributo'] ?? false);

        if ($conceptosCandidatos === []) {
            throw new RuntimeException('No hay conceptos candidatos para la OC.');
        }

        $asignados = [];
        $usados = [];

        foreach ($lineasIa as $linea) {
            $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
            if ($importe <= 0) {
                continue;
            }

            $mejor = $this->elegirConcepto($conceptosCandidatos, $linea, $usados);
            if ($mejor === null) {
                throw new RuntimeException(
                    'No se pudo identificar concepto IVA para línea «'.($linea['descripcion'] ?? '').'» (importe '.$importe.')'
                );
            }

            $clave = (int) $mejor['id_concepto'];
            if (! isset($asignados[$clave])) {
                $asignados[$clave] = [
                    'id_concepto' => $mejor['id_concepto'],
                    'importe' => 0.0,
                    'descripcion_ia' => (string) $mejor['descripcion_ai'],
                    'concepto_nombre' => (string) $mejor['nombre'],
                ];
            }
            $asignados[$clave]['importe'] = round($asignados[$clave]['importe'] + $importe, 2);
            $usados[$clave] = true;
        }

        if ($asignados === []) {
            throw new RuntimeException('La IA no devolvió importes de conceptos.');
        }

        return array_values($asignados);
    }

    /**
     * @param  list<array<string, mixed>>  $candidatos
     * @param  array<string, mixed>  $linea
     * @param  array<int, bool>  $usados
     * @return ?array<string, mixed>
     */
    private function elegirConcepto(array $candidatos, array $linea, array $usados): ?array
    {
        $descLinea = $this->normalizar((string) ($linea['descripcion'] ?? ''));
        $tipoLinea = strtolower((string) ($linea['tipo'] ?? ''));
        $alicuotaLinea = isset($linea['alicuota_iva']) ? (float) $linea['alicuota_iva'] : null;
        $jurisdiccionHint = strtolower((string) ($linea['jurisdiccion_iibb'] ?? ''));

        $mejor = null;
        $mejorScore = -1;

        foreach ($candidatos as $candidato) {
            $id = (int) ($candidato['id_concepto'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $tipoConcepto = strtoupper((string) ($candidato['tipoconcepto'] ?? ''));
            if ($tipoConcepto === '0' || $tipoConcepto === '') {
                continue;
            }
            $nombre = $this->normalizar((string) ($candidato['nombre'] ?? ''));
            $codigo = (string) ($candidato['id_concepto'] ?? $candidato['codigo'] ?? '');
            if (! $this->tipoConceptoCompatibleConLinea($tipoLinea, $tipoConcepto, $nombre, $codigo, $descLinea)) {
                continue;
            }

            $aliases = $this->aliasesDescripcionIa((string) ($candidato['descripcion_ai'] ?? ''));
            if ($nombre !== '') {
                $aliases[] = $nombre;
            }
            $aliases = array_values(array_unique(array_filter($aliases)));

            $score = $this->mejorScoreAlias($descLinea, $aliases);
            $score += $this->scorePorTipoLinea($tipoLinea, $tipoConcepto, $nombre);
            $score += $this->scoreNombreParaLinea($tipoLinea, $nombre, $descLinea);

            if (str_contains($descLinea, 'exent') && $tipoConcepto === 'E') {
                $score += 40;
            }

            $alicuotaConcepto = $candidato['alicuota_iva'] ?? null;
            if ($alicuotaLinea !== null && $alicuotaConcepto !== null
                && abs($alicuotaLinea - (float) $alicuotaConcepto) < 0.01) {
                // Alicuota 0 es ambigua (exento / percepción / no gravado): solo bonificar E/N.
                if ($alicuotaLinea > 0.01 || in_array($tipoConcepto, ['E', 'N'], true)) {
                    $score += 55;
                }
            } elseif ($alicuotaLinea !== null && $alicuotaLinea > 0.01) {
                $needle = $this->formatearAlicuota($alicuotaLinea);
                foreach ($aliases as $alias) {
                    if (str_contains($alias, $needle) || str_contains($alias, (string) (int) $alicuotaLinea)) {
                        $score += 35;
                        break;
                    }
                }
            }

            if ($jurisdiccionHint !== '' && $this->nombreSugierePercepcionIibb($nombre)) {
                $score += $this->scoreJurisdiccionIibb($jurisdiccionHint, $aliases, $nombre);
            }

            if (isset($usados[$id])) {
                $score -= 15;
            }

            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $candidato;
            }
        }

        return $mejorScore >= 20 ? $mejor : null;
    }

    /**
     * @return list<string>
     */
    private function aliasesDescripcionIa(string $descripcionAi): array
    {
        $texto = str_replace(["\r", "\n"], ' ', $descripcionAi);
        $partes = preg_split('/\s*,\s*/u', $texto) ?: [];
        $aliases = [];
        foreach ($partes as $parte) {
            $norm = $this->normalizar($parte);
            if ($norm !== '' && mb_strlen($norm) >= 2) {
                $aliases[] = $norm;
            }
        }

        return $aliases;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function mejorScoreAlias(string $descLinea, array $aliases): int
    {
        if ($descLinea === '' || $aliases === []) {
            return 0;
        }

        $mejor = 0;
        foreach ($aliases as $alias) {
            if ($alias === '') {
                continue;
            }
            if ($descLinea === $alias || str_contains($descLinea, $alias) || str_contains($alias, $descLinea)) {
                $mejor = max($mejor, 90);
                continue;
            }
            // Tokens significativos del alias presentes en la línea (AGIP, ARBA, RG 5329…).
            $tokens = preg_split('/\s+/', $alias) ?: [];
            $hits = 0;
            $utiles = 0;
            $hitsFuertes = 0;
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 3 || $this->esTokenAliasDebil($token)) {
                    continue;
                }
                $utiles++;
                if (str_contains($descLinea, $token) || $this->tokenEquivalente($token, $descLinea)) {
                    $hits++;
                    $hitsFuertes++;
                }
            }
            // Evitar que «iva» dentro de «sin desglose IVA» dispare match 85 a «iva 21».
            if ($utiles > 0 && $hits === $utiles && $hitsFuertes > 0) {
                $mejor = max($mejor, 85);
            } elseif ($hitsFuertes > 0) {
                $mejor = max($mejor, min(70, 25 + ($hitsFuertes * 20)));
            }

            similar_text($descLinea, $alias, $pct);
            $aporteSimilar = (int) $pct;
            // Sin una palabra en común, el parecido de letras (p. ej. «desglose» contra
            // «deb y cred») no puede ganarles a un concepto que sí nombra el impuesto.
            if ($hitsFuertes === 0) {
                $aporteSimilar = min(30, $aporteSimilar);
            }
            $mejor = max($mejor, $aporteSimilar);
        }

        return $mejor;
    }

    /**
     * Total de una factura C de monotributista: no es percepción ni IVA discriminado.
     */
    private function lineaVaAMonotributo(string $tipoLinea, string $descLinea): bool
    {
        if (! $this->esMonotributo) {
            return false;
        }
        foreach (['percepcion', 'retencion', 'otro_tributo', 'interno'] as $bloqueo) {
            if (str_contains($tipoLinea, $bloqueo)) {
                return false;
            }
        }
        if (preg_match('/\biva\b/u', $tipoLinea) === 1) {
            return false;
        }

        return str_contains($tipoLinea, 'exento')
            || str_contains($tipoLinea, 'neto')
            || str_contains($tipoLinea, 'no_gravado')
            || str_contains($tipoLinea, 'subtotal')
            || str_contains($descLinea, 'sin desglose')
            || str_contains($descLinea, 'monotribut');
    }

    /** «exentas» del texto de la factura equivale al concepto «exento». */
    private function tokenEquivalente(string $token, string $descLinea): bool
    {
        return $token === 'exento' && str_contains($descLinea, 'exent');
    }

    private function esTokenAliasDebil(string $token): bool
    {
        $t = mb_strtolower($token);

        return in_array($t, [
            'iva', 'neto', 'total', 'importe', 'compra', 'compras', 'gravado',
            'inscripto', 'impuesto', 'valor', 'agregado', 'perc', 'percep', 'percepcion',
        ], true);
    }

    private function scorePorTipoLinea(string $tipoLinea, string $tipoConcepto, string $nombre = ''): int
    {
        $mapa = [
            // Primero los tipos específicos: "percepcion_iva" también contiene "iva".
            'percepcion_iva' => [ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IVA => 55],
            'percepcion_iibb' => [ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB => 60],
            'iva' => ['I' => 50, 'P' => 15],
            'neto' => ['G' => 50, 'N' => 40, 'E' => 25],
            'exento' => ['E' => 80, 'N' => 35, 'G' => -40, 'I' => -50, 'P' => -50],
            'no_gravado' => ['N' => 50, 'G' => 25, 'E' => 30],
            'percepcion_ganancias' => ['P' => 40, 'B' => 15],
            'interno' => ['T' => 55, 'N' => 15],
            'otro_tributo' => ['T' => 35, 'P' => 25, 'B' => 20, 'M' => 25],
            'retencion_iva' => ['V' => 55, 'P' => 25, 'I' => 20],
            'retencion_iibb' => ['B' => 40, 'S' => 35],
            'subtotal' => ['G' => 45, 'N' => 35],
        ];

        foreach ($mapa as $clave => $tipos) {
            if (str_contains($tipoLinea, $clave) && isset($tipos[$tipoConcepto])) {
                return $tipos[$tipoConcepto];
            }
        }

        // Ferli/Anita: tipoconcepto N genérico — bonificar por nombre de percepción.
        if ((str_contains($tipoLinea, 'percepcion_iva') || str_contains($tipoLinea, 'otro_tributo'))
            && $tipoConcepto === 'N'
            && $this->nombreSugierePercepcionIva($nombre)) {
            return 50;
        }

        if (str_contains($tipoLinea, 'percepcion_iibb') && $tipoConcepto === 'N' && $this->nombreSugierePercepcionIibb($nombre)) {
            return 60;
        }

        if (str_contains($tipoLinea, 'percepc') && in_array($tipoConcepto, ['P', 'B'], true)) {
            return 30;
        }

        return 0;
    }

    private function scoreNombreParaLinea(string $tipoLinea, string $nombre, string $descLinea): int
    {
        if ($nombre === '') {
            return 0;
        }

        // Nunca asignar «RESP. MONOTRIBUTO» a percepción / otros tributos AFIP.
        if (str_contains($nombre, 'monotribut')) {
            if ($this->lineaVaAMonotributo($tipoLinea, $descLinea)) {
                return 120;
            }
            if (str_contains($tipoLinea, 'percepcion')
                || str_contains($tipoLinea, 'otro_tributo')
                || str_contains($descLinea, 'percep')
                || str_contains($descLinea, 'tributo')
                || str_contains($descLinea, 'rg ')) {
                return -80;
            }
        }

        if ((str_contains($tipoLinea, 'percepcion_iva') || str_contains($tipoLinea, 'otro_tributo'))
            && $this->nombreSugierePercepcionIva($nombre)) {
            return 70;
        }

        if (str_contains($tipoLinea, 'iva') && ! str_contains($tipoLinea, 'percepcion')
            && preg_match('/\biva\b/u', $nombre) && ! str_contains($nombre, 'percep')) {
            return 25;
        }

        if ((str_contains($tipoLinea, 'neto') || str_contains($tipoLinea, 'subtotal'))
            && preg_match('/\bcompras?\b|\bgravado\b/u', $nombre)) {
            return 25;
        }

        return 0;
    }

    /** «CABA PERCEP I.B.» queda en tipoconcepto N; igual es percepción de ingresos brutos. */
    private function nombreSugierePercepcionIibb(string $nombre): bool
    {
        if ($nombre === '') {
            return false;
        }
        if (preg_match('/iibb|ibr|sircreb|arba|agip|ingresos\s+brutos/u', $nombre)) {
            return true;
        }

        return (bool) preg_match('/percep/u', $nombre)
            && (bool) preg_match('/\bi\.?\s*b\b/u', $nombre);
    }

    private function nombreSugierePercepcionIva(string $nombre): bool
    {
        if ($nombre === '') {
            return false;
        }
        if (str_contains($nombre, 'iibb') || str_contains($nombre, 'ibr') || str_contains($nombre, 'sircreb')) {
            return false;
        }

        return (bool) preg_match('/percep.*iva|iva.*percep|rg\s*5329|rg\s*3337/u', $nombre);
    }

    /**
     * Las percepciones detectadas explícitamente no pueden degradar a otro tipo
     * por similitud de nombre o por falta de un candidato con buen puntaje.
     */
    private function tipoConceptoCompatibleConLinea(
        string $tipoLinea,
        string $tipoConcepto,
        string $nombre = '',
        string $codigo = '',
        string $descLinea = '',
    ): bool {
        if (str_contains($tipoLinea, 'percepcion_iibb')) {
            return $tipoConcepto === ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB
                || ($tipoConcepto === 'N' && $this->nombreSugierePercepcionIibb($nombre));
        }

        if (str_contains($tipoLinea, 'percepcion_iva')) {
            if ($tipoConcepto === ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IVA) {
                return true;
            }
            // Código Anita 103 / nombre «Percepcion IVA» aunque el maestro diga N.
            if ($tipoConcepto === 'N' && ($codigo === '103' || $this->nombreSugierePercepcionIva($nombre))) {
                return true;
            }

            return false;
        }

        // Monotributo no compite por neto/IVA/percepción, salvo el total sin IVA
        // de un proveedor monotributista (factura C).
        if (str_contains($nombre, 'monotribut')
            && ! $this->lineaVaAMonotributo($tipoLinea, $descLinea)
            && (str_contains($tipoLinea, 'neto')
                || str_contains($tipoLinea, 'iva')
                || str_contains($tipoLinea, 'percepcion')
                || str_contains($tipoLinea, 'otro_tributo')
                || str_contains($tipoLinea, 'subtotal'))) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function scoreJurisdiccionIibb(string $hint, array $aliases, string $nombre): int
    {
        $blob = implode(' ', $aliases).' '.$nombre;
        if ($hint === 'arba' || $hint === 'bsas' || $hint === 'buenos_aires') {
            if (preg_match('/\b(?:arba|bs\.?\s*as|buenos\s+aires|ibr\s*bs)\b/u', $blob)) {
                return 40;
            }
            if (preg_match('/\b(?:caba|agip|capital)\b/u', $blob)) {
                return -25;
            }
        }
        if ($hint === 'caba' || $hint === 'agip' || $hint === 'capital') {
            if (preg_match('/\b(?:caba|agip|capital|agi)\b/u', $blob)) {
                return 40;
            }
            if (preg_match('/\b(?:arba|bs\.?\s*as|buenos\s+aires)\b/u', $blob)) {
                return -25;
            }
        }
        if ($hint === 'cordoba' && preg_match('/cordoba/u', $blob)) {
            return 40;
        }
        if ($hint === 'tdf' && preg_match('/tdf|tierra\s+del\s+fuego/u', $blob)) {
            return 40;
        }

        return 0;
    }

    private function formatearAlicuota(float $alicuota): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($alicuota, 1, '.', ''), '0'), '.'));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim(str_replace(["\r", "\n"], ' ', $texto)));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\b(iva)\s+c(?:ont(?:ado)?|tdo)\.?(?!\w)/u', '$1 ', $texto) ?? $texto;

        return $texto;
    }
}
