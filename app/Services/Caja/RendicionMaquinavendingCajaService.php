<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Caja\RendicionMaquinavendingMovimientoCaja;
use App\Models\Ventas\MaquinavendingRendicion;
use App\Support\Caja\RendicionMaquinavendingCajaListadoFiltros;
use App\Support\Caja\RendicionMaquinavendingCajaPermiso;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\MaquinavendingRendicionAnitaAdvertenciaSupport;
use App\Services\Ventas\MaquinavendingRendicionAnitaSyncService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RendicionMaquinavendingCajaService
{
    private const TOLERANCIA = 0.02;

    public function __construct(
        private MaquinavendingRendicionAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionMaquinavendingCaja::query()
            ->with([
                'empresa:id,nombre',
                'caja:id,nombre',
                'puntoventaCae:id,codigo,nombre',
                'maquinavending:id,nombre',
                'maquinavendingRendicion:id,numero_cierre,fecha_jornada',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        RendicionMaquinavendingCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);

        if (RendicionMaquinavendingCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RendicionMaquinavendingCajaListadoFiltros::aplicar($q, $filtros);
        }

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * Rendiciones Ventas aún no presentadas en caja.
     *
     * @return Collection<int, MaquinavendingRendicion>
     */
    public function rendicionesVentasPendientes(?int $exceptoRendicionCajaId = null, ?int $empresaId = null): Collection
    {
        $presentadas = RendicionMaquinavendingCaja::query()
            ->when($exceptoRendicionCajaId, fn ($q) => $q->where('id', '!=', $exceptoRendicionCajaId))
            ->pluck('maquinavending_rendicion_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return MaquinavendingRendicion::query()
            ->with(['empresa:id,nombre', 'maquinavending.puntoventa:id,codigo,nombre', 'usuario:id,nombre'])
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotIn('id', $presentadas)
            ->orderByDesc('fecha_rendicion')
            ->limit(200)
            ->get();
    }

    public function rendicionVentasYaPresentada(int $maquinavendingRendicionId, ?int $exceptoRendicionCajaId = null): bool
    {
        if ($maquinavendingRendicionId <= 0) {
            return false;
        }

        return RendicionMaquinavendingCaja::query()
            ->where('maquinavending_rendicion_id', $maquinavendingRendicionId)
            ->when($exceptoRendicionCajaId, fn ($q) => $q->where('id', '!=', $exceptoRendicionCajaId))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeRendicionVentas(int $maquinavendingRendicionId, ?int $exceptoRendicionCajaId = null): array
    {
        $rendicion = MaquinavendingRendicion::query()
            ->with(['empresa', 'maquinavending.puntoventa', 'mediosPago.cuentacaja.monedas', 'usuario'])
            ->findOrFail($maquinavendingRendicionId);

        if ($this->rendicionVentasYaPresentada($maquinavendingRendicionId, $exceptoRendicionCajaId)) {
            throw new InvalidArgumentException(
                'La rendición vending #'.(int) $rendicion->numero_cierre.' ya fue presentada en caja.'
            );
        }

        $maquina = $rendicion->maquinavending;
        $pv = $maquina?->puntoventa;
        $pvId = (int) ($pv?->id ?? 0);

        $movimientos = $rendicion->mediosPago->map(static fn ($m) => [
            'cuentacaja_id' => (int) $m->cuentacaja_id,
            'cuentacaja_nombre' => (string) ($m->cuentacaja->nombre ?? ''),
            'cuentacaja_codigo' => (string) ($m->cuentacaja->codigo ?? ''),
            'monto' => round((float) $m->monto, 2),
            'cotizacion' => round((float) $m->cotizacion, 4),
        ])->values()->all();

        $totalVentas = round((float) $rendicion->total_ventas, 2);
        $totalCobrado = round((float) $rendicion->total_cobrado, 2);

        return [
            'maquinavending_rendicion_id' => (int) $rendicion->id,
            'numero_cierre' => (int) $rendicion->numero_cierre,
            'codigo_sugerido' => $this->proponerCodigo($rendicion),
            'empresa_id' => (int) $rendicion->empresa_id,
            'empresa_nombre' => (string) ($rendicion->empresa->nombre ?? ''),
            'maquinavending_id' => (int) $rendicion->maquinavending_id,
            'maquina_nombre' => (string) ($maquina->nombre ?? ''),
            'puntoventa_cae_id' => $pvId,
            'puntoventa_caea_id' => $pvId,
            'puntoventa_codigo' => (string) ($pv->codigo ?? ''),
            'puntoventa_nombre' => (string) ($pv->nombre ?? ''),
            'fecha_jornada' => $rendicion->fecha_jornada?->format('Y-m-d') ?? '',
            'fecha_jornada_fmt' => $rendicion->fecha_jornada?->format('d/m/Y') ?? '',
            'fecha_rendicion_ventas' => $rendicion->fecha_rendicion?->format('d/m/Y H:i') ?? '',
            'usuario_ventas' => (string) ($rendicion->usuario->nombre ?? ''),
            'totalfactura' => $totalVentas,
            'totalcobrado' => $totalCobrado,
            'totalinvitacion' => 0.0,
            'totalnotacredito' => 0.0,
            'totalredondeo' => 0.0,
            'totalredondeoinvitacion' => 0.0,
            'sobrantefaltante' => round($totalCobrado - $totalVentas, 2),
            'iniciodelfondo' => 0.0,
            'movimientos' => $movimientos,
            'comprobante_url' => route('maquinavending_rendicion_comprobante', ['id' => $rendicion->id, 'inline' => 1]),
        ];
    }

    public function proponerCodigo(MaquinavendingRendicion $rendicion): string
    {
        return 'MV-'.(int) $rendicion->numero_cierre.'-'.(int) $rendicion->id;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     * @return array{presentacion: RendicionMaquinavendingCaja, advertencias_anita: list<string>}
     */
    public function guardar(array $cabecera, array $movimientos): array
    {
        $rendicion = DB::transaction(function () use ($cabecera, $movimientos) {
            $rendicionVentasId = (int) ($cabecera['maquinavending_rendicion_id'] ?? 0);
            if ($rendicionVentasId <= 0) {
                throw new InvalidArgumentException('Debe seleccionar una rendición vending registrada en Ventas.');
            }

            if ($this->rendicionVentasYaPresentada($rendicionVentasId)) {
                throw new InvalidArgumentException('Esta rendición vending ya fue presentada en caja.');
            }

            $rendicionVentas = MaquinavendingRendicion::query()
                ->with('maquinavending.puntoventa')
                ->findOrFail($rendicionVentasId);

            RendicionMaquinavendingCajaPermiso::assertAltaPermitida(
                (int) $rendicionVentas->empresa_id,
                Carbon::parse($cabecera['fecharendicion'] ?? now()),
                $rendicionVentas->fecha_jornada ? Carbon::parse($rendicionVentas->fecha_jornada) : null,
            );

            $this->validarCuadre($cabecera, $movimientos);

            if (trim((string) ($cabecera['codigo'] ?? '')) === '') {
                $cabecera['codigo'] = $this->proponerCodigo($rendicionVentas);
            }

            $pvId = (int) ($rendicionVentas->maquinavending?->puntoventa_id ?? 0);
            $cabecera['empresa_id'] = (int) $rendicionVentas->empresa_id;
            $cabecera['maquinavending_id'] = (int) $rendicionVentas->maquinavending_id;
            $cabecera['puntoventa_cae_id'] = (int) ($cabecera['puntoventa_cae_id'] ?? $pvId);
            $cabecera['puntoventa_caea_id'] = (int) ($cabecera['puntoventa_caea_id'] ?? $pvId);
            $cabecera['creousuario_id'] = (int) Auth::id();

            $rendicion = RendicionMaquinavendingCaja::create($cabecera);
            $this->persistirMovimientos($rendicion, $movimientos);

            return [
                'presentacion' => $rendicion,
                'total_z' => round((float) ($cabecera['totalfactura'] ?? 0), 2),
                'rendicion_ventas_id' => (int) $rendicionVentas->id,
            ];
        });

        $advertenciasAnita = $this->sincronizarAnitaTrasPresentacionCaja(
            (int) $rendicion['rendicion_ventas_id'],
            (float) $rendicion['total_z'],
            'al presentar en caja',
        );

        return [
            'presentacion' => $rendicion['presentacion']->fresh(['movimientos.cuentacaja', 'empresa', 'caja', 'maquinavendingRendicion']),
            'advertencias_anita' => $advertenciasAnita,
        ];
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     * @return array{presentacion: RendicionMaquinavendingCaja, advertencias_anita: list<string>}
     */
    public function actualizar(int $id, array $cabecera, array $movimientos): array
    {
        $resultado = DB::transaction(function () use ($id, $cabecera, $movimientos) {
            $rendicion = RendicionMaquinavendingCaja::query()->findOrFail($id);
            RendicionMaquinavendingCajaPermiso::assertModificacionPermitida($rendicion);

            $rendicionVentasId = (int) ($cabecera['maquinavending_rendicion_id'] ?? $rendicion->maquinavending_rendicion_id);

            if ($this->rendicionVentasYaPresentada($rendicionVentasId, $id)) {
                throw new InvalidArgumentException('Otra rendición en caja ya utiliza esa rendición vending.');
            }

            $this->validarCuadre($cabecera, $movimientos);

            $rendicion->update($cabecera);
            RendicionMaquinavendingMovimientoCaja::query()
                ->where('rendicion_maquinavending_caja_id', $rendicion->id)
                ->delete();
            $this->persistirMovimientos($rendicion, $movimientos);

            return [
                'presentacion' => $rendicion,
                'total_z' => round((float) ($cabecera['totalfactura'] ?? 0), 2),
                'rendicion_ventas_id' => (int) $rendicionVentasId,
            ];
        });

        $advertenciasAnita = $this->sincronizarAnitaTrasPresentacionCaja(
            (int) $resultado['rendicion_ventas_id'],
            (float) $resultado['total_z'],
            'al actualizar presentación en caja',
        );

        return [
            'presentacion' => $resultado['presentacion']->fresh(['movimientos.cuentacaja', 'empresa', 'caja', 'maquinavendingRendicion']),
            'advertencias_anita' => $advertenciasAnita,
        ];
    }

    public function eliminar(int $id): array
    {
        $rendicionVentasId = DB::transaction(function () use ($id) {
            $rendicion = RendicionMaquinavendingCaja::query()
                ->with('maquinavendingRendicion')
                ->findOrFail($id);

            RendicionMaquinavendingCajaPermiso::assertModificacionPermitida($rendicion);

            $rendicionVentas = $rendicion->maquinavendingRendicion;
            $rendicionVentasId = (int) ($rendicionVentas?->id ?? 0);

            RendicionMaquinavendingMovimientoCaja::query()
                ->where('rendicion_maquinavending_caja_id', $rendicion->id)
                ->delete();
            $rendicion->delete();

            return $rendicionVentasId;
        });

        $advertenciasAnita = [];
        if ($rendicionVentasId > 0) {
            $advertenciasAnita = $this->sincronizarAnitaTrasPresentacionCaja(
                $rendicionVentasId,
                0.0,
                'al anular presentación en caja',
            );
        }

        return $advertenciasAnita;
    }

    public function findConDetalle(int $id): RendicionMaquinavendingCaja
    {
        return RendicionMaquinavendingCaja::query()
            ->with([
                'empresa',
                'caja',
                'puntoventaCae',
                'puntoventaCaea',
                'maquinavending.puntoventa',
                'maquinavendingRendicion.usuario',
                'movimientos.cuentacaja.monedas',
                'creousuario',
            ])
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosComprobante(RendicionMaquinavendingCaja $rendicion): array
    {
        $rendicion->loadMissing([
            'empresa',
            'caja',
            'puntoventaCae',
            'maquinavending.puntoventa',
            'maquinavendingRendicion',
            'movimientos.cuentacaja',
            'creousuario',
        ]);

        $empresaNombre = (string) ($rendicion->empresa->nombre ?? '');
        $ventas = $rendicion->maquinavendingRendicion;
        $fechaJornada = $ventas?->fecha_jornada;
        $fechaRegistroCaja = $rendicion->fecharendicion;
        $fechaJornadaIso = $fechaJornada?->format('Y-m-d') ?? '';
        $fechasMismoDia = $fechaJornadaIso !== ''
            && $fechaRegistroCaja !== null
            && $fechaJornadaIso === $fechaRegistroCaja->format('Y-m-d');

        $lineas = $rendicion->movimientos->map(static fn ($m) => [
            'codigo' => (string) ($m->cuentacaja->codigo ?? ''),
            'nombre' => (string) ($m->cuentacaja->nombre ?? ''),
            'monto' => round((float) $m->monto, 2),
            'cotizacion' => round((float) $m->cotizacion, 4),
            'monto_pesos' => round((float) $m->monto * (float) $m->cotizacion, 2),
        ])->values()->all();

        $totalGrilla = round(array_sum(array_column($lineas, 'monto_pesos')), 2);

        return [
            'titulo' => 'Rendición vending — caja',
            'subtitulo' => 'Ticket '.(string) $rendicion->codigo
                .' — Cierre Ventas #'.(int) ($ventas?->numero_cierre ?? 0),
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'codigo_anita' => (string) $rendicion->codigo,
            'rendicion_id' => (int) $rendicion->id,
            'fecha_emision_comprobante' => now()->format('d/m/Y H:i'),
            'fecha_jornada' => $fechaJornada?->format('d/m/Y') ?? '',
            'fecha_registro_caja' => $fechaRegistroCaja?->format('d/m/Y H:i') ?? '',
            'fechas_mismo_dia' => $fechasMismoDia,
            'caja_nombre' => (string) ($rendicion->caja->nombre ?? ''),
            'usuario_registro' => (string) ($rendicion->creousuario->nombre ?? ''),
            'numero_cierre_ventas' => (int) ($ventas?->numero_cierre ?? 0),
            'maquina_nombre' => (string) ($rendicion->maquinavending->nombre ?? ''),
            'pv_cae_label' => trim(
                ($rendicion->puntoventaCae->codigo ?? '').' — '.($rendicion->puntoventaCae->nombre ?? ''),
                ' —'
            ),
            'totalfactura' => round((float) $rendicion->totalfactura, 2),
            'totalcobrado' => round((float) $rendicion->totalcobrado, 2),
            'sobrante_faltante' => round((float) $rendicion->sobrantefaltante, 2),
            'observacion' => (string) ($rendicion->observacion ?? ''),
            'lineas_medios' => $lineas,
            'resumen_rendicion' => [
                'total_grilla' => $totalGrilla,
                'total_ajustado' => round((float) $rendicion->totalcobrado, 2),
            ],
            // Compatibilidad con vistas/export legacy
            'fecha_emision' => now()->format('d/m/Y H:i'),
            'codigo' => (string) $rendicion->codigo,
            'fecharendicion' => $fechaRegistroCaja?->format('d/m/Y H:i') ?? '',
            'puntoventa' => trim(
                ($rendicion->puntoventaCae->codigo ?? '').' — '.($rendicion->puntoventaCae->nombre ?? ''),
                ' —'
            ),
            'usuario_caja' => (string) ($rendicion->creousuario->nombre ?? ''),
            'medios_pago' => $lineas,
        ];
    }

    /**
     * Fecha/hora de presentación en caja: se fija al crear (now) y no se modifica en edición.
     */
    public function resolverFecharendicionInmutable(?int $rendicionId = null): string
    {
        if ($rendicionId !== null && $rendicionId > 0) {
            $existente = RendicionMaquinavendingCaja::query()->find($rendicionId);
            if ($existente?->fecharendicion !== null) {
                return $existente->fecharendicion->format('Y-m-d H:i:s');
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function cabeceraDesdeRequest(array $validated, ?int $rendicionId = null): array
    {
        return [
            'codigo' => trim((string) ($validated['codigo'] ?? '')),
            'maquinavending_rendicion_id' => (int) ($validated['maquinavending_rendicion_id'] ?? 0),
            'empresa_id' => (int) ($validated['empresa_id'] ?? 0),
            'maquinavending_id' => (int) ($validated['maquinavending_id'] ?? 0),
            'puntoventa_cae_id' => (int) ($validated['puntoventa_cae_id'] ?? 0),
            'puntoventa_caea_id' => (int) ($validated['puntoventa_caea_id'] ?? 0),
            'caja_id' => (int) ($validated['caja_id'] ?? 0),
            'fecharendicion' => $this->resolverFecharendicionInmutable($rendicionId),
            'iniciodelfondo' => round((float) ($validated['iniciodelfondo'] ?? 0), 2),
            'totalfactura' => round((float) ($validated['totalfactura'] ?? 0), 2),
            'totalcobrado' => round((float) ($validated['totalcobrado'] ?? 0), 2),
            'totalinvitacion' => round((float) ($validated['totalinvitacion'] ?? 0), 2),
            'totalnotacredito' => round((float) ($validated['totalnotacredito'] ?? 0), 2),
            'totalredondeo' => round((float) ($validated['totalredondeo'] ?? 0), 2),
            'totalredondeoinvitacion' => round((float) ($validated['totalredondeoinvitacion'] ?? 0), 2),
            'sobrantefaltante' => round((float) ($validated['sobrantefaltante'] ?? 0), 2),
            'observacion' => trim((string) ($validated['observacion'] ?? '')) ?: null,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{cuentacaja_id:int, monto:float, cotizacion:float}>
     */
    public function normalizarMovimientosRequest(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $movimientos = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cuentacajaId = (int) ($row['cuentacaja_id'] ?? 0);
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if ($cuentacajaId <= 0 || abs($monto) < 0.005) {
                continue;
            }
            $cotizacion = round((float) ($row['cotizacion'] ?? 1), 4);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }
            $movimientos[] = [
                'cuentacaja_id' => $cuentacajaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
            ];
        }

        return $movimientos;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function validarCuadre(array $cabecera, array $movimientos): void
    {
        $totalGrilla = round(array_sum(array_map(
            static fn (array $m) => round((float) $m['monto'] * (float) $m['cotizacion'], 2),
            $movimientos
        )), 2);
        $totalCobrado = round((float) ($cabecera['totalcobrado'] ?? 0), 2);

        if (abs($totalGrilla - $totalCobrado) > self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Los medios de pago ($'.number_format($totalGrilla, 2, ',', '.')
                .') no coinciden con el total cobrado ($'.number_format($totalCobrado, 2, ',', '.').').'
            );
        }
    }

    /**
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function persistirMovimientos(RendicionMaquinavendingCaja $rendicion, array $movimientos): void
    {
        foreach ($movimientos as $row) {
            RendicionMaquinavendingMovimientoCaja::create([
                'rendicion_maquinavending_caja_id' => $rendicion->id,
                'cuentacaja_id' => $row['cuentacaja_id'],
                'monto' => $row['monto'],
                'cotizacion' => $row['cotizacion'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function sincronizarAnitaTrasPresentacionCaja(int $rendicionVentasId, float $totalZ, string $contexto): array
    {
        if ($rendicionVentasId <= 0 || ! $this->anitaSyncService->sincronizacionHabilitada()) {
            return [];
        }

        $fresh = MaquinavendingRendicion::query()->find($rendicionVentasId);
        if ($fresh === null) {
            return [];
        }

        try {
            $this->anitaSyncService->actualizarSoloTotalZ($fresh, $totalZ);
            $fresh->refresh();
            $leido = $this->anitaSyncService->leerTotalesCabeceraEnAnita($fresh);
            $leidoZ = $leido !== null ? (float) ($leido['total_z'] ?? 0) : null;
            if ($leidoZ === null || abs($leidoZ - round($totalZ, 2)) > self::TOLERANCIA) {
                return [
                    MaquinavendingRendicionAnitaAdvertenciaSupport::mensajeTotalZNoConfirmado(
                        $fresh,
                        round($totalZ, 2),
                        $leidoZ,
                    ),
                ];
            }
        } catch (\Throwable $e) {
            Log::error('rendicion_maquinavending_caja.anita_total_z.fallo', [
                'rendicion_ventas_id' => $rendicionVentasId,
                'mensaje' => $e->getMessage(),
            ]);

            return [
                MaquinavendingRendicionAnitaAdvertenciaSupport::mensajeDesdeExcepcion($fresh, $e, $contexto),
            ];
        }

        return [];
    }
}
