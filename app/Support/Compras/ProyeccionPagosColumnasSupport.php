<?php

namespace App\Support\Compras;

/**
 * Configuración de columnas del informe de proyección de pagos.
 *
 * Una sola definición gobierna pantalla, PDF y Excel: el catálogo declara etiqueta,
 * tipo de dato, ancho y si la columna admite ocultarse. El operador elige columnas
 * visibles y su orden; la selección viaja en el filtro `columnas`.
 */
final class ProyeccionPagosColumnasSupport
{
    public const TIPO_TEXTO = 'texto';

    public const TIPO_FECHA = 'fecha';

    public const TIPO_ENTERO = 'entero';

    public const TIPO_IMPORTE = 'importe';

    public const TIPO_RATIO = 'ratio';

    public const GRUPO_PROVEEDOR = 'Proveedor';

    public const GRUPO_COMPROBANTE = 'Comprobante';

    public const GRUPO_VENCIMIENTOS = 'Vencimientos';

    public const GRUPO_IMPORTES = 'Importes y totales';

    public const GRUPO_PAGO = 'Condiciones de pago';

    public const GRUPO_APROBACION = 'Aprobación y origen';

    public const GRUPO_CASHFLOW = 'Cash flow';

    /**
     * Columnas fijas del informe (sin tramos de vencimiento).
     *
     * @return list<array{clave: string, etiqueta: string, grupo: string, tipo: string, fija: bool, visible: bool, solo_detalle: bool, ancho_excel: int, ancho_pdf: float, ayuda: string}>
     */
    public static function catalogo(): array
    {
        return [
            self::col('proveedor_codigo', 'N.Pro.', self::GRUPO_PROVEEDOR, self::TIPO_TEXTO, fija: true, anchoExcel: 10, anchoPdf: 2.6, ayuda: 'Código de proveedor con enlace al ABM.'),
            self::col('proveedor_nombre', 'Nombre', self::GRUPO_PROVEEDOR, self::TIPO_TEXTO, fija: true, anchoExcel: 34, anchoPdf: 8.5),
            self::col('empresa', 'Empresa', self::GRUPO_PROVEEDOR, self::TIPO_TEXTO, anchoExcel: 20, anchoPdf: 5),
            self::col('tipo', 'Tip', self::GRUPO_COMPROBANTE, self::TIPO_TEXTO, soloDetalle: true, anchoExcel: 6, anchoPdf: 1.8),
            self::col('comprobante', 'Comprobante', self::GRUPO_COMPROBANTE, self::TIPO_TEXTO, soloDetalle: true, anchoExcel: 18, anchoPdf: 4.6, ayuda: 'Letra-sucursal-número con enlace al comprobante.'),
            self::col('cuota', 'Cuota', self::GRUPO_COMPROBANTE, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 7, anchoPdf: 1.8),
            self::col('estado_comprobante', 'Estado', self::GRUPO_COMPROBANTE, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 18, anchoPdf: 4),
            self::col('fecha_comprobante', 'F.Comp.', self::GRUPO_COMPROBANTE, self::TIPO_FECHA, soloDetalle: true, anchoExcel: 11, anchoPdf: 2.8),
            self::col('fecha_iva', 'F.IVA', self::GRUPO_COMPROBANTE, self::TIPO_FECHA, visible: false, soloDetalle: true, anchoExcel: 11, anchoPdf: 2.8),
            self::col('fecha_carga', 'F.Carga', self::GRUPO_COMPROBANTE, self::TIPO_FECHA, visible: false, soloDetalle: true, anchoExcel: 13, anchoPdf: 3),
            self::col('fecha_vencimiento', 'F.Vto.', self::GRUPO_VENCIMIENTOS, self::TIPO_FECHA, soloDetalle: true, anchoExcel: 11, anchoPdf: 2.8),
            self::col('dias_vencimiento', 'Días', self::GRUPO_VENCIMIENTOS, self::TIPO_ENTERO, soloDetalle: true, anchoExcel: 8, anchoPdf: 2, ayuda: 'Días entre la fecha base y el vencimiento (negativo = vencido).'),
            self::col('fecha_diferida', 'F.Difer.', self::GRUPO_VENCIMIENTOS, self::TIPO_FECHA, visible: false, soloDetalle: true, anchoExcel: 11, anchoPdf: 2.8, ayuda: 'Vencimiento + días de entrega de cheque del proveedor.'),
            self::col('tramo_vencimiento', 'Tramo', self::GRUPO_VENCIMIENTOS, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 16, anchoPdf: 3.6),
            self::col('moneda', 'Mon.', self::GRUPO_IMPORTES, self::TIPO_TEXTO, soloDetalle: true, anchoExcel: 7, anchoPdf: 1.8),
            self::col('cotizacion', 'Cotiz.', self::GRUPO_IMPORTES, self::TIPO_RATIO, visible: false, soloDetalle: true, anchoExcel: 11, anchoPdf: 2.4),
            self::col('importe_origen', 'Saldo origen', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, visible: false, soloDetalle: true, anchoExcel: 14, anchoPdf: 3.4, ayuda: 'Saldo impago en la moneda del comprobante.'),
            self::col('a_compensar', 'A compensar', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, anchoExcel: 14, anchoPdf: 3.4, ayuda: 'Comprobantes con condición de pago marcada como compensación.'),
            self::col('adelantos', 'Adelantos', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, anchoExcel: 14, anchoPdf: 3.4, ayuda: 'Pagos a cuenta sin aplicar.'),
            self::col('pend_aprobacion', 'Pend.aprob.', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, anchoExcel: 14, anchoPdf: 3.4),
            self::col('total_aprobado', 'Total aprob.', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, anchoExcel: 14, anchoPdf: 3.4, ayuda: 'Deuda aprobada neta de adelantos.'),
            self::col('total_adeudado', 'Total adeudado', self::GRUPO_IMPORTES, self::TIPO_IMPORTE, fija: true, anchoExcel: 15, anchoPdf: 3.6),
            self::col('condicion_pago_dias', 'Días cond.', self::GRUPO_PAGO, self::TIPO_ENTERO, visible: false, anchoExcel: 9, anchoPdf: 2.2),
            self::col('condicion_pago', 'Condición de pago', self::GRUPO_PAGO, self::TIPO_TEXTO, anchoExcel: 26, anchoPdf: 6),
            self::col('medio_pago', 'M.Pago', self::GRUPO_PAGO, self::TIPO_TEXTO, soloDetalle: true, anchoExcel: 10, anchoPdf: 2.4),
            self::col('detalle_pago', 'Detalle pago', self::GRUPO_PAGO, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 28, anchoPdf: 6),
            self::col('dias_entrega_cheque', 'Días entr.chq', self::GRUPO_PAGO, self::TIPO_ENTERO, visible: false, anchoExcel: 11, anchoPdf: 2.4),
            self::col('aprobacion', 'Ap.', self::GRUPO_APROBACION, self::TIPO_TEXTO, soloDetalle: true, anchoExcel: 6, anchoPdf: 1.6, ayuda: 'A = aprobado / contabilizado, P = pendiente.'),
            self::col('nro_referencia', 'Nro.OC', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 10, anchoPdf: 2.4, ayuda: 'Orden de compra origen con enlace al ABM.'),
            self::col('requisicion', 'Requis.', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 10, anchoPdf: 2.4),
            self::col('usuario_requisicion', 'Confecciona requis.', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 24, anchoPdf: 5),
            self::col('autorizante_requisicion', 'Autorizante requis.', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 24, anchoPdf: 5, ayuda: 'Último usuario que aprobó la requisición origen (historia APROBADA o último firmante del árbol).'),
            self::col('aprobacion_requisicion', 'Aprob. requis.', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 28, anchoPdf: 5.4, ayuda: 'Autorizante y fecha de aprobación de la requisición origen.'),
            self::col('detalle_item', 'Detalle ítem comprado', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 30, anchoPdf: 6),
            self::col('concepto', 'N.Con.', self::GRUPO_CASHFLOW, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 9, anchoPdf: 2.2, ayuda: 'Código del concepto de cash flow (Anita concoper) con enlace al ABM.'),
            self::col('detalle_concepto', 'Detalle del concepto', self::GRUPO_CASHFLOW, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 26, anchoPdf: 5.4, ayuda: 'Concepto de cash flow: del pago, de la cuenta contable imputada o el asignado al proveedor.'),
            self::col('cuenta_concepto', 'Cuenta cash flow', self::GRUPO_CASHFLOW, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 30, anchoPdf: 6, ayuda: 'Cuenta contable que aporta el concepto de cash flow.'),
            self::col('leyenda', 'Leyenda', self::GRUPO_APROBACION, self::TIPO_TEXTO, visible: false, soloDetalle: true, anchoExcel: 28, anchoPdf: 5.6),
        ];
    }

