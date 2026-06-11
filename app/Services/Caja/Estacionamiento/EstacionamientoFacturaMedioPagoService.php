<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Cobranza;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Caja\Usocuentacaja;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Ventas\Venta;
use App\Support\Caja\Estacionamiento\EstacionamientoVentaDetalleSupport;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EstacionamientoFacturaMedioPagoService
{
    private const TOLERANCIA_MONTO = 0.009;

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   venta_id?: int,
     *   venta_total?: float,
     *   empresa_id?: int,
     *   cobranzas?: list<array{
     *     cobranza_id: int,
     *     monto: float,
     *     lineas: list<array{
     *       id: int,
     *       cuentacaja_id: int,
     *       codigo: string,
     *       nombre: string,
     *       monto: float,
     *       moneda_id: int,
     *       moneda: string
     *     }>
     *   }>,
     *   cuentas_caja?: list<array{id:int,codigo:string,nombre:string,moneda_id:int}>
     * }
     */
    public function datosParaCambio(int $ventaId): array
    {
        $venta = $this->resolverVentaEstacionamiento($ventaId);
        if ($venta === null) {
            return ['ok' => false, 'error' => 'La venta no corresponde a una emisión estacionamiento.'];
        }

        $empresaId = (int) ($venta->puntoventas?->empresa_id ?? 0);
        $cobranzas = EstacionamientoVentaDetalleSupport::cobranzasDeVenta($venta);
        if ($cobranzas->isEmpty()) {
            return ['ok' => false, 'error' => 'La factura no tiene cobranzas registradas.'];
        }

        return [
            'ok' => true,
            'venta_id' => (int) $venta->id,
            'venta_codigo' => (string) ($venta->codigo ?? ''),
            'venta_total' => round((float) $venta->total, 2),
            'empresa_id' => $empresaId,
            'usocuentacaja_estacionamiento_id' => $this->usoCuentacajaEstacionamientoId(),
            'cobranzas' => $this->armarCobranzasPayload($cobranzas),
            'cuentas_caja' => $this->listarCuentasCajaEstacionamiento($empresaId),
        ];
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,moneda_id:int,moneda_abreviatura:?string,error?:string}
     */
    public function cuentaPorCodigo(int $ventaId, string $codigo): array
    {
        $venta = $this->resolverVentaEstacionamiento($ventaId);
        if ($venta === null) {
            return ['id' => 0, 'error' => 'La venta no corresponde a una emisión estacionamiento.'];
        }

        $empresaId = (int) ($venta->puntoventas?->empresa_id ?? 0);
        $usoId = $this->usoCuentacajaEstacionamientoId();
        if (! $usoId) {
            return ['id' => 0, 'error' => 'Uso de cuenta de caja estacionamiento no configurado.'];
        }

        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['id' => 0, 'error' => 'Indique el código de cuenta de caja.'];
        }

        $variantes = array_values(array_unique(array_filter([
            $codigo,
            ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : null,
        ])));

        $query = Cuentacaja::query()
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->whereIn('codigo', $variantes)
            ->with('monedas:id,abreviatura,nombre');

        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $cuentas = $query->get(['id', 'nombre', 'codigo', 'moneda_id', 'empresa_id']);
        $cuenta = $cuentas->first(fn ($c) => (int) $c->empresa_id === $empresaId)
            ?? $cuentas->first();

        if ($cuenta === null) {
            return [
                'id' => 0,
                'error' => 'No existe cuenta de caja con uso Estacionamiento para ese código.',
            ];
        }

        if (GastronomiaCuentacajaSoloAutomaticaSupport::esSoloAutomatica(
            (int) $cuenta->id,
            (string) $cuenta->codigo,
            $empresaId,
        )) {
            return [
                'id' => 0,
                'error' => GastronomiaCuentacajaSoloAutomaticaSupport::mensajeRechazoManual((string) $cuenta->codigo),
            ];
        }

        return [
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
            'moneda_id' => (int) ($cuenta->moneda_id ?? 0),
            'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
            ...$this->presentacionCuenta((string) $cuenta->nombre, (string) $cuenta->codigo),
        ];
    }

    /**
     * @param  list<array{caja_movimiento_cuentacaja_id:int,cuentacaja_id:int,monto?:mixed}>  $cambios
     * @return array{ok:bool,error?:string,mensaje?:string}
     */
    public function aplicarCambio(int $ventaId, array $cambios): array
    {
        if ($cambios === []) {
            return ['ok' => false, 'error' => 'Debe indicar al menos un medio de pago.'];
        }

        $venta = $this->resolverVentaEstacionamiento($ventaId);
        if ($venta === null) {
            return ['ok' => false, 'error' => 'La venta no corresponde a una emisión estacionamiento.'];
        }

        $totalFacturaOriginal = round((float) $venta->total, 2);
        $empresaId = (int) ($venta->puntoventas?->empresa_id ?? 0);
        $cobranzaIds = EstacionamientoVentaDetalleSupport::cobranzasDeVenta($venta)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $lineasPermitidas = $this->lineasCuentacajaPorVenta($cobranzaIds);
        if ($lineasPermitidas->isEmpty()) {
            return ['ok' => false, 'error' => 'No hay líneas de cobranza editables para esta venta.'];
        }

        $porId = $lineasPermitidas->keyBy('id');

        try {
            DB::transaction(function () use ($cambios, $porId, $empresaId, $cobranzaIds, $venta, $totalFacturaOriginal) {
                $cobranzaMontosOriginales = Cobranza::query()
                    ->whereIn('id', $cobranzaIds)
                    ->pluck('monto', 'id')
                    ->map(fn ($m) => round((float) $m, 2))
                    ->all();

                foreach ($cambios as $cambio) {
                    $lineaId = (int) ($cambio['caja_movimiento_cuentacaja_id'] ?? 0);
                    $nuevaCuentaId = (int) ($cambio['cuentacaja_id'] ?? 0);

                    if ($lineaId <= 0 || $nuevaCuentaId <= 0) {
                        throw new \InvalidArgumentException('Datos de medio de pago incompletos.');
                    }

                    /** @var Caja_Movimiento_Cuentacaja|null $linea */
                    $linea = $porId->get($lineaId);
                    if ($linea === null) {
                        throw new \InvalidArgumentException('La línea de cobranza no pertenece a esta factura.');
                    }

                    $montoEnviado = $cambio['monto'] ?? null;
                    if ($montoEnviado !== null && abs((float) $montoEnviado - (float) $linea->monto) > self::TOLERANCIA_MONTO) {
                        throw new \InvalidArgumentException('No se puede modificar el monto de la factura ni de la cobranza.');
                    }

                    if ((int) $linea->cuentacaja_id === $nuevaCuentaId) {
                        continue;
                    }

                    if (! Cuentacaja::existeParaEmpresa($nuevaCuentaId, $empresaId)) {
                        throw new \InvalidArgumentException('La cuenta de caja seleccionada no es válida para la empresa.');
                    }

                    $nuevaCuenta = Cuentacaja::query()->find($nuevaCuentaId);
                    if ($nuevaCuenta === null) {
                        throw new \InvalidArgumentException('Cuenta de caja inexistente.');
                    }

                    if (GastronomiaCuentacajaSoloAutomaticaSupport::esSoloAutomatica(
                        $nuevaCuentaId,
                        (string) $nuevaCuenta->codigo,
                        $empresaId,
                    )) {
                        throw new \InvalidArgumentException(
                            GastronomiaCuentacajaSoloAutomaticaSupport::mensajeRechazoManual((string) $nuevaCuenta->codigo)
                        );
                    }

                    if ((int) $nuevaCuenta->moneda_id > 0 && (int) $nuevaCuenta->moneda_id !== (int) $linea->moneda_id) {
                        throw new \InvalidArgumentException('El medio de pago debe mantener la misma moneda.');
                    }

                    $cuentaAnterior = Cuentacaja::query()->find((int) $linea->cuentacaja_id);
                    $this->actualizarLineaYAsiento($linea, $nuevaCuenta, $cuentaAnterior);
                }

                foreach ($cobranzaMontosOriginales as $cobId => $montoOriginal) {
                    $montoActual = round((float) (Cobranza::query()->whereKey($cobId)->value('monto') ?? 0), 2);
                    if (abs($montoActual - $montoOriginal) > self::TOLERANCIA_MONTO) {
                        throw new \InvalidArgumentException('El monto de la cobranza no puede modificarse.');
                    }
                }

                $venta->refresh();
                if (abs(round((float) $venta->total, 2) - $totalFacturaOriginal) > self::TOLERANCIA_MONTO) {
                    throw new \InvalidArgumentException('El total de la factura no puede modificarse.');
                }
            });
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'No se pudo actualizar el medio de pago.'];
        }

        return ['ok' => true, 'mensaje' => 'Medio de pago actualizado correctamente.'];
    }

    private function resolverVentaEstacionamiento(int $ventaId): ?Venta
    {
        if (! VentaEstacionamientoEmision::query()->where('venta_id', $ventaId)->exists()) {
            return null;
        }

        return Venta::query()
            ->with(['puntoventas'])
            ->find($ventaId);
    }

    /**
     * @param  list<int>  $cobranzaIds
     * @return Collection<int, Caja_Movimiento_Cuentacaja>
     */
    private function lineasCuentacajaPorVenta(array $cobranzaIds): Collection
    {
        if ($cobranzaIds === []) {
            return collect();
        }

        $movimientoIds = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzaIds)
            ->pluck('id');

        return Caja_Movimiento_Cuentacaja::query()
            ->whereIn('caja_movimiento_id', $movimientoIds)
            ->get();
    }

    /**
     * @param  Collection<int, Cobranza>  $cobranzas
     * @return list<array{cobranza_id:int,monto:float,lineas:list<array{id:int,cuentacaja_id:int,codigo:string,nombre:string,monto:float,moneda_id:int,moneda:string}>}>
     */
    private function armarCobranzasPayload(Collection $cobranzas): array
    {
        $movimientos = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzas->pluck('id'))
            ->with(['caja_movimiento_cuentacajas.cuentacajas', 'caja_movimiento_cuentacajas.monedas'])
            ->get()
            ->groupBy('cobranza_id');

        $out = [];

        foreach ($cobranzas as $cob) {
            $lineas = [];
            foreach ($movimientos->get((int) $cob->id, collect()) as $mov) {
                foreach ($mov->caja_movimiento_cuentacajas as $cc) {
                    $nombre = (string) ($cc->cuentacajas->nombre ?? '');
                    $codigo = (string) ($cc->cuentacajas->codigo ?? '');
                    $lineas[] = array_merge([
                        'id' => (int) $cc->id,
                        'cuentacaja_id' => (int) $cc->cuentacaja_id,
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'monto' => round((float) $cc->monto, 2),
                        'moneda_id' => (int) ($cc->moneda_id ?? 0),
                        'moneda' => (string) ($cc->monedas->abreviatura ?? ''),
                    ], $this->presentacionCuenta($nombre, $codigo));
                }
            }

            $out[] = [
                'cobranza_id' => (int) $cob->id,
                'monto' => round((float) $cob->monto, 2),
                'lineas' => $lineas,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *   id:int,
     *   codigo:string,
     *   nombre:string,
     *   moneda_id:int,
     *   moneda_abreviatura:?string,
     *   icono:string,
     *   icono_color:string,
     *   etiqueta_boton:string
     * }>
     */
    private function listarCuentasCajaEstacionamiento(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $usoId = $this->usoCuentacajaEstacionamientoId();
        if (! $usoId) {
            return [];
        }

        $excluidas = GastronomiaCuentacajaSoloAutomaticaSupport::idsParaEmpresa($empresaId);

        return Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->when($excluidas !== [], fn ($q) => $q->whereNotIn('id', $excluidas))
            ->with('monedas:id,abreviatura,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'moneda_id'])
            ->map(function (Cuentacaja $c) {
                return array_merge([
                    'id' => (int) $c->id,
                    'codigo' => (string) $c->codigo,
                    'nombre' => (string) $c->nombre,
                    'moneda_id' => (int) ($c->moneda_id ?? 0),
                    'moneda_abreviatura' => $c->monedas->abreviatura ?? null,
                ], $this->presentacionCuenta((string) $c->nombre, (string) $c->codigo));
            })
            ->values()
            ->all();
    }

    private function usoCuentacajaEstacionamientoId(): ?int
    {
        $configured = config('estacionamiento.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Estacionamiento')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return array{icono: string, icono_color: string, etiqueta_boton: string}
     */
    private function presentacionCuenta(string $nombre, string $codigo): array
    {
        $presentacion = GastronomiaCuentacajaIconoSupport::presentacion($nombre, $codigo);

        return [
            'icono' => $presentacion['icono'],
            'icono_color' => $presentacion['color'],
            'etiqueta_boton' => $presentacion['etiqueta_boton'],
        ];
    }

    private function actualizarLineaYAsiento(
        Caja_Movimiento_Cuentacaja $linea,
        Cuentacaja $nuevaCuenta,
        ?Cuentacaja $cuentaAnterior,
    ): void {
        $cobranzaId = (int) (Caja_Movimiento::query()
            ->whereKey((int) $linea->caja_movimiento_id)
            ->value('cobranza_id') ?? 0);

        $linea->cuentacaja_id = (int) $nuevaCuenta->id;
        $linea->save();

        if ($cobranzaId <= 0) {
            return;
        }

        $contableAnterior = (int) ($cuentaAnterior?->cuentacontable_id ?? 0);
        $contableNuevo = (int) ($nuevaCuenta->cuentacontable_id ?? 0);
        if ($contableAnterior <= 0 || $contableNuevo <= 0 || $contableAnterior === $contableNuevo) {
            return;
        }

        $asientos = Asiento::query()
            ->where('cobranza_id', $cobranzaId)
            ->with('asiento_movimientos')
            ->get();

        foreach ($asientos as $asiento) {
            foreach ($asiento->asiento_movimientos as $mov) {
                if ((int) $mov->cuentacontable_id !== $contableAnterior) {
                    continue;
                }
                if (abs(abs((float) $mov->monto) - abs((float) $linea->monto)) > self::TOLERANCIA_MONTO) {
                    continue;
                }

                Asiento_Movimiento::query()
                    ->whereKey((int) $mov->id)
                    ->update(['cuentacontable_id' => $contableNuevo]);
            }
        }
    }
}
