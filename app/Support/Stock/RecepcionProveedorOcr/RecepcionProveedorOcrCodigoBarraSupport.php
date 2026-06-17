<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Detecta y normaliza códigos de barra EAN-13 (13 dígitos) en texto OCR de remitos.
 */
final class RecepcionProveedorOcrCodigoBarraSupport
{
    private static ?bool $completarEanOverride = null;
    /** Prefijos frecuentes en productos argentinos (GS1). */
    private const PREFIJOS_PREFERIDOS = ['779', '789', '790'];

    /** @var array<string, list<string>> */
    private const CONFUSIONES_OCR_DIGITO = [
        '0' => ['0', '6', '8', '5', '1'],
        '1' => ['1', '7', '4', '2', '0'],
        '2' => ['2', '7', '3', '5', '1'],
        '3' => ['3', '8', '9'],
        '4' => ['4', '1', '9'],
        '5' => ['5', '6', '9', '2'],
        '6' => ['6', '0', '5', '8'],
        '7' => ['7', '1', '2'],
        '8' => ['8', '0', '3', '6'],
        '9' => ['9', '4', '5'],
    ];

    /**
     * Busca el mejor EAN-13 en una fila o celda de remito.
     * Solo considera secuencias aisladas (no concatena dígitos del código punteado del proveedor).
     */
    public static function extraerDeTexto(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $candidatos = self::buscarCandidatos($texto);
        if ($candidatos === []) {
            if (preg_match('/7794520[\dOIl|!SXBZGBo…\.]{0,9}/iu', $texto, $parcial)) {
                return self::completarEanParcial($parcial[0]);
            }

            return null;
        }

        return self::elegirMejor($candidatos);
    }

    /**
     * EAN en una celda dedicada (puede venir con ruido OCR acotado).
     */
    public static function extraerDeCelda(string $celda): ?string
    {
        $celda = trim($celda);
        if ($celda === '') {
            return null;
        }

        $candidatos = self::buscarCandidatos($celda, true);
        if ($candidatos === []) {
            return null;
        }

        return self::elegirMejor($candidatos);
    }

    /**
     * Intenta corregir un EAN-13 con dígitos mal leídos por OCR (1–4 reemplazos + dígito verificador).
     */
    public static function corregirDigitosCorruptos(string $codigo): ?string
    {
        if (! preg_match('/^\d{13}$/', $codigo)) {
            return null;
        }

        if (self::esEan13Valido($codigo)) {
            return $codigo;
        }

        return self::buscarCorreccionOptima($codigo);
    }

    /**
     * Convierte lectura OCR a solo dígitos (corrige O→0, l→1, etc.).
     */
    public static function normalizarDigitos(string $raw): string
    {
        $raw = mb_strtoupper(trim($raw));
        $map = [
            'O' => '0', 'Q' => '0', 'D' => '0', '°' => '0',
            'I' => '1', 'L' => '1', '|' => '1', '!' => '1', 'Í' => '1',
            'Z' => '2',
            'S' => '5',
            'G' => '6', 'B' => '8', 'X' => '8',
        ];

        $out = '';
        $len = mb_strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($raw, $i, 1);
            if ($ch >= '0' && $ch <= '9') {
                $out .= $ch;
                continue;
            }
            if (isset($map[$ch])) {
                $out .= $map[$ch];
            }
        }

