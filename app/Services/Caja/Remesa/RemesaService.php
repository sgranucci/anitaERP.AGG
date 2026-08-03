<?php

declare(strict_types=1);

namespace App\Services\Caja\Remesa;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Remesa;
use App\Models\Caja\RemesaLinea;
use App\Models\Caja\Usocuentacaja;
use App\Models\Contable\Asiento;
use App\Repositories\Caja\RemesaRepositoryInterface;
use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RemesaService
{
    private const TOLERANCIA_CUADRE = 0.02;

    public function __construct(
        private readonly RemesaRepositoryInterface $remesaRepository,
        private readonly RemesaAsientoService $asientoService,
        private readonly RemesaCajaMovimientoService $cajaMovimientoService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCuentasPorUso(int $empresaId, string $usoNombre, ?Remesa $remesa = null, string $lado = ''): array
    {
        $usoId = (int) (Usocuentacaja::query()
            ->where('nombre', $usoNombre)
            ->value('id') ?? 0);

        $montosGuardados = [];
        if ($remesa !== null) {
            foreach ($remesa->lineas as $linea) {
                if ($lado !== '' && $linea->lado !== $lado) {
                    continue;
                }
                $montosGuardados[(int) $linea->cuentacaja_id] = (float) $linea->monto;
            }
        }

        if ($usoId <= 0 || $empresaId <= 0) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->with('monedas:id,abreviatura,nombre')
            ->whereHas('usocuentacajas', fn ($q) => $q->where('usocuentacaja.id', $usoId))
            ->get(['id', 'codigo', 'nombre', 'descripcion_operaciones', 'moneda_id']);

        $lineas = [];
        foreach ($cuentas as $cuenta) {
            $lineas[] = [
                'cuentacaja_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => $cuenta->etiquetaOperaciones(),
                'descripcion_operaciones' => trim((string) ($cuenta->descripcion_operaciones ?? '')),
                'moneda_id' => (int) ($cuenta->moneda_id ?: 1),
                'moneda_abrev' => (string) ($cuenta->monedas->abreviatura ?? ''),
                'monto' => $montosGuardados[(int) $cuenta->id] ?? 0.0,
            ];
        }

        return $this->ordenarLineasPorCodigo($lineas);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function guardar(array $payload, int $usuarioId, ?int $id = null): Remesa
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $fecha = (string) ($payload['fecha'] ?? '');
        $tipo = strtoupper(trim((string) ($payload['tipo'] ?? '')));

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe indicar la empresa.');
        }
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida.');
        }
        if ($fecha > date('Y-m-d')) {
            throw new InvalidArgumentException('La fecha no puede ser posterior a hoy.');
        }
        if (! in_array($tipo, [RemesaSupport::TIPO_INTERNA, RemesaSupport::TIPO_EXTERNA], true)) {
            throw new InvalidArgumentException('Tipo de remesa inválido.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $lineasDestino = $this->normalizarLineasPayload($payload, RemesaSupport::LADO_DESTINO);
        $lineasOrigen = $this->normalizarLineasPayload($payload, RemesaSupport::LADO_ORIGEN);
        if ($lineasDestino === [] && $lineasOrigen === []) {
            throw new InvalidArgumentException('Debe cargar al menos un monto en destino u origen.');
        }

        $importeDestino = round(array_sum(array_column($lineasDestino, 'monto')), 2);
        $importeOrigen = round(array_sum(array_column($lineasOrigen, 'monto')), 2);

        if ($tipo === RemesaSupport::TIPO_EXTERNA) {
            $this->assertCuadrePorMoneda($lineasDestino, $lineasOrigen);
        }

        return DB::transaction(function () use (
            $payload,
            $usuarioId,
            $id,
            $empresaId,
            $fecha,
            $tipo,
            $lineasDestino,
            $lineasOrigen,
            $importeDestino,
            $importeOrigen,
        ) {
            $remesa = null;
            $asientoAnteriorId = null;
            $cajaAnteriorId = null;

            if ($id !== null && $id > 0) {
                $remesa = $this->remesaRepository->findOrFail($id);
                if ($remesa->estaInactiva()) {
                    throw new InvalidArgumentException('No se puede modificar una remesa revertida o anulada.');
                }
                $asientoAnteriorId = $remesa->asiento_id ? (int) $remesa->asiento_id : null;
                $cajaAnteriorId = $remesa->caja_movimiento_id ? (int) $remesa->caja_movimiento_id : null;
            }

            $cabecera = [
                'empresa_id' => $empresaId,
                'fecha' => $fecha,
                'tipo' => $tipo,
                'estado' => RemesaSupport::ESTADO_CONFIRMADA,
                'remito' => $this->nullableString($payload['remito'] ?? null),
                'bolsa' => $this->nullableString($payload['bolsa'] ?? null),
                'precinto' => $this->nullableString($payload['precinto'] ?? null),
                'importe_destino' => $importeDestino,
                'importe_origen' => $importeOrigen,
                'observacion' => $this->nullableString($payload['observacion'] ?? null),
                'usuario_id' => $usuarioId,
                'asiento_id' => null,
                'caja_movimiento_id' => null,
            ];

            if ($remesa === null) {
                $cabecera['numero'] = $this->remesaRepository->nextNumero($empresaId);
                $remesa = Remesa::query()->create($cabecera);
            } else {
                $remesa->update($cabecera);
                RemesaLinea::query()->where('remesa_id', $remesa->id)->delete();
            }

            $orden = 0;
            foreach (array_merge($lineasDestino, $lineasOrigen) as $linea) {
                RemesaLinea::query()->create([
                    'remesa_id' => (int) $remesa->id,
                    'lado' => $linea['lado'],
                    'cuentacaja_id' => $linea['cuentacaja_id'],
                    'monto' => $linea['monto'],
                    'orden' => $orden++,
                ]);
            }

            if ($remesa !== null) {
                $this->asientoService->anularTodosDeRemesa($remesa);
            }
            if ($asientoAnteriorId !== null && $asientoAnteriorId > 0) {
                // Por si el asiento viejo no tenía remesa_id (defensa).
                $this->asientoService->anular($asientoAnteriorId);
            }
            if ($cajaAnteriorId !== null && $cajaAnteriorId > 0) {
                $this->cajaMovimientoService->anular($cajaAnteriorId);
            }

            $remesa = $remesa->fresh(['lineas.cuentacaja']);
            $cajaMovimientoId = $this->cajaMovimientoService->generar($remesa);
            $remesa->update(['caja_movimiento_id' => $cajaMovimientoId]);

            if ($tipo === RemesaSupport::TIPO_EXTERNA) {
                $remesa = $remesa->fresh(['lineas.cuentacaja']);
                $asientoId = $this->asientoService->generar($remesa);
                $remesa->update(['asiento_id' => $asientoId]);
            }

            return $remesa->fresh([
                'lineas.cuentacaja',
                'empresa',
                'asiento',
                'cajaMovimiento',
                'usuario',
            ]);
        });
    }

    /**
     * Reversión operativa (tesorería): conserva original y graba contraasiento +
     * contramovimiento de caja con fecha de hoy (válido con mes original cerrado).
     */
    public function revertir(int $id): Remesa
    {
        $remesa = $this->remesaRepository->findOrFail($id);

        if ($remesa->estaInactiva()) {
            throw new InvalidArgumentException('La remesa ya está revertida o anulada.');
        }

        $fechaReverso = date('Y-m-d');

        // Control de cierre sobre el día de la reversión (no sobre la fecha original).
        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $remesa->empresa_id,
            $fechaReverso,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        return DB::transaction(function () use ($remesa, $fechaReverso) {
            $cajaReversoId = null;
            if ($remesa->caja_movimiento_id) {
                $cajaReversoId = $this->cajaMovimientoService->generarReverso($remesa, $fechaReverso);
            }

            if ($remesa->esExterna()) {
                if ($cajaReversoId === null || $cajaReversoId <= 0) {
                    throw new InvalidArgumentException(
                        'No se puede revertir el asiento sin generar el movimiento de caja compensatorio.'
                    );
                }
                $this->asientoService->generarReversos($remesa, $fechaReverso, $cajaReversoId);
            }

            $remesa->update([
                'estado' => RemesaSupport::ESTADO_REVERTIDA,
            ]);

            return $remesa->fresh([
                'lineas.cuentacaja',
                'empresa',
                'asiento',
                'cajaMovimiento',
                'usuario',
            ]);
        });
    }

    /**
     * Anulación física (solo administrador): borra remesa + asientos/ctamov + movimientos de caja.
     * Exige período de caja abierto en la fecha original de la remesa.
     */
    public function anular(int $id): void
    {
        $remesa = $this->remesaRepository->findOrFail($id);

        if ($remesa->estaRevertida()) {
            throw new InvalidArgumentException(
                'No se puede anular una remesa ya revertida. Los contramovimientos ya compensan el original.'
            );
        }
        if ($remesa->estaAnulada()) {
            throw new InvalidArgumentException('La remesa ya está anulada.');
        }

        $fechaOriginal = $remesa->fecha?->format('Y-m-d') ?? date('Y-m-d');

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $remesa->empresa_id,
            $fechaOriginal,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        DB::transaction(function () use ($remesa) {
            $asientoIds = $this->asientoService->idsAsientosDeRemesa($remesa);

            $cajaIds = Asiento::query()
                ->whereIn('id', $asientoIds)
                ->pluck('caja_movimiento_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($remesa->caja_movimiento_id) {
                $cajaIds[] = (int) $remesa->caja_movimiento_id;
            }
            $cajaIds = array_values(array_unique($cajaIds));

            foreach ($asientoIds as $asientoId) {
                $this->asientoService->anular($asientoId);
            }

            foreach ($cajaIds as $cajaId) {
                $this->cajaMovimientoService->anular($cajaId);
            }

            RemesaLinea::query()->where('remesa_id', (int) $remesa->id)->delete();
            $remesa->delete();
        });
    }

    /**
     * @return array{
     *   destino: list<array<string, mixed>>,
     *   origen: list<array<string, mixed>>,
     *   remesa: Remesa|null,
     *   uso_origen: string,
     *   totales: array{destino: float, origen: float}
     * }
     */
    public function datosPantalla(int $empresaId, ?Remesa $remesa = null, ?string $tipo = null): array
    {
        $tipoEfectivo = strtoupper(trim((string) (
            $tipo
            ?? $remesa?->tipo
            ?? RemesaSupport::TIPO_EXTERNA
        )));
        $usoOrigen = RemesaSupport::usoOrigenParaTipo($tipoEfectivo);

        $destino = $this->listarCuentasPorUso(
            $empresaId,
            RemesaSupport::USO_DESTINO,
            $remesa,
            RemesaSupport::LADO_DESTINO
        );
        $origen = $this->listarCuentasPorUso(
            $empresaId,
            $usoOrigen,
            $remesa,
            RemesaSupport::LADO_ORIGEN
        );

        $totalDestino = round(array_sum(array_column($destino, 'monto')), 2);
        $totalOrigen = round(array_sum(array_column($origen, 'monto')), 2);

        return [
            'destino' => $destino,
            'origen' => $origen,
            'remesa' => $remesa,
            'uso_origen' => $usoOrigen,
            'totales' => [
                'destino' => $totalDestino,
                'origen' => $totalOrigen,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{lado: string, cuentacaja_id: int, monto: float, moneda_id: int}>
     */
    private function normalizarLineasPayload(array $payload, string $lado): array
    {
        $prefix = $lado === RemesaSupport::LADO_DESTINO ? 'destino' : 'origen';
        $ids = array_values((array) ($payload[$prefix.'_cuentacaja_ids'] ?? []));
        $montos = array_values((array) ($payload[$prefix.'_montos'] ?? []));

        $lineas = [];
        $n = max(count($ids), count($montos));
        for ($i = 0; $i < $n; $i++) {
            $cuentacajaId = (int) ($ids[$i] ?? 0);
            $monto = round((float) ($montos[$i] ?? 0), 2);
            if ($cuentacajaId <= 0 || abs($monto) < 0.00001) {
                continue;
            }
            $monedaId = (int) (Cuentacaja::query()->whereKey($cuentacajaId)->value('moneda_id') ?? 1);
            $lineas[] = [
                'lado' => $lado,
                'cuentacaja_id' => $cuentacajaId,
                'monto' => $monto,
                'moneda_id' => $monedaId > 0 ? $monedaId : 1,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array{monto: float, moneda_id: int}>  $destino
     * @param  list<array{monto: float, moneda_id: int}>  $origen
     */
    private function assertCuadrePorMoneda(array $destino, array $origen): void
    {
        $totDest = [];
        foreach ($destino as $linea) {
            $m = (int) ($linea['moneda_id'] ?? 1);
            $totDest[$m] = round(($totDest[$m] ?? 0) + (float) $linea['monto'], 2);
        }
        $totOrig = [];
        foreach ($origen as $linea) {
            $m = (int) ($linea['moneda_id'] ?? 1);
            $totOrig[$m] = round(($totOrig[$m] ?? 0) + (float) $linea['monto'], 2);
        }

        $monedas = array_unique(array_merge(array_keys($totDest), array_keys($totOrig)));
        sort($monedas);

        if ($monedas === []) {
            throw new InvalidArgumentException('Debe cargar al menos un monto en destino u origen.');
        }

        foreach ($monedas as $monedaId) {
            $d = (float) ($totDest[$monedaId] ?? 0);
            $o = (float) ($totOrig[$monedaId] ?? 0);
            if (abs($d - $o) > self::TOLERANCIA_CUADRE) {
                throw new InvalidArgumentException(
                    'En remesa externa los totales deben cuadrar por moneda. Moneda #'
                    .$monedaId.': destino '.number_format($d, 2, ',', '.')
                    .' / origen '.number_format($o, 2, ',', '.').'.'
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function ordenarLineasPorCodigo(array $lineas): array
    {
        usort($lineas, static function (array $a, array $b): int {
            $ca = trim((string) ($a['codigo'] ?? ''));
            $cb = trim((string) ($b['codigo'] ?? ''));
            $na = ctype_digit($ca) ? (int) $ca : null;
            $nb = ctype_digit($cb) ? (int) $cb : null;

            if ($na !== null && $nb !== null && $na !== $nb) {
                return $na <=> $nb;
            }
            if ($na !== null && $nb === null) {
                return -1;
            }
            if ($na === null && $nb !== null) {
                return 1;
            }

            return strnatcasecmp($ca, $cb);
        });

        return $lineas;
    }

    private function nullableString(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }
}
