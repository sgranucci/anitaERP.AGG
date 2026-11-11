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
        $filas = [];
        foreach ($this->flashesPendientes($fechaDesde, $fechaHasta, $salaId) as $flash) {
            $cfg = FacturacionJuegoConfigSupport::salaPorSalaId((int) $flash->sala_id);
            foreach ($this->rubros() as $rubro) {
                $item = $this->armarItemPreview($flash, $rubro, $cfg);
                if ($item !== null) {
                    $filas[] = $item;
                }
            }
        }

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

        $flashes = $this->flashesPendientes($fechaDesde, $fechaHasta, $salaId);
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
                    foreach ($this->rubros() as $rubro) {
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
    private function flashesPendientes(string $fechaDesde, string $fechaHasta, ?int $salaId): Collection
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

        return $q->get()->filter(function (FlashCrown $flash) {
            foreach ($this->rubros() as $rubro) {
                if (! FacturacionJuegoTotalesSupport::procesado($flash, $rubro)
                    && ! FacturacionJuegoTotalesSupport::esCero(
                        FacturacionJuegoTotalesSupport::resultadoRubro($flash, $rubro)
                    )
                ) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @return list<string>
     */
    private function rubros(): array
    {
        return [
            FacturacionJuegoTipoSupport::RUBRO_MESA,
            FacturacionJuegoTipoSupport::RUBRO_SLOT,
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL,
        ];
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
            'sucursal_anita' => (int) ($cfg->sucursal_anita ?? 0),
            'faltantes' => $faltantes,
            'listo' => $faltantes === [],
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
        $leyenda = $nombreCliente.' '.$fechaDia.' — sala '.(string) ($flash->sala->codigo ?? $flash->sala_id);

        $venta = $this->exentaEmisionService->emitir(
            $tipo,
            FacturacionJuegoTipoSupport::letra(),
            $nombreCliente,
            (int) $flash->empresa_id,
            $fechaDia,
            $monto,
            (int) $cfg->sucursal_anita,
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

        // Crown: asiento solo en ERP (venta ya no va a Anita; no duplicar en ctamov).
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
