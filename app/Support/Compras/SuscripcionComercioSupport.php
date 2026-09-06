<?php

namespace App\Support\Compras;

/**
 * Normalización y comparación de descripciones de comercio del resumen de tarjeta.
 *
 * El emisor manda cosas como "ADOBE *CREATIVE CLOU 4085078188 IE" y la OC dice
 * "Adobe Systems Software Ireland". Acá se limpia el ruido del canal de cobro para
 * que el diccionario de alias y el matcheo por similitud trabajen sobre algo estable.
 */
final class SuscripcionComercioSupport
{
    /** Prefijos que el emisor antepone y no dicen nada del comercio. */
    private const PREFIJOS_PASARELA = [
        'PAYPAL', 'PP', 'SQ', 'SP', 'TST', 'WPY', 'DLOCAL', 'DL', 'EBANX', 'MERPAGO',
        'MERCADOPAGO', 'STRIPE', 'FS', 'CHECKOUT', 'PADDLE', 'FASTSPRING', 'RECURLY',
    ];

    /** Sufijos societarios y de plaza que agregan ruido a la comparación. */
    private const SUFIJOS_RUIDO = [
        'SA', 'SAS', 'SRL', 'SAU', 'INC', 'LLC', 'LTD', 'LTDA', 'CORP', 'CO', 'GMBH',
        'BV', 'NV', 'PLC', 'PTY', 'AG', 'AB', 'OY', 'SL', 'SPA', 'COM', 'IE', 'US',
        'AR', 'UY', 'BR', 'NL', 'GB', 'CA', 'SG', 'HTTPSADOBE',
    ];

    /** Debajo de esto no se propone match automático. */
    public const UMBRAL_SIMILITUD = 72.0;

    /**
     * Deja el comercio en mayúsculas sin acentos, sin pasarela, sin números de trámite
     * ni sufijos societarios. Es la clave con la que se guarda y se busca el alias.
     */
    public static function normalizar(?string $texto): string
    {
        $t = mb_strtoupper(trim((string) $texto), 'UTF-8');
        if ($t === '') {
            return '';
        }

        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ]);

        // El asterisco separa pasarela de comercio: "PAYPAL *NOTION" -> "PAYPAL NOTION".
        $t = (string) preg_replace('/[^A-Z0-9]+/', ' ', $t);
        $t = trim((string) preg_replace('/\s+/', ' ', $t));
        if ($t === '') {
            return '';
        }

        $tokens = explode(' ', $t);

        // Pasarela: solo si viene adelante, porque "SQUARE" en el medio sí es el comercio.
        while ($tokens !== [] && in_array($tokens[0], self::PREFIJOS_PASARELA, true)) {
            array_shift($tokens);
        }

        $tokens = array_values(array_filter($tokens, static function (string $tok): bool {
            if ($tok === '') {
                return false;
            }
            // Números de autorización, teléfonos y fechas del resumen.
            if (preg_match('/^\d+$/', $tok)) {
                return false;
            }

            // Token de una sola letra que quedó de un separador.
            return mb_strlen($tok) > 1;
        }));

        // Sufijos societarios: se podan desde el final para no comerse el nombre.
        while ($tokens !== [] && in_array(end($tokens), self::SUFIJOS_RUIDO, true) && count($tokens) > 1) {
            array_pop($tokens);
        }

        return mb_substr(implode(' ', $tokens), 0, 180);
    }

    /**
     * Puntaje 0-100 entre un comercio del resumen y un candidato (proveedor o servicio).
     *
     * Combina cobertura de tokens con similitud de cadena: el primer término pesa más
     * porque "ADOBE CREATIVE CLOUD" vs "ADOBE" tiene que dar alto aunque difiera el largo.
     */
    public static function similitud(string $comercioNormalizado, ?string $candidato): float
    {
        $a = $comercioNormalizado;
        $b = self::normalizar($candidato);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 100.0;
        }

        $tokensA = array_values(array_unique(explode(' ', $a)));
        $tokensB = array_values(array_unique(explode(' ', $b)));

        $comunes = 0;
        foreach ($tokensB as $tb) {
            foreach ($tokensA as $ta) {
                if ($ta === $tb || str_starts_with($ta, $tb) || str_starts_with($tb, $ta)) {
                    $comunes++;
                    break;
                }
            }
        }

        $cobertura = $comunes / max(1, min(count($tokensA), count($tokensB)));

        $porcentaje = 0.0;
        similar_text($a, $b, $porcentaje);

        return round(($cobertura * 100 * 0.65) + ($porcentaje * 0.35), 2);
    }

    /**
     * Últimos 4 dígitos a partir de cualquier formato del resumen (****4821, 4821, XXXX-4821).
     */
    public static function ult4(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);
        if ($digitos === null || strlen($digitos) < 4) {
            return null;
        }

        return substr($digitos, -4);
    }
}