    /**
     * Catálogo completo con los tramos de vencimiento intercalados antes de los importes.
     *
     * @param  array<string, mixed>  $definicionTramos  Resultado de ProyeccionPagosTramosSupport::construir()
     * @return list<array<string, mixed>>
     */
    public static function catalogoConTramos(array $definicionTramos): array
    {
        $columnasTramos = [];

        if (! empty($definicionTramos['abre_anterior'])) {
            $columnasTramos[] = self::col(
                ProyeccionPagosTramosSupport::CLAVE_SALDO_ANTERIOR,
                ProyeccionPagosTramosSupport::etiquetaSaldoAnterior($definicionTramos),
                self::GRUPO_VENCIMIENTOS,
                self::TIPO_IMPORTE,
                anchoExcel: 14,
                anchoPdf: 3.4,
                ayuda: 'Saldo fuera de la ventana abierta.',
            );
        }

        foreach ($definicionTramos['tramos'] ?? [] as $tramo) {
            $columnasTramos[] = self::col(
                (string) $tramo['clave'],
                (string) $tramo['etiqueta'],
                self::GRUPO_VENCIMIENTOS,
                self::TIPO_IMPORTE,
                anchoExcel: 14,
                anchoPdf: 3.4,
            );
        }

        $columnasTramos[] = self::col(
            ProyeccionPagosTramosSupport::CLAVE_POSTERIOR,
            'Posterior',
            self::GRUPO_VENCIMIENTOS,
            self::TIPO_IMPORTE,
            anchoExcel: 14,
            anchoPdf: 3.4,
        );

        $catalogo = [];
        $insertado = false;
        foreach (self::catalogo() as $columna) {
            if (! $insertado && $columna['clave'] === 'a_compensar') {
                foreach ($columnasTramos as $columnaTramo) {
                    $catalogo[] = $columnaTramo;
                }
                $insertado = true;
            }
            $catalogo[] = $columna;
        }

        if (! $insertado) {
            foreach ($columnasTramos as $columnaTramo) {
                $catalogo[] = $columnaTramo;
            }
        }

        return $catalogo;
    }

