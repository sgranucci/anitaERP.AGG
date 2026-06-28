<?php

namespace App\Services\Ventas;

use App\Models\Caja\Cuentacaja;
use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\MaquinavendingArticulo;
use App\Models\Ventas\MaquinavendingRendicion;
use App\Models\Ventas\MaquinavendingRendicionArticulo;
use App\Models\Ventas\MaquinavendingRendicionMedioPago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MaquinavendingRendicionRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\MaquinavendingRendicionPermiso;
use App\Services\Stock\PrecioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MaquinavendingRendicionService
{
    private const TOLERANCIA = 0.02;

    public function __construct(
        private MaquinavendingRendicionRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private MaquinavendingRendicionAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function articulosParaMaquina(int $maquinavendingId, int $empresaId, ?string $fechaJornada = null): array
    {
        $maquina = Maquinavending::query()
            ->with(['articulos.articulo', 'puntoventa', 'listaprecio'])
            ->where('empresa_id', $empresaId)
            ->findOrFail($maquinavendingId);

        $fecha = $fechaJornada
            ? Carbon::parse($fechaJornada)->startOfDay()
            : now()->startOfDay();
        $listaprecioId = (int) ($maquina->listaprecio_id ?? config('precio.listaprecio_default_id', 2));

        $filas = [];
        foreach ($maquina->articulos as $linea) {
            $articulo = $linea->articulo;
            $filas[] = [
                'numero_rulo' => (int) $linea->numero_rulo,
                'articulo_id' => (int) $linea->articulo_id,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'precio_lista' => $this->resolverPrecioLista($linea, $listaprecioId, $fecha),
                'listaprecio_id' => $listaprecioId,
            ];
        }

        return $filas;
    }

    private function resolverPrecioLista(MaquinavendingArticulo $linea, int $listaprecioId, Carbon $fecha): float
    {
        if ($linea->precio_lista !== null && (float) $linea->precio_lista > 0) {
            return round((float) $linea->precio_lista, 2);
        }

        $precios = PrecioService::asignaPrecioPorLista(
            (int) $linea->articulo_id,
            $listaprecioId,
            $fecha->format('Y-m-d'),
        );
        $ultimo = is_array($precios) && count($precios) > 0 ? end($precios) : null;

        return round((float) ($ultimo['precio'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function guardar(array $payload): MaquinavendingRendicion
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $maquinaId = (int) ($payload['maquinavending_id'] ?? 0);

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('Empresa no permitida para su usuario.');
        }

        $maquina = Maquinavending::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($maquinaId);

        [$lineas, $medios, $totalVentas, $totalCobrado, $fechaRendicion, $fechaJornada] = $this->parsearPayload(
            $payload,
            $empresaId,
        );

        return DB::transaction(function () use (
            $empresaId,
            $maquina,
            $fechaRendicion,
            $fechaJornada,
            $totalVentas,
            $totalCobrado,
            $lineas,
            $medios,
            $payload,
        ) {
            $numeroCierre = $this->siguienteNumeroCierre($empresaId);

            $rendicion = $this->repository->create([
                'empresa_id' => $empresaId,
                'maquinavending_id' => (int) $maquina->id,
                'numero_cierre' => $numeroCierre,
                'fecha_rendicion' => $fechaRendicion,
                'fecha_jornada' => $fechaJornada,
                'total_ventas' => $totalVentas,
                'total_cobrado' => $totalCobrado,
                'observacion' => trim((string) ($payload['observacion'] ?? '')) ?: null,
                'usuario_id' => (int) Auth::id(),
            ]);

            $this->persistirDetalle($rendicion, $lineas, $medios);
            $this->programarSyncAnita((int) $rendicion->id);

            return $this->repository->findOrFail((int) $rendicion->id);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function actualizar(MaquinavendingRendicion $rendicion, array $payload): MaquinavendingRendicion
    {
        $this->assertPuedeModificar($rendicion);

        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            throw new InvalidArgumentException('Empresa no permitida para su usuario.');
        }

        $empresaId = (int) $rendicion->empresa_id;
        $maquinaId = (int) $rendicion->maquinavending_id;

        if ((int) ($payload['empresa_id'] ?? 0) !== $empresaId
            || (int) ($payload['maquinavending_id'] ?? 0) !== $maquinaId) {
            throw new InvalidArgumentException('No se puede cambiar la empresa ni la máquina de una rendición existente.');
        }

        [$lineas, $medios, $totalVentas, $totalCobrado, $fechaRendicion, $fechaJornada] = $this->parsearPayload(
            $payload,
            $empresaId,
        );

        return DB::transaction(function () use (
            $rendicion,
            $fechaRendicion,
            $fechaJornada,
            $totalVentas,
            $totalCobrado,
            $lineas,
            $medios,
            $payload,
        ) {
            $rendicion->update([
                'fecha_rendicion' => $fechaRendicion,
                'fecha_jornada' => $fechaJornada,
                'total_ventas' => $totalVentas,
                'total_cobrado' => $totalCobrado,
                'observacion' => trim((string) ($payload['observacion'] ?? '')) ?: null,
            ]);

            MaquinavendingRendicionArticulo::query()
                ->where('maquinavending_rendicion_id', $rendicion->id)
                ->delete();
            MaquinavendingRendicionMedioPago::query()
                ->where('maquinavending_rendicion_id', $rendicion->id)
                ->delete();

            $this->persistirDetalle($rendicion, $lineas, $medios);
            $this->programarSyncAnita((int) $rendicion->id);

            return $this->repository->findOrFail((int) $rendicion->id);
        });
    }

    public function eliminar(MaquinavendingRendicion $rendicion): void
    {
        $this->assertPuedeModificar($rendicion);

        if (! $this->empresaRepository->empresaIdPermitida((int) $rendicion->empresa_id)) {
            throw new InvalidArgumentException('Empresa no permitida para su usuario.');
        }

        $rendicion->load(['mediosPago.cuentacaja', 'maquinavending.puntoventa']);

        DB::transaction(function () use ($rendicion) {
            $snapshot = $rendicion->replicate();
            $snapshot->setRawAttributes($rendicion->getAttributes());
            $snapshot->setRelations($rendicion->getRelations());
            $rendicionId = (int) $rendicion->id;

            MaquinavendingRendicionArticulo::query()
                ->where('maquinavending_rendicion_id', $rendicionId)
                ->delete();
            MaquinavendingRendicionMedioPago::query()
                ->where('maquinavending_rendicion_id', $rendicionId)
                ->delete();
            $rendicion->delete();

            DB::afterCommit(function () use ($snapshot) {
                try {
                    $this->anitaSyncService->eliminarEnAnita($snapshot);
                } catch (\Throwable $e) {
                    Log::error('maquinavending_rendicion.anita_delete.fallo', [
                        'rendicion_id' => (int) ($snapshot->id ?? 0),
                        'mensaje' => $e->getMessage(),
                    ]);
                }
            });
        });
    }

    public function assertPuedeModificar(MaquinavendingRendicion $rendicion): void
    {
        if (! MaquinavendingRendicionPermiso::puedeModificar($rendicion)) {
            throw new InvalidArgumentException(
                MaquinavendingRendicionPermiso::mensajeBloqueoModificacion($rendicion)
            );
        }
    }

    public function datosComprobante(MaquinavendingRendicion $rendicion): array
    {
        $rendicion->loadMissing([
            'empresa',
            'maquinavending.puntoventa',
            'usuario',
            'articulos.articulo',
            'mediosPago.cuentacaja',
        ]);

        $empresa = $rendicion->empresa;
        $maquina = $rendicion->maquinavending;
        $pv = $maquina?->puntoventa;

        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([$empresa]));

        return [
            'titulo' => 'Rendición máquina vending',
            'subtitulo' => trim(($pv->codigo ?? '').' — '.($maquina->nombre ?? ''), ' —'),
            'fecha_emision_comprobante' => now()->format('d/m/Y H:i'),
            'fecha_rendicion' => $rendicion->fecha_rendicion?->format('d/m/Y H:i') ?? '',
            'fecha_jornada' => $rendicion->fecha_jornada?->format('d/m/Y') ?? '',
            'numero_cierre' => (int) $rendicion->numero_cierre,
            'rendicion_id' => (int) $rendicion->id,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'maquina_nombre' => (string) ($maquina->nombre ?? ''),
            'puntoventa_codigo' => (string) ($pv->codigo ?? ''),
            'puntoventa_nombre' => (string) ($pv->nombre ?? ''),
            'usuario_nombre' => (string) ($rendicion->usuario->nombre ?? ''),
            'total_ventas' => round((float) $rendicion->total_ventas, 2),
            'total_cobrado' => round((float) $rendicion->total_cobrado, 2),
            'observacion' => (string) ($rendicion->observacion ?? ''),
            'logo' => $logos[0] ?? null,
            'articulos' => $rendicion->articulos->map(static fn ($a) => [
                'numero_rulo' => (int) $a->numero_rulo,
                'sku' => (string) ($a->articulo->sku ?? ''),
                'descripcion' => (string) ($a->articulo->descripcion ?? ''),
                'cantidad' => (float) $a->cantidad,
                'precio_lista' => round((float) $a->precio_lista, 2),
                'importe_total' => round((float) $a->importe_total, 2),
            ])->values()->all(),
            'medios_pago' => $rendicion->mediosPago->map(static fn ($m) => [
                'codigo' => (string) ($m->cuentacaja->codigo ?? ''),
                'nombre' => (string) ($m->cuentacaja->nombre ?? ''),
                'monto' => round((float) $m->monto, 2),
                'cotizacion' => round((float) $m->cotizacion, 4),
                'monto_pesos' => round((float) $m->monto * (float) $m->cotizacion, 2),
            ])->values()->all(),
        ];
    }

    /** Correlativo global por empresa (todas las máquinas comparten la misma secuencia). */
    private function siguienteNumeroCierre(int $empresaId): int
    {
        $max = (int) MaquinavendingRendicion::query()
            ->where('empresa_id', $empresaId)
            ->max('numero_cierre');

        return $max + 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: float, 3: float, 4: Carbon, 5: Carbon}
     */
    private function parsearPayload(array $payload, int $empresaId): array
    {
        $lineas = $this->normalizarLineas($payload['articulos'] ?? []);
        $medios = $this->normalizarMedios($payload['medios_pago'] ?? [], $empresaId);

        $totalVentas = round(array_sum(array_column($lineas, 'importe_total')), 2);
        $totalCobrado = round(array_sum(array_map(
            static fn (array $m) => round((float) $m['monto'] * (float) $m['cotizacion'], 2),
            $medios
        )), 2);

        if ($totalVentas <= 0) {
            throw new InvalidArgumentException('Indique al menos una cantidad vendida para rendir.');
        }

        if (abs($totalVentas - $totalCobrado) > self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'El total a rendir ($'.number_format($totalVentas, 2, ',', '.')
                .') no coincide con los medios de pago ($'.number_format($totalCobrado, 2, ',', '.').').'
            );
        }

        $fechaRendicion = Carbon::parse($payload['fecha_rendicion'] ?? now());
        $fechaJornada = ! empty($payload['fecha_jornada'])
            ? Carbon::parse($payload['fecha_jornada'])->startOfDay()
            : $fechaRendicion->copy()->startOfDay();

        return [$lineas, $medios, $totalVentas, $totalCobrado, $fechaRendicion, $fechaJornada];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $medios
     */
    private function persistirDetalle(MaquinavendingRendicion $rendicion, array $lineas, array $medios): void
    {
        foreach ($lineas as $linea) {
            MaquinavendingRendicionArticulo::query()->create([
                'maquinavending_rendicion_id' => $rendicion->id,
                'numero_rulo' => $linea['numero_rulo'],
                'articulo_id' => $linea['articulo_id'],
                'cantidad' => $linea['cantidad'],
                'precio_lista' => $linea['precio_lista'],
                'importe_total' => $linea['importe_total'],
            ]);
        }

        foreach ($medios as $medio) {
            MaquinavendingRendicionMedioPago::query()->create([
                'maquinavending_rendicion_id' => $rendicion->id,
                'cuentacaja_id' => $medio['cuentacaja_id'],
                'monto' => $medio['monto'],
                'cotizacion' => $medio['cotizacion'],
            ]);
        }
    }

    private function programarSyncAnita(int $rendicionId): void
    {
        DB::afterCommit(function () use ($rendicionId) {
            try {
                $fresh = $this->repository->findOrFail($rendicionId);
                $this->anitaSyncService->sincronizarDespuesDeGuardar($fresh);
            } catch (\Throwable $e) {
                Log::error('maquinavending_rendicion.anita_sync.fallo', [
                    'rendicion_id' => $rendicionId,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * @param  mixed  $raw
     * @return list<array{numero_rulo:int, articulo_id:int, cantidad:float, precio_lista:float, importe_total:float}>
     */
    private function normalizarLineas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $lineas = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cantidad = round((float) ($row['cantidad'] ?? 0), 3);
            if ($cantidad <= 0) {
                continue;
            }
            $precio = round((float) ($row['precio_lista'] ?? 0), 2);
            $lineas[] = [
                'numero_rulo' => (int) ($row['numero_rulo'] ?? 0),
                'articulo_id' => (int) ($row['articulo_id'] ?? 0),
                'cantidad' => $cantidad,
                'precio_lista' => $precio,
                'importe_total' => round($cantidad * $precio, 2),
            ];
        }

        return $lineas;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{cuentacaja_id:int, monto:float, cotizacion:float}>
     */
    private function normalizarMedios(mixed $raw, int $empresaId): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $medios = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cuentacajaId = (int) ($row['cuentacaja_id'] ?? 0);
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if ($cuentacajaId <= 0 || abs($monto) < 0.005) {
                continue;
            }
            if (! Cuentacaja::existeParaEmpresa($cuentacajaId, $empresaId)) {
                throw new InvalidArgumentException('Cuenta de caja id '.$cuentacajaId.' no válida para la empresa.');
            }
            $cotizacion = round((float) ($row['cotizacion'] ?? 1), 4);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }
            $medios[] = [
                'cuentacaja_id' => $cuentacajaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
            ];
        }

        if ($medios === []) {
            throw new InvalidArgumentException('Indique al menos un medio de pago con monto.');
        }

        return $medios;
    }
}
