<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Caja\Flash\FlashCajaValidacionSupport;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Anita\AnitaMovimientoDetalleModuloSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionRendgAsientosDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlCtamovRendgDiaAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlFlashSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Conciliación Contable (solo lectura):
 * Facturación (cierres + post-cierre Waitry) ↔ Flash_ayb ↔ Asientos Waitry ↔
 * Mayor Anita de las cuentas del control ctamov/rendg (ventas/kiosco/tabaco/IVA).
 *
 * Mayor = neto haber (subdiario+ctamov) del día en esas cuentas, sin filtrar por nº de asiento Waitry.
 * Solo toma movimientos cuyo detalle indique gastronomía, porque las mismas cuentas
 * de ventas/IVA pueden recibir ajustes o estacionamiento.
 *
 * Transición Anita → ERP: Informix no discrimina vending en flash_ayb (= AyB + vending).
 * Mientras `control_flash_ayb_incluye_vending` esté activo, se resta el vending ERP del flash
 * antes de comparar con la facturación gastronomía. El ERP exporta AyB+vending a flash_ayb.
 *
 * Los asientos Waitry se resuelven igual que en el proceso de cierre
 * ({@see GastronomiaConciliacionRendgAsientosDiaSupport::auditarAsientosFacturacionJornada}).
 */
