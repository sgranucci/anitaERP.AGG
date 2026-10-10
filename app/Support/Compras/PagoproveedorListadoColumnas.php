<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Catálogo de la grilla unificada de pago a proveedores (OP + OPP/OPA de ingresos y egresos).
 */
final class PagoproveedorListadoColumnas
{
    public const RECURSO = 'compras.pagoproveedor';

    public const GRUPO_PAGO = 'pago';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_PAGO => 'Pago',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'listado_op.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_PAGO,
        ],
        'op' => [
            'label' => 'OP',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'listado_op.numerotransaccion',
            'attr' => 'op',
            'group' => self::GRUPO_PAGO,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.nombreempresa',
            'attr' => 'empresa',
            'group' => self::GRUPO_PAGO,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.nombreproveedor',
            'attr' => 'proveedor',
            'group' => self::GRUPO_PAGO,
        ],
        'detalle' => [
            'label' => 'Descripción',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.detalle',
            'attr' => 'detalle',
            'group' => self::GRUPO_PAGO,
        ],
        'cuentas' => [
            'label' => 'Cuentas de caja',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'cuentas',
            'group' => self::GRUPO_PAGO,
        ],
        'monto' => [
            'label' => 'Monto',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'listado_op.monto',
            'attr' => 'monto',
            'alinea' => 'derecha',
            'group' => self::GRUPO_PAGO,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_PAGO,
        ],
        'mail' => [
            'label' => 'Mail',
            'default' => true,
            'export' => false,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'mail',
            'alinea' => 'centro',
            'group' => self::GRUPO_PAGO,
        ],
        'id' => [
            'label' => 'ID',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'listado_op.pk_id',
            'attr' => 'id',
            'group' => self::GRUPO_PAGO,
        ],
        'origen' => [
            'label' => 'Origen',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.origen',
            'attr' => 'origen',
            'group' => self::GRUPO_PAGO,
        ],
        'tipocomprobante' => [
            'label' => 'Tipo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.tipocomprobante',
            'attr' => 'tipocomprobante',
            'group' => self::GRUPO_PAGO,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'listado_op.moneda_abrev',
            'attr' => 'moneda',
            'group' => self::GRUPO_PAGO,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    public static function valorCelda(object $row, string $key): string
    {
        if ($row instanceof PagoproveedorListadoFila) {
            return match ($key) {
                'fecha' => $row->fecha?->format('d/m/Y') ?? '',
                'op' => $row->etiquetaComprobante(),
                'empresa' => $row->nombreEmpresa,
                'proveedor' => $row->nombreProveedor,
                'detalle' => $row->detalleIndicativo(),
                'cuentas' => $row->cuentasCaja,
                'monto' => number_format($row->monto, 2, ',', '.').($row->monedaAbreviatura !== '' ? ' '.$row->monedaAbreviatura : ''),
                'estado' => $row->estado,
                'mail' => $row->esIeOpp() ? '' : ($row->mailEnviado ? 'Enviado' : 'Sin enviar'),
                'id' => (string) $row->id,
                'origen' => $row->esIeOpp() ? 'Ingresos y egresos' : 'Orden de pago',
                'tipocomprobante' => $row->etiquetaComprobante(),
                'moneda' => $row->monedaAbreviatura,
                default => '',
            };
        }

        $valor = $row->{$key} ?? '';
        if ($key === 'fecha' && $valor !== '' && $valor !== null) {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        }
        if ($key === 'monto' && $valor !== '' && $valor !== null) {
            return number_format((float) $valor, 2, ',', '.');
        }
        if ($key === 'origen') {
            return match ((string) $valor) {
                PagoproveedorListadoFila::ORIGEN_IE_OPP => 'Ingresos y egresos',
                PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR => 'Orden de pago',
                default => trim((string) $valor),
            };
        }

        return trim((string) $valor);
    }
}
