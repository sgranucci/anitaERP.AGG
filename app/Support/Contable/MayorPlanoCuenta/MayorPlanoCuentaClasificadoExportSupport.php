<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use App\Support\Export\XlsxStreamWriter;

/**
 * Export clasificado del mayor (cortado por cuenta): mismas columnas que pantalla/Excel chico.
 * Encabezado de cuenta → saldo inicial → movimientos → total cuenta.
 */
final class MayorPlanoCuentaClasificadoExportSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public static function cabeceras(array $filtros): array
    {
        $mostrarCc = MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros);
        $multiempresa = self::esMultiempresa($filtros);

        $cols = [
            'Fecha',
            'N.Asi.',
            'Tip',
            'Comprobante',
            'Cód. emisor',
            'Nombre emisor',
            'CUIT',
            'Descripción mov.',
        ];
        if ($mostrarCc) {
            $cols[] = 'Centro de costo';
        }
        $cols = array_merge($cols, [
            'O.Compra',
            'Mon',
            'Cotiz.',
            'Mon. Ref.',
            'Debe',
            'Haber',
            'Saldo del mes',
            'Saldo ejerc.',
        ]);
        if ($multiempresa) {
            $cols[] = 'Empr.';
        }

        return $cols;
    }

    /**
     * Mapea una fila estructural (header/saldo/total/detalle) a columnas del export analítico.
     *
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $filtros
     * @return list<string|int|float|null>
     */
    public static function filaAColumnas(array $fila, array $filtros): array
    {
        $mostrarCc = MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros);
        $multiempresa = self::esMultiempresa($filtros);
        $nCols = count(self::cabeceras($filtros));
        $tipo = (string) ($fila['tipo_fila'] ?? 'detalle');

        $idxDebe = $mostrarCc ? 13 : 12;
        $idxHaber = $idxDebe + 1;
        $idxSaldoEjerc = $mostrarCc ? 16 : 15;
        $idxEmpresa = $multiempresa ? $nCols - 1 : null;

        $vacio = array_fill(0, $nCols, '');

        if ($tipo === 'header_empresa') {
            $vacio[0] = 'Empresa: '.trim((string) ($fila['nombreempresa'] ?? ''));

            return $vacio;
        }

        if ($tipo === 'header_cuenta') {
            $vacio[0] = trim('Cuenta: '.trim((string) ($fila['cuenta_codigo'] ?? '')).' '.trim((string) ($fila['cuenta_nombre'] ?? '')));

            return $vacio;
        }

        if ($tipo === 'header_cc') {
            $cc = trim((string) ($fila['centrocosto_codigo'] ?? ''));
            $cc = $cc !== '' ? $cc : 'Sin CC';
            $nombre = trim((string) ($fila['centrocosto_nombre'] ?? ''));
            $vacio[0] = trim('Centro de costo: '.$cc.($nombre !== '' ? ' '.$nombre : ''));

            return $vacio;
        }

        if ($tipo === 'saldo_inicial') {
            $vacio[0] = 'Saldo Inicial';
            $vacio[$idxSaldoEjerc] = self::numeroOVacio($fila['saldo_ejercicio'] ?? null);

            return $vacio;
        }

        if ($tipo === 'total_cuenta' || $tipo === 'total_cc') {
            if ($tipo === 'total_cc') {
                $cc = trim((string) ($fila['centrocosto_codigo'] ?? ''));
                $cc = $cc !== '' ? $cc : 'Sin CC';
                $nombreCc = trim((string) ($fila['centrocosto_nombre'] ?? ''));
                $vacio[0] = trim('Total centro de costo '.$cc.($nombreCc !== '' ? ' — '.$nombreCc : ''));
            } else {
                $codigo = trim((string) ($fila['cuenta_codigo'] ?? ''));
                $nombre = trim((string) ($fila['cuenta_nombre'] ?? ''));
                $vacio[0] = trim('Total cuenta '.$codigo.($nombre !== '' ? ' — '.$nombre : ''));
            }
            $vacio[$idxDebe] = self::numeroOVacio($fila['debe'] ?? null);
            $vacio[$idxHaber] = self::numeroOVacio($fila['haber'] ?? null);

            return $vacio;
        }

        $row = [
            (string) ($fila['fecha_fmt'] ?? ''),
            (string) ($fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? ''),
            (string) ($fila['tipo_comp'] ?? ''),
            (string) ($fila['comprobante'] ?? ''),
            (string) ($fila['emisor'] ?? ''),
            (string) ($fila['emisor_nombre'] ?? ''),
            (string) ($fila['cuit'] ?? ''),
            (string) ($fila['descripcion'] ?? ''),
        ];
        if ($mostrarCc) {
            $cc = trim((string) ($fila['centrocosto_codigo'] ?? ''));
            $nombreCc = trim((string) ($fila['centrocosto_nombre'] ?? ''));
            $row[] = trim(($cc !== '' ? $cc : 'Sin CC').($nombreCc !== '' ? ' '.$nombreCc : ''));
        }
        $row[] = ((int) ($fila['nro_oc'] ?? 0) > 0) ? $fila['nro_oc'] : '';
        $row[] = (string) ($fila['moneda_abrev'] ?? '');
        $row[] = self::numeroOVacio($fila['cotizacion'] ?? null);
        $row[] = self::numeroOVacio($fila['mon_referencia'] ?? null);
        $row[] = self::numeroOVacio($fila['debe'] ?? null);
        $row[] = self::numeroOVacio($fila['haber'] ?? null);
        $row[] = self::numeroOVacio($fila['saldo_mes'] ?? null);
        $row[] = self::numeroOVacio($fila['saldo_ejercicio'] ?? null);
        if ($idxEmpresa !== null) {
            $row[] = $fila['empresa_id'] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, filas: int, bytes: int}
     */
    public static function escribirCsv(
        MayorPlanoCuentaReporteService $reporteService,
        array $resultado,
        array $filtros,
        string $rutaAbsoluta,
    ): array {
        self::asegurarDirectorio($rutaAbsoluta);

        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear CSV: '.$rutaAbsoluta);
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::cabeceras($filtros), ';');

        $n = 0;
        foreach ($reporteService->iterarFilasClasificadas($resultado, $filtros) as $fila) {
            fputcsv($out, self::filaAColumnas($fila, $filtros), ';');
            $n++;
            if ($n % 2000 === 0) {
                fflush($out);
            }
        }

        fclose($out);
        @chmod($rutaAbsoluta, 0664);

        return [
            'path' => $rutaAbsoluta,
            'filas' => $n,
            'bytes' => (int) filesize($rutaAbsoluta),
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, filas: int, bytes: int}
     */
    public static function escribirXlsx(
        MayorPlanoCuentaReporteService $reporteService,
        array $resultado,
        array $filtros,
        string $rutaAbsoluta,
        string $nombreHoja = 'Mayor por cuenta',
    ): array {
        $writer = new XlsxStreamWriter($rutaAbsoluta, $nombreHoja);
        $writer->escribirCabecera(self::cabeceras($filtros));

        foreach ($reporteService->iterarFilasClasificadas($resultado, $filtros) as $fila) {
            $writer->escribirFila(
                self::filaAColumnas($fila, $filtros),
                self::estiloFilaExcel($fila),
            );
        }

        return $writer->cerrar();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function estiloFilaExcel(array $fila): ?string
    {
        return match ((string) ($fila['tipo_fila'] ?? 'detalle')) {
            'header_cuenta' => 'cuenta',
            'header_cc' => 'cc',
            'header_empresa' => 'empresa',
            'saldo_inicial' => 'saldo',
            'total_cuenta' => 'total',
            'total_cc' => 'total_cc',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function esMultiempresa(array $filtros): bool
    {
        return count($filtros['empresa_ids'] ?? []) > 1
            || empty($filtros['consolidar_empresas']);
    }

    private static function numeroOVacio(mixed $valor): float|string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return is_numeric($valor) ? (float) $valor : '';
    }

    private static function asegurarDirectorio(string $rutaAbsoluta): void
    {
        $dir = dirname($rutaAbsoluta);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('No se pudo crear directorio de export: '.$dir);
            }
            @chmod($dir, 0775);
        }
        if (! is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (! is_writable($dir)) {
            throw new \RuntimeException('Directorio de export sin permiso de escritura: '.$dir);
        }
    }
}
