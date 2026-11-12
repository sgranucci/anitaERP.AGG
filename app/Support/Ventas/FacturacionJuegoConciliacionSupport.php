<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\FacturacionJuegoSala;
use App\Models\Ventas\Venta;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Conciliación facturación juego: Flash × Ventas × Contab. ERP × Mayor Anita.
 */
final class FacturacionJuegoConciliacionSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    public function __construct(
        private readonly AnitaMayorAnaliticoSupport $mayorAnita = new AnitaMayorAnaliticoSupport(),
    ) {
    }

    /**
     * @return array{
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   sala_id: int,
     *   tolerancia: float,
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, mixed>,
     *   help_salas: list<array<string, mixed>>,
     *   subtitulo: string
     * }
     */
    public function conciliar(string $fechaDesde, string $fechaHasta, ?int $salaId = null): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tolerancia = (float) config(
            'facturacion_juego.conciliacion_tolerancia',
            self::TOLERANCIA_DEFAULT,
        );

        $configs = FacturacionJuegoConfigSupport::salasActivas();
        if ($salaId !== null && $salaId > 0) {
            $configs = $configs->where('sala_id', $salaId)->values();
        }

        $helpSalas = $this->armarHelpSalas($configs);
        $cuentaPorRubroSala = $this->indiceCuentasPorSalaRubro($configs);
        $mapaCuentaASalaRubro = $this->mapaCodigoCuentaASalaRubro($configs);

        $flashes = $this->cargarFlashes($desde, $hasta, $salaId);
        $ventaIds = [];
        $asientoIds = [];
        foreach ($flashes as $flash) {
            foreach ($this->rubros() as $rubro) {
                $ventaId = $this->ventaIdFlash($flash, $rubro);
                $asientoId = $this->asientoIdFlash($flash, $rubro);
                if ($ventaId > 0) {
                    $ventaIds[] = $ventaId;
                }
                if ($asientoId > 0) {
                    $asientoIds[] = $asientoId;
                }
            }
        }

        $ventasPorId = $this->cargarVentasFirmadas($ventaIds);
        $erpPorAsientoCuenta = $this->cargarErpPorAsientoCuenta($asientoIds, $cuentaPorRubroSala);
        $anitaPorFechaCodigo = $this->cargarAnitaPorFechaCodigo(
            $flashes,
            $configs,
            $desde,
            $hasta,
            $mapaCuentaASalaRubro,
        );

        $filas = [];
        $clavesUsadasAnita = [];

        foreach ($flashes as $flash) {
            $fechaStr = Carbon::parse((string) $flash->fecha)->toDateString();
            $salaFlashId = (int) $flash->sala_id;
            $cfg = $configs->firstWhere('sala_id', $salaFlashId);
            $salaCodigo = (string) ($flash->sala->codigo ?? '');
            $salaNombre = (string) ($flash->sala->nombre ?? '');

            foreach ($this->rubros() as $rubro) {
                $cuentaId = (int) ($cuentaPorRubroSala[$salaFlashId][$rubro]['id'] ?? 0);
                $cuentaCodigo = (string) ($cuentaPorRubroSala[$salaFlashId][$rubro]['codigo'] ?? '');
                $codigoAnita = (int) preg_replace('/\D+/', '', $cuentaCodigo);

                $flashMonto = FacturacionJuegoTotalesSupport::resultadoRubro($flash, $rubro);
                $ventaId = $this->ventaIdFlash($flash, $rubro);
                $asientoId = $this->asientoIdFlash($flash, $rubro);
                $procesado = FacturacionJuegoTotalesSupport::procesado($flash, $rubro);

                $ventaMonto = $ventaId > 0 ? (float) ($ventasPorId[$ventaId] ?? 0.0) : 0.0;
                $erpMonto = 0.0;
                if ($asientoId > 0 && $cuentaId > 0) {
                    $erpMonto = (float) ($erpPorAsientoCuenta[$asientoId][$cuentaId] ?? 0.0);
                }

                $anitaMonto = 0.0;
                if ($codigoAnita > 0) {
                    $claveAnita = $fechaStr.'|'.$codigoAnita;
                    $anitaMonto = (float) ($anitaPorFechaCodigo[$claveAnita] ?? 0.0);
                    if (abs($anitaMonto) > $tolerancia || isset($anitaPorFechaCodigo[$claveAnita])) {
                        $clavesUsadasAnita[$claveAnita] = true;
                    }
                }

                $sinCfg = $cfg === null
                    || ! $cfg->activo
                    || FacturacionJuegoConfigSupport::faltantesSala($cfg, $rubro) !== [];

                $hayAlgo = abs($flashMonto) > $tolerancia
                    || abs($ventaMonto) > $tolerancia
                    || abs($erpMonto) > $tolerancia
                    || abs($anitaMonto) > $tolerancia
                    || $procesado
                    || $ventaId > 0
                    || $asientoId > 0;

                if (! $hayAlgo) {
                    continue;
                }

                $filas[] = $this->armarFila([
                    'fecha' => $fechaStr,
                    'sala_id' => $salaFlashId,
                    'sala_codigo' => $salaCodigo,
                    'sala_nombre' => $salaNombre,
                    'rubro' => $rubro,
                    'flash_id' => (int) $flash->id,
                    'venta_id' => $ventaId > 0 ? $ventaId : null,
                    'asiento_id' => $asientoId > 0 ? $asientoId : null,
                    'cuenta_codigo' => $cuentaCodigo,
                    'flash' => $flashMonto,
                    'ventas' => $ventaMonto,
                    'erp' => $erpMonto,
                    'anita' => $anitaMonto,
                    'procesado' => $procesado,
                    'sin_cfg' => $sinCfg,
                    'sin_flash' => false,
                    'tolerancia' => $tolerancia,
                ]);
            }
        }

        foreach ($anitaPorFechaCodigo as $clave => $montoAnita) {
            if (isset($clavesUsadasAnita[$clave])) {
                continue;
            }
            if (abs((float) $montoAnita) <= $tolerancia) {
                continue;
            }
            [$fechaStr, $codigoAnitaStr] = array_pad(explode('|', (string) $clave, 2), 2, '');
            $codigoAnita = (int) $codigoAnitaStr;
            $meta = $mapaCuentaASalaRubro[$codigoAnita] ?? null;
            if ($meta === null) {
                continue;
            }
            if ($salaId !== null && $salaId > 0 && (int) $meta['sala_id'] !== $salaId) {
                continue;
            }

            $filas[] = $this->armarFila([
                'fecha' => $fechaStr,
                'sala_id' => (int) $meta['sala_id'],
                'sala_codigo' => (string) $meta['sala_codigo'],
                'sala_nombre' => (string) $meta['sala_nombre'],
                'rubro' => (string) $meta['rubro'],
                'flash_id' => null,
                'venta_id' => null,
                'asiento_id' => null,
                'cuenta_codigo' => (string) $meta['cuenta_codigo'],
                'flash' => 0.0,
                'ventas' => 0.0,
                'erp' => 0.0,
                'anita' => (float) $montoAnita,
                'procesado' => false,
                'sin_cfg' => false,
                'sin_flash' => true,
                'tolerancia' => $tolerancia,
            ]);
        }

        usort($filas, static function (array $a, array $b): int {
            return [$a['fecha'], $a['sala_codigo'], $a['rubro']]
                <=> [$b['fecha'], $b['sala_codigo'], $b['rubro']];
        });

        $resumen = $this->resumen($filas, $tolerancia);
        $salaEtiqueta = 'Todas';
        if ($salaId !== null && $salaId > 0) {
            $cfgSala = $configs->firstWhere('sala_id', $salaId);
            $salaEtiqueta = (string) ($cfgSala?->sala?->codigo ?? '#'.$salaId);
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'sala_id' => (int) ($salaId ?? 0),
            'tolerancia' => $tolerancia,
            'filas' => $filas,
            'resumen' => $resumen,
            'help_salas' => $helpSalas,
            'subtitulo' => sprintf(
                'Período %s a %s — Sala: %s — Tolerancia %s',
                Carbon::parse($desde)->format('d/m/Y'),
                Carbon::parse($hasta)->format('d/m/Y'),
                $salaEtiqueta,
                number_format($tolerancia, 2, ',', '.'),
            ),
        ];
    }

    /**
     * @param  Collection<int, FacturacionJuegoSala>  $configs
     * @return list<array<string, mixed>>
     */
    public function armarHelpSalas(Collection $configs): array
    {
        $out = [];
        foreach ($configs as $cfg) {
            $cfg->loadMissing([
                'sala',
                'cuentacaja.cuentacontables',
                'cuentacontableMesa',
                'cuentacontableSlot',
                'cuentacontableRuletaEl',
                'cuentacontableDelRio',
                'cuentacontableBetsur',
                'cuentacontableApuestas',
                'centrocosto',
            ]);
            $caja = $cfg->cuentacaja;
            $cuentaCajaContable = $caja?->cuentacontables;
            $param = FacturacionJuegoConfigSupport::parametro();

            $out[] = [
                'sala_id' => (int) $cfg->sala_id,
                'sala_codigo' => (string) ($cfg->sala->codigo ?? ''),
                'sala_nombre' => (string) ($cfg->sala->nombre ?? ''),
                'sucursal_anita' => (int) $cfg->sucursal_anita,
                'sucursal_del_rio' => (int) ($param->sucursal_del_rio ?? 67),
                'sucursal_betsur' => (int) ($param->sucursal_betsur ?? 68),
                'sucursal_apuestas' => (int) ($param->sucursal_apuestas ?? 0),
                'cuentacaja_codigo' => (string) ($caja->codigo ?? ''),
                'cuentacaja_nombre' => (string) ($caja->nombre ?? ''),
                'cuenta_caja_contable_codigo' => (string) ($cuentaCajaContable->codigo ?? ''),
                'cuenta_caja_contable_nombre' => (string) ($cuentaCajaContable->nombre ?? ''),
                'cuenta_mesa_codigo' => (string) ($cfg->cuentacontableMesa->codigo ?? ''),
                'cuenta_mesa_nombre' => (string) ($cfg->cuentacontableMesa->nombre ?? ''),
                'cuenta_slot_codigo' => (string) ($cfg->cuentacontableSlot->codigo ?? ''),
                'cuenta_slot_nombre' => (string) ($cfg->cuentacontableSlot->nombre ?? ''),
                'cuenta_ruleta_codigo' => (string) ($cfg->cuentacontableRuletaEl->codigo ?? ''),
                'cuenta_ruleta_nombre' => (string) ($cfg->cuentacontableRuletaEl->nombre ?? ''),
                'cuenta_del_rio_codigo' => (string) ($cfg->cuentacontableDelRio->codigo ?? ''),
                'cuenta_del_rio_nombre' => (string) ($cfg->cuentacontableDelRio->nombre ?? ''),
                'cuenta_betsur_codigo' => (string) ($cfg->cuentacontableBetsur->codigo ?? ''),
                'cuenta_betsur_nombre' => (string) ($cfg->cuentacontableBetsur->nombre ?? ''),
                'cuenta_apuestas_codigo' => (string) ($cfg->cuentacontableApuestas->codigo ?? ''),
                'cuenta_apuestas_nombre' => (string) ($cfg->cuentacontableApuestas->nombre ?? ''),
                'centrocosto_codigo' => (string) ($cfg->centrocosto->codigo ?? ''),
                'centrocosto_nombre' => (string) ($cfg->centrocosto->nombre ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private function rubros(): array
    {
        return FacturacionJuegoTipoSupport::rubros();
    }

    /**
     * @return Collection<int, FlashCrown>
     */
    private function cargarFlashes(string $desde, string $hasta, ?int $salaId): Collection
    {
        $q = FlashCrown::query()
            ->with(['sala:id,codigo,nombre', 'empresa:id,nombre,codigo'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->orderBy('sala_id');

        if ($salaId !== null && $salaId > 0) {
            $q->where('sala_id', $salaId);
        }

        return $q->get();
    }

    /**
     * @param  Collection<int, FacturacionJuegoSala>  $configs
     * @return array<int, array<string, array{id:int,codigo:string}>>
     */
    private function indiceCuentasPorSalaRubro(Collection $configs): array
    {
        $out = [];
        foreach ($configs as $cfg) {
            $cfg->loadMissing([
                'cuentacontableMesa',
                'cuentacontableSlot',
                'cuentacontableRuletaEl',
                'cuentacontableDelRio',
                'cuentacontableBetsur',
                'cuentacontableApuestas',
            ]);
            $out[(int) $cfg->sala_id] = [
                FacturacionJuegoTipoSupport::RUBRO_MESA => [
                    'id' => (int) ($cfg->cuentacontable_mesa_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableMesa->codigo ?? ''),
                ],
                FacturacionJuegoTipoSupport::RUBRO_SLOT => [
                    'id' => (int) ($cfg->cuentacontable_slot_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableSlot->codigo ?? ''),
                ],
                FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => [
                    'id' => (int) ($cfg->cuentacontable_ruleta_el_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableRuletaEl->codigo ?? ''),
                ],
                FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => [
                    'id' => (int) ($cfg->cuentacontable_del_rio_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableDelRio->codigo ?? ''),
                ],
                FacturacionJuegoTipoSupport::RUBRO_BETSUR => [
                    'id' => (int) ($cfg->cuentacontable_betsur_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableBetsur->codigo ?? ''),
                ],
                FacturacionJuegoTipoSupport::RUBRO_APUESTAS => [
                    'id' => (int) ($cfg->cuentacontable_apuestas_id ?? 0),
                    'codigo' => (string) ($cfg->cuentacontableApuestas->codigo ?? ''),
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, FacturacionJuegoSala>  $configs
     * @return array<int, array{sala_id:int,sala_codigo:string,sala_nombre:string,rubro:string,cuenta_codigo:string}>
     */
    private function mapaCodigoCuentaASalaRubro(Collection $configs): array
    {
        $out = [];
        foreach ($configs as $cfg) {
            $cfg->loadMissing([
                'sala',
                'cuentacontableMesa',
                'cuentacontableSlot',
                'cuentacontableRuletaEl',
                'cuentacontableDelRio',
                'cuentacontableBetsur',
                'cuentacontableApuestas',
            ]);
            $pares = [
                FacturacionJuegoTipoSupport::RUBRO_MESA => $cfg->cuentacontableMesa,
                FacturacionJuegoTipoSupport::RUBRO_SLOT => $cfg->cuentacontableSlot,
                FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => $cfg->cuentacontableRuletaEl,
                FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => $cfg->cuentacontableDelRio,
                FacturacionJuegoTipoSupport::RUBRO_BETSUR => $cfg->cuentacontableBetsur,
                FacturacionJuegoTipoSupport::RUBRO_APUESTAS => $cfg->cuentacontableApuestas,
            ];
            foreach ($pares as $rubro => $cuenta) {
                $codigoStr = (string) ($cuenta->codigo ?? '');
                $codigoInt = (int) preg_replace('/\D+/', '', $codigoStr);
                if ($codigoInt <= 0) {
                    continue;
                }
                $out[$codigoInt] = [
                    'sala_id' => (int) $cfg->sala_id,
                    'sala_codigo' => (string) ($cfg->sala->codigo ?? ''),
                    'sala_nombre' => (string) ($cfg->sala->nombre ?? ''),
                    'rubro' => $rubro,
                    'cuenta_codigo' => $codigoStr,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float> venta_id => importe firmado
     */
    private function cargarVentasFirmadas(array $ventaIds): array
    {
        $ventaIds = array_values(array_unique(array_filter(array_map('intval', $ventaIds))));
        if ($ventaIds === []) {
            return [];
        }

        $ventas = Venta::query()
            ->with(['tipotransacciones:id,signo,abreviatura'])
            ->whereIn('id', $ventaIds)
            ->get(['id', 'total', 'tipotransaccion_id']);

        $out = [];
        foreach ($ventas as $venta) {
            $total = abs((float) ($venta->total ?? 0));
            $signo = (int) ($venta->tipotransacciones->signo ?? 0);
            $out[(int) $venta->id] = round($signo >= 0 ? $total : -$total, 2);
        }

        return $out;
    }

    /**
     * Neto Haber−Debe (= −sum monto) por asiento y cuenta de ventas.
     *
     * @param  list<int>  $asientoIds
     * @param  array<int, array<string, array{id:int,codigo:string}>>  $cuentaPorRubroSala
     * @return array<int, array<int, float>> asiento_id => [cuentacontable_id => neto]
     */
    private function cargarErpPorAsientoCuenta(array $asientoIds, array $cuentaPorRubroSala): array
    {
        $asientoIds = array_values(array_unique(array_filter(array_map('intval', $asientoIds))));
        if ($asientoIds === []) {
            return [];
        }

        $cuentaIds = [];
        foreach ($cuentaPorRubroSala as $porRubro) {
            foreach ($porRubro as $meta) {
                $id = (int) ($meta['id'] ?? 0);
                if ($id > 0) {
                    $cuentaIds[$id] = true;
                }
            }
        }
        $cuentaIds = array_keys($cuentaIds);
        if ($cuentaIds === []) {
            return [];
        }

        $rows = Asiento_Movimiento::query()
            ->selectRaw('asiento_id, cuentacontable_id, SUM(monto) as suma_monto')
            ->whereIn('asiento_id', $asientoIds)
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->groupBy('asiento_id', 'cuentacontable_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $asientoId = (int) $row->asiento_id;
            $cuentaId = (int) $row->cuentacontable_id;
            // monto: debe +, haber − → Haber−Debe = −suma
            $out[$asientoId][$cuentaId] = round(-1 * (float) $row->suma_monto, 2);
        }

        return $out;
    }

    /**
     * @param  Collection<int, FlashCrown>  $flashes
     * @param  Collection<int, FacturacionJuegoSala>  $configs
     * @param  array<int, array{sala_id:int,sala_codigo:string,sala_nombre:string,rubro:string,cuenta_codigo:string}>  $mapaCuenta
     * @return array<string, float> clave fecha|codigoAnita => neto_haber
     */
    private function cargarAnitaPorFechaCodigo(
        Collection $flashes,
        Collection $configs,
        string $desde,
        string $hasta,
        array $mapaCuenta,
    ): array {
        $codigos = array_keys($mapaCuenta);
        if ($codigos === []) {
            return [];
        }

        $empresaIds = $flashes->pluck('empresa_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($empresaIds === []) {
            $empresaIds = Empresa::query()->orderBy('id')->limit(5)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $desdeYmd = (int) str_replace('-', '', $desde);
        $hastaYmd = (int) str_replace('-', '', $hasta);
        $out = [];

        foreach ($empresaIds as $empresaId) {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $empresaId);
            if ($empresaAnita <= 0) {
                continue;
            }
            $movimientos = $this->mayorAnita->listarMovimientosPeriodo(
                $empresaAnita,
                $desdeYmd,
                $hastaYmd,
                $codigos,
            );
            foreach ($movimientos as $mov) {
                $fecha = (string) ($mov['fecha'] ?? '');
                $codigo = (int) ($mov['cuenta_codigo'] ?? 0);
                if ($fecha === '' || $codigo <= 0) {
                    continue;
                }
                $clave = $fecha.'|'.$codigo;
                $out[$clave] = round(((float) ($out[$clave] ?? 0)) + (float) ($mov['neto_haber'] ?? 0), 2);
            }
        }

        return $out;
    }

    private function ventaIdFlash(FlashCrown $flash, string $rubro): int
    {
        return (int) match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => $flash->venta_mesa_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_SLOT => $flash->venta_slot_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => $flash->venta_ruleta_el_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => $flash->venta_del_rio_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_BETSUR => $flash->venta_betsur_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_APUESTAS => $flash->venta_apuestas_id ?? 0,
            default => 0,
        };
    }

    private function asientoIdFlash(FlashCrown $flash, string $rubro): int
    {
        return (int) match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => $flash->asiento_mesa_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_SLOT => $flash->asiento_slot_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => $flash->asiento_ruleta_el_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => $flash->asiento_del_rio_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_BETSUR => $flash->asiento_betsur_id ?? 0,
            FacturacionJuegoTipoSupport::RUBRO_APUESTAS => $flash->asiento_apuestas_id ?? 0,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function armarFila(array $datos): array
    {
        $flash = round((float) $datos['flash'], 2);
        $ventas = round((float) $datos['ventas'], 2);
        $erp = round((float) $datos['erp'], 2);
        $anita = round((float) $datos['anita'], 2);
        $tol = (float) $datos['tolerancia'];

        $difVentas = round($flash - $ventas, 2);
        $difErp = round($flash - $erp, 2);
        $difAnita = round($flash - $anita, 2);
        $difMax = max(abs($difVentas), abs($difErp), abs($difAnita));

        $estado = 'OK';
        if (! empty($datos['sin_cfg'])) {
            $estado = 'SIN_CFG';
        } elseif (! empty($datos['sin_flash'])) {
            $estado = 'SIN_FLASH';
        } elseif (empty($datos['procesado']) && abs($flash) > $tol) {
            $estado = 'PEND';
        } elseif ($difMax > $tol) {
            $estado = 'DIF';
        }

        $rubro = (string) $datos['rubro'];

        return [
            'fecha' => (string) $datos['fecha'],
            'fecha_fmt' => Carbon::parse((string) $datos['fecha'])->format('d/m/Y'),
            'sala_id' => (int) $datos['sala_id'],
            'sala_codigo' => (string) $datos['sala_codigo'],
            'sala_nombre' => (string) $datos['sala_nombre'],
            'rubro' => $rubro,
            'rubro_label' => FacturacionJuegoTipoSupport::nombreCliente($rubro),
            'flash_id' => $datos['flash_id'],
            'venta_id' => $datos['venta_id'],
            'asiento_id' => $datos['asiento_id'],
            'cuenta_codigo' => (string) $datos['cuenta_codigo'],
            'flash' => $flash,
            'ventas' => $ventas,
            'erp' => $erp,
            'anita' => $anita,
            'dif_ventas' => $difVentas,
            'dif_erp' => $difErp,
            'dif_anita' => $difAnita,
            'dif_max' => round($difMax, 2),
            'estado' => $estado,
            'procesado' => ! empty($datos['procesado']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function resumen(array $filas, float $tolerancia): array
    {
        $porEstado = [
            'OK' => 0,
            'DIF' => 0,
            'PEND' => 0,
            'SIN_FLASH' => 0,
            'SIN_CFG' => 0,
        ];
        $totFlash = 0.0;
        $totVentas = 0.0;
        $totErp = 0.0;
        $totAnita = 0.0;

        foreach ($filas as $fila) {
            $est = (string) ($fila['estado'] ?? '');
            if (isset($porEstado[$est])) {
                $porEstado[$est]++;
            }
            $totFlash += (float) ($fila['flash'] ?? 0);
            $totVentas += (float) ($fila['ventas'] ?? 0);
            $totErp += (float) ($fila['erp'] ?? 0);
            $totAnita += (float) ($fila['anita'] ?? 0);
        }

        return [
            'total_filas' => count($filas),
            'por_estado' => $porEstado,
            'total_flash' => round($totFlash, 2),
            'total_ventas' => round($totVentas, 2),
            'total_erp' => round($totErp, 2),
            'total_anita' => round($totAnita, 2),
            'tolerancia' => $tolerancia,
        ];
    }
}
