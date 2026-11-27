<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\AplicacionCcClienteComprobante;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo de columnas del workbench de comprobantes de aplicación CC de clientes
 * (pantalla, export, QBE, cortes y etiquetas).
 *
 * «total» está en pesos (valor de libro de cada deuda): es la medida sumable en cortes y gráficos.
 * «total_moneda» queda en la moneda del comprobante y solo existe si todas las deudas comparten moneda.
 */
final class AplicacionCcClienteComprobanteListadoColumnas
{
    public const RECURSO = 'ventas.aplicacion_cc_cliente';

    public const TABLA = 'aplicacion_cc_cliente_comprobante';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'total', 'column' => 'aplicacion_cc_cliente_comprobante.total', 'label' => 'Total ($)'],
        ['key' => 'diferencia_cambio', 'column' => 'aplicacion_cc_cliente_comprobante.diferencia_cambio', 'label' => 'Dif. cambio'],
    ];

    public const GRUPO_COMPROBANTE = 'comprobante';

    public const GRUPO_IMPORTES = 'importes';

    public const GRUPO_CONTROL = 'control';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_COMPROBANTE => 'Comprobante',
        self::GRUPO_IMPORTES => 'Importes',
        self::GRUPO_CONTROL => 'Estado / control',
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        AplicacionCcClienteComprobante::ESTADO_EMITIDO => 'Emitido',
        AplicacionCcClienteComprobante::ESTADO_ANULADO => 'Anulado',
    ];

    /** @var array<string, string> */
    public const ORIGENES = [
        AplicacionCcClienteComprobante::ORIGEN_ERP => 'anitaERP',
        AplicacionCcClienteComprobante::ORIGEN_HISTORICO => 'Histórico',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const COLUMNAS = [
        'numero' => [
            'label' => 'Número',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'aplicacion_cc_cliente_comprobante.numero',
            'attr' => 'numero',
            'group' => self::GRUPO_COMPROBANTE,
            'sticky' => true,
            'ancho' => 100,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'aplicacion_cc_cliente_comprobante.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_COMPROBANTE,
            'ancho' => 78,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_COMPROBANTE,
            'ancho' => 95,
        ],
        'cliente_codigo' => [
            'label' => 'Cód. cliente',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.codigo',
            'attr' => 'codigocliente',
            'group' => self::GRUPO_COMPROBANTE,
            'ancho' => 100,
        ],
        'cliente' => [
            'label' => 'Cliente',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.nombre',
            'attr' => 'nombrecliente',
            'group' => self::GRUPO_COMPROBANTE,
            'ancho' => 150,
        ],
        'detalle' => [
            'label' => 'Comprobantes aplicados',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'aplicacion_cc_cliente_comprobante.detalle',
            'attr' => 'detalle',
            'group' => self::GRUPO_COMPROBANTE,
            'ancho' => 210,
        ],
        'cantidad_lineas' => [
            'label' => 'Líneas',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'aplicacion_cc_cliente_comprobante.cantidad_lineas',
            'attr' => 'cantidad_lineas',
            'group' => self::GRUPO_COMPROBANTE,
            'alinea' => 'derecha',
            'ancho' => 50,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.abreviatura',
            'attr' => 'abreviaturamoneda',
            'group' => self::GRUPO_IMPORTES,
            'ancho' => 70,
        ],
        'total_moneda' => [
            'label' => 'Total moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'aplicacion_cc_cliente_comprobante.total_moneda',
            'attr' => 'total_moneda',
            'group' => self::GRUPO_IMPORTES,
            'alinea' => 'derecha',
        ],
        'total' => [
            'label' => 'Total ($)',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'aplicacion_cc_cliente_comprobante.total',
            'attr' => 'total',
            'group' => self::GRUPO_IMPORTES,
            'alinea' => 'derecha',
            'ancho' => 100,
        ],
        'diferencia_cambio' => [
            'label' => 'Dif. cambio',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'aplicacion_cc_cliente_comprobante.diferencia_cambio',
            'attr' => 'diferencia_cambio',
            'group' => self::GRUPO_IMPORTES,
            'alinea' => 'derecha',
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'aplicacion_cc_cliente_comprobante.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_CONTROL,
            'ancho' => 72,
        ],
        'origen' => [
            'label' => 'Origen',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'aplicacion_cc_cliente_comprobante.origen',
            'attr' => 'origen',
            'group' => self::GRUPO_CONTROL,
        ],
        'usuario' => [
            'label' => 'Emitió',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'usr.nombre',
            'attr' => 'nombreusuario',
            'group' => self::GRUPO_CONTROL,
        ],
        'observacion' => [
            'label' => 'Observación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'aplicacion_cc_cliente_comprobante.observacion',
            'attr' => 'observacion',
            'group' => self::GRUPO_CONTROL,
            'ancho' => 220,
        ],
        'fecha_anulacion' => [
            'label' => 'Fecha anulación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'aplicacion_cc_cliente_comprobante.anulado_at',
            'attr' => 'anulado_at',
            'group' => self::GRUPO_CONTROL,
        ],
        'usuario_anulacion' => [
            'label' => 'Anuló',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'usr_anula.nombre',
            'attr' => 'nombreusuarioanula',
            'group' => self::GRUPO_CONTROL,
        ],
        'id' => [
            'label' => 'ID',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'aplicacion_cc_cliente_comprobante.id',
            'attr' => 'id',
            'group' => self::GRUPO_CONTROL,
        ],
        'fecha_registro' => [
            'label' => 'Fecha de registro',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'aplicacion_cc_cliente_comprobante.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_CONTROL,
        ],
        'fecha_modificacion' => [
            'label' => 'Fecha de modificación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'aplicacion_cc_cliente_comprobante.updated_at',
            'attr' => 'updated_at',
            'group' => self::GRUPO_CONTROL,
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
     * @return list<array{key: string, column: string, label: string}>
     */
    public static function medidasActivas(): array
    {
        return self::MEDIDAS;
    }

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::COLUMNAS as $key => $meta) {
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
        if ($solicitadas === null || $solicitadas === []) {
            return self::defaultsVisibles();
        }

        $keys = [];
        foreach ($solicitadas as $key) {
            $key = (string) $key;
            if (isset(self::COLUMNAS[$key]) && ! in_array($key, $keys, true)) {
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
        foreach (self::COLUMNAS as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
                'group' => $meta['group'],
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
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'] ?? 'texto',
                'column' => $column,
            ];
        }

        return $out;
    }

    public static function valorCelda(object $data, string $key): string
    {
        $meta = self::COLUMNAS[$key] ?? null;
        if ($meta === null) {
            return '';
        }

        if ($key === 'numero') {
            $numero = $data->numero ?? null;

            return $numero === null || $numero === '' ? '' : AplicacionCcClienteComprobante::formatearNumero($numero);
        }
        if ($key === 'estado') {
            $estado = (string) ($data->estado ?? '');

            return self::ESTADOS[$estado] ?? $estado;
        }
        if ($key === 'origen') {
            $origen = (string) ($data->origen ?? '');

            return self::ORIGENES[$origen] ?? $origen;
        }

        if ($key === 'detalle') {
            // Con varias líneas el texto guardado queda largo y truncado: se resume con los créditos usados.
            $lineas = (int) ($data->cantidad_lineas ?? 0);
            if ($lineas > 1) {
                $creditos = trim((string) ($data->creditos_resumen ?? ''));

                return $lineas.' comprobantes'.($creditos !== '' ? ' ← '.$creditos : '');
            }
        }

        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $data->{$attr} ?? '';
        $type = (string) ($meta['type'] ?? 'texto');
        if ($type === 'fecha') {
            if ($valor instanceof \DateTimeInterface) {
                $valor = $valor->format('Y-m-d');
            }

            return ListadoQbeSupport::formatearFecha($valor);
        }
        if ($type === 'decimal') {
            if ($valor === null || $valor === '') {
                return '';
            }

            return number_format((float) $valor, 2, ',', '.');
        }

        return (string) $valor;
    }

    /**
     * Valor numérico crudo para export Excel (importes como número).
     */
    public static function valorNumerico(object $data, string $key): ?float
    {
        $meta = self::COLUMNAS[$key] ?? null;
        if ($meta === null || ($meta['type'] ?? '') !== 'decimal') {
            return null;
        }
        $valor = $data->{(string) ($meta['attr'] ?? $key)} ?? null;

        return $valor === null || $valor === '' ? null : round((float) $valor, 2);
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::COLUMNAS[$key] ?? []);
    }
}