    /**
     * Columnas visibles en el orden elegido por el operador.
     *
     * @param  list<array<string, mixed>>  $catalogo
     * @return list<array<string, mixed>>
     */
    public static function resolverVisibles(array $catalogo, string $configuracion, string $salida): array
    {
        $detalle = $salida === ProyeccionPagosReporteFiltros::SALIDA_DETALLE;
        $disponibles = [];
        foreach ($catalogo as $columna) {
            if (! $detalle && ! empty($columna['solo_detalle'])) {
                continue;
            }
            $disponibles[$columna['clave']] = $columna;
        }

        $elegidas = self::interpretar($configuracion);
        $visibles = [];

        foreach ($elegidas as $clave) {
            if (isset($disponibles[$clave])) {
                $visibles[$clave] = $disponibles[$clave];
            }
        }

        foreach ($disponibles as $clave => $columna) {
            $obligatoria = ! empty($columna['fija']);
            $porDefecto = $elegidas === [] && ! empty($columna['visible']);

            if (($obligatoria || $porDefecto) && ! isset($visibles[$clave])) {
                $visibles[$clave] = $columna;
            }
        }

        $visibles = self::insertarAutorizanteSiCorresponde($visibles, $disponibles);

        return array_values($visibles);
    }

    /**
     * Panel de configuración: todas las columnas disponibles, visibles primero y en orden.
     *
     * @param  list<array<string, mixed>>  $catalogo
     * @return list<array<string, mixed>>
     */
    public static function panelConfiguracion(array $catalogo, string $configuracion, string $salida): array
    {
        $visibles = self::resolverVisibles($catalogo, $configuracion, $salida);
        $clavesVisibles = array_column($visibles, 'clave');
        $panel = [];

        foreach ($visibles as $columna) {
            $columna['activa'] = true;
            $panel[] = $columna;
        }

        foreach ($catalogo as $columna) {
            if (in_array($columna['clave'], $clavesVisibles, true)) {
                continue;
            }
            $columna['activa'] = false;
            $panel[] = $columna;
        }

        return $panel;
    }

