<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Transporte;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Guía suburbano (Ferli): listado Excel estilo Anita controlrem / lista_control.
 */
final class CotGuiaSuburbanoSupport
{
    public static function habilitadoEnEntorno(): bool
    {
        return EntornoEmpresaSupport::esFerli() && CotConfiguracionSupport::esPorGuia();
    }

    public static function esSuburbano(?Transporte $transporte): bool
    {
        if ($transporte === null) {
            return false;
        }

        return self::esSuburbanoPorTexto(
            (string) ($transporte->nombre ?? ''),
            (string) ($transporte->codigo ?? '')
        );
    }

    public static function esSuburbanoPorTexto(string $nombre, string $codigo = ''): bool
    {
        $nombre = mb_strtoupper(trim($nombre));
        if ($nombre !== '' && str_contains($nombre, 'SUBURBANO')) {
            return true;
        }

        // Código histórico Ferli del expreso suburbano (VELOCE).
        return trim($codigo) === '88';
    }

    /**
     * Separa altura numérica al final del domicilio (misma lógica que a-controlrem.c).
     *
     * @return array{direccion: string, altura: string}
     */
    public static function separarDireccionYAltura(string $domicilio): array
    {
        $domicilio = trim($domicilio);
        if ($domicilio === '') {
            return ['direccion' => '', 'altura' => ''];
        }

        $chars = mb_str_split($domicilio);
        $len = count($chars);
        $jj = $len - 1;
        while ($jj >= 0) {
            $ch = $chars[$jj];
            if (! ctype_digit($ch) && $ch !== ' ') {
                break;
            }
            $jj--;
        }

        $altura = '';
        $flBusca = false;
        for ($kk = $jj; $kk < $len; $kk++) {
            $ch = $chars[$kk];
            if (! ctype_digit($ch)) {
                $flBusca = true;
            }
            if (ctype_digit($ch) && $flBusca) {
                $altura .= $ch;
                $chars[$kk] = ' ';
            }
        }

        return [
            'direccion' => trim(implode('', $chars)),
            'altura' => $altura,
        ];
    }

    public static function etiquetaFactura(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        $letra = trim($letra) !== '' ? trim($letra) : 'A';

        return sprintf('%s%04d-%08d', $letra, $sucursal, $numero);
    }

    public static function contactoConTelefono(?string $contacto, ?string $telefono): string
    {
        $contacto = trim((string) $contacto);
        $telefono = trim((string) $telefono);
        if ($contacto === '') {
            return $telefono;
        }
        if ($telefono === '') {
            return $contacto;
        }
        if (preg_match('/\d{4,}/', $contacto)) {
            return $contacto;
        }

        return $contacto.' '.$telefono;
    }
}
