<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuentaListadoFiltros;

/**
 * Excel plano de l-mayor.c (opción 3): una fila por movimiento, sin cabeceras ni saldos.
 * Puede ordenarse por cuenta o por centro de costo.
 */
class MayorPlanoCuentaExcelPlanoSupport
{
    public const SEPARADOR_FACTURAS = '; ';

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function soloMovimientos(array $filas): array
    {
        return array_values(array_filter(
            $filas,
            fn (array $fila) => ($fila['tipo_fila'] ?? 'detalle') === 'detalle',
        ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return 'cuenta'|'centrocosto'
     */
    public static function dimensionOrden(array $filtros): string
    {
        if (! empty($filtros['agrupar_por_cc'])) {
            return 'centrocosto';
        }

        $dimensionSolapas = MayorPlanoCuentaListadoFiltros::dimensionExcelSolapas($filtros);
        if ($dimensionSolapas === 'centrocosto') {
            return 'centrocosto';
        }

        $soloCc = MayorPlanoCuentaListadoFiltros::tieneSeleccionParticularCentrocostos($filtros)
            && ! MayorPlanoCuentaListadoFiltros::tieneSeleccionParticularCuentas($filtros);

        return $soloCc ? 'centrocosto' : 'cuenta';
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  'cuenta'|'centrocosto'  $dimension
     * @return list<array<string, mixed>>
     */
    public static function ordenar(array $filas, string $dimension): array
    {
        usort($filas, function (array $a, array $b) use ($dimension) {
            if ($dimension === 'centrocosto') {
                return [
                    (string) ($a['centrocosto_codigo'] ?? ''),
                    (int) ($a['fecha'] ?? 0),
                    (int) ($a['cuenta'] ?? 0),
                    (int) ($a['nro_asiento'] ?? 0),
                ] <=> [
                    (string) ($b['centrocosto_codigo'] ?? ''),
                    (int) ($b['fecha'] ?? 0),
                    (int) ($b['cuenta'] ?? 0),
                    (int) ($b['nro_asiento'] ?? 0),
                ];
            }

            return [
                (int) ($a['cuenta'] ?? 0),
                (int) ($a['fecha'] ?? 0),
                (string) ($a['centrocosto_codigo'] ?? ''),
                (int) ($a['nro_asiento'] ?? 0),
            ] <=> [
                (int) ($b['cuenta'] ?? 0),
                (int) ($b['fecha'] ?? 0),
                (string) ($b['centrocosto_codigo'] ?? ''),
                (int) ($b['nro_asiento'] ?? 0),
            ];
        });

        return array_values($filas);
    }

    /**
     * Último recurso si la OC no tiene ítems: comentario o detalle de cabecera.
     */
    public static function observacionOc(?string $comentario, ?string $detalle): string
    {
        $comentario = trim((string) $comentario);
        if ($comentario !== '') {
            return $comentario;
        }

        return trim((string) $detalle);
    }

    /**
     * Varios números de factura en una sola celda, sin duplicar filas.
     *
     * @param  list<string>  $etiquetas
     */
    public static function concatenarEnUnaCelda(array $etiquetas, string $separador = self::SEPARADOR_FACTURAS): string
    {
        $unicos = [];
        foreach ($etiquetas as $etiqueta) {
            $texto = trim((string) $etiqueta);
            if ($texto === '' || ! self::esEtiquetaFacturaValida($texto)) {
                continue;
            }
            $unicos[$texto] = $texto;
        }

        return implode($separador, array_values($unicos));
    }

    /**
     * @param  list<string>  $codigos
     */
    public static function proyectoCapexDesdeCodigos(array $codigos): string
    {
        return self::concatenarEnUnaCelda($codigos, ', ');
    }

    public static function formatearNumeroFactura(
        string $tipo,
        string $letra,
        int $sucursal,
        string|int $numero,
    ): string {
        $tipo = strtoupper(trim($tipo));
        $letra = trim($letra);
        $nro = trim((string) $numero);
        if ($tipo === '' || $nro === '' || ! self::esTipoFacturaCompra($tipo)) {
            return '';
        }

        return trim(sprintf('%s %s %s-%s', $tipo, $letra, $sucursal, $nro));
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function etiquetaFacturaDesdeMovimiento(array $fila): string
    {
        $tipo = strtoupper(trim((string) ($fila['tipo_comp'] ?? '')));
        if ($tipo === '' || ! self::esTipoFacturaCompra($tipo)) {
            return '';
        }

        $comprobante = trim((string) ($fila['comprobante'] ?? ''));
        if ($comprobante === '') {
            return '';
        }

        return trim($tipo.' '.$comprobante);
    }

    public static function esTipoFacturaCompra(string $tipo): bool
    {
        $tipo = strtoupper(trim($tipo));
        if ($tipo === '') {
            return false;
        }

        // COM / PEP / REC / DEP son OC, pedido, recepción o depósito: no son factura.
        if (in_array($tipo, ['COM', 'PEP', 'REC', 'DEP', 'OP', 'OPA', 'AOP'], true)) {
            return false;
        }

        if (in_array($tipo, [
            'FGA', 'FGB', 'FGC', 'FAB', 'FAC', 'FAD', 'FAE', 'FCB', 'FCC',
            'FIA', 'FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIS', 'FNS', 'FNB',
            'NCA', 'NCB', 'NCC', 'NDA', 'NDB', 'NDC',
        ], true)) {
            return true;
        }

        return str_starts_with($tipo, 'F') || str_starts_with($tipo, 'NC') || str_starts_with($tipo, 'ND');
    }

    public static function esEtiquetaFacturaValida(string $etiqueta): bool
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if ($etiqueta === '') {
            return false;
        }

        $tipo = preg_split('/\s+/', $etiqueta)[0] ?? '';

        return self::esTipoFacturaCompra((string) $tipo);
    }
}
