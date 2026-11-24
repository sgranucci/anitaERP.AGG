<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

final class CobranzaListadoColumnas
{
    public const RECURSO = 'caja.cobranza';

    public const GRUPO_COBRANZA = 'cobranza';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_COBRANZA => 'Cobranza',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'cobranza.id',
            'attr' => 'id',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 60,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_COBRANZA,
        ],
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cobranza.numerotransaccion',
            'attr' => 'numerotransaccion',
            'group' => self::GRUPO_COBRANZA,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'cobranza.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_COBRANZA,
        ],
        'cliente' => [
            'label' => 'Cliente',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.nombre',
            'attr' => 'nombrecliente',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 150,
        ],
        'detalle' => [
            'label' => 'Detalle',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cobranza.detalle',
            'attr' => 'detalle',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 130,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cobranza.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_COBRANZA,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.abreviatura',
            'attr' => 'abreviatura_moneda',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 70,
        ],
        'monto' => [
            'label' => 'Monto',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'decimal',
            'source' => '',
            'attr' => 'monto',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 100,
        ],
        'prom_dias' => [
            'label' => 'Prom. días',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'decimal',
            'source' => '',
            'attr' => 'promedio_pago_dias',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 80,
        ],
        'movimientos' => [
            'label' => 'Movimientos',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'movimientos',
            'group' => self::GRUPO_COBRANZA,
            'ancho' => 170,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (! empty($meta['default'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>|null  $solicitadas
     * @return list<string>
     */
    public static function normalizarVisibles(?array $solicitadas): array
    {
        $permitidas = array_keys(self::catalogoActivo());
        if ($solicitadas === null || $solicitadas === []) {
            return self::defaultsVisibles();
        }
        $keys = [];
        foreach ($solicitadas as $key) {
            $key = (string) $key;
            if (in_array($key, $permitidas, true) && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? self::defaultsVisibles() : $keys;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable']) || ($meta['source'] ?? '') === '') {
                continue;
            }
            $out[$key] = [
                'label' => (string) $meta['label'],
                'type' => (string) $meta['type'],
                'column' => (string) $meta['source'],
                'group' => (string) ($meta['group'] ?? self::GRUPO_COBRANZA),
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
        foreach (self::camposFiltrables() as $key => $meta) {
            $column = (string) $meta['column'];
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
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

    /**
     * Líneas de texto del resumen de movimientos (cuentas de caja, retenciones y totales de cheques por moneda).
     *
     * @return list<string>
     */
    public static function lineasMovimientos(object $data): array
    {
        $lineas = [];

        $movimientos = $data->caja_movimientos ?? null;
        $movimientoCaja = $movimientos ? $movimientos->first() : null;
        if ($movimientoCaja && $movimientoCaja->caja_movimiento_cuentacajas) {
            foreach ($movimientoCaja->caja_movimiento_cuentacajas as $mov) {
                $monto = (float) $mov->monto;
                $lineas[] = trim(
                    (string) ($mov->cuentacajas->nombre ?? '').' '.($monto != 0.0 ? number_format($monto, 2) : '')
                );
            }
        }

        if ($data->cobranza_retenciones ?? null) {
            foreach ($data->cobranza_retenciones as $retencion) {
                $lineas[] = trim(
                    (string) ($retencion->retencion_cobranzas->nombre ?? '').' '.number_format((float) $retencion->monto, 2)
                );
            }
        }

        $totales = [];
        $abreviaturas = [];
        foreach (($data->cheques ?? []) as $cheque) {
            $monedaId = (int) $cheque->moneda_id;
            $totales[$monedaId] = ($totales[$monedaId] ?? 0.0) + (float) $cheque->monto;
            $abreviaturas[$monedaId] = (string) ($cheque->monedas->abreviatura ?? '');
        }
        ksort($totales);
        foreach ($totales as $monedaId => $total) {
            if ($total != 0.0) {
                $lineas[] = 'Total cheques en '.$abreviaturas[$monedaId].' '.number_format($total, 2);
            }
        }

        return $lineas;
    }

    public static function valorCelda(object $data, string $key): string
    {
        if ($key === 'moneda') {
            $abrev = $data->abreviatura_moneda ?? null;
            if ($abrev === null || $abrev === '') {
                $abrev = $data->monedas->abreviatura ?? '';
            }

            return (string) $abrev;
        }
        if ($key === 'monto') {
            return number_format((float) ($data->monto ?? 0), 2, ',', '.');
        }
        if ($key === 'prom_dias') {
            $dias = $data->promedio_pago_dias ?? null;

            return $dias === null || $dias === '' ? '—' : (string) $dias;
        }
        if ($key === 'movimientos') {
            return implode(' | ', self::lineasMovimientos($data));
        }

        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null) {
            return '';
        }
        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        if (($meta['type'] ?? 'texto') === 'fecha') {
            return ListadoQbeSupport::formatearFecha($valor);
        }
        if ($valor === null) {
            return '';
        }

        return (string) $valor;
    }

    public static function valorAgrupacion(object $data, string $key): string
    {
        $texto = trim(self::valorCelda($data, $key));

        return $texto === '' ? '(vacío)' : $texto;
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
