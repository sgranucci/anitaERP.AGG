<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use Illuminate\Support\Collection;

/**
 * Armado de registros LID desde compra+concmov Anita (maestro conceptos ERP).
 */
final class LibroIvaDigitalComprasAnitaArmadoSupport
{
    /** @var array<string, Tipotransaccion_Compra>|null */
    private static ?array $tiposPorAbrev = null;

    /** @var array<int, Concepto_Ivacompra>|null */
    private static ?array $conceptosPorCodigo = null;

    public static function claveNatural(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): string {
        $tipoNorm = self::normalizarAbreviaturaTipo($tipo);
        if ($tipoNorm === '') {
            $tipoNorm = strtoupper(substr(trim($tipo), 0, 3));
        }

        return implode('|', [
            str_pad(trim($proveedor), 6, '0', STR_PAD_LEFT),
            $tipoNorm,
            strtoupper(substr(trim($letra), 0, 1)),
            (string) $sucursal,
            (string) $numero,
        ]);
    }

    /**
     * @param  array<string, mixed>  $compra
     * @param  list<array{concepto: int, importe: float}>  $conceptos
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    public static function armarRegistro(array $compra, array $conceptos, bool $prorrateoGlobal): ?array
    {
        $letra = strtoupper(substr(trim((string) ($compra['com_letra'] ?? 'A')), 0, 1));
        if ($letra === '' || $letra === 'E') {
            return null;
        }

        $tipoAbrev = (string) ($compra['com_tipo'] ?? '');
        $tipo = self::tipoPorAbreviatura($tipoAbrev);
        if ($tipo === null) {
            return null;
        }

        $subdiario = strtoupper(trim((string) ($tipo->getRawOriginal('subdiario') ?? $tipo->subdiario ?? '')));
        if ($subdiario !== 'C' && $subdiario !== 'G') {
            return null;
        }

        $codigoAfip = str_pad(
            preg_replace('/\D+/', '', (string) ($tipo->codigoafip ?? '001')) ?: '001',
            2,
            '0',
            STR_PAD_LEFT,
        );
        if (strncmp($codigoAfip, '04', 2) === 0) {
            return null;
        }

        $tipoComprobante = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoAfip, $letra);
        $puntoVenta = (int) ($compra['com_sucursal'] ?? 0);
        $numero = (int) ($compra['com_nro'] ?? 0);
        if ($puntoVenta < 0 || $numero <= 0) {
            return null;
        }

        $codigoMoneda = self::codigoMonedaAnita((string) ($compra['com_cod_mon'] ?? '1'));
        $cotizacion = (float) ($compra['com_cotizacion'] ?? 1);
        $coeficiente = self::coeficienteMoneda($codigoMoneda, $cotizacion);
        $totales = self::desglosarConcmov($conceptos, $letra, $coeficiente);
        $cuit = preg_replace('/\D+/', '', (string) ($compra['com_cuit_prov'] ?? '')) ?? '';
        $fechaIva = self::fechaYmdAnita((string) ($compra['com_fecha_iva'] ?? $compra['com_fecha'] ?? ''));
        if ($fechaIva === null) {
            return null;
        }

        $importeTotal = abs((float) ($compra['com_monto'] ?? 0)) * $coeficiente;
        $credito = $prorrateoGlobal ? 0.0 : (float) $totales['credito_computable'];

        $cabecera = [
            'fecha' => $fechaIva,
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'despacho_importacion' => '',
            'codigo_documento' => '80',
            'numero_identificacion' => $cuit !== '' ? $cuit : '0',
            'nombre_vendedor' => trim((string) ($compra['com_nombre_prov'] ?? '')),
            'importe_total' => $importeTotal,
            'no_integra_neto' => $totales['no_integra'],
            'operaciones_exentas' => $totales['exento'],
            'percepciones_iva' => $totales['perc_iva'],
            'percepciones_nacionales' => $totales['perc_nacional'],
            'percepciones_iibb' => $totales['perc_iibb'],
            'percepciones_municipales' => $totales['perc_municipal'],
            'impuestos_internos' => $totales['imp_interno'],
            'codigo_moneda' => $codigoMoneda,
            'tipo_cambio' => LibroIvaDigitalMapeosSupport::tipoCambioArca($codigoMoneda, $cotizacion),
            'cantidad_alicuotas' => $totales['cantidad_alicuotas'],
            'codigo_operacion' => ' ',
            'credito_fiscal_computable' => $credito,
            'otros_tributos' => 0,
            'cuit_emisor_corredor' => '0',
            'denominacion_emisor_corredor' => '',
            'iva_comision' => 0,
        ];
        $cabecera = LibroIvaDigitalMapeosSupport::cabeceraImportesEnPesos($cabecera);

        $alicuotas = [];
        foreach ($totales['alicuotas'] as $row) {
            $alicuotas[] = [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
                'numero_comprobante' => $numero,
                'codigo_documento' => '80',
                'numero_identificacion' => $cuit !== '' ? $cuit : '0',
                'neto_gravado' => $row['neto'],
                'alicuota_iva' => $row['codigo_lid'],
                'impuesto_liquidado' => $row['iva'],
            ];
        }

        return LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
    }

    /**
     * @param  list<array{concepto: int, importe: float}>  $conceptos
     * @return array{
     *     no_integra: float,
     *     exento: float,
     *     perc_iva: float,
     *     perc_nacional: float,
     *     perc_iibb: float,
     *     perc_municipal: float,
     *     imp_interno: float,
     *     cantidad_alicuotas: int,
     *     credito_computable: float,
     *     alicuotas: list<array{neto: float, iva: float, tasa: float, codigo_lid: string, concepto_iva_simple: int}>
     * }
     */
    /**
     * @param  list<array{concepto: int, importe: float}>  $conceptos
     */
    private static function desglosarConcmov(array $conceptos, string $letra, float $coeficiente = 1.0): array
    {
        $noIntegra = 0.0;
        $exento = 0.0;
        $percIva = 0.0;
        $percNac = 0.0;
        $percIibb = 0.0;
        $percMun = 0.0;
        $impInterno = 0.0;
        $coef = $coeficiente > 0.000001 ? $coeficiente : 1.0;
        /** @var array<string, array{neto: float, iva: float, tasa: float}> $alicuotas */
        $alicuotas = [];

        foreach ($conceptos as $linea) {
            $codigo = (int) ($linea['concepto'] ?? 0);
            $importe = (float) ($linea['importe'] ?? 0) * $coef;
            if ($codigo <= 0 || abs($importe) < 0.0001) {
                continue;
            }

            $concepto = self::conceptoPorCodigo($codigo);
            if ($concepto === null) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
            $tasa = (float) ($concepto->impuestos?->valor ?? 0);
            $key = (string) round($tasa, 3);
            $conceptoIvaSimple = LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre(
                (string) ($concepto->nombre ?? ''),
            );

            switch ($tipo) {
                case 'N':
                    $noIntegra += $importe;
                    break;
                case 'G':
                    $alicuotas[$key]['neto'] = ($alicuotas[$key]['neto'] ?? 0) + $importe;
                    $alicuotas[$key]['tasa'] = $tasa;
                    $alicuotas[$key]['iva'] = $alicuotas[$key]['iva'] ?? 0;
                    $alicuotas[$key]['concepto_iva_simple'] = $conceptoIvaSimple;
                    break;
                case 'I':
                    $alicuotas[$key]['iva'] = ($alicuotas[$key]['iva'] ?? 0) + $importe;
                    $alicuotas[$key]['tasa'] = $tasa;
                    $alicuotas[$key]['neto'] = $alicuotas[$key]['neto'] ?? 0;
                    if (! isset($alicuotas[$key]['concepto_iva_simple'])) {
                        $alicuotas[$key]['concepto_iva_simple'] = $conceptoIvaSimple;
                    }
                    break;
                case 'E':
                    $exento += $importe;
                    break;
                case 'P':
                    $percIva += $importe;
                    break;
                case 'B':
                case 'S':
                    $percIibb += $importe;
                    break;
                case 'M':
                case 'V':
                case 'A':
                    $percNac += $importe;
                    break;
                case 'T':
                    $impInterno += $importe;
                    break;
            }
        }

        // Anita: gravado sin IVA → trata como exento.
        $netoSum = 0.0;
        $ivaSum = 0.0;
        foreach ($alicuotas as $row) {
            $netoSum += (float) ($row['neto'] ?? 0);
            $ivaSum += (float) ($row['iva'] ?? 0);
        }
        if ($netoSum > 0.0001 && $ivaSum < 0.0001) {
            $exento += $netoSum;
            $alicuotas = [];
        }

        $filas = [];
        foreach ($alicuotas as $row) {
            $neto = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) ($row['neto'] ?? 0));
            $iva = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) ($row['iva'] ?? 0));
            $tasa = (float) ($row['tasa'] ?? 0);
            if ($neto <= 0 && $iva <= 0) {
                continue;
            }
            $filas[] = [
                'neto' => $neto,
                'iva' => $iva,
                'tasa' => $tasa,
                'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid($tasa),
                'concepto_iva_simple' => (int) ($row['concepto_iva_simple'] ?? 1),
            ];
        }

        $esC = strtoupper($letra) === 'C';
        $cantidad = $esC ? 0 : count($filas);
        $credito = array_sum(array_column($filas, 'iva'));

        return [
            'no_integra' => $esC ? 0.0 : LibroIvaDigitalComprasImportesSupport::importeNeteado($noIntegra),
            'exento' => $esC ? 0.0 : LibroIvaDigitalComprasImportesSupport::importeNeteado($exento),
            'perc_iva' => LibroIvaDigitalComprasImportesSupport::absolutoInformable($percIva),
            'perc_nacional' => LibroIvaDigitalComprasImportesSupport::absolutoInformable($percNac),
            'perc_iibb' => LibroIvaDigitalComprasImportesSupport::absolutoInformable($percIibb),
            'perc_municipal' => LibroIvaDigitalComprasImportesSupport::absolutoInformable($percMun),
            'imp_interno' => LibroIvaDigitalComprasImportesSupport::absolutoInformable($impInterno),
            'cantidad_alicuotas' => $cantidad,
            'credito_computable' => $cantidad > 0 ? $credito : 0.0,
            'alicuotas' => $cantidad > 0 ? $filas : [],
        ];
    }

    public static function normalizarAbreviaturaTipo(string $abrev): string
    {
        return strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $abrev) ?? '', 0, 3));
    }

    public static function tipoPorAbreviatura(string $abrev): ?Tipotransaccion_Compra
    {
        $raw = strtoupper(substr(trim($abrev), 0, 3));
        $norm = self::normalizarAbreviaturaTipo($abrev);
        if ($raw === '' && $norm === '') {
            return null;
        }
        self::cargarTipos();

        if ($norm !== '' && isset(self::$tiposPorAbrev[$norm])) {
            return self::$tiposPorAbrev[$norm];
        }
        if ($raw !== '' && isset(self::$tiposPorAbrev[$raw])) {
            return self::$tiposPorAbrev[$raw];
        }
        if (str_starts_with($norm, 'NC')) {
            foreach (self::$tiposPorAbrev as $clave => $tipo) {
                if (str_starts_with($clave, 'NC')) {
                    return $tipo;
                }
            }
        }

        return null;
    }

    public static function coeficienteMoneda(string $codigoMoneda, float $cotizacion): float
    {
        if (strtoupper(trim($codigoMoneda)) === 'PES') {
            return 1.0;
        }

        return $cotizacion > 0.000001 ? $cotizacion : 1.0;
    }

    public static function esNotaCreditoAbreviatura(string $abrev): bool
    {
        $norm = self::normalizarAbreviaturaTipo($abrev);
        if ($norm === '') {
            $norm = strtoupper(preg_replace('/[^A-Z]/', '', $abrev) ?? '');
        }

        return str_starts_with($norm, 'NC') || $norm === 'CRE';
    }

    public static function esNotaCreditoTipo(?Tipotransaccion_Compra $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        if ((int) $tipo->getRawOriginal('signo') < 0) {
            return true;
        }

        if (self::esNotaCreditoAbreviatura((string) $tipo->abreviatura)) {
            return true;
        }

        $codigoAfip = (string) ($tipo->codigoafip ?? '');

        return LibroIvaDigitalMapeosSupport::esTipoNotaCredito(
            LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoAfip, 'A'),
        );
    }

    /**
     * @param  list<array{concepto: int, importe: float}>  $conceptos
     * @return list<array{neto: float, iva: float, tasa: float, concepto_iva_simple: int}>
     */
    public static function alicuotasIvaSimple(array $conceptos, string $letra, float $coeficiente = 1.0): array
    {
        $totales = self::desglosarConcmov($conceptos, $letra, $coeficiente);
        $filas = [];
        foreach ($totales['alicuotas'] as $row) {
            $filas[] = [
                'neto' => (float) ($row['neto'] ?? 0),
                'iva' => (float) ($row['iva'] ?? 0),
                'tasa' => (float) ($row['tasa'] ?? 0),
                'concepto_iva_simple' => (int) ($row['concepto_iva_simple'] ?? 1),
            ];
        }

        return $filas;
    }

    private static function cargarTipos(): void
    {
        if (self::$tiposPorAbrev !== null) {
            return;
        }

        self::$tiposPorAbrev = [];
        /** @var Collection<int, Tipotransaccion_Compra> $tipos */
        $tipos = Tipotransaccion_Compra::query()->whereNull('deleted_at')->get();
        foreach ($tipos as $tipo) {
            $abrev = strtoupper(substr(trim((string) $tipo->abreviatura), 0, 3));
            $norm = self::normalizarAbreviaturaTipo((string) $tipo->abreviatura);
            if ($abrev !== '') {
                self::$tiposPorAbrev[$abrev] = $tipo;
            }
            if ($norm !== '' && $norm !== $abrev) {
                self::$tiposPorAbrev[$norm] = $tipo;
            }
        }
    }

    private static function conceptoPorCodigo(int $codigo): ?Concepto_Ivacompra
    {
        self::cargarConceptos();

        return self::$conceptosPorCodigo[$codigo] ?? null;
    }

    private static function cargarConceptos(): void
    {
        if (self::$conceptosPorCodigo !== null) {
            return;
        }

        self::$conceptosPorCodigo = [];
        $conceptos = Concepto_Ivacompra::query()->with('impuestos')->get();
        foreach ($conceptos as $concepto) {
            $codigoNum = (int) preg_replace('/\D+/', '', (string) $concepto->codigo);
            if ($codigoNum > 0) {
                self::$conceptosPorCodigo[$codigoNum] = $concepto;
            }
        }
    }

    private static function codigoMonedaAnita(string $codMon): string
    {
        $cod = trim($codMon);
        if ($cod === '' || $cod === '1') {
            return 'PES';
        }
        if ($cod === '2') {
            return 'DOL';
        }
        if ($cod === '3') {
            return '060';
        }

        return LibroIvaDigitalMapeosSupport::codigoMonedaAfip($cod, null);
    }

    private static function fechaYmdAnita(string $valor): ?string
    {
        $digits = preg_replace('/\D+/', '', $valor) ?? '';
        if (strlen($digits) !== 8) {
            return null;
        }

        return $digits;
    }
}