    /**
     * @param  list<array<string, mixed>>  $catalogo
     */
    public static function configuracionPorDefecto(array $catalogo): string
    {
        return collect($catalogo)
            ->filter(fn (array $columna) => ! empty($columna['visible']) || ! empty($columna['fija']))
            ->pluck('clave')
            ->implode(',');
    }

    /** @return list<string> */
    public static function interpretar(string $configuracion): array
    {
        return collect(explode(',', $configuracion))
            ->map(fn ($clave) => trim((string) $clave))
            ->filter(fn (string $clave) => $clave !== '' && preg_match('/^[a-z0-9_]+$/', $clave) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<array<string, mixed>> $columnas */
    public static function serializar(array $columnas): string
    {
        return implode(',', array_column($columnas, 'clave'));
    }

    /**
     * Claves de columnas que se totalizan en subtotales y total general.
     *
     * @param  list<array<string, mixed>>  $columnas
     * @return list<string>
     */
    public static function clavesImporte(array $columnas): array
    {
        return collect($columnas)
            ->filter(fn (array $columna) => $columna['tipo'] === self::TIPO_IMPORTE)
            ->pluck('clave')
            ->values()
            ->all();
    }

    /**
     * Si el operador ya muestra origen de requisición, agrega el autorizante al lado.
     *
     * @param  array<string, array<string, mixed>>  $visibles
     * @param  array<string, array<string, mixed>>  $disponibles
     * @return array<string, array<string, mixed>>
     */
    private static function insertarAutorizanteSiCorresponde(array $visibles, array $disponibles): array
    {
        if (! isset($disponibles['autorizante_requisicion']) || isset($visibles['autorizante_requisicion'])) {
            return $visibles;
        }

        $tieneOrigen = isset($visibles['requisicion'])
            || isset($visibles['usuario_requisicion'])
            || isset($visibles['aprobacion_requisicion']);

        if (! $tieneOrigen) {
            return $visibles;
        }

        $salida = [];
        $insertado = false;
        foreach ($visibles as $clave => $columna) {
            if ($clave === 'aprobacion_requisicion' && ! $insertado) {
                $salida['autorizante_requisicion'] = $disponibles['autorizante_requisicion'];
                $insertado = true;
            }
            $salida[$clave] = $columna;
            $despuesDe = $clave === 'usuario_requisicion'
                || ($clave === 'requisicion' && ! isset($visibles['usuario_requisicion']));
            if ($despuesDe && ! $insertado) {
                $salida['autorizante_requisicion'] = $disponibles['autorizante_requisicion'];
                $insertado = true;
            }
        }

        if (! $insertado) {
            $salida['autorizante_requisicion'] = $disponibles['autorizante_requisicion'];
        }

        return $salida;
    }

    /**
     * @return array{clave: string, etiqueta: string, grupo: string, tipo: string, fija: bool, visible: bool, solo_detalle: bool, ancho_excel: int, ancho_pdf: float, ayuda: string}
     */
    private static function col(
        string $clave,
        string $etiqueta,
        string $grupo,
        string $tipo,
        bool $fija = false,
        bool $visible = true,
        bool $soloDetalle = false,
        int $anchoExcel = 14,
        float $anchoPdf = 3.0,
        string $ayuda = '',
    ): array {
        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'grupo' => $grupo,
            'tipo' => $tipo,
            'fija' => $fija,
            'visible' => $visible || $fija,
            'solo_detalle' => $soloDetalle,
            'ancho_excel' => $anchoExcel,
            'ancho_pdf' => $anchoPdf,
            'ayuda' => $ayuda,
        ];
    }
}