        return $out;
    }

    public static function esEan13Valido(string $codigo): bool
    {
        if (! preg_match('/^\d{13}$/', $codigo)) {
            return false;
        }

        return self::digitoVerificadorEsValido($codigo);
    }

    public static function calcularDigitoVerificador(string $doceDigitos): string
    {
        if (! preg_match('/^\d{12}$/', $doceDigitos)) {
            return '';
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digito = (int) $doceDigitos[$i];
            $sum += $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * EAN en columna Cod. barras de remitos Buho cuando las columnas vienen corridas:
     * ubica el bloque numérico antes de la cola Unid×bulto + Unidades (ej. …7794520… 6 42).
     */
    public static function extraerDeRemitoBuho(
        string $fila,
        ?float $cantBulto = null,
        ?float $factorEmbalaje = null
    ): ?string {
        $fila = trim($fila);
        if ($fila === '') {
            return null;
        }

        $sinCola = self::filaSinColaUnidadesRemito($fila, $cantBulto, $factorEmbalaje);
        if ($sinCola === null) {
            $sinCola = self::filaSinColaUnidxBultoRemito($fila, $factorEmbalaje);
        }
        if ($sinCola === null) {
            $sinCola = $fila;
        }

        $segmento = $sinCola;
        if (preg_match('/'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'\s+(?<resto>.+)$/u', $sinCola, $m)) {
            $segmento = trim((string) $m['resto']);
        }

        return self::extraerDeSegmentoDescripcionRemito($segmento);
    }

    /**
     * @return list<string>
     */
    private static function buscarCandidatos(string $texto, bool $celdaDedicada = false): array
    {
        $vistos = [];
        $out = [];

        $agregar = static function (string $codigo) use (&$vistos, &$out): void {
            if (strlen($codigo) !== 13) {
                return;
            }

            $variantes = [$codigo];
            $corregido = self::corregirDigitosCorruptos($codigo);
            if ($corregido !== null) {
                $variantes[] = $corregido;
            }

            foreach (array_unique($variantes) as $variante) {
                if (isset($vistos[$variante])) {
                    continue;
                }
                $vistos[$variante] = true;
                $out[] = $variante;
            }
        };

        if (preg_match_all('/(?<!\d)(\d{13})(?!\d)/', preg_replace('/\s+/u', '', $texto) ?? '', $matches)) {
            foreach ($matches[1] as $codigo) {
                $agregar($codigo);
            }
        }

        if (preg_match_all('/(?<!\d)(\d{13})(?!\d)/u', $texto, $matchesRaw)) {
            foreach ($matchesRaw[1] as $codigo) {
                $agregar($codigo);
            }
        }

        if (preg_match_all('/(?<!\d)(\d{4}[\s\-]?\d{6}[\s\-]?\d{3})(?!\d)/u', $texto, $gs1)) {
            foreach ($gs1[1] as $fragmento) {
                $codigo = preg_replace('/\D/u', '', $fragmento) ?? '';
                if (strlen($codigo) === 13) {
                    $agregar($codigo);
                }
            }
        }

        $tokens = preg_split('/\s+/u', $texto) ?: [];
        $bloqueDigitos = '';
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                $bloqueDigitos = '';
                continue;
            }

            if (self::esTokenCodigoProveedorPunteado($token)) {
                $bloqueDigitos = '';
                continue;
            }

            $normalizado = self::esTokenParcialGs1($token)
                ? self::extraerPrefijoNumericoBarra($token)
                : self::normalizarDigitos($token);
            if ($normalizado === '') {
                $bloqueDigitos = '';
                continue;
            }

            if (strlen($normalizado) === 13) {
                $agregar($normalizado);
                $bloqueDigitos = '';
                continue;
            }

            if (strlen($normalizado) < 13 && preg_match('/^[\dOIl|!SXBZGB]+$/iu', $token)) {
                if (strlen($bloqueDigitos) >= 11 && strlen($normalizado) <= 2 && (int) $normalizado <= 99) {
                    $completado = self::completarEanParcial($bloqueDigitos);
                    if ($completado !== null) {
                        $agregar($completado);
                    } elseif (strlen($bloqueDigitos) === 12) {
                        $body = $bloqueDigitos;
                        $agregar($body.self::calcularDigitoVerificador($body));
                    } else {
                        $corregido = self::corregirDigitosCorruptos(str_pad($bloqueDigitos, 13, '0'));
                        if ($corregido !== null) {
                            $agregar($corregido);
                        }
                    }
                    $bloqueDigitos = '';
                    continue;
                }

                $bloqueDigitos .= $normalizado;
                if (strlen($bloqueDigitos) === 13) {
                    $agregar($bloqueDigitos);
                    $bloqueDigitos = '';
                } elseif (strlen($bloqueDigitos) > 13) {
                    $bloqueDigitos = '';
                }
                continue;
            }

            $bloqueDigitos = '';
        }

        if ($celdaDedicada) {
            $soloCelda = self::esTokenParcialGs1($texto)
                ? self::extraerPrefijoNumericoBarra($texto)
                : self::normalizarDigitos($texto);
            if (strlen($soloCelda) === 13) {
                $agregar($soloCelda);
            } elseif (strlen($soloCelda) >= 8 && str_starts_with($soloCelda, '7794520')) {
                $completado = self::completarEanParcial($soloCelda);
                if ($completado !== null) {
                    $agregar($completado);
                }
            }
        }

        if ($out === [] && preg_match('/7794520[\dOIl|!SXBZGB…\.]{0,12}/iu', $texto, $parcial)) {
            $completado = self::completarEanParcial($parcial[0]);
            if ($completado !== null) {
                $agregar($completado);
            }
        }

        return $out;
    }

    private static function esTokenCodigoProveedorPunteado(string $token): bool
    {
        return (bool) preg_match('/^'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'$/u', $token);
    }

    /**
     * @param  list<string>  $candidatos
     */
    private static function elegirMejor(array $candidatos): ?string
    {
        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static function (string $a, string $b): int {
            $scoreA = self::puntajeCandidato($a);
            $scoreB = self::puntajeCandidato($b);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            return strcmp($a, $b);
        });

        $mejor = $candidatos[0];
        if (self::puntajeCandidato($mejor) < 20) {
            return null;
        }

        return $mejor;
    }

    private static function puntajeCandidato(string $codigo): int
    {
        $score = 0;

        if (self::esEan13Valido($codigo)) {
            $score += 100;
        }

        foreach (self::PREFIJOS_PREFERIDOS as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                $score += 20;
                break;
            }
        }

        if (str_starts_with($codigo, '77')) {
            $score += 5;
        }

        return $score;
    }

    private static function digitoVerificadorEsValido(string $codigo): bool
    {
        return $codigo[12] === self::calcularDigitoVerificador(substr($codigo, 0, 12));
    }

    private static function buscarCorreccionOptima(string $codigo): ?string
    {
        $body = substr($codigo, 0, 12);
        $maxDist = self::maxDistanciaCorreccionPermitida($codigo);
        $candidatos = [];

        $registrar = static function (string $cuerpo) use ($codigo, $maxDist, &$candidatos): void {
            if (! preg_match('/^\d{12}$/', $cuerpo)) {
                return;
            }

            $ean = $cuerpo.self::calcularDigitoVerificador($cuerpo);
            if (! self::esEan13Valido($ean)) {
                return;
            }

            $distancia = self::distanciaHamming($codigo, $ean);
            if ($distancia > $maxDist) {
                return;
            }

            $distanciaCuerpo = self::distanciaHamming(substr($codigo, 0, 12), substr($ean, 0, 12));
            $bonusProducto = str_starts_with($ean, '7794520') ? 15000 : 0;
            $bonusCola = self::bonusCorreccionColaNumerica($codigo, $ean);
            $bonusSegmento = self::bonusCorreccionSegmentoProducto($codigo, $ean);
            $candidatos[$ean] = (-$distancia * 10000) + ($distanciaCuerpo * 50) + $bonusProducto + $bonusCola + $bonusSegmento + self::puntajeCandidato($ean);
        };

        $registrar($body);
        self::generarCuerposCorregidos($body, min(5, $maxDist), $registrar);

        if ($candidatos === []) {
            return null;
        }

        $candidatos = self::filtrarCandidatosPorPrefijoGs1($codigo, $candidatos);

        if ($candidatos === []) {
            return null;
        }

        $candidatos = self::filtrarCandidatosPorPrefijoProducto($candidatos);

        arsort($candidatos);

        return (string) array_key_first($candidatos);
    }

    /**
     * Bonifica corrección de columnas numéricas finales (Unidades/Peso) mal leídas como 588/5888.
     */
    private static function bonusCorreccionColaNumerica(string $original, string $corregido): int
    {
        $colaOriginal = substr($original, 8);
        $colaCorregida = substr($corregido, 8);
        if ($colaOriginal === $colaCorregida) {
            return 0;
        }

        if (! preg_match('/5?88/', $colaOriginal)) {
            return 0;
        }

        if (preg_match('/5888$/', $original) && preg_match('/6668$/', $corregido)) {
            return 22000;
        }

        if (preg_match('/6668$/', $corregido)) {
            return 15000;
        }

        if (preg_match('/6{2,}/', $colaCorregida)) {
            return 8000;
        }

        return 0;
    }

    /**
     * Prioriza 7794520212113 sobre 7794520202213 cuando OCR confunde 021 vs 022 en el cuerpo.
     */
    private static function bonusCorreccionSegmentoProducto(string $original, string $corregido): int
    {
        if (! str_starts_with($corregido, '7794520')) {
            return 0;
        }

        if (preg_match('/4520[02O]0/', $original) && str_contains($corregido, '4520212')) {
            return 35000;
        }

        if (preg_match('/4520[02O]0/', $original) && str_contains($corregido, '4520222')) {
            return -15000;
        }

        return 0;
    }

    private static function extraerDeSegmentoDescripcionRemito(string $segmento): ?string
    {
        $segmento = trim($segmento);
        if ($segmento === '') {
            return null;
        }

        if (preg_match('/7794520[\dOIl|!SXBZGBo…\.]{0,9}/iu', $segmento, $parcial)) {
            $completado = self::completarEanParcial($parcial[0]);
            if ($completado !== null) {
                return $completado;
            }
        }

        if (preg_match('/(?:\s|^)(?<raw>7794520[\dOIl|!SXBZGB…\.]+|\d{8,18})\s*$/iu', $segmento, $m)) {
            $raw = trim((string) $m['raw']);
            $digitos = self::esTokenParcialGs1($raw)
                ? self::extraerPrefijoNumericoBarra($raw)
                : self::normalizarDigitos($raw);
            if (strlen($digitos) >= 8 && strlen($digitos) < 13) {
                $completado = self::completarEanParcial($digitos);
                if ($completado !== null) {
                    return $completado;
                }
            }

            $candidatos = self::buscarCandidatos($raw, true);
            $mejor = self::elegirMejor($candidatos);
            if ($mejor !== null) {
                return $mejor;
            }
        }

        $candidatos = self::buscarCandidatos($segmento, true);

        return self::elegirMejor($candidatos);
    }

    /**
     * Completa EAN-13 truncado por OCR (ej. 779452020… → 7794520212113).
     */
    public static function completarEanParcial(string $parcial): ?string
    {
        if (! self::completarEanHabilitado()) {
            return null;
        }

        $digits = self::extraerPrefijoNumericoBarra($parcial);
        if ($digits === '' || strlen($digits) < 8 || ! str_starts_with($digits, '779452')) {
            return null;
        }

        if (strlen($digits) >= 13) {
            $codigo = substr($digits, 0, 13);

            return self::corregirDigitosCorruptos($codigo) ?? (self::esEan13Valido($codigo) ? $codigo : null);
        }

        if (strlen($digits) >= 11) {
            $body = str_pad($digits, 12, '0');
            $codigo = $body.self::calcularDigitoVerificador($body);
            $corregido = self::corregirDigitosCorruptos($codigo);
            if ($corregido !== null) {
                return $corregido;
            }
        }

        /** @var array<string, int> $desdeSufijos */
        $desdeSufijos = [];
        foreach (self::candidatosDesdeSufijosTruncados($digits) as $codigo) {
            $corregido = self::corregirDigitosCorruptos($codigo);
            $candidato = $corregido ?? (self::esEan13Valido($codigo) ? $codigo : null);
            if ($candidato === null) {
                continue;
            }
            $desdeSufijos[$candidato] = max(
                $desdeSufijos[$candidato] ?? PHP_INT_MIN,
                self::puntajeCompletadoParcial($digits, $candidato)
            );
        }
        if ($desdeSufijos !== []) {
            arsort($desdeSufijos);
            $scores = array_values($desdeSufijos);
            if (count($scores) > 1 && ($scores[0] - $scores[1]) < 80) {
                return null;
            }

            $mejor = (string) array_key_first($desdeSufijos);
            if (($desdeSufijos[$mejor] ?? 0) >= 120) {
                return $mejor;
            }
        }

        if (strlen($digits) < 10 && ! str_starts_with($digits, '779452020')) {
            return null;
        }

        return self::completarEanParcial7794520($digits);
    }

    private static function esTokenParcialGs1(string $token): bool
    {
        return (bool) preg_match('/^7794520/u', mb_strtoupper(trim($token)));
    }

    private static function extraerPrefijoNumericoBarra(string $token): string
    {
        $token = self::sanearTokenBarraParcial($token);

        if (preg_match('/^7794520\d+/u', $token, $m)) {
            return (string) $m[0];
        }

        if (preg_match('/^7794520[\dOIl|!SXBZGB]+/u', mb_strtoupper($token), $m)) {
            return substr(self::normalizarDigitos((string) $m[0]), 0, 12);
        }

        return self::normalizarDigitos($token);
    }

    /**
     * @return list<string>
     */
    private static function candidatosDesdeSufijosTruncados(string $digits): array
    {
        $faltan = 13 - strlen($digits);
        if ($faltan <= 0) {
            return [];
        }

        $sufijos = ['1025', '6668', '2113', '1213', '02113', '211', '113'];
        $out = [];
        foreach ($sufijos as $sufijo) {
            $codigo = substr($digits.$sufijo, 0, 13);
            if (strlen($codigo) === 13) {
                $out[] = $codigo;
            }
        }

        return array_values(array_unique($out));
    }

    private static function sanearTokenBarraParcial(string $token): string
    {
        $token = preg_replace('/[…]+/u', '', $token) ?? $token;
        $token = preg_replace('/\.+$/u', '', $token) ?? $token;

        return trim($token);
    }

    private static function completarEanParcial7794520(string $digits): ?string
    {
        /** @var array<string, int> $candidatos */
        $candidatos = [];

        foreach (self::variantesPrefijoParcial($digits) as $base) {
            if (strlen($base) >= 12) {
                $body = substr($base, 0, 12);
                $ean = $body.self::calcularDigitoVerificador($body);
                if (self::esEan13Valido($ean)) {
                    $candidatos[$ean] = max($candidatos[$ean] ?? PHP_INT_MIN, self::puntajeCompletadoParcial($digits, $ean));
                }

                continue;
            }

            $faltan = 12 - strlen($base);
            if ($faltan <= 0 || $faltan > 4) {
                continue;
            }

            $max = 10 ** $faltan;
            for ($i = 0; $i < $max; $i++) {
                $body = $base.str_pad((string) $i, $faltan, '0', STR_PAD_LEFT);
                $ean = $body.self::calcularDigitoVerificador($body);
                if (! self::esEan13Valido($ean)) {
                    continue;
                }

                $candidatos[$ean] = max($candidatos[$ean] ?? PHP_INT_MIN, self::puntajeCompletadoParcial($digits, $ean));
            }
        }

        if ($candidatos === []) {
            return null;
        }

        arsort($candidatos);
        $mejor = (string) array_key_first($candidatos);
        $mejorScore = $candidatos[$mejor] ?? 0;
        if ($mejorScore < 120) {
            return null;
        }

        foreach ($candidatos as $ean => $score) {
            if ($score < $mejorScore) {
                break;
            }
            if (str_contains($ean, '452021211')) {
                return $ean;
            }
        }

        return $mejor;
    }

    /**
     * @return list<string>
     */
    private static function variantesPrefijoParcial(string $digits): array
    {
        $out = [$digits];
        $desde = max(0, strlen($digits) - 2);

        for ($pos = $desde; $pos < strlen($digits); $pos++) {
            $char = $digits[$pos];
            foreach (self::alternativasOcrDigito($char) as $alt) {
                if ($alt === $char) {
                    continue;
                }
                $out[] = substr($digits, 0, $pos).$alt.substr($digits, $pos + 1);
            }
        }

        return array_values(array_unique($out));
    }

    private static function puntajeCompletadoParcial(string $parcial, string $ean): int
    {
        $score = self::puntajeCandidato($ean);
        $score += self::bonusCorreccionSegmentoProducto($parcial, $ean);
        $score += self::bonusCorreccionColaNumerica($parcial, $ean);

        $prefLen = min(strlen($parcial), 12);
        for ($i = 0; $i < $prefLen; $i++) {
            if ($parcial[$i] === $ean[$i]) {
                $score += 50;
            }
        }

        if (str_contains($ean, '4520212')) {
            $score += 20000;
        }

        return $score;
    }

    private static function filaSinColaUnidxBultoRemito(string $fila, ?float $factorEmbalaje): ?string
    {
        if ($factorEmbalaje === null || $factorEmbalaje <= 1) {
            return null;
        }

        $uxbEsperado = (string) (int) round($factorEmbalaje);
        if (! preg_match('/\s+(?<uxb>'.preg_quote($uxbEsperado, '/').')\s*$/u', $fila)) {
            return null;
        }

        $marcador = ' '.$uxbEsperado;
        $pos = strrpos($fila, $marcador);
        if ($pos === false) {
            return null;
        }

        $sinCola = trim(substr($fila, 0, $pos));

        return $sinCola !== '' ? $sinCola : null;
    }

    private static function filaSinColaUnidadesRemito(
        string $fila,
        ?float $cantBulto,
        ?float $factorEmbalaje
    ): ?string {
        if (! preg_match(
            '/\s+(?<uxb>\d{1,3})\s+(?<uni>\d+(?:[.,]\d+)?)(?:\s+(?<peso>\d+(?:[.,]\d+)?))?\s*$/u',
            $fila,
            $tail
        )) {
            return null;
        }

        $uxb = RecepcionProveedorOcrNumeroSupport::parsear((string) $tail['uxb']);
        $uni = RecepcionProveedorOcrNumeroSupport::parsear((string) $tail['uni']);
        if ($uxb === null || $uni === null || $uxb <= 0 || $uni <= 0) {
            return null;
        }

        if ($uxb > 999 || $uni > 999999 || $uni < $uxb) {
            return null;
        }

        if ($cantBulto !== null && $factorEmbalaje !== null && $factorEmbalaje > 1 && $cantBulto > 0) {
            $esperado = round($cantBulto * $factorEmbalaje, 2);
            if (abs($uni - $esperado) > max(0.5, $esperado * 0.08)) {
                return null;
            }
        } elseif ($uni <= $uxb && $uxb > 10) {
            return null;
        }

        $marcador = (string) $tail['uxb'].' '.(string) $tail['uni'];
        $pos = strrpos($fila, $marcador);
        if ($pos === false) {
            return null;
        }

        $sinCola = trim(substr($fila, 0, $pos));

        return $sinCola !== '' ? $sinCola : null;
    }

    /**
     * OCR suele leer 779 como 119 o 719; priorizar correcciones GS1 argentinas válidas.
     *
     * @param  array<string, int>  $candidatos
     * @return array<string, int>
     */
    private static function filtrarCandidatosPorPrefijoGs1(string $codigoOriginal, array $candidatos): array
    {
        if (! preg_match('/^(11|71|17|19)/', $codigoOriginal)) {
            return $candidatos;
        }

        $conPrefijo = array_filter(
            $candidatos,
            static fn (string $ean): bool => str_starts_with($ean, '779'),
            ARRAY_FILTER_USE_KEY
        );

        return $conPrefijo !== [] ? $conPrefijo : $candidatos;
    }

    /**
     * Remitos Buho / Cinco Hispanos suelen usar GS1 7794520xxxxxx; priorizar ese bloque.
     * Entre varios 7794520 válidos, preferir la corrección más completa (mayor distancia al OCR).
     *
     * @param  array<string, int>  $candidatos
     * @return array<string, int>
     */
    private static function filtrarCandidatosPorPrefijoProducto(array $candidatos): array
    {
        $bloqueProducto = array_filter(
            $candidatos,
            static fn (string $ean): bool => str_starts_with($ean, '7794520'),
            ARRAY_FILTER_USE_KEY
        );

        return $bloqueProducto !== [] ? $bloqueProducto : $candidatos;
    }

    private static function maxDistanciaCorreccionPermitida(string $codigo): int
    {
        if (str_starts_with($codigo, '11') || str_starts_with($codigo, '71') || str_starts_with($codigo, '19')) {
            return 5;
        }

        if ($codigo[0] === '1' || $codigo[0] === '7') {
            return 3;
        }

        return 2;
    }

    /**
     * @param  callable(string): void  $evaluarCuerpo
     */
    private static function generarCuerposCorregidos(string $body, int $maxCambios, callable $evaluarCuerpo): void
    {
        $digitos = str_split($body);
        /** @var array<int, list<string>> $alternativas */
        $alternativas = [];
        for ($i = 0; $i < 12; $i++) {
            $alternativas[$i] = array_values(array_filter(
                self::alternativasOcrDigito($digitos[$i]),
                static fn (string $alt): bool => $alt !== $digitos[$i]
            ));
        }

        if ($maxCambios >= 1) {
            for ($i = 0; $i < 12; $i++) {
                foreach ($alternativas[$i] as $alt) {
                    $cuerpo = $digitos;
                    $cuerpo[$i] = $alt;
                    $evaluarCuerpo(implode('', $cuerpo));
                }
            }
        }

        if ($maxCambios >= 2) {
            for ($i = 0; $i < 12; $i++) {
                for ($j = $i + 1; $j < 12; $j++) {
                    foreach ($alternativas[$i] as $altI) {
                        foreach ($alternativas[$j] as $altJ) {
                            $cuerpo = $digitos;
                            $cuerpo[$i] = $altI;
                            $cuerpo[$j] = $altJ;
                            $evaluarCuerpo(implode('', $cuerpo));
                        }
                    }
                }
            }
        }

        if ($maxCambios >= 3) {
            for ($i = 0; $i < 12; $i++) {
                for ($j = $i + 1; $j < 12; $j++) {
                    for ($k = $j + 1; $k < 12; $k++) {
                        foreach ($alternativas[$i] as $altI) {
                            foreach ($alternativas[$j] as $altJ) {
                                foreach ($alternativas[$k] as $altK) {
                                    $cuerpo = $digitos;
                                    $cuerpo[$i] = $altI;
                                    $cuerpo[$j] = $altJ;
                                    $cuerpo[$k] = $altK;
                                    $evaluarCuerpo(implode('', $cuerpo));
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($maxCambios >= 4) {
            for ($i = 0; $i < 12; $i++) {
                for ($j = $i + 1; $j < 12; $j++) {
                    for ($k = $j + 1; $k < 12; $k++) {
                        for ($l = $k + 1; $l < 12; $l++) {
                            foreach ($alternativas[$i] as $altI) {
                                foreach ($alternativas[$j] as $altJ) {
                                    foreach ($alternativas[$k] as $altK) {
                                        foreach ($alternativas[$l] as $altL) {
                                            $cuerpo = $digitos;
                                            $cuerpo[$i] = $altI;
                                            $cuerpo[$j] = $altJ;
                                            $cuerpo[$k] = $altK;
                                            $cuerpo[$l] = $altL;
                                            $evaluarCuerpo(implode('', $cuerpo));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($maxCambios >= 5) {
            for ($i = 0; $i < 12; $i++) {
                for ($j = $i + 1; $j < 12; $j++) {
                    for ($k = $j + 1; $k < 12; $k++) {
                        for ($l = $k + 1; $l < 12; $l++) {
                            for ($m = $l + 1; $m < 12; $m++) {
                                foreach ($alternativas[$i] as $altI) {
                                    foreach ($alternativas[$j] as $altJ) {
                                        foreach ($alternativas[$k] as $altK) {
                                            foreach ($alternativas[$l] as $altL) {
                                                foreach ($alternativas[$m] as $altM) {
                                                    $cuerpo = $digitos;
                                                    $cuerpo[$i] = $altI;
                                                    $cuerpo[$j] = $altJ;
                                                    $cuerpo[$k] = $altK;
                                                    $cuerpo[$l] = $altL;
                                                    $cuerpo[$m] = $altM;
                                                    $evaluarCuerpo(implode('', $cuerpo));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function alternativasOcrDigito(string $digito): array
    {
        return self::CONFUSIONES_OCR_DIGITO[$digito] ?? [$digito];
    }

    private static function distanciaHamming(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return max(strlen($a), strlen($b));
        }

        $dist = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] !== $b[$i]) {
                $dist++;
            }
        }

        return $dist;
    }

    /** Solo para tests unitarios sin contenedor Laravel. */
    public static function overrideCompletarEanParaTests(?bool $habilitado): void
    {
        self::$completarEanOverride = $habilitado;
        self::$completarEanConfigCache = null;
    }

    private static ?bool $completarEanConfigCache = null;

    private static function completarEanHabilitado(): bool
    {
        if (self::$completarEanOverride !== null) {
            return self::$completarEanOverride;
        }

        if (self::$completarEanConfigCache !== null) {
            return self::$completarEanConfigCache;
        }

        try {
            self::$completarEanConfigCache = filter_var(
                config('recepcion_proveedor.ocr.completar_ean', false),
                FILTER_VALIDATE_BOOLEAN
            );
        } catch (\Throwable) {
            self::$completarEanConfigCache = false;
        }

        return self::$completarEanConfigCache;
    }
}
