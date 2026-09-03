<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaArmadoSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use App\Support\Ventas\MaquinaFslTipoSupport;

/**
 * Arma filas del listado IVA ventas desde cabecera Anita FSL (máquinas/ruletas, 100% exento).
 */
final class IvaVentasFslAnitaArmadoSupport
{
    /**
     * @param  array<string, mixed>  $fila
     * @param  array{
     *   puntoventa_id: int,
     *   puntoventa_codigo: string,
     *   puntoventa_nombre: string,
     *   sucursal: int,
     *   tipotransaccion_id: int,
     *   nombreempresa: string
     * }  $pv
     * @return array<string, mixed>|null
     */
    public static function filaReporte(array $fila, array $pv, array $filtros): ?array
    {
        $porFechaJornada = ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA)
            === IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA;
        $registro = LibroIvaDigitalVentasFslAnitaArmadoSupport::armarRegistroLibro(
            $fila,
            $porFechaJornada,
            (int) ($pv['sucursal'] ?? 0),
        );
        if ($registro === null) {
            return null;
        }

        $letra = MaquinaFslTipoSupport::LETRA;
        $subdiario = (string) ($filtros['subdiario'] ?? IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B);
        if (! IvaVentasListadoFiltros::pasaSubdiario($letra, $subdiario)) {
            return null;
        }

        $cab = $registro['cabecera'];
        $exento = round((float) ($cab['operaciones_exentas'] ?? 0), 2);
        $total = round((float) ($cab['importe_total'] ?? 0), 2);
        $columnas = IvaVentasColumnasSupport::montosVacios();
        $columnas['exento'] = $exento;
        $columnas['total'] = abs($total) > 0.0001 ? $total : $exento;

        $fechaYmd = (string) ($cab['fecha'] ?? '');
        $fechaIso = strlen($fechaYmd) === 8
            ? substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2)
            : $fechaYmd;
        $ts = strtotime($fechaIso);
        $fechaMov = $ts ? date('d/m/Y', $ts) : '';
        $numero = (int) ($cab['numero_comprobante'] ?? 0);
        $sucursal = (int) ($cab['punto_venta'] ?? $pv['sucursal']);
        $nombre = trim((string) ($cab['nombre_comprador'] ?? ''));
        if ($nombre === '') {
            $nombre = MaquinaFslTipoSupport::nombreCliente();
        }

        return [
            'tipo_fila' => 'detalle',
            'seccion' => 'administracion',
            'seccion_label' => 'Facturas de administración',
            'host' => 'Máquinas FSL (Anita)',
            'unidad_negocio' => IvaVentasUnidadNegocioSupport::OTROS,
            'unidad_negocio_label' => 'Administración',
            'puntoventa_id' => (int) ($pv['puntoventa_id'] ?? 0),
            'puntoventa_codigo' => (string) ($pv['puntoventa_codigo'] ?? (string) $sucursal),
            'puntoventa_nombre' => (string) ($pv['puntoventa_nombre'] ?? ('PV '.$sucursal)),
            'sucursal' => $sucursal,
            'cliente_codigo' => '000000',
            'cliente_nombre' => $nombre,
            'cliente_id' => 0,
            'cuit' => '',
            'fecha_mov' => $fechaMov,
            'fecha_orden' => $fechaIso,
            'tipo' => MaquinaFslTipoSupport::ABREVIATURA,
            'tipo_orden' => MaquinaFslTipoSupport::ABREVIATURA,
            'tipotransaccion_id' => (int) ($pv['tipotransaccion_id'] ?? 0),
            'comprobante' => sprintf('%s%04d-%08d', $letra, $sucursal, $numero),
            'numerocomprobante' => $numero,
            'letra' => $letra,
            'columnas' => $columnas,
            'venta_id' => 0,
            'anulada' => false,
            'nombreempresa' => (string) ($pv['nombreempresa'] ?? ''),
            'fuente' => 'anita_fsl',
        ];
    }
}
