<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\ApiAnita;
use App\Models\Ventas\CertificadoSanitario;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Numeración de solicitud SENASA como p-certsan.c:
 * SER (t_comp) → serie A/B/C…; numabm p-certsan.c → nro solicitud;
 * CSI / CSP → certificado interno / patagónico.
 *
 * Por ahora manda Anita; cuando el ERP tenga numerador propio se apaga con SENASA_NUMERACION_ANITA=false.
 */
final class CertificadoSanitarioAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return filter_var(config('senasa.numeracion_anita.habilitada', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{serie: string, numero: int, nro_interno: ?int, nro_patagonico: ?int}
     */
    public static function reservar(bool $patagonico): array
    {
        $tope = max(1, (int) config('senasa.numeracion_anita.tope_por_serie', 10000));
        $index = self::indiceSerieActual();

        for ($paso = 0; $paso < 26; $paso++) {
            $letra = self::letra($index);
            $ultimoNumabm = self::leerNumabm($index);
            $maxErp = self::maxErpSerie($letra);
            $numero = max($ultimoNumabm, $maxErp) + 1;

            if ($numero <= $tope) {
                if ($index !== self::indiceSerieActual()) {
                    self::actualizarSer($index + 1);
                }
                self::actualizarNumabm($index, $numero);

                $interno = $patagonico ? null : self::reservarComprobante('CSI');
                $patag = $patagonico ? self::reservarComprobante('CSP') : null;

                Log::info('CertificadoSanitarioAnitaNumeracion: reservado', [
                    'serie' => $letra,
                    'numero' => $numero,
                    'nro_interno' => $interno,
                    'nro_patagonico' => $patag,
                ]);

                return [
                    'serie' => $letra,
                    'numero' => $numero,
                    'nro_interno' => $interno,
                    'nro_patagonico' => $patag,
                ];
            }

            $index++;
        }

        throw new RuntimeException('Se agotaron las series de certificado SENASA (A-Z) en Anita.');
    }

    private static function indiceSerieActual(): int
    {
        $ordinal = self::leerNumeradorTComp('SER');
        $index = max(0, $ordinal - 1);

        return min(25, $index);
    }

    private static function letra(int $index): string
    {
        return chr(ord('A') + max(0, min(25, $index)));
    }

    private static function maxErpSerie(string $serie): int
    {
        return (int) CertificadoSanitario::query()->where('serie', $serie)->max('numero');
    }

    private static function leerNumabm(int $referencia): int
    {
        $sistemaAbm = self::escSqlLiteral((string) config('senasa.numeracion_anita.numabm_sistema', 'ventas'));
        $programa = self::escSqlLiteral((string) config('senasa.numeracion_anita.numabm_programa', 'p-certsan.c'));
        $ref = self::escSqlLiteral((string) $referencia);

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('senasa.numeracion_anita.sistema_shared', 'shared'),
            'tabla' => 'numabm',
            'campos' => 'numa_ult_numero',
            'whereArmado' => " WHERE numa_sistema='{$sistemaAbm}' AND numa_programa='{$programa}' AND numa_referencia='{$ref}'",
        ], 'certsan numabm lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer numabm Anita (p-certsan.c ref '.$referencia.'): '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->numa_ult_numero)) {
            throw new RuntimeException(
                'numabm inexistente para p-certsan.c referencia '.$referencia.' (serie '.self::letra($referencia).').'
            );
        }

        return max(0, (int) $fila->numa_ult_numero);
    }

    private static function actualizarNumabm(int $referencia, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de solicitud SENASA inválido.');
        }

        $sistemaAbm = self::escSqlLiteral((string) config('senasa.numeracion_anita.numabm_sistema', 'ventas'));
        $programa = self::escSqlLiteral((string) config('senasa.numeracion_anita.numabm_programa', 'p-certsan.c'));
        $ref = self::escSqlLiteral((string) $referencia);

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('senasa.numeracion_anita.sistema_shared', 'shared'),
            'tabla' => 'numabm',
            'valores' => 'numa_ult_numero = '.(int) $numero,
            'whereArmado' => " WHERE numa_sistema='{$sistemaAbm}' AND numa_programa='{$programa}' AND numa_referencia='{$ref}'",
        ], 'certsan numabm update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo actualizar numabm Anita (p-certsan.c): '.$err);
        }
    }

    private static function actualizarSer(int $ordinal1Based): void
    {
        self::actualizarNumeradorTComp('SER', $ordinal1Based);
    }

    private static function reservarComprobante(string $tcompClave): int
    {
        $ultimo = self::leerNumeradorTComp($tcompClave);
        $columna = $tcompClave === 'CSP' ? 'nro_cert_patagonico' : 'nro_cert_interno';
        $maxErp = (int) CertificadoSanitario::query()->max($columna);
        $siguiente = max($ultimo, $maxErp) + 1;
        self::actualizarNumeradorTComp($tcompClave, $siguiente);

        return $siguiente;
    }

    private static function leerNumeradorTComp(string $tcompClave): int
    {
        $claveNumerador = self::resolverClaveNumerador($tcompClave);
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('senasa.numeracion_anita.sistema_ventas', 'ventas'),
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave = '".$clave."'",
        ], 'certsan numerador '.$tcompClave.' lectura');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer numerador Anita '.$tcompClave.': '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new RuntimeException('Numerador Anita inexistente para t_comp '.$tcompClave.' (num_clave='.$claveNumerador.').');
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    private static function actualizarNumeradorTComp(string $tcompClave, int $numero): void
    {
        if ($numero < 0) {
            throw new \InvalidArgumentException('Número Anita inválido para '.$tcompClave.'.');
        }

        $claveNumerador = self::resolverClaveNumerador($tcompClave);
        $clave = self::escSqlLiteral($claveNumerador);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => (string) config('senasa.numeracion_anita.sistema_ventas', 'ventas'),
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => " WHERE num_clave = '".$clave."'",
        ], 'certsan numerador '.$tcompClave.' update');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo actualizar numerador Anita '.$tcompClave.': '.$err);
        }
    }

    private static function resolverClaveNumerador(string $tcompClave): string
    {
        $clave = self::escSqlLiteral($tcompClave);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('senasa.numeracion_anita.sistema_ventas', 'ventas'),
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => " WHERE tcomp_clave = '".$clave."'",
        ], 'certsan t_comp '.$tcompClave);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer t_comp ('.$tcompClave.') en Anita: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '') {
            throw new RuntimeException('t_comp sin tcomp_refer para clave '.$tcompClave.' en Anita ventas.');
        }

        return $refer;
    }

    private static function escSqlLiteral(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