final class CierreTurnoGastronomiaContableConciliacionSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    /** Estacionamiento: fuera del alcance gastronomía Contable. */
    private const CUENTA_ESTACIONAMIENTO = 415010003;

    public function __construct(
        private readonly GastronomiaControlFlashSupport $flashSupport,
        private readonly GastronomiaConciliacionRendgAsientosDiaSupport $asientosSupport,
        private readonly GastronomiaControlCtamovRendgDiaAnitaSupport $ctamovSupport,
        private readonly AnitaMayorAnaliticoSupport $mayorSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
    ) {
    }

    /**
     * @return array{
     *   empresa_id: int,
     *   empresa_nombre: string,
     *   empresa_codigo_anita: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   tolerancia: float,
     *   flash_offset_dias: int,
     *   flash_ayb_incluye_vending: bool,
     *   cuentas_mayor: list<int>,
     *   dias: list<array<string, mixed>>,
     *   resumen: array<string, int>
     * }
     */
    public function conciliar(int $empresaId, string $fechaDesde, string $fechaHasta, ?float $tolerancia = null): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tolerancia = $tolerancia ?? (float) config(
            'gastronomia.conciliacion_diaria_reporte.tolerancia',
            self::TOLERANCIA_DEFAULT,
        );
        // Misma fecha de jornada: el offset del reporte diario (día anterior) no aplica acá.
        $flashOffset = 0;
        $flashIncluyeVending = (bool) config(
            'gastronomia.conciliacion_diaria_reporte.control_flash_ayb_incluye_vending',
            true,
        );

        $cierres = $this->cargarCierresDefinitivos($empresaId, $desde, $hasta);
        $empresaCodigo = (int) ($empresa->codigo ?? 0);
        $flashPorFecha = $this->cargarFlashAybPorFecha($empresaCodigo, $desde, $hasta);
        $flashValidadoPorFecha = FlashCajaValidacionSupport::mapaValidadoPorFecha($empresaId, $desde, $hasta);
        $vendingPorFecha = $flashIncluyeVending
            ? $this->cargarVendingErpPorFecha($empresaId, $desde, $hasta)
            : [];

        $asientosPorFecha = $this->cargarAsientosWaitryPorFecha($empresaId, $desde, $hasta);
        $cuentasMayor = $this->codigosMayorGastronomia($empresaId);
        $mayorPorFecha = $this->cargarMayorNetoCuentasControl(
            $empresaCodigo,
            $desde,
            $hasta,
            $cuentasMayor,
        );

        $dias = [];
        $diasOk = 0;
        $diasDif = 0;
        $diasSinAsiento = 0;

        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $dia = $this->armarDia(
                $fechaStr,
                $cierres,
                $flashPorFecha,
                $vendingPorFecha,
                $mayorPorFecha,
                $asientosPorFecha[$fechaStr] ?? $this->asientosVacios(),
                $flashOffset,
                $tolerancia,
                $flashIncluyeVending,
                $flashValidadoPorFecha,
            );

            $sinActividad = (int) ($dia['cantidad_cierres'] ?? 0) === 0
                && abs((float) ($dia['total_flash_ayb'] ?? 0)) <= $tolerancia
                && abs((float) ($dia['total_asientos_debe'] ?? 0)) <= $tolerancia
                && abs((float) ($dia['total_mayor_neto'] ?? 0)) <= $tolerancia;

            if ($sinActividad) {
                continue;
            }

            $dias[] = $dia;
            if (($dia['estado'] ?? '') === 'OK') {
                $diasOk++;
            } elseif (($dia['estado'] ?? '') === 'DIF') {
                $diasDif++;
            }
            if ((int) ($dia['cantidad_asientos'] ?? 0) === 0
                && (float) ($dia['total_facturacion'] ?? 0) > $tolerancia) {
                $diasSinAsiento++;
            }
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo_anita' => $empresaCodigo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'flash_offset_dias' => $flashOffset,
            'flash_ayb_incluye_vending' => $flashIncluyeVending,
            'cuentas_mayor' => $cuentasMayor,
            'dias' => $dias,
            'resumen' => [
                'total_dias' => count($dias),
                'dias_ok' => $diasOk,
                'dias_dif' => $diasDif,
                'dias_sin_asiento_waitry' => $diasSinAsiento,
            ],
        ];
    }

    /**
     * @return Collection<int, TurnoOperativoGastronomia>
     */
    private function cargarCierresDefinitivos(int $empresaId, string $desde, string $hasta): Collection
    {
        return TurnoOperativoGastronomia::query()
            ->with([
                'turno:id,nombre',
                'jornada:id,fecha_jornada',
                'configuracionPuntoventa.puntoventaCae:id,codigo,nombre',
                'configuracionPuntoventa.puntoventaCaea:id,codigo,nombre',
                'usuarioCierre:id,nombre',
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereHas('jornada', function ($j) use ($desde, $hasta) {
                $j->whereDate('fecha_jornada', '>=', $desde)
                    ->whereDate('fecha_jornada', '<=', $hasta);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, float> Y-m-d => flash_ayb
     */
    private function cargarFlashAybPorFecha(int $empresaCodigoAnita, string $desde, string $hasta): array
    {
        if ($empresaCodigoAnita <= 0) {
            return [];
        }

        try {
            $desglose = $this->flashSupport->desglosePorEmpresaJornada($desde, $hasta, [$empresaCodigoAnita]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo leer flash (caja Anita): '.$e->getMessage(), 0, $e);
        }

        $porEmpresa = $desglose[$empresaCodigoAnita] ?? [];
        $out = [];
        foreach ($porEmpresa as $fecha => $partes) {
            $out[$fecha] = round((float) ($partes['flash_ayb'] ?? 0), 2);
        }

        return $out;
    }

    /**
     * Ventas vending ERP por jornada (Σ MaquinavendingRendicion.total_ventas).
     *
     * @return array<string, float> Y-m-d => total
     */
    private function cargarVendingErpPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        return $this->vendingRendgSupport->totalesMaquinavendingErpPorJornada($empresaId, $desde, $hasta);
    }

    /**
     * Asientos Waitry de facturación por jornada (misma fuente que el proceso de cierre).
     *
     * @return array<string, array<string, mixed>> Y-m-d => resultado auditarAsientosFacturacionJornada
     */
    private function cargarAsientosWaitryPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $out = [];
        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $out[$fechaStr] = $this->asientosSupport->auditarAsientosFacturacionJornada($empresaId, $fechaStr);
        }

        return $out;
    }

    /**
     * Mayor Anita del día sobre las cuentas del control ctamov/rendg
     * (ventas / kiosco / tabaco / IVA débito / IVA crédito fiscal), sin filtrar por asiento Waitry.
     * Solo incluye líneas con detalle de gastronomía; evita ajustes ajenos y estacionamiento
     * en cuentas compartidas (ej. IVA 214/114).
     *
     * @param  list<int>  $cuentas
     * @return array<string, float> Y-m-d => neto haber
     */
    private function cargarMayorNetoCuentasControl(
        int $empresaCodigoAnita,
        string $desde,
        string $hasta,
        array $cuentas,
    ): array {
        if ($empresaCodigoAnita <= 0 || $cuentas === []) {
            return [];
        }

        $fechaDesdeYmd = (int) str_replace('-', '', $desde);
        $fechaHastaYmd = (int) str_replace('-', '', $hasta);

        try {
            $movimientos = $this->mayorSupport->listarMovimientosPeriodo(
                $empresaCodigoAnita,
                $fechaDesdeYmd,
                $fechaHastaYmd,
                $cuentas,
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo leer mayor Anita (subdiario/ctamov): '.$e->getMessage(), 0, $e);
        }

        $porFecha = [];
        foreach ($movimientos as $mov) {
            $cuenta = (int) ($mov['cuenta_codigo'] ?? $mov['cuenta'] ?? 0);
            if ($cuenta <= 0 || $cuenta === self::CUENTA_ESTACIONAMIENTO) {
                continue;
            }
            if (! AnitaMovimientoDetalleModuloSupport::esGastronomia($mov)) {
                continue;
            }

            $fecha = (string) ($mov['fecha'] ?? '');
            if ($fecha === '') {
                continue;
            }

            $neto = (float) ($mov['neto_haber'] ?? 0);
            if ($neto == 0.0) {
                $neto = round((float) ($mov['haber'] ?? 0) - (float) ($mov['debe'] ?? 0), 2);
            }
            $porFecha[$fecha] = round(($porFecha[$fecha] ?? 0) + $neto, 2);
        }

        return $porFecha;
    }

    /**
     * Cuentas del control {@see GastronomiaControlCtamovRendgDiaAnitaSupport::codigosCtamovEmpresa()}
     * sin estacionamiento (esta pantalla confronta Facturación gastronomía).
     *
     * @return list<int>
     */
    private function codigosMayorGastronomia(int $empresaId): array
    {
        return array_values(array_filter(
            $this->ctamovSupport->codigosCtamovEmpresa($empresaId),
            static fn (int $codigo): bool => $codigo !== self::CUENTA_ESTACIONAMIENTO && $codigo > 0,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function asientosVacios(): array
    {
        return [
            'factura_dia' => 0.0,
            'post_cierre' => 0.0,
            'totem_ventas' => 0.0,
            'totem_puente' => 0.0,
            'agregados_caea' => 0.0,
            'total' => 0.0,
            'cantidad' => 0,
            'detalle' => [],
            'otros' => [],
        ];
    }

    /**
     * @param  Collection<int, TurnoOperativoGastronomia>  $cierres
     * @param  array<string, float>  $flashPorFecha
     * @param  array<string, float>  $vendingPorFecha
     * @param  array<string, float>  $mayorPorFecha
     * @param  array<string, mixed>  $asientos
     * @param  array<string, bool>  $flashValidadoPorFecha
     * @return array<string, mixed>
     */
    private function armarDia(
        string $fechaJornada,
        Collection $cierres,
        array $flashPorFecha,
        array $vendingPorFecha,
        array $mayorPorFecha,
        array $asientos,
        int $flashOffset,
        float $tolerancia,
        bool $flashIncluyeVending,
        array $flashValidadoPorFecha = [],
    ): array {
        $fechaFlash = $flashOffset > 0
            ? Carbon::parse($fechaJornada)->subDays($flashOffset)->toDateString()
            : $fechaJornada;

        /** @var array<string, array<string, mixed>> $porPc */
        $porPc = [];
        $totalCierres = 0.0;
        $totalHabilitacion = 0.0;
        $cantidad = 0;

        foreach ($cierres as $cierre) {
            $fecha = $cierre->jornada?->fecha_jornada?->format('Y-m-d');
            if ($fecha !== $fechaJornada) {
                continue;
            }

            $pc = trim((string) ($cierre->identificador_pc ?? ''));
            if ($pc === '') {
                $pc = '—';
            }
            $pv = $cierre->configuracionPuntoventa?->puntoventaCae
                ?? $cierre->configuracionPuntoventa?->puntoventaCaea;
            $pvCodigo = trim((string) ($pv?->codigo ?? '—'));
            $pvNombre = trim((string) ($pv?->nombre ?? ''));
            $key = $pc.'|'.$pvCodigo;

            if (! isset($porPc[$key])) {
                $porPc[$key] = [
                    'identificador_pc' => $pc,
                    'pv_codigo' => $pvCodigo,
                    'pv_nombre' => $pvNombre !== '' ? $pvNombre : $pvCodigo,
                    'turno_nombre' => '',
                    'cantidad' => 0,
                    'total_facturacion' => 0.0,
                    'total_habilitacion' => 0.0,
                    'cierres' => [],
                ];
            }

            $facturacion = round((float) ($cierre->monto_facturacion_turno ?? 0), 2);
            $habilitacion = round((float) ($cierre->monto_habilitacion ?? 0), 2);
            $porPc[$key]['cantidad']++;
            $porPc[$key]['total_facturacion'] = round($porPc[$key]['total_facturacion'] + $facturacion, 2);
            $porPc[$key]['total_habilitacion'] = round($porPc[$key]['total_habilitacion'] + $habilitacion, 2);
            $porPc[$key]['turno_nombre'] = (string) ($cierre->turno?->nombre ?? '');
            $porPc[$key]['cierres'][] = [
                'id' => (int) $cierre->id,
                'turno' => (string) ($cierre->turno?->nombre ?? ''),
                'usuario' => (string) ($cierre->usuarioCierre?->nombre ?? ''),
                'cierre_en' => $cierre->cierre_en?->format('d/m/Y H:i') ?? '',
                'total_facturacion' => $facturacion,
            ];
            $totalCierres = round($totalCierres + $facturacion, 2);
            $totalHabilitacion = round($totalHabilitacion + $habilitacion, 2);
            $cantidad++;
        }

        $terminales = array_values($porPc);
        usort($terminales, static function (array $a, array $b): int {
            return strcmp($a['identificador_pc'].$a['pv_codigo'], $b['identificador_pc'].$b['pv_codigo']);
        });

        $totalAsientos = round((float) ($asientos['total'] ?? 0), 2);
        $facturaDia = round((float) ($asientos['factura_dia'] ?? 0), 2);
        $postCierre = round((float) ($asientos['post_cierre'] ?? 0), 2);
        $totemVentas = round((float) ($asientos['totem_ventas'] ?? 0), 2);
        $agregadosCaea = round((float) ($asientos['agregados_caea'] ?? 0), 2);
        $cantidadAsientos = (int) ($asientos['cantidad'] ?? 0);

        // Total facturado del día = cierres de turno + facturación post-cierre Waitry (+ agregados CAEA).
        // El tótem NO se suma aparte: el monto_facturacion_turno del cierre YA lo incluye. Verificado en
        // BIYEMAS (única empresa con tótem) 16/7 y 17/7: sum(cierres) − factura_día del asiento == totem_ventas
        // exacto. Volver a sumar $totemVentas lo contaba dos veces y generaba una DIF falsa igual al importe
        // del tótem ($5.400 el 16/7, $5.900 el 17/7). $totemVentas se conserva solo para el detalle informativo.
        $totalFacturacion = round($totalCierres + $postCierre + $agregadosCaea, 2);

        // Anita Informix: flash_ayb = AyB + vending. Restamos vending ERP para confrontar solo gastronomía.
        $flashAybBruto = round((float) ($flashPorFecha[$fechaFlash] ?? 0), 2);
        $vendingErp = $flashIncluyeVending
            ? round((float) ($vendingPorFecha[$fechaJornada] ?? 0), 2)
            : 0.0;
        $flashAyb = round($flashAybBruto - $vendingErp, 2);
        $mayorNeto = round((float) ($mayorPorFecha[$fechaJornada] ?? 0), 2);

        $diferenciaFlash = round($totalFacturacion - $flashAyb, 2);
        $diferenciaAsientos = round($totalFacturacion - $totalAsientos, 2);
        $diferenciaMayor = round($totalFacturacion - $mayorNeto, 2);

        $estado = (abs($diferenciaFlash) <= $tolerancia
            && abs($diferenciaMayor) <= $tolerancia
            && abs($diferenciaAsientos) <= $tolerancia)
            ? 'OK'
            : 'DIF';

        return [
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => Carbon::parse($fechaJornada)->format('d/m/Y'),
            'fecha_flash' => $fechaFlash,
            'fecha_flash_fmt' => Carbon::parse($fechaFlash)->format('d/m/Y'),
            'terminales' => $terminales,
            'cantidad_cierres' => $cantidad,
            'cantidad_asientos' => $cantidadAsientos,
            'total_facturacion' => $totalFacturacion,
            'total_cierres' => $totalCierres,
            'total_habilitacion' => $totalHabilitacion,
            'total_flash_ayb' => $flashAyb,
            'flash_validado' => ! empty($flashValidadoPorFecha[$fechaFlash]),
            'total_flash_ayb_bruto' => $flashAybBruto,
            'total_vending' => $vendingErp,
            'flash_ayb_incluye_vending' => $flashIncluyeVending,
            'total_asientos_debe' => $totalAsientos,
            'total_mayor_neto' => $mayorNeto,
            'diferencia_flash' => $diferenciaFlash,
            'diferencia_asientos' => $diferenciaAsientos,
            'diferencia_mayor' => $diferenciaMayor,
            'asientos_detalle' => $asientos['detalle'] ?? [],
            'asientos_tipos' => [
                'factura_dia' => $facturaDia,
                'post_cierre' => $postCierre,
                'totem_ventas' => $totemVentas,
                'totem_puente' => (float) ($asientos['totem_puente'] ?? 0),
                'agregados_caea' => $agregadosCaea,
            ],
            'estado' => $estado,
        ];
    }
}
