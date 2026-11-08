<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownKardexValoresListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_kardex_valores';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'fecha' => ['label' => 'Fecha', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'j.fecha', 'group' => 'kardex'],
        'deposito' => ['label' => 'Depósito', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'd.codigo', 'group' => 'kardex'],
        'abreviatura' => ['label' => 'Abv', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 't.abreviatura', 'group' => 'kardex'],
        'numero' => ['label' => 'Número', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'b.numero', 'group' => 'kardex'],
        'tipo' => ['label' => 'Tipo', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 't.nombre', 'group' => 'kardex'],
        'subtipo' => ['label' => 'Subtipo', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'st.nombre', 'group' => 'kardex'],
        'valor' => ['label' => 'Valor', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'v.denominacion', 'group' => 'kardex'],
        'entrada' => ['label' => 'Entrada', 'default' => true, 'filterable' => false, 'type' => 'decimal', 'source' => 'l.cantidad', 'group' => 'kardex'],
        'salida' => ['label' => 'Salida', 'default' => true, 'filterable' => false, 'type' => 'decimal', 'source' => 'l.cantidad', 'group' => 'kardex'],
        'saldo' => ['label' => 'Saldo', 'default' => true, 'filterable' => false, 'type' => 'decimal', 'source' => 'l.cantidad', 'group' => 'kardex'],
        'monto' => ['label' => 'Monto', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tc_kardex_monto.monto', 'group' => 'kardex'],
        'comentario' => ['label' => 'Comentario', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'b.comentario', 'group' => 'kardex'],
        'efecto' => ['label' => 'Efecto', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 't.signo', 'group' => 'kardex'],
        'operacion' => ['label' => 'Operación', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 't.operacion', 'group' => 'kardex'],
        'moneda' => ['label' => 'Moneda', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 'tv.codigo', 'group' => 'kardex'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['kardex' => 'Kardex valores'];

    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::COLUMNAS as $key => $meta) {
            $column = (string) ($meta['source'] ?? '');
            if ($column === '' || str_contains($column, 'tc_kardex_monto')) {
                continue;
            }
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column) && ! preg_match('/^[a-z_]+\.[a-z_]+$/i', $column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $column,
            ];
        }

        return $out;
    }

    public static function valorCelda(object $fila, string $key): string
    {
        $enriquecidas = TesoreriaCrownKardexValoresSupport::enriquecer([$fila]);
        $fila = $enriquecidas[0] ?? $fila;

        return match ($key) {
            'fecha' => $fila->fecha instanceof \DateTimeInterface
                ? $fila->fecha->format('d/m/Y')
                : (string) (\Carbon\Carbon::parse((string) $fila->fecha)->format('d/m/Y')),
            'deposito' => trim(($fila->deposito_codigo ?? '').' '.($fila->deposito_tipo ?? '')),
            'abreviatura' => (string) ($fila->abreviatura ?? ''),
            'numero' => (string) ($fila->numero_visible ?? TesoreriaCrownNumeradorSupport::visible((int) ($fila->numero ?? 0))),
            'tipo' => (string) ($fila->tipo_nombre ?? ''),
            'subtipo' => (string) ($fila->subtipo_etiqueta ?? ''),
            'valor' => number_format((float) ($fila->denominacion ?? 0), 0, ',', '.').' '.($fila->moneda ?? ''),
            'entrada' => $fila->entrada !== null ? number_format((float) $fila->entrada, 0, ',', '.') : '',
            'salida' => $fila->salida !== null ? number_format((float) $fila->salida, 0, ',', '.') : '',
            'saldo' => isset($fila->saldo_acum) ? number_format((float) $fila->saldo_acum, 0, ',', '.') : '',
            'monto' => number_format((float) ($fila->monto ?? 0), 2, ',', '.'),
            'comentario' => (string) ($fila->comentario ?? ''),
            'efecto' => (string) ($fila->efecto_texto ?? ''),
            'operacion' => (string) ($fila->operacion ?? ''),
            'moneda' => (string) ($fila->moneda ?? ''),
            default => '',
        };
    }
}
