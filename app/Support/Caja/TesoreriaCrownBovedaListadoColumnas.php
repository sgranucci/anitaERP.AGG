<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Support\Listado\ListadoOrdenamientoSupport;

final class TesoreriaCrownBovedaListadoColumnas
{
    public const RECURSO = 'caja.tesoreria_crown_boveda';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'numero' => ['label' => 'Número', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda.numero', 'group' => 'boveda'],
        'tipo' => ['label' => 'Tipo', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda_tipo.abreviatura', 'group' => 'boveda'],
        'efecto' => ['label' => 'Efecto', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda_tipo.signo', 'group' => 'boveda'],
        'estado' => ['label' => 'Estado', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda.estado', 'group' => 'boveda'],
        'valor' => ['label' => 'Valor', 'default' => true, 'filterable' => true, 'type' => 'decimal', 'source' => 'tc_boveda_importe.importe', 'group' => 'boveda'],
        'comprobante' => ['label' => 'Comprobante', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_comprobante.numero', 'group' => 'boveda'],
        'comentario' => ['label' => 'Comentario', 'default' => true, 'filterable' => true, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda.comentario', 'group' => 'boveda'],
        'usuario' => ['label' => 'Confirmó', 'default' => false, 'filterable' => true, 'type' => 'texto', 'source' => 'usuario.nombre', 'group' => 'boveda'],
        'confirmado' => ['label' => 'Confirmado', 'default' => false, 'filterable' => false, 'type' => 'texto', 'source' => 'tesoreria_crown_boveda.confirmado_at', 'group' => 'boveda'],
    ];

    /** @var array<string, string> */
    public const GRUPOS = ['boveda' => 'Bóveda'];

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
        foreach (self::COLUMNAS as $key => $meta) {
            $column = (string) ($meta['source'] ?? '');
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
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
        if (! $fila instanceof TesoreriaCrownBoveda) {
            return '';
        }

        return match ($key) {
            'numero' => TesoreriaCrownNumeradorSupport::visible((int) $fila->numero),
            'tipo' => trim(($fila->tipo?->etiqueta() ?? '').($fila->revierte_id ? ' (reversa)' : '')),
            'efecto' => TesoreriaCrownBovedaSupport::efectoTextoConSubtipo(
                (string) ($fila->tipo->signo ?? '2'),
                $fila->subtipo->mueve_billetaje ?? null,
                $fila->subtipo->signo ?? null
            ),
            'estado' => TesoreriaCrownBovedaSupport::estado((string) $fila->estado),
            'valor' => self::textoValor($fila),
            'comprobante' => self::textoComprobante($fila),
            'comentario' => trim((string) ($fila->comentario ?? '').($fila->motivo ? ' — '.$fila->motivo : '')),
            'usuario' => (string) ($fila->usuario->nombre ?? ''),
            'confirmado' => $fila->confirmado_at?->format('d/m/Y H:i') ?? '',
            default => '',
        };
    }

    public static function textoComprobante(TesoreriaCrownBoveda $fila): string
    {
        if ($fila->comprobante === null) {
            return '';
        }
        $abrev = match ((string) $fila->comprobante->tipo) {
            TesoreriaCrownComprobanteSupport::HABILITACION => 'HAB',
            TesoreriaCrownComprobanteSupport::RENDICION => 'REN',
            default => strtoupper((string) $fila->comprobante->tipo),
        };

        return trim($abrev.' '.TesoreriaCrownNumeradorSupport::visible((int) $fila->comprobante->numero));
    }

    /**
     * @return array{ARS: float, USD: float, EUR: float}
     */
    public static function montosPorMoneda(TesoreriaCrownBoveda $fila): array
    {
        $out = ['ARS' => 0.0, 'USD' => 0.0, 'EUR' => 0.0];
        foreach (TesoreriaCrownBovedaSupport::importesMovimiento($fila) as $importe) {
            $codigo = strtoupper(trim((string) $importe['codigo']));
            if (! array_key_exists($codigo, $out)) {
                continue;
            }
            $out[$codigo] = round($out[$codigo] + (float) $importe['monto'], 2);
        }

        return $out;
    }

    private static function textoValor(TesoreriaCrownBoveda $fila): string
    {
        $partes = [];
        foreach (TesoreriaCrownBovedaSupport::importesMovimiento($fila) as $importe) {
            $partes[] = number_format((float) $importe['monto'], 2, ',', '.').' '.$importe['codigo'];
        }

        return implode(' · ', $partes);
    }
}
