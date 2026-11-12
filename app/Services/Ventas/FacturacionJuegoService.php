<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Contable\Tipoasiento;
use App\Models\Ventas\FacturacionJuegoSala;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionJuegoAsientoSupport;
use App\Support\Ventas\FacturacionJuegoConfigSupport;
use App\Support\Ventas\FacturacionJuegoTipoSupport;
use App\Support\Ventas\FacturacionJuegoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generación de comprobantes de resultado de juego (paridad p-resultado.c).
 *
 * Rubros de sala (mesa/slot/ruleta): una factura por sala/día.
 * Rubros online (Del Río / Betsur / Apuestas): una sola factura por día (dato global).
 */
class FacturacionJuegoService
{
    public function __construct(
        private readonly CierreSalaExentaEmisionService $exentaEmisionService,
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preview(string $fechaDesde, string $fechaHasta, ?int $salaId = null): array
    {
        $flashes = $this->flashesEnRango($fechaDesde, $fechaHasta, $salaId);
        $filas = [];

        foreach ($flashes as $flash) {
            $cfg = FacturacionJuegoConfigSupport::salaPorSalaId((int) $flash->sala_id);
            foreach (FacturacionJuegoTipoSupport::rubrosSala() as $rubro) {
                $item = $this->armarItemPreview($flash, $rubro, $cfg);
                if ($item !== null) {
                    $filas[] = $item;
                }
            }
        }

        foreach ($this->fechasOnlinePendientes($flashes) as $fecha => $flashRep) {
            foreach (FacturacionJuegoTipoSupport::rubrosOnline() as $rubro) {
                if ($this->onlineYaProcesadoEnFecha($flashes, $fecha, $rubro)) {
                    continue;
                }
                $resultado = FacturacionJuegoTotalesSupport::resultadoRubro($flashRep, $rubro);
                if (FacturacionJuegoTotalesSupport::esCero($resultado)) {
                    continue;
                }
                $cfg = FacturacionJuegoConfigSupport::salaParaRubroOnline($rubro)
                    ?? FacturacionJuegoConfigSupport::salaPorSalaId((int) $flashRep->sala_id);
                $abrev = FacturacionJuegoTipoSupport::abreviaturaParaResultado($rubro, $resultado);
                $faltantes = $cfg
                    ? FacturacionJuegoConfigSupport::faltantesSala($cfg, $rubro)
                    : ['Sin configuración de sala'];
                $filas[] = [
                    'flash_id' => (int) $flashRep->id,
                    'fecha' => $fecha,
                    'sala_id' => 0,
                    'sala_codigo' => 'GLOBAL',
                    'sala_nombre' => 'Online (todas las salas)',
                    'empresa_id' => (int) $flashRep->empresa_id,
                    'rubro' => $rubro,
                    'rubro_label' => FacturacionJuegoTipoSupport::nombreCliente($rubro),
                    'resultado' => $resultado,
                    'monto' => abs($resultado),
                    'tipo' => $abrev,
                    'letra' => FacturacionJuegoTipoSupport::letra(),
                    'sucursal_anita' => FacturacionJuegoTipoSupport::sucursalAnitaParaRubro(
                        $rubro,
                        (int) ($cfg->sucursal_anita ?? 0),
                    ),
                    'faltantes' => $faltantes,
                    'listo' => $faltantes === [],
                    'global_online' => true,
                ];
            }
        }

        usort($filas, static function (array $a, array $b): int {
            return [$a['fecha'], $a['sala_codigo'], $a['rubro']]
                <=> [$b['fecha'], $b['sala_codigo'], $b['rubro']];
        });

        return $filas;
    }

    /**
     * @return array{generados: list<array<string, mixed>>, omitidos: list<array<string, mixed>>, errores: list<string>}
     */
    public function ejecutar(string $fechaDesde, string $fechaHasta, ?int $salaId = null): array
    {
        $generados = [];
        $omitidos = [];
        $errores = [];

        $flashes = $this->flashesEnRango($fechaDesde, $fechaHasta, $salaId);

        // 1) Online global: una factura por día y rubro.
        foreach ($this->fechasOnlinePendientes($flashes) as $fecha => $flashRep) {
            try {
                $resultadoDia = DB::transaction(function () use ($fecha, $fechaDesde, $fechaHasta, $salaId) {
                    $delDia = FlashCrown::query()
                        ->with(['sala:id,codigo,nombre'])
                        ->whereDate('fecha', $fecha)
                        ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
                        ->whereDate('fecha', '>=', $fechaDesde)
                        ->whereDate('fecha', '<=', $fechaHasta)
                        ->lockForUpdate()
                        ->orderBy('sala_id')
                        ->get();

                    $out = ['generados' => [], 'omitidos' => []];
                    $rep = $delDia->first();
                    if ($rep === null) {
                        return $out;
                    }

                    foreach (FacturacionJuegoTipoSupport::rubrosOnline() as $rubro) {
                        $item = $this->emitirOnlineGlobalSiCorresponde($delDia, $rep, $rubro);
                        if ($item === null) {
                            continue;
                        }
                        if (! empty($item['omitido'])) {
                            $out['omitidos'][] = $item;
                        } else {
                            $out['generados'][] = $item;
                        }
                    }

                    return $out;
                });
                $generados = array_merge($generados, $resultadoDia['generados']);
                $omitidos = array_merge($omitidos, $resultadoDia['omitidos']);
            } catch (\Throwable $e) {
                $errores[] = 'Online '.$fecha.': '.$e->getMessage();
            }
        }

        // 2) Rubros de sala: por flash.
        foreach ($flashes as $flash) {
            try {
                $resultadoDia = DB::transaction(function () use ($flash) {
                    /** @var FlashCrown $bloqueado */
                    $bloqueado = FlashCrown::query()->whereKey($flash->id)->lockForUpdate()->firstOrFail();
                    $bloqueado->loadMissing(['sala:id,codigo,nombre']);
                    $cfg = FacturacionJuegoConfigSupport::salaPorSalaId((int) $bloqueado->sala_id);
                    if ($cfg === null || ! $cfg->activo) {
                        throw new InvalidArgumentException(
                            'Sala #'.$bloqueado->sala_id.' sin configuración activa de facturación juego.',
                        );
                    }

                    $out = ['generados' => [], 'omitidos' => []];
                    foreach (FacturacionJuegoTipoSupport::rubrosSala() as $rubro) {
                        $item = $this->emitirRubroSiCorresponde($bloqueado, $cfg, $rubro);
                        if ($item === null) {
                            continue;
                        }
                        if (! empty($item['omitido'])) {
                            $out['omitidos'][] = $item;
                        } else {
                            $out['generados'][] = $item;
                        }
                    }

                    return $out;
                });
                $generados = array_merge($generados, $resultadoDia['generados']);
                $omitidos = array_merge($omitidos, $resultadoDia['omitidos']);
            } catch (\Throwable $e) {
                $errores[] = 'Flash #'.$flash->id.' '
                    .Carbon::parse((string) $flash->fecha)->format('d/m/Y')
                    .' sala '.$flash->sala_id.': '.$e->getMessage();
            }
        }

        return compact('generados', 'omitidos', 'errores');
    }

    /**
     * @return Collection<int, FlashCrown>
     */
    private function flashesEnRango(string $fechaDesde, string $fechaHasta, ?int $salaId): Collection
    {
        $q = FlashCrown::query()
            ->with(['sala:id,codigo,nombre', 'empresa:id,nombre'])
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->orderBy('fecha')
            ->orderBy('sala_id');

        if ($salaId !== null && $salaId > 0) {
            $q->where('sala_id', $salaId);
        }

        return $q->get();
    }

    /**
     * Primer flash de cada fecha (para montos online globales).
     *
     * @param  Collection<int, FlashCrown>  $flashes
     * @return array<string, FlashCrown>
     */
    private function fechasOnlinePendientes(Collection $flashes): array
    {
        $porFecha = [];
        foreach ($flashes as $flash) {
            $fecha = Carbon::parse((string) $flash->fecha)->toDateString();
            if (! isset($porFecha[$fecha])) {
                $porFecha[$fecha] = $flash;
            }
        }
        ksort($porFecha);

        return $porFecha;
    }

    /**
     * @param  Collection<int, FlashCrown>  $flashes
     */
    private function onlineYaProcesadoEnFecha(Collection $flashes, string $fecha, string $rubro): bool
    {
        foreach ($flashes as $flash) {
            if (Carbon::parse((string) $flash->fecha)->toDateString() !== $fecha) {
                continue;
            }
            if (FacturacionJuegoTotalesSupport::procesado($flash, $rubro)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function armarItemPreview(FlashCrown $flash, string $rubro, ?FacturacionJuegoSala $cfg): ?array
    {
        if (FacturacionJuegoTotalesSupport::procesado($flash, $rubro)) {
            return null;
        }
        $resultado = FacturacionJuegoTotalesSupport::resultadoRubro($flash, $rubro);
        if (FacturacionJuegoTotalesSupport::esCero($resultado)) {
            return null;
        }

        $abrev = FacturacionJuegoTipoSupport::abreviaturaParaResultado($rubro, $resultado);
        $faltantes = $cfg ? FacturacionJuegoConfigSupport::faltantesSala($cfg, $rubro) : ['Sin configuración de sala'];

        return [
            'flash_id' => (int) $flash->id,
            'fecha' => Carbon::parse((string) $flash->fecha)->toDateString(),
            'sala_id' => (int) $flash->sala_id,
            'sala_codigo' => (string) ($flash->sala->codigo ?? ''),
            'sala_nombre' => (string) ($flash->sala->nombre ?? ''),
            'empresa_id' => (int) $flash->empresa_id,
            'rubro' => $rubro,
            'rubro_label' => FacturacionJuegoTipoSupport::nombreCliente($rubro),
            'resultado' => $resultado,
            'monto' => abs($resultado),
            'tipo' => $abrev,
            'letra' => FacturacionJuegoTipoSupport::letra(),
            'sucursal_anita' => FacturacionJuegoTipoSupport::sucursalAnitaParaRubro(
                $rubro,
                (int) ($cfg->sucursal_anita ?? 0),
            ),
            'faltantes' => $faltantes,
            'listo' => $faltantes === [],
        ];
    }

    /**
     * @param  Collection<int, FlashCrown>  $delDia
     * @return array<string, mixed>|null
     */
    private function emitirOnlineGlobalSiCorresponde(Collection $delDia, FlashCrown $rep, string $rubro): ?array
    {
        if ($this->onlineYaProcesadoEnFecha($delDia, Carbon::parse((string) $rep->fecha)->toDateString(), $rubro)) {
            return null;
        }

        $resultado = FacturacionJuegoTotalesSupport::resultadoRubro($rep, $rubro);
        if (FacturacionJuegoTotalesSupport::esCero($resultado)) {
            return [
                'omitido' => true,
                'flash_id' => (int) $rep->id,
                'rubro' => $rubro,
                'motivo' => 'Monto en cero',
                'global_online' => true,
            ];
        }

        $cfg = FacturacionJuegoConfigSupport::salaParaRubroOnline($rubro);
        if ($cfg === null || ! $cfg->activo) {
            throw new InvalidArgumentException(
                FacturacionJuegoTipoSupport::nombreCliente($rubro).': no hay sala activa con configuración.',
            );
        }
        $faltantes = FacturacionJuegoConfigSupport::faltantesSala($cfg, $rubro);
        if ($faltantes !== []) {
            throw new InvalidArgumentException(
                FacturacionJuegoTipoSupport::nombreCliente($rubro).': falta '.implode(', ', $faltantes),
            );
        }

        $abrev = FacturacionJuegoTipoSupport::abreviaturaParaResultado($rubro, $resultado);
        $tipo = FacturacionJuegoTipoSupport::tipo($abrev);
        $monto = abs($resultado);
        $fechaDia = Carbon::parse((string) $rep->fecha)->toDateString();
        $nombreCliente = FacturacionJuegoTipoSupport::nombreCliente($rubro);
        $sucursalAnita = FacturacionJuegoTipoSupport::sucursalAnitaParaRubro(
            $rubro,
            (int) $cfg->sucursal_anita,
        );
        $leyenda = $nombreCliente.' '.$fechaDia.' — PV '.$sucursalAnita.' (global)';

        $venta = $this->exentaEmisionService->emitir(
            $tipo,
            FacturacionJuegoTipoSupport::letra(),
            $nombreCliente,
            (int) $rep->empresa_id,
            $fechaDia,
            $monto,
            $sucursalAnita,
            $leyenda,
        );

        $conceptoMayor = FacturacionJuegoTipoSupport::conceptoMayor(
            (string) $venta['codigo'],
            $nombreCliente,
        );
        $asientoId = $this->grabarAsiento(
            $rep,
            $cfg,
            $rubro,
            $tipo,
            $monto,
            $fechaDia,
            $conceptoMayor,
            (int) $venta['venta_id'],
        );

        foreach ($delDia as $flash) {
            $this->marcarProcesado($flash, $rubro, (int) $venta['venta_id'], $asientoId);
        }

        return [
            'omitido' => false,
            'flash_id' => (int) $rep->id,
            'fecha' => $fechaDia,
            'sala_id' => 0,
            'sala_codigo' => 'GLOBAL',
            'rubro' => $rubro,
            'tipo' => $abrev,
            'monto' => $monto,
            'venta_id' => (int) $venta['venta_id'],
            'codigo' => (string) $venta['codigo'],
            'asiento_id' => $asientoId,
            'global_online' => true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function emitirRubroSiCorresponde(FlashCrown $flash, FacturacionJuegoSala $cfg, string $rubro): ?array
    {
        if (FacturacionJuegoTotalesSupport::procesado($flash, $rubro)) {
            return null;
        }

        $resultado = FacturacionJuegoTotalesSupport::resultadoRubro($flash, $rubro);
        if (FacturacionJuegoTotalesSupport::esCero($resultado)) {
            return [
                'omitido' => true,
                'flash_id' => (int) $flash->id,
                'rubro' => $rubro,
                'motivo' => 'Monto en cero',
            ];
        }

        $faltantes = FacturacionJuegoConfigSupport::faltantesSala($cfg, $rubro);
        if ($faltantes !== []) {
            throw new InvalidArgumentException(
                FacturacionJuegoTipoSupport::nombreCliente($rubro).': falta '.implode(', ', $faltantes),
            );
        }

        $abrev = FacturacionJuegoTipoSupport::abreviaturaParaResultado($rubro, $resultado);
        $tipo = FacturacionJuegoTipoSupport::tipo($abrev);
        $monto = abs($resultado);
        $fechaDia = Carbon::parse((string) $flash->fecha)->toDateString();
        $nombreCliente = FacturacionJuegoTipoSupport::nombreCliente($rubro);
        $sucursalAnita = FacturacionJuegoTipoSupport::sucursalAnitaParaRubro(
            $rubro,
            (int) $cfg->sucursal_anita,
        );
        $leyenda = $nombreCliente.' '.$fechaDia.' — sala '.(string) ($flash->sala->codigo ?? $flash->sala_id)
            .' PV '.$sucursalAnita;

        $venta = $this->exentaEmisionService->emitir(
            $tipo,
            FacturacionJuegoTipoSupport::letra(),
            $nombreCliente,
            (int) $flash->empresa_id,
            $fechaDia,
            $monto,
            $sucursalAnita,
            $leyenda,
        );

        $conceptoMayor = FacturacionJuegoTipoSupport::conceptoMayor(
            (string) $venta['codigo'],
            $nombreCliente,
        );
        $asientoId = $this->grabarAsiento(
            $flash,
            $cfg,
            $rubro,
            $tipo,
            $monto,
            $fechaDia,
            $conceptoMayor,
            (int) $venta['venta_id'],
        );

        $this->marcarProcesado($flash, $rubro, (int) $venta['venta_id'], $asientoId);

        return [
            'omitido' => false,
            'flash_id' => (int) $flash->id,
            'fecha' => $fechaDia,
            'sala_id' => (int) $flash->sala_id,
            'rubro' => $rubro,
            'tipo' => $abrev,
            'monto' => $monto,
            'venta_id' => (int) $venta['venta_id'],
            'codigo' => (string) $venta['codigo'],
            'asiento_id' => $asientoId,
        ];
    }

    private function grabarAsiento(
        FlashCrown $flash,
        FacturacionJuegoSala $cfg,
        string $rubro,
        $tipo,
        float $monto,
        string $fechaDia,
        string $conceptoMayor,
        int $ventaId,
    ): int {
        $armado = FacturacionJuegoAsientoSupport::armarLineas($cfg, $rubro, $tipo, $monto, $conceptoMayor);
        $tipoAsientoId = $this->resolverTipoAsientoId();
        $payload = FacturacionJuegoAsientoSupport::armarPayloadAsiento(
            $armado['lineas'],
            (int) $flash->empresa_id,
            $fechaDia,
            $conceptoMayor,
            $tipoAsientoId,
        );
        $payload['venta_id'] = $ventaId;

        if (EntornoEmpresaSupport::esCrown()) {
            $payload['omitir_anita'] = true;
        }

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || $asiento === null) {
            throw new RuntimeException('Error al grabar asiento de facturación juego.');
        }
        $this->asientoMovimientoRepository->create($payload, $asiento->id);

        return (int) $asiento->id;
    }

    private function marcarProcesado(FlashCrown $flash, string $rubro, int $ventaId, int $asientoId): void
    {
        $data = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => [
                'proc_mesa' => '*',
                'venta_mesa_id' => $ventaId,
                'asiento_mesa_id' => $asientoId,
            ],
            FacturacionJuegoTipoSupport::RUBRO_SLOT => [
                'proc_slot' => '*',
                'venta_slot_id' => $ventaId,
                'asiento_slot_id' => $asientoId,
            ],
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => [
                'proc_ruleta_el' => '*',
                'venta_ruleta_el_id' => $ventaId,
                'asiento_ruleta_el_id' => $asientoId,
            ],
            FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => [
                'proc_del_rio' => '*',
                'venta_del_rio_id' => $ventaId,
                'asiento_del_rio_id' => $asientoId,
            ],
            FacturacionJuegoTipoSupport::RUBRO_BETSUR => [
                'proc_betsur' => '*',
                'venta_betsur_id' => $ventaId,
                'asiento_betsur_id' => $asientoId,
            ],
            FacturacionJuegoTipoSupport::RUBRO_APUESTAS => [
                'proc_apuestas' => '*',
                'venta_apuestas_id' => $ventaId,
                'asiento_apuestas_id' => $asientoId,
            ],
            default => throw new InvalidArgumentException('Rubro inválido'),
        };

        $flash->forceFill($data)->save();
    }

    private function resolverTipoAsientoId(): int
    {
        $abrev = strtoupper(trim((string) FacturacionJuegoConfigSupport::parametro()->tipoasiento_abreviatura));
        if ($abrev === '') {
            $abrev = 'VTA';
        }
        $id = (int) (Tipoasiento::query()->where('abreviatura', $abrev)->value('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Falta tipo de asiento '.$abrev.' para facturación juego.');
        }

        return $id;
    }
}
