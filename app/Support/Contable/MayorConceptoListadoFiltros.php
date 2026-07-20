<?php

namespace App\Support\Contable;

use App\Support\Export\ExcelFormatoNumero;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte mayor por concepto (pantalla y exportaciones).
 */
class MayorConceptoListadoFiltros
{
    /** @var list<string> */
    public const CAMPOS_FILTRO_DETALLE = [
        'filtro_nro_asiento',
        'filtro_cuenta',
        'filtro_concepto',
        'filtro_comprobante',
        'filtro_emisor',
        'filtro_cuit',
        'filtro_texto',
    ];

    /**
     * @return array{
     *   empresa_ids: list<int>,
     *   empresa_id: int,
     *   consolidar_empresas: bool,
     *   moneda_id: int,
     *   modo_periodo: string,
     *   mes: int,
     *   anio: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   solo_moneda_origen: bool,
     *   agrupacion_resumen: string,
     *   excel_formato_numero: string,
     *   filtro_nro_asiento: string,
     *   filtro_cuenta: string,
     *   filtro_concepto: string,
     *   filtro_comprobante: string,
     *   filtro_emisor: string,
     *   filtro_cuit: string,
     *   filtro_texto: string
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = trim((string) $request->input('modo_periodo', 'mes'));
        if (! in_array($modo, ['mes', 'rango'], true)) {
            $modo = 'mes';
        }

        $agrupacion = trim((string) $request->input('agrupacion_resumen', 'concepto_cuenta'));
        if (! in_array($agrupacion, ['concepto_cuenta', 'cuenta_concepto'], true)) {
            $agrupacion = 'concepto_cuenta';
        }

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($empresaIds === [] && (int) $request->input('empresa_id', 0) > 0) {
            $empresaIds = [(int) $request->input('empresa_id')];
        }

        return [
            'empresa_ids' => $empresaIds,
            'empresa_id' => (int) ($empresaIds[0] ?? 0),
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'modo_periodo' => $modo,
            'mes' => max(1, min(12, (int) $request->input('mes', (int) date('n')))),
            'anio' => max(2000, min(2100, (int) $request->input('anio', (int) date('Y')))),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'agrupacion_resumen' => $agrupacion,
            'excel_formato_numero' => MayorConceptoExcelFormatoNumero::normalizar(
                $request->input('excel_formato_numero', ExcelFormatoNumero::preferenciaGlobal())
            ),
            'filtro_nro_asiento' => trim((string) $request->input('filtro_nro_asiento', '')),
            'filtro_cuenta' => trim((string) $request->input('filtro_cuenta', '')),
            'filtro_concepto' => trim((string) $request->input('filtro_concepto', '')),
            'filtro_comprobante' => trim((string) $request->input('filtro_comprobante', '')),
            'filtro_emisor' => trim((string) $request->input('filtro_emisor', '')),
            'filtro_cuit' => trim((string) $request->input('filtro_cuit', '')),
            'filtro_texto' => trim((string) $request->input('filtro_texto', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        $ids = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));

        if ($ids === [] && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            return [(int) $filtros['empresa_id']];
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function esMultiempresa(array $filtros): bool
    {
        return count(self::empresaIds($filtros)) > 1;
    }

    public static function tieneFiltroDetalle(array $filtros): bool
    {
        foreach (self::CAMPOS_FILTRO_DETALLE as $campo) {
            if (trim((string) ($filtros[$campo] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function aplicarFiltroDetalle(array $filas, array $filtros): array
    {
        if (! self::tieneFiltroDetalle($filtros)) {
            return $filas;
        }

        $nroAsiento = trim((string) ($filtros['filtro_nro_asiento'] ?? ''));
        $cuenta = self::normalizarTextoBusqueda($filtros['filtro_cuenta'] ?? '');
        $concepto = self::normalizarTextoBusqueda($filtros['filtro_concepto'] ?? '');
        $comprobante = self::normalizarTextoBusqueda($filtros['filtro_comprobante'] ?? '');
        $emisor = self::normalizarTextoBusqueda($filtros['filtro_emisor'] ?? '');
        $cuit = self::soloDigitos($filtros['filtro_cuit'] ?? '');
        $texto = self::normalizarTextoBusqueda($filtros['filtro_texto'] ?? '');

        $detalleFiltrado = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            if ($nroAsiento !== '' && ! self::coincideNroAsiento($fila, $nroAsiento)) {
                continue;
            }

            if ($cuenta !== '' && ! self::contieneTexto($fila['cuenta_codigo'] ?? '', $cuenta)
                && ! self::contieneTexto($fila['cuenta_nombre'] ?? '', $cuenta)) {
                continue;
            }

            if ($concepto !== '' && ! self::coincideConcepto($fila, $concepto)) {
                continue;
            }

            if ($comprobante !== '' && ! self::contieneTexto($fila['comprobante'] ?? '', $comprobante)
                && ! self::contieneTexto($fila['tipo_comp'] ?? '', $comprobante)) {
                continue;
            }

            if ($emisor !== '' && ! self::contieneTexto($fila['emisor'] ?? '', $emisor)) {
                continue;
            }

            if ($cuit !== '' && ! str_contains(self::soloDigitos($fila['cuit'] ?? ''), $cuit)) {
                continue;
            }

            if ($texto !== '' && ! self::coincideTextoLibre($fila, $texto)) {
                continue;
            }

            $detalleFiltrado[] = $fila;
        }

        return self::reinsertarSubtotalesDetalleFiltrado($filas, $detalleFiltrado);
    }

    /**
     * @param  list<array<string, mixed>>  $filasOriginales
     * @param  list<array<string, mixed>>  $detalleFiltrado
     * @return list<array<string, mixed>>
     */
    private static function reinsertarSubtotalesDetalleFiltrado(array $filasOriginales, array $detalleFiltrado): array
    {
        if ($detalleFiltrado === []) {
            return [];
        }

        $clavesDetalle = [];
        $paresCuenta = [];
        $conceptosVisibles = [];

        foreach ($detalleFiltrado as $fila) {
            $clavesDetalle[self::claveDetalle($fila)] = true;
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $conceptoId = (int) ($fila['concepto_id'] ?? 0);
            $cuentaCodigo = (string) ($fila['cuenta_codigo'] ?? '');
            $paresCuenta[$empresaId.'|'.$conceptoId.'|'.$cuentaCodigo] = true;
            $paresCuenta[$conceptoId.'|'.$cuentaCodigo] = true; // consolidado (sin empresa en subtotal)
            $conceptosVisibles[$empresaId.'|'.$conceptoId] = true;
            $conceptosVisibles[$conceptoId] = true;
        }

        $salida = [];

        foreach ($filasOriginales as $fila) {
            $tipo = $fila['tipo_fila'] ?? 'detalle';

            if ($tipo === 'header_empresa') {
                $salida[] = $fila;

                continue;
            }

            if ($tipo === 'detalle') {
                if (isset($clavesDetalle[self::claveDetalle($fila)])) {
                    $salida[] = $fila;
                }

                continue;
            }

            if ($tipo === 'total_cuenta') {
                $empresaId = (int) ($fila['empresa_id'] ?? 0);
                $conceptoId = (int) ($fila['concepto_id'] ?? 0);
                $cuentaCodigo = (string) ($fila['cuenta_codigo'] ?? '');
                $claveConEmpresa = $empresaId.'|'.$conceptoId.'|'.$cuentaCodigo;
                $claveSinEmpresa = $conceptoId.'|'.$cuentaCodigo;

                if ($empresaId > 0) {
                    if (! isset($paresCuenta[$claveConEmpresa])) {
                        continue;
                    }
                    $lineasCuenta = array_values(array_filter(
                        $detalleFiltrado,
                        fn (array $d) => (int) ($d['empresa_id'] ?? 0) === $empresaId
                            && (int) ($d['concepto_id'] ?? 0) === $conceptoId
                            && (string) ($d['cuenta_codigo'] ?? '') === $cuentaCodigo,
                    ));
                } else {
                    if (! isset($paresCuenta[$claveSinEmpresa])) {
                        continue;
                    }
                    $lineasCuenta = array_values(array_filter(
                        $detalleFiltrado,
                        fn (array $d) => (int) ($d['concepto_id'] ?? 0) === $conceptoId
                            && (string) ($d['cuenta_codigo'] ?? '') === $cuentaCodigo,
                    ));
                }
                $salida[] = self::filaTotalCuentaDesdeDetalle($fila, $lineasCuenta);

                continue;
            }

            if ($tipo === 'total_concepto') {
                $empresaId = (int) ($fila['empresa_id'] ?? 0);
                $conceptoId = (int) ($fila['concepto_id'] ?? 0);

                if ($empresaId > 0) {
                    if (! isset($conceptosVisibles[$empresaId.'|'.$conceptoId])) {
                        continue;
                    }
                    $lineasConcepto = array_values(array_filter(
                        $detalleFiltrado,
                        fn (array $d) => (int) ($d['empresa_id'] ?? 0) === $empresaId
                            && (int) ($d['concepto_id'] ?? 0) === $conceptoId,
                    ));
                } else {
                    if (! isset($conceptosVisibles[$conceptoId])) {
                        continue;
                    }
                    $lineasConcepto = array_values(array_filter(
                        $detalleFiltrado,
                        fn (array $d) => (int) ($d['concepto_id'] ?? 0) === $conceptoId,
                    ));
                }
                $salida[] = self::filaTotalConceptoDesdeDetalle($fila, $lineasConcepto);
            }
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $plantilla
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private static function filaTotalCuentaDesdeDetalle(array $plantilla, array $lineas): array
    {
        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        return array_merge($plantilla, [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $plantilla
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private static function filaTotalConceptoDesdeDetalle(array $plantilla, array $lineas): array
    {
        return self::filaTotalCuentaDesdeDetalle($plantilla, $lineas);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function claveDetalle(array $fila): string
    {
        return implode('|', [
            (int) ($fila['empresa_id'] ?? 0),
            (int) ($fila['concepto_id'] ?? 0),
            (string) ($fila['cuenta_codigo'] ?? ''),
            (int) ($fila['nro_asiento'] ?? 0),
            (string) ($fila['tipo_comp'] ?? ''),
            (string) ($fila['comprobante'] ?? ''),
            (string) ($fila['fecha'] ?? ''),
            (string) ($fila['debe'] ?? ''),
            (string) ($fila['haber'] ?? ''),
            (string) ($fila['descripcion'] ?? ''),
            (string) ($fila['origen'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function coincideNroAsiento(array $fila, string $busqueda): bool
    {
        $nroFila = (string) ($fila['nro_asiento'] ?? '');
        if ($nroFila === '' || (int) $nroFila <= 0) {
            return false;
        }

        foreach (self::variantesBusquedaNroAsiento($busqueda) as $variante) {
            if (str_contains($nroFila, $variante)) {
                return true;
            }

            $digFila = self::soloDigitos($nroFila);
            $digVar = self::soloDigitos($variante);
            if ($digVar !== '' && str_contains($digFila, $digVar)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza búsqueda de N. asiento (prefijo S de Anita, cero extra tipo 52603579 → 5263579).
     *
     * @return list<string>
     */
    public static function variantesBusquedaNroAsiento(string $busqueda): array
    {
        $busqueda = trim($busqueda);
        if ($busqueda === '') {
            return [];
        }

        $sinPrefijo = preg_replace('/^[A-Za-z]+/', '', $busqueda) ?? $busqueda;
        $variantes = array_values(array_filter(array_unique([
            $busqueda,
            trim($sinPrefijo),
            self::soloDigitos($busqueda),
            self::soloDigitos($sinPrefijo),
        ])));

        foreach ($variantes as $digits) {
            if (preg_match('/^5260(\d+)$/', $digits, $coincidencia)) {
                $variantes[] = '526'.$coincidencia[1];
            }
            if (preg_match('/^52600(\d+)$/', $digits, $coincidencia)) {
                $variantes[] = '526'.$coincidencia[1];
            }
        }

        return array_values(array_filter(array_unique($variantes)));
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function coincideConcepto(array $fila, string $concepto): bool
    {
        if (ctype_digit($concepto) && (int) $concepto === (int) ($fila['concepto_id'] ?? 0)) {
            return true;
        }

        return self::contieneTexto($fila['concepto_nombre'] ?? '', $concepto)
            || self::contieneTexto((string) ($fila['concepto_id'] ?? ''), $concepto);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function coincideTextoLibre(array $fila, string $texto): bool
    {
        $campos = [
            $fila['descripcion'] ?? '',
            $fila['comprobante'] ?? '',
            $fila['tipo_comp'] ?? '',
            $fila['emisor'] ?? '',
            $fila['cheque'] ?? '',
            $fila['cuenta_codigo'] ?? '',
            $fila['cuenta_nombre'] ?? '',
            $fila['concepto_nombre'] ?? '',
            $fila['cuit'] ?? '',
            $fila['nombreempresa'] ?? '',
            (string) ($fila['nro_asiento'] ?? ''),
            (string) ($fila['nro_oc'] ?? ''),
        ];

        foreach ($campos as $valor) {
            if (self::contieneTexto($valor, $texto)) {
                return true;
            }
        }

        return false;
    }

    private static function contieneTexto(mixed $valor, string $busqueda): bool
    {
        $busqueda = self::normalizarTextoBusqueda($busqueda);
        if ($busqueda === '') {
            return true;
        }

        $texto = self::normalizarTextoBusqueda($valor);

        return $texto !== '' && str_contains($texto, $busqueda);
    }

    private static function normalizarTextoBusqueda(mixed $valor): string
    {
        $texto = trim(mb_strtolower((string) $valor, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    private static function soloDigitos(mixed $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{cantidad_filas: int, total_debe: float, total_haber: float}
     */
    public static function totalesDesdeFilasVisibles(array $filas): array
    {
        $cantidad = 0;
        $debe = 0.0;
        $haber = 0.0;

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $cantidad++;
            $debe += (float) ($fila['debe'] ?? 0);
            $haber += (float) ($fila['haber'] ?? 0);
        }

        return [
            'cantidad_filas' => $cantidad,
            'total_debe' => round($debe, 2),
            'total_haber' => round($haber, 2),
        ];
    }

    /**
     * @return list<string>
     */
    public static function descripcionFiltrosDetalleActivos(array $filtros): array
    {
        $partes = [];
        $etiquetas = [
            'filtro_nro_asiento' => 'N. asiento',
            'filtro_cuenta' => 'Cuenta',
            'filtro_concepto' => 'Concepto',
            'filtro_comprobante' => 'Comprobante',
            'filtro_emisor' => 'Emisor',
            'filtro_cuit' => 'CUIT',
            'filtro_texto' => 'Texto',
        ];

        foreach ($etiquetas as $campo => $etiqueta) {
            $valor = trim((string) ($filtros[$campo] ?? ''));
            if ($valor !== '') {
                $partes[] = $etiqueta.': '.$valor;
            }
        }

        return $partes;
    }

    /**
     * Query string del reporte sin filtros de detalle (para limpiar búsqueda).
     *
     * @return array<string, mixed>
     */
    public static function paraQueryStringBase(array $filtros): array
    {
        $base = $filtros;
        foreach (self::CAMPOS_FILTRO_DETALLE as $campo) {
            unset($base[$campo]);
        }

        return self::paraQueryString($base);
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (self::empresaIds($filtros) === []) {
            return false;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            return (int) ($filtros['mes'] ?? 0) > 0 && (int) ($filtros['anio'] ?? 0) > 0;
        }

        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => (string) ($filtros['modo_periodo'] ?? 'mes'),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
        ];

        foreach (self::empresaIds($filtros) as $empresaId) {
            $out['empresa_ids'][] = $empresaId;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'rango') {
            $out['fecha_desde'] = trim((string) ($filtros['fecha_desde'] ?? ''));
            $out['fecha_hasta'] = trim((string) ($filtros['fecha_hasta'] ?? ''));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        $agrupacion = (string) ($filtros['agrupacion_resumen'] ?? 'concepto_cuenta');
        if ($agrupacion === 'cuenta_concepto') {
            $out['agrupacion_resumen'] = $agrupacion;
        }

        $formatoExcel = MayorConceptoExcelFormatoNumero::normalizar(
            $filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal()
        );
        // "auto" es el default: solo se propaga en query string cuando se fuerza ar/intl.
        if ($formatoExcel !== ExcelFormatoNumero::AUTO) {
            $out['excel_formato_numero'] = $formatoExcel;
        }

        foreach (self::CAMPOS_FILTRO_DETALLE as $campo) {
            $valor = trim((string) ($filtros[$campo] ?? ''));
            if ($valor !== '') {
                $out[$campo] = $valor;
            }
        }

        // No filtrar consolidar_empresas=0 ni arrays empresa_ids.
        return $out;
    }

    public static function firma(array $filtros): string
    {
        $filtrosConsulta = $filtros;
        unset($filtrosConsulta['agrupacion_resumen'], $filtrosConsulta['excel_formato_numero']);
        foreach (self::CAMPOS_FILTRO_DETALLE as $campo) {
            unset($filtrosConsulta[$campo]);
        }

        return md5(json_encode(self::paraQueryString($filtrosConsulta)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' || $hasta === '') {
            return ['', ''];
        }

        try {
            $d = Carbon::parse($desde)->format('Y-m-d');
            $h = Carbon::parse($hasta)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }
}
