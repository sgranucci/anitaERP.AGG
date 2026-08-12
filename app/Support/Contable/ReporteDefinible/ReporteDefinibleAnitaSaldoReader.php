<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Configuracion\Empresa;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaAnitaBridgeReader;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

/**
 * Movimientos del período leídos de Anita (ctamov + subdiario) con la misma forma
 * que {@see ReporteDefinibleSaldoReader::listarMovimientos()}, para comparar el
 * informe definible contra la fuente Informix (paridad l-infomae).
 *
 * Convención de signo igual al ERP: monto positivo = Debe, negativo = Haber.
 */
class ReporteDefinibleAnitaSaldoReader
{
    public function __construct(
        private readonly MayorPlanoCuentaAnitaBridgeReader $bridge,
        private readonly MayorConceptoMonedaConverter $monedaConverter,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds  IDs de empresa del ERP
     * @param  list<int>  $codigos  códigos de cuenta contable (enteros Anita)
     * @return array{
     *   movimientos: list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id: int}>,
     *   errores: list<string>,
     *   stats: array<string, int>
     * }
     */
    public function listarMovimientos(
        array $empresaIds,
        string $fechaDesde,
        string $fechaHasta,
        array $codigos,
        string $modoAsientos = 'sin_cierre_ni_inflacion',
        ?int $monedaId = null,
        bool $soloMonedaOrigen = false,
        bool $incluyeSubdiario = true,
    ): array {
        $vacio = ['movimientos' => [], 'errores' => [], 'stats' => ['ctamov' => 0, 'subdiario' => 0, 'movimientos' => 0]];
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds), fn (int $i) => $i > 0)));
        $codigos = array_values(array_unique(array_filter(array_map('intval', $codigos), fn (int $c) => $c > 0)));
        if ($empresaIds === [] || $codigos === [] || $fechaDesde === '' || $fechaHasta === '') {
            return $vacio;
        }

        $monedaId = $monedaId ?: CuentacontableSaldoMesSupport::monedaLocalId();
        $codMonReporte = $this->monedaConverter->codigoAnitaDesdeMonedaId($monedaId);
        $ymdDesde = $this->ymd($fechaDesde);
        $ymdHasta = $this->ymd($fechaHasta);
        if ($ymdDesde <= 0 || $ymdHasta < $ymdDesde) {
            return $vacio;
        }

        [$codigosAnita, $empresaIdPorCodigoAnita] = $this->mapearEmpresas($empresaIds);
        if ($codigosAnita === []) {
            return $vacio;
        }

        $errores = [];
        // fechaSaldoDesde = fechaDesde ⇒ el reader no baja el tramo previo (paridad de período).
        $datos = $this->bridge->cargarPeriodo(
            $codigosAnita,
            $ymdDesde,
            $ymdHasta,
            $ymdDesde,
            $incluyeSubdiario,
            0,
            0,
            $codigos,
        );
        $errores = array_merge($errores, $datos['errores'] ?? []);

        $codigosSet = array_fill_keys($codigos, true);
        $movs = [];

        foreach ($datos['ctamov'] ?? [] as $linea) {
            $mov = $this->desdeCtamov(
                $linea, $codigosSet, $modoAsientos, $codMonReporte, $monedaId,
                $soloMonedaOrigen, $empresaIdPorCodigoAnita
            );
            if ($mov !== null) {
                $movs[] = $mov;
            }
        }

        if ($incluyeSubdiario) {
            foreach ($datos['subdiario'] ?? [] as $linea) {
                foreach ($this->desdeSubdiario(
                    $linea, $codigosSet, $codMonReporte, $monedaId,
                    $soloMonedaOrigen, $empresaIdPorCodigoAnita
                ) as $mov) {
                    $movs[] = $mov;
                }
            }
        }

        return [
            'movimientos' => $movs,
            'errores' => array_values(array_unique(array_map('strval', $errores))),
            'stats' => [
                'ctamov' => count($datos['ctamov'] ?? []),
                'subdiario' => count($datos['subdiario'] ?? []),
                'movimientos' => count($movs),
            ],
        ];
    }

    /**
     * @param  array<int, true>  $codigosSet
     * @param  array<int, int>  $empresaIdPorCodigoAnita
     * @return array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id: int}|null
     */
    private function desdeCtamov(
        object $linea,
        array $codigosSet,
        string $modoAsientos,
        string $codMonReporte,
        int $monedaId,
        bool $soloMonedaOrigen,
        array $empresaIdPorCodigoAnita,
    ): ?array {
        if (strtoupper(trim((string) ($linea->ctav_balancea ?? 'S'))) !== 'S') {
            return null;
        }
        if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            (string) ($linea->ctav_tipo_asiento ?? ''),
            $modoAsientos
        )) {
            return null;
        }

        $imputacion = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($linea);
        if ($imputacion === null || ! isset($codigosSet[$imputacion['cuenta']])) {
            return null;
        }

        $codMon = $this->codMon((string) ($linea->ctav_cod_mon ?? '1'));
        $cotizacion = (float) ($linea->ctav_cotizacion ?? 0);
        if (! MayorPlanoCuentaSupport::movimientoVisibleMoneda($codMon, $cotizacion, $codMonReporte, $soloMonedaOrigen)) {
            return null;
        }

        $fechaYmd = (int) ($linea->ctav_fecha ?? 0);
        $monto = $this->montoFirmado($imputacion['importe'], $imputacion['dh'], $codMon, $cotizacion, $fechaYmd, $monedaId);
        if ($monto === null) {
            return null;
        }

        return [
            'codigo' => (int) $imputacion['cuenta'],
            'ccosto' => (int) ($linea->ctav_ccosto ?? 0),
            'monto' => $monto,
            'fecha' => $this->fechaIso($fechaYmd),
            'empresa_id' => (int) ($empresaIdPorCodigoAnita[(int) ($linea->ctav_empresa ?? 0)] ?? 0),
        ];
    }

    /**
     * Ambas piernas de la línea de subdiario (cuenta + contrapartida), con el c.costo del lado.
     *
     * @param  array<int, true>  $codigosSet
     * @param  array<int, int>  $empresaIdPorCodigoAnita
     * @return list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id: int}>
     */
    private function desdeSubdiario(
        object $linea,
        array $codigosSet,
        string $codMonReporte,
        int $monedaId,
        bool $soloMonedaOrigen,
        array $empresaIdPorCodigoAnita,
    ): array {
        $codMon = $this->codMon((string) ($linea->subd_cod_mon ?? '1'));
        $cotizacion = (float) ($linea->subd_cotizacion ?? 0);
        if (! MayorPlanoCuentaSupport::movimientoVisibleMoneda($codMon, $cotizacion, $codMonReporte, $soloMonedaOrigen)) {
            return [];
        }

        $fechaYmd = (int) ($linea->subd_fecha ?? 0);
        $empresaId = (int) ($empresaIdPorCodigoAnita[(int) ($linea->subd_empresa ?? 0)] ?? 0);
        $out = [];

        foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($linea) as $imputacion) {
            if (! isset($codigosSet[$imputacion['cuenta']])) {
                continue;
            }
            $monto = $this->montoFirmado($imputacion['importe'], $imputacion['dh'], $codMon, $cotizacion, $fechaYmd, $monedaId);
            if ($monto === null) {
                continue;
            }
            $ccosto = $imputacion['lado'] === 'cuenta'
                ? (int) ($linea->subd_ccosto_cta ?? 0)
                : (int) ($linea->subd_ccosto_con ?? 0);

            $out[] = [
                'codigo' => (int) $imputacion['cuenta'],
                'ccosto' => $ccosto,
                'monto' => $monto,
                'fecha' => $this->fechaIso($fechaYmd),
                'empresa_id' => $empresaId,
            ];
        }

        return $out;
    }

    private function montoFirmado(
        float $importe,
        string $dh,
        string $codMon,
        float $cotizacion,
        int $fechaYmd,
        int $monedaId,
    ): ?float {
        $convertido = $this->monedaConverter->convertirImporte($importe, $codMon, $cotizacion, $fechaYmd, $monedaId);
        if (abs($convertido) < 1e-9) {
            return null;
        }

        return strtoupper(trim($dh)) === 'D' ? $convertido : -$convertido;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{0: list<int>, 1: array<int, int>}
     */
    private function mapearEmpresas(array $empresaIds): array
    {
        $codigosAnita = [];
        $reverso = [];
        foreach (Empresa::query()->whereIn('id', $empresaIds)->get(['id', 'codigo']) as $empresa) {
            $id = (int) $empresa->id;
            $codigo = trim((string) ($empresa->codigo ?? ''));
            $codigoAnita = ($codigo !== '' && ctype_digit($codigo)) ? (int) $codigo : $id;
            $codigosAnita[] = $codigoAnita;
            $reverso[$codigoAnita] = $id;
        }

        return [array_values(array_unique($codigosAnita)), $reverso];
    }

    private function codMon(string $valor): string
    {
        $valor = trim($valor);

        return $valor !== '' ? $valor : '1';
    }

    private function ymd(string $fechaIso): int
    {
        $limpia = preg_replace('/\D/', '', substr(trim($fechaIso), 0, 10)) ?? '';

        return strlen($limpia) === 8 ? (int) $limpia : 0;
    }

    private function fechaIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8) {
            return '';
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
