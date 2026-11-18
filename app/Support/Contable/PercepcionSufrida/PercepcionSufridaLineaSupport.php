<?php

declare(strict_types=1);

namespace App\Support\Contable\PercepcionSufrida;

use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

final class PercepcionSufridaLineaSupport
{
    /** Rango AFIP / SIFERE Web (misma ventana que retenciones sufridas). */
    public const JURISDICCION_MIN = 901;

    public const JURISDICCION_MAX = 924;

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function armar(array $datos): array
    {
        $tipo = strtoupper(substr(trim((string) ($datos['tipo'] ?? '')), 0, 3));
        $letra = substr(trim((string) ($datos['letra'] ?? '')).' ', 0, 1);
        $sucursal = (int) ($datos['sucursal'] ?? 0);
        $nro = (int) ($datos['nro'] ?? 0);
        $comprobante = trim(MayorPlanoCuentaSupport::formatearComprobante($tipo, $letra, $sucursal, $nro));
        $importe = round((float) ($datos['importe'] ?? 0), 2);
        $jurisdiccion = (int) ($datos['jurisdiccion'] ?? 0);
        if ($jurisdiccion === 0) {
            $jurisdiccion = self::jurisdiccionDesdeTexto((string) ($datos['descripcion'] ?? ''));
        }

        $linea = [
            'fecha' => substr((string) ($datos['fecha'] ?? ''), 0, 10),
            'tipo' => $tipo,
            'letra' => trim($letra),
            'sucursal' => $sucursal,
            'nro' => $nro,
            'comprobante' => $comprobante,
            'clave_comprobante' => $tipo.'|'.$comprobante,
            'emisor' => trim((string) ($datos['emisor'] ?? '')),
            'emisor_nombre' => trim((string) ($datos['emisor_nombre'] ?? '')),
            'cuit' => self::cuit11((string) ($datos['cuit'] ?? '')),
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
            'importe' => $importe,
            'jurisdiccion' => $jurisdiccion,
            'origen' => (string) ($datos['origen'] ?? ''),
            'moneda_id' => (int) ($datos['moneda_id'] ?? 0),
            'cotizacion' => (float) ($datos['cotizacion'] ?? 0),
            'cod_mon' => trim((string) ($datos['cod_mon'] ?? '')),
        ];
        $linea['clave_importe'] = $linea['clave_comprobante'].'|'.number_format($importe, 2, '.', '');

        return $linea;
    }

    public static function esJurisdiccionSifere(int $jurisdiccion): bool
    {
        return $jurisdiccion >= self::JURISDICCION_MIN && $jurisdiccion <= self::JURISDICCION_MAX;
    }

    public static function etiquetaJurisdiccion(int $jurisdiccion): string
    {
        return match ($jurisdiccion) {
            901 => '901 (CABA)',
            902 => '902 (Buenos Aires)',
            915 => '915 (Neuquén)',
            916 => '916 (Río Negro)',
            919 => '919 (San Luis)',
            924 => '924 (Tucumán)',
            default => (string) $jurisdiccion,
        };
    }

    /**
     * Código AFIP de la provincia del concepto. Prioriza provincia.jurisdiccion;
     * si falta, reconoce CABA / Bs.As. / RN / NQN por nombre o código.
     */
    public static function jurisdiccionDesdeProvincia(object $fila): int
    {
        $jur = (int) ($fila->jurisdiccion ?? 0);
        if (self::esJurisdiccionSifere($jur)) {
            return $jur;
        }

        $provincia = new Provincia();
        $provincia->nombre = (string) ($fila->provincia_nombre ?? $fila->nombre ?? '');
        $provincia->codigo = (string) ($fila->provincia_codigo ?? $fila->codigo ?? '');
        $provincia->jurisdiccion = (string) ($fila->jurisdiccion ?? '');
        $provincia->codigoexterno = (string) ($fila->codigoexterno ?? '');

        if (IngresosBrutosProvinciaAnitaSupport::esCaba($provincia)) {
            return 901;
        }
        if (IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia)) {
            return 902;
        }
        if (IngresosBrutosProvinciaAnitaSupport::esRioNegro($provincia)) {
            return 916;
        }

        $nombre = self::normalizar((string) $provincia->nombre);
        if (str_contains($nombre, 'neuquen') || str_contains($nombre, 'nqn')) {
            return 915;
        }
        if (str_contains($nombre, 'san luis')) {
            return 919;
        }
        if (str_contains($nombre, 'tucuman')) {
            return 924;
        }

        return self::jurisdiccionDesdeTexto(
            (string) ($fila->concepto ?? $fila->descripcion ?? $provincia->nombre)
        );
    }

    public static function jurisdiccionDesdeTexto(string $texto): int
    {
        $t = self::normalizar($texto);
        if ($t === '') {
            return 0;
        }
        if (preg_match('/\b(9(?:0[1-9]|1[0-9]|2[0-4]))\b/', $t, $m) === 1) {
            return (int) $m[1];
        }
        if (str_contains($t, 'capital') || str_contains($t, 'caba')) {
            return 901;
        }
        if (
            str_contains($t, 'bsas')
            || str_contains($t, 'bs as')
            || str_contains($t, 'buenos aires')
            || str_contains($t, 'arba')
        ) {
            return 902;
        }
        if (str_contains($t, 'neuquen') || str_contains($t, 'nqn')) {
            return 915;
        }
        if (str_contains($t, 'rio negro') || str_contains($t, 'r negro')) {
            return 916;
        }
        if (str_contains($t, 'san luis')) {
            return 919;
        }
        if (str_contains($t, 'tucuman')) {
            return 924;
        }

        return 0;
    }

    public static function esTextoPercepcionIva(string $texto): bool
    {
        $t = self::normalizar($texto);

        return str_contains($t, 'percepcion iva') || str_contains($t, 'perc iva') || str_contains($t, 'perc. iva');
    }

    public static function cuit11(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) !== 11 || $d === '00000000000') {
            return '';
        }

        return $d;
    }

    public static function cuitInformado(string $cuit): string
    {
        $d = self::cuit11($cuit);
        if ($d === '') {
            return '';
        }

        return IngresosBrutosFormatoArbaSupport::normalizarCuit($d);
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', '.'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', ' '],
            $t,
        );
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return trim($t);
    }
}
