<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownMovimientoListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_movimiento';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'numero' => ['label' => 'Número', 'default' => true, 'filterable' => true, 'type' => 'entero', 'source' => 'tesoreria_crown_comprobante.numero', 'group' => 'comprobante'],
        'fecha' => ['label' => 'Fecha', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_jornada.fecha', 'group' => 'comprobante'],
        'tipo' => ['label' => 'Tipo', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_comprobante.tipo', 'group' => 'comprobante'],
        'caja' => ['label' => 'Caja', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_deposito.nombre', 'group' => 'comprobante'],
        'cajero' => ['label' => 'Cajero', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'cajero_crown.nombre', 'group' => 'comprobante'],
        'estado' => ['label' => 'Estado', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_comprobante.estado', 'group' => 'comprobante'],
        'entrega' => ['label' => 'Entrega', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_comprobante.entrega_at', 'group' => 'comprobante'],
        'recibe' => ['label' => 'Recibe', 'default' => true, 'filterable' => true, 'type' => 'fecha', 'source' => 'tesoreria_crown_comprobante.recibe_at', 'group' => 'comprobante'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['comprobante' => 'Comprobante'];

    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

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

    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::camposFiltrables() as $key => $meta) {
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($meta['column'])) {
                continue;
            }
            $out[$key] = $meta;
        }

        return $out;
    }

    public static function valorCelda(object $fila, string $key): string
    {
        return match ($key) {
            'numero' => TesoreriaCrownNumeradorSupport::visible(isset($fila->numero) ? (int) $fila->numero : null),
            'fecha' => $fila->jornada?->fecha?->format('d/m/Y') ?? '',
            'tipo' => TesoreriaCrownComprobanteSupport::tipo((string) $fila->tipo).($fila->revierte_id ? ' (reversa)' : ''),
            'caja' => trim((string) (($fila->asignacion->deposito->codigo ?? '').' — '.($fila->asignacion->deposito->nombre ?? '')), ' —'),
            'cajero' => (string) ($fila->asignacion->cajero->nombre ?? ''),
            'estado' => TesoreriaCrownComprobanteSupport::estado((string) $fila->estado),
            'entrega' => $fila->entrega_at?->format('d/m/Y H:i') ?? '',
            'recibe' => $fila->recibe_at?->format('d/m/Y H:i') ?? '',
            default => '',
        };
    }
}
