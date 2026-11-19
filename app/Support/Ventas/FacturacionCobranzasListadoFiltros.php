<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte Facturación y cobranzas (Anita l-factycobr).
 */
final class FacturacionCobranzasListadoFiltros
{
    public const ORDEN_REPARTO = 'reparto';

    public const ORDEN_COMPROBANTE = 'comprobante';

    public const FUENTE_AMBOS = 'ambos';

    public const FUENTE_ERP = 'erp';

    public const FUENTE_ANITA = 'anita';

    public const INCLUYE_TODOS = 'todos';

    public const INCLUYE_FACTURAS = 'facturas';

    public const INCLUYE_COBRANZAS = 'cobranzas';

    /** @var list<string> */
    public const ORDENES = [self::ORDEN_REPARTO, self::ORDEN_COMPROBANTE];

    /** @var list<string> */
    public const FUENTES = [self::FUENTE_AMBOS, self::FUENTE_ERP, self::FUENTE_ANITA];

    /** @var list<string> */
    public const INCLUYES = [self::INCLUYE_TODOS, self::INCLUYE_FACTURAS, self::INCLUYE_COBRANZAS];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', '')),
            trim((string) $request->input('fecha_hasta', '')),
        );

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'orden' => self::normalizarOrden($request->input('orden')),
            'fuente' => self::normalizarFuente($request->input('fuente')),
            'incluye' => self::normalizarIncluye($request->input('incluye')),
            'solo_totales' => $request->boolean('solo_totales'),
            'moneda_origen' => $request->boolean('moneda_origen'),
            'moneda_id' => self::enteroOpcional($request->input('moneda_id')) ?? 1,
            'reparto_desde' => trim((string) $request->input('reparto_desde', '')),
            'reparto_hasta' => trim((string) $request->input('reparto_hasta', '')),
            'reparto_desde_nombre' => trim((string) $request->input('reparto_desde_nombre', '')),
            'reparto_hasta_nombre' => trim((string) $request->input('reparto_hasta_nombre', '')),
        ];
    }

    public static function normalizarOrden(mixed $valor): string
    {
        $v = (string) $valor;

        return in_array($v, self::ORDENES, true) ? $v : self::ORDEN_COMPROBANTE;
    }

    public static function normalizarFuente(mixed $valor): string
    {
        $v = (string) $valor;

        return in_array($v, self::FUENTES, true) ? $v : self::FUENTE_AMBOS;
    }

    public static function normalizarIncluye(mixed $valor): string
    {
        $v = (string) $valor;

        return in_array($v, self::INCLUYES, true) ? $v : self::INCLUYE_TODOS;
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $out['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $out['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        $out['orden'] = self::normalizarOrden($filtros['orden'] ?? null);
        $out['fuente'] = self::normalizarFuente($filtros['fuente'] ?? null);
        $out['incluye'] = self::normalizarIncluye($filtros['incluye'] ?? null);
        if (! empty($filtros['solo_totales'])) {
            $out['solo_totales'] = 1;
        }
        if (! empty($filtros['moneda_origen'])) {
            $out['moneda_origen'] = 1;
        }
        $out['moneda_id'] = (int) ($filtros['moneda_id'] ?? 1);
        if (($filtros['reparto_desde'] ?? '') !== '') {
            $out['reparto_desde'] = $filtros['reparto_desde'];
            $out['reparto_desde_nombre'] = (string) ($filtros['reparto_desde_nombre'] ?? '');
        }
        if (($filtros['reparto_hasta'] ?? '') !== '' && ($filtros['reparto_hasta'] ?? '') !== '999999') {
            $out['reparto_hasta'] = $filtros['reparto_hasta'];
            $out['reparto_hasta_nombre'] = (string) ($filtros['reparto_hasta_nombre'] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde === '' || $hasta === '') {
            return '';
        }

        return Carbon::parse($desde)->format('d/m/Y').' — '.Carbon::parse($hasta)->format('d/m/Y');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearOrdenTexto(array $filtros): string
    {
        return self::normalizarOrden($filtros['orden'] ?? null) === self::ORDEN_REPARTO
            ? 'Por reparto'
            : 'Por comprobante';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearFuenteTexto(array $filtros): string
    {
        return match (self::normalizarFuente($filtros['fuente'] ?? null)) {
            self::FUENTE_ERP => 'Solo ERP',
            self::FUENTE_ANITA => 'Solo Anita',
            default => 'ERP + Anita (sin duplicar)',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0:int,1:int}
     */
    public static function rangoRepartoCodigos(array $filtros): array
    {
        $desdeRaw = trim((string) ($filtros['reparto_desde'] ?? ''));
        $hastaRaw = trim((string) ($filtros['reparto_hasta'] ?? ''));
        $desde = $desdeRaw === '' ? 0 : (int) $desdeRaw;
        $hasta = $hastaRaw === '' || $hastaRaw === '999999' ? 999999 : (int) $hastaRaw;
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private static function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }
}
