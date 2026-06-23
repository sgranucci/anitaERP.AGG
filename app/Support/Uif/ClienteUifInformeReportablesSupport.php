<?php

namespace App\Support\Uif;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClienteUifInformeReportablesSupport
{
    /**
     * @param  Collection<int, object>|iterable<int, object>  $premios
     */
    public static function tituloInformeExcel(string $periodo, ?string $nombreEmpresa = null): string
    {
        $partes = parsePeriodoMesAnio($periodo);
        $razonSocial = self::nombreEmpresaInforme($nombreEmpresa);

        return sprintf(
            'Informe de Datos de Clientes UIF - %s Periodo: %d/%d',
            $razonSocial,
            $partes['mes'],
            $partes['anio']
        );
    }

    public static function nombreEmpresaInforme(?string $nombreEmpresa): string
    {
        $nombre = trim((string) $nombreEmpresa);

        if ($nombre !== '') {
            return $nombre;
        }

        return (string) config('uif.INFORME_RAZON_SOCIAL', 'Argentina Gaming Group S.A.');
    }

    public static function nombreArchivoReportables(string $periodo, ?string $nombreEmpresa = null): string
    {
        $partes = parsePeriodoMesAnio($periodo);
        $empresa = self::nombreEmpresaInforme($nombreEmpresa);
        $empresaSlug = str_replace(['.', ','], '', $empresa);
        $empresaSlug = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9]+/', ' ', $empresaSlug) ?? 'UIF') ?? 'UIF');

        return sprintf('%s Reportables %02d %d', $empresaSlug, $partes['mes'], $partes['anio']);
    }

    public static function directorioExportacionXml(string $periodo, int $empresaId): string
    {
        $base = trim((string) config('uif.EXPORTACION_XML_PATH', 'tmp/exportacion_uif'), '/');
        $slug = self::slugPeriodoExportacion($periodo);

        return $base.'/'.$empresaId.'/'.$slug;
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $premios
     * @deprecated Usar nombreEmpresaInforme con la empresa del filtro.
     */
    public static function empresasTituloInforme(iterable $premios): string
    {
        $nombres = collect($premios)
            ->pluck('nombreempresa')
            ->filter(fn ($nombre) => is_string($nombre) && trim($nombre) !== '')
            ->map(fn ($nombre) => trim((string) $nombre))
            ->unique()
            ->sort()
            ->values();

        if ($nombres->count() === 1) {
            return $nombres->first();
        }

        if ($nombres->count() > 1) {
            return $nombres->implode(' / ');
        }

        return self::nombreEmpresaInforme(null);
    }

    public static function slugPeriodoExportacion(string $periodo): string
    {
        $partes = parsePeriodoMesAnio($periodo);

        return sprintf('%04d-%02d', $partes['anio'], $partes['mes']);
    }

    public static function idClienteInforme(object $premio): int
    {
        $legacy = (int) ($premio->inroclienteid ?? 0);

        return $legacy > 0 ? $legacy : (int) ($premio->clienteid ?? 0);
    }

    public static function tipoDocumentoInforme(?string $abreviatura): string
    {
        $map = [
            'DNI' => 'D.N.I.',
            'LE' => 'L.E.',
            'LC' => 'L.C.',
            'PAS' => 'Pasaporte',
            'CDI' => 'C.D.I.',
        ];

        $clave = strtoupper(trim((string) $abreviatura));

        return $map[$clave] ?? (string) $abreviatura;
    }

    public static function sexoInforme(?string $sexo): string
    {
        $valor = mb_strtolower(trim((string) $sexo));

        return match ($valor) {
            'm', 'masculino' => 'Masculino',
            'f', 'femenino' => 'Femenino',
            default => ucfirst($valor),
        };
    }

    public static function pepInforme(?string $nombre): string
    {
        $valor = mb_strtoupper(trim((string) $nombre));

        return match ($valor) {
            'NO EXPUESTO' => 'No expuesta',
            'SI EXPUESTO' => 'Si expuesta',
            default => self::tituloPalabras($nombre),
        };
    }

    public static function soInforme(?string $nombre): string
    {
        $valor = mb_strtoupper(trim((string) $nombre));

        return match ($valor) {
            'NO OBLIGADO' => 'No obligado',
            'SI OBLIGADO' => 'Si obligado',
            default => self::tituloPalabras($nombre),
        };
    }

    public static function resideInforme(?string $valor): string
    {
        $clave = mb_strtoupper(trim((string) $valor));

        return match ($clave) {
            'N', 'NO RESIDE' => 'No reside',
            'S', 'RESIDE' => 'Reside',
            default => self::tituloPalabras($valor),
        };
    }

    public static function monedaInforme(?string $nombre): string
    {
        $valor = mb_strtolower(trim((string) $nombre));

        return match ($valor) {
            'pesos' => 'Pesos',
            'dolares', 'dólares' => 'Dólares',
            'euros' => 'Euros',
            default => self::tituloPalabras($nombre),
        };
    }

    public static function paisInforme(?string $nombre): string
    {
        if ($nombre === null || trim($nombre) === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower(trim($nombre)), MB_CASE_TITLE, 'UTF-8');
    }

    public static function fechaInforme($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        return Carbon::parse($fecha)->format('d/m/Y');
    }

    public static function horaInforme($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        return Carbon::parse($fecha)->format('G:i');
    }

    public static function esArgentina(?int $paisUifId, ?string $nombrePais): bool
    {
        if ((int) $paisUifId === (int) config('uif.PAIS_ARGENTINA_ID', 5)) {
            return true;
        }

        return mb_strtoupper(trim((string) $nombrePais)) === 'ARGENTINA';
    }

    public static function textoInforme(?string $texto): string
    {
        return self::tituloPalabras($texto);
    }

    private static function tituloPalabras(?string $texto): string
    {
        if ($texto === null || trim($texto) === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower(trim($texto)), MB_CASE_TITLE, 'UTF-8');
    }
}
