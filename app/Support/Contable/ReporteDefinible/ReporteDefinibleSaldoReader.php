<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de saldos para reportes definibles (asientos + filtro c.costo).
 */
class ReporteDefinibleSaldoReader
{
    /** @var array<string, array{valor: float, fecha: string|null, exacta: bool, hacia_adelante: bool}> */
    private array $cacheCotizacionDia = [];

    /** Movimientos descartados en la última lectura porque la moneda no tiene ninguna cotización cargada. */
    private int $movimientosSinCotizacion = 0;

    /** Movimientos convertidos con la cotización vigente de un día anterior (la tabla no la tiene para su fecha). */
    private int $movimientosCotizacionVigente = 0;

    private ?string $fechaCotizacionVigenteMasVieja = null;

    public function movimientosSinCotizacion(): int
    {
        return $this->movimientosSinCotizacion;
    }

    public function movimientosCotizacionVigente(): int
    {
        return $this->movimientosCotizacionVigente;
    }

    public function fechaCotizacionVigenteMasVieja(): ?string
    {
        return $this->fechaCotizacionVigenteMasVieja;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $codigos
     * @return list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id: int}>
     */
    public function listarMovimientos(
        array $empresaIds,
        string $fechaDesde,
        string $fechaHasta,
        array $codigos,
        string $modoAsientos,
        int $monedaId,
        bool $soloMonedaOrigen = false,
        ?string $fechaCotizacionCierre = null,
    ): array {
        if ($empresaIds === [] || $codigos === [] || $fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $this->movimientosSinCotizacion = 0;
        $this->movimientosCotizacionVigente = 0;
        $this->fechaCotizacionVigenteMasVieja = null;
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();
        $codigosStr = array_map('strval', $codigos);
        $fechaFx = $fechaCotizacionCierre && strlen($fechaCotizacionCierre) >= 10
            ? substr($fechaCotizacionCierre, 0, 10)
            : null;

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'am.centrocosto_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->whereNotNull('am.cuentacontable_id')
            ->whereBetween('a.fecha', [$fechaDesde, $fechaHasta])
            ->whereIn('c.codigo', $codigosStr)
            ->select([
                'c.codigo',
                'cc.codigo as ccosto_codigo',
                'a.fecha',
                'a.empresa_id',
                'am.monto',
                'am.moneda_id',
                'am.cotizacion',
                'ta.abreviatura as tipo_abrev',
            ]);

        if ($soloMonedaOrigen) {
            $query->where('am.moneda_id', $monedaId);
        }

        $out = [];
        foreach ($query->cursor() as $row) {
            $tipo = (string) ($row->tipo_abrev ?? '');
            if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento($tipo, $modoAsientos)) {
                continue;
            }

            $fechaAsiento = (string) $row->fecha;
            $fechaParaFx = $fechaFx ?? $fechaAsiento;

            $importe = $this->importeEnMonedaReporte(
                (float) $row->monto,
                (int) $row->moneda_id,
                $monedaId,
                $monedaLocalId,
                $soloMonedaOrigen,
                isset($row->cotizacion) ? (float) $row->cotizacion : null,
                $fechaParaFx,
            );
            if (abs($importe) < 1e-9) {
                continue;
            }

            $out[] = [
                'codigo' => (int) $row->codigo,
                'ccosto' => (int) ($row->ccosto_codigo ?? 0),
                'monto' => $importe,
                'fecha' => $fechaAsiento,
                'empresa_id' => (int) ($row->empresa_id ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string}>  $movimientos
     * @param  list<array{desde: int, hasta: int}>  $rangosDefinicion
     * @param  array{desde: int, hasta: int}|null  $filtroRuntime
     */
    public function sumarAsignacion(
        array $movimientos,
        int $codigoCuenta,
        string $cargaCcosto,
        array $rangosDefinicion,
        ?array $filtroRuntime,
        int $signo = 1,
    ): float {
        $signo = $signo >= 0 ? 1 : -1;
        $total = 0.0;

        foreach ($movimientos as $mov) {
            if ((int) $mov['codigo'] !== $codigoCuenta) {
                continue;
            }
            $cc = (int) $mov['ccosto'];
            if (! $this->pasaFiltroDefinicion($cargaCcosto, $rangosDefinicion, $cc)) {
                continue;
            }
            if ($filtroRuntime !== null && ! $this->pasaRango($cc, (int) $filtroRuntime['desde'], (int) $filtroRuntime['hasta'])) {
                continue;
            }
            $total += (float) $mov['monto'];
        }

        return round($total * $signo, 2);
    }

    /**
     * Suma de una asignación acotada a un c.costo concreto (0 = sin c.costo).
     *
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string}>  $movimientos
     * @param  list<array{desde: int, hasta: int}>  $rangosDefinicion
     */
    public function sumarAsignacionEnCcosto(
        array $movimientos,
        int $codigoCuenta,
        string $cargaCcosto,
        array $rangosDefinicion,
        int $ccostoExacto,
        int $signo = 1,
    ): float {
        $signo = $signo >= 0 ? 1 : -1;
        $total = 0.0;

        foreach ($movimientos as $mov) {
            if ((int) $mov['codigo'] !== $codigoCuenta) {
                continue;
            }
            $cc = (int) $mov['ccosto'];
            if ($cc !== $ccostoExacto) {
                continue;
            }
            if (! $this->pasaFiltroDefinicion($cargaCcosto, $rangosDefinicion, $cc)) {
                continue;
            }
            $total += (float) $mov['monto'];
        }

        return round($total * $signo, 2);
    }

    /**
     * Códigos de c.costo presentes en movimientos (incluye 0 si hay sin c.costo).
     *
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string}>  $movimientos
     * @return list<int>
     */
    public function codigosCcostoEnMovimientos(array $movimientos): array
    {
        $set = [];
        foreach ($movimientos as $mov) {
            $set[(int) $mov['ccosto']] = true;
        }
        $out = array_map('intval', array_keys($set));
        sort($out);

        return $out;
    }

    /**
     * @param  list<array{desde: int, hasta: int}>  $rangos
     */
    private function pasaFiltroDefinicion(string $carga, array $rangos, int $ccosto): bool
    {
        $carga = ReporteDefinibleSupport::normalizarCargaCcosto($carga);
        if ($carga === ReporteDefinibleSupport::CCOSTO_SIN) {
            return true;
        }
        if ($rangos === []) {
            return false;
        }
        foreach ($rangos as $r) {
            if ($this->pasaRango($ccosto, (int) $r['desde'], (int) $r['hasta'])) {
                return true;
            }
        }

        return false;
    }

    private function pasaRango(int $ccosto, int $desde, int $hasta): bool
    {
        if ($desde <= 0 && $hasta <= 0) {
            return true;
        }
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde > 0 && $ccosto < $desde) {
            return false;
        }
        if ($hasta > 0 && $ccosto > $hasta) {
            return false;
        }

        return true;
    }

    private function importeEnMonedaReporte(
        float $montoFirmado,
        int $monedaMovimientoId,
        int $monedaReporteId,
        int $monedaLocalId,
        bool $soloOrigen,
        ?float $cotizacionMovimiento,
        string $fechaAsiento = '',
    ): float {
        if (abs($montoFirmado) < 1e-12) {
            return 0.0;
        }
        if ($soloOrigen) {
            return $monedaMovimientoId === $monedaReporteId ? $montoFirmado : 0.0;
        }
        if ($monedaMovimientoId === $monedaReporteId) {
            return $montoFirmado;
        }

        $enPesos = CuentacontableSaldoMesSupport::convertirMontoLocal(
            $montoFirmado,
            $monedaMovimientoId,
            $cotizacionMovimiento,
        );
        if ($monedaReporteId === $monedaLocalId) {
            return $enPesos;
        }

        $cotizReporte = $this->cotizacionVentaParaFecha($fechaAsiento, $monedaReporteId);
        if ($cotizReporte < 0.01) {
            // Sin cotización del día el movimiento queda afuera: se cuenta para avisarlo,
            // porque una columna en otra moneda que pierde importes en silencio miente.
            $this->movimientosSinCotizacion++;

            return 0.0;
        }

        return $enPesos * calculaCoeficienteMoneda($monedaReporteId, $monedaLocalId, $cotizReporte);
    }

    private function cotizacionVentaParaFecha(string $fecha, int $monedaId): float
    {
        $fecha = trim($fecha);
        if ($fecha === '' || $monedaId <= 1) {
            return 1.0;
        }
        $fechaKey = strlen($fecha) >= 10 ? substr($fecha, 0, 10) : $fecha;
        $cacheKey = $fechaKey.'|'.$monedaId;
        $cot = $this->cacheCotizacionDia[$cacheKey]
            ??= CotizacionVigenteSupport::venta($fechaKey, $monedaId);

        $valor = (float) $cot['valor'];
        if ($valor > 0 && ! $cot['exacta'] && $cot['fecha'] !== null) {
            // La tabla no tiene cotización propia de ese día (fila en cero o día sin carga):
            // se usó la vigente y se cuenta el movimiento para poder avisarlo en el informe.
            $this->movimientosCotizacionVigente++;
            if ($this->fechaCotizacionVigenteMasVieja === null || $cot['fecha'] < $this->fechaCotizacionVigenteMasVieja) {
                $this->fechaCotizacionVigenteMasVieja = $cot['fecha'];
            }
        }

        return $valor;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function fechasDesdePeriodo(int $periodo): array
    {
        $y = intdiv($periodo, 100);
        $m = $periodo % 100;
        $desde = sprintf('%04d-%02d-01', $y, $m);
        $hasta = date('Y-m-t', strtotime($desde));

        return [$desde, $hasta];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function fechasDesdePeriodos(int $periodoDesde, int $periodoHasta): array
    {
        [$d] = self::fechasDesdePeriodo($periodoDesde);
        [, $h] = self::fechasDesdePeriodo($periodoHasta);

        return [$d, $h];
    }
}
