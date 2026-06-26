<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Corrige ventas CAEA cuyo numerocomprobante ERP quedó desfasado (p. ej. serie Rebisco en sucursal 00031).
 *
 * Prioriza el número embebido en codigo y asigna correlativos ERP libres para el resto.
 * Opcionalmente sincroniza ven_nro en Anita (réplica) cuando el número grabado allí difiere.
 */
final class GastronomiaCaeaCorregirNumeracionDesfasadaService
{
    /**
     * @return array<string, mixed>
     */
    public function preview(
        int $puntoventaId,
        int $empresaId,
        int $tipotransaccionId = 1,
        int $umbralDesfasaje = 100_000,
    ): array {
        return $this->planificar($puntoventaId, $empresaId, $tipotransaccionId, $umbralDesfasaje);
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        int $puntoventaId,
        int $empresaId,
        int $tipotransaccionId = 1,
        int $umbralDesfasaje = 100_000,
        bool $dryRun = true,
        bool $actualizarAnita = true,
    ): array {
        $plan = $this->planificar($puntoventaId, $empresaId, $tipotransaccionId, $umbralDesfasaje);
        if ($plan['correcciones'] === []) {
            return $plan + ['aplicadas' => 0, 'dry_run' => $dryRun];
        }

        if ($dryRun) {
            return $plan + ['aplicadas' => 0, 'dry_run' => true];
        }

        $contexto = $plan['contexto'];
        $aplicadas = 0;

        DB::transaction(function () use ($plan, $contexto, $actualizarAnita, &$aplicadas): void {
            foreach ($plan['correcciones'] as $corr) {
                $this->aplicarCorreccion($corr, $contexto, $actualizarAnita);
                $aplicadas++;
            }
        });

        return $plan + ['aplicadas' => $aplicadas, 'dry_run' => false];
    }

    /**
     * @return array<string, mixed>
     */
    private function planificar(
        int $puntoventaId,
        int $empresaId,
        int $tipotransaccionId,
        int $umbralDesfasaje,
    ): array {
        $pv = $this->resolverPuntoventa($puntoventaId, $empresaId);
        $tipo = Tipotransaccion::query()->find($tipotransaccionId);
        if ($tipo === null) {
            throw new InvalidArgumentException('Tipo de transacción '.$tipotransaccionId.' inexistente.');
        }

        $empresa = Empresa::query()->findOrFail($empresaId);
        $tipoAnita = CaeaEmisionNumeracionSupport::tipoAnitaDesdeTipotransaccion($tipo);
        $letra = 'B';
        $sucursal = (string) $pv->codigo;

        $maxNumeracionErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            (int) $pv->id,
            (int) ($tipo->codigo ?? 0),
            $letra,
            $empresaId,
        );

        $codigoAfipObjetivo = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            (int) ($tipo->codigo ?? 0),
            $letra,
        );

        $ventas = Venta::query()
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->where('venta.puntoventa_id', $pv->id)
            ->whereHas('puntoventas', static fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNull('venta.deleted_at')
            ->select(['venta.*', 'tt.codigo as tt_codigo'])
            ->orderBy('venta.fecha')
            ->orderBy('venta.id')
            ->get()
            ->filter(static function (Venta $venta) use ($codigoAfipObjetivo): bool {
                return TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                    (int) ($venta->tt_codigo ?? 0),
                    (string) ($venta->codigo ?? ''),
                ) === $codigoAfipObjetivo;
            })
            ->values();

        $ocupados = [];
        foreach ($ventas as $venta) {
            $ocupados[(int) $venta->numerocomprobante] = (int) $venta->id;
        }

        $maxCorrelativoValido = (int) $ventas
            ->filter(static fn (Venta $v) => (int) $v->numerocomprobante < $umbralDesfasaje)
            ->max('numerocomprobante');

        $siguienteLibre = $maxCorrelativoValido;
        $correcciones = [];

        foreach ($ventas as $venta) {
            $numeroErp = (int) $venta->numerocomprobante;
            $numeroCodigo = VentaNumeracionEmpresaSupport::numeroDesdeCodigoVenta($venta->codigo);

            if (! $this->ventaDesfasada($numeroErp, $numeroCodigo, $umbralDesfasaje)) {
                continue;
            }

            $nuevo = $this->resolverNumeroDestino(
                $venta->id,
                $numeroErp,
                $numeroCodigo,
                $umbralDesfasaje,
                $ocupados,
                $siguienteLibre,
            );
            $siguienteLibre = max($siguienteLibre, $nuevo);

            $correcciones[] = [
                'venta_id' => (int) $venta->id,
                'fecha' => (string) $venta->fecha,
                'leyenda' => (string) ($venta->leyenda ?? ''),
                'numero_erp_actual' => $numeroErp,
                'numero_codigo_actual' => $numeroCodigo,
                'numero_nuevo' => $nuevo,
                'codigo_nuevo' => VentaNumeracionEmpresaSupport::formatearCodigoVenta(
                    $tipoAnita,
                    $letra,
                    $sucursal,
                    $nuevo,
                ),
                'factura_nueva' => VentaNumeracionEmpresaSupport::etiquetaFactura(
                    $tipoAnita,
                    $letra,
                    $sucursal,
                    $nuevo,
                ),
                'anita_nro_origen' => $this->resolverNroAnitaOrigen(
                    $tipoAnita,
                    $letra,
                    $sucursal,
                    (string) $empresa->codigo,
                    (string) ($pv->modofacturacion ?? ''),
                    $numeroErp,
                    $numeroCodigo,
                ),
            ];

            unset($ocupados[$numeroErp]);
            $ocupados[$nuevo] = (int) $venta->id;
        }

        return [
            'ok' => true,
            'contexto' => [
                'puntoventa_id' => (int) $pv->id,
                'puntoventa_codigo' => $sucursal,
                'empresa_id' => $empresaId,
                'empresa_codigo' => (string) $empresa->codigo,
                'tipotransaccion_id' => $tipotransaccionId,
                'tipo_anita' => $tipoAnita,
                'letra' => $letra,
                'modofacturacion' => (string) ($pv->modofacturacion ?? ''),
            ],
            'max_numeracion_erp' => $maxNumeracionErp,
            'ultimo_compemis' => $maxNumeracionErp,
            'max_correlativo_valido' => $maxCorrelativoValido,
            'cantidad_ventas' => $ventas->count(),
            'correcciones' => $correcciones,
        ];
    }

    private function ventaDesfasada(int $numeroErp, int $numeroCodigo, int $umbralDesfasaje): bool
    {
        if ($numeroErp >= $umbralDesfasaje) {
            return true;
        }

        return $numeroCodigo > 0 && $numeroCodigo !== $numeroErp;
    }

    /**
     * @param  array<int, int>  $ocupados
     */
    private function resolverNumeroDestino(
        int $ventaId,
        int $numeroErp,
        int $numeroCodigo,
        int $umbralDesfasaje,
        array &$ocupados,
        int $siguienteLibre,
    ): int {
        if (
            $numeroCodigo > 0
            && $numeroCodigo < $umbralDesfasaje
            && (! isset($ocupados[$numeroCodigo]) || $ocupados[$numeroCodigo] === $ventaId)
        ) {
            return $numeroCodigo;
        }

        $candidato = max($siguienteLibre, 1);
        while (isset($ocupados[$candidato]) && $ocupados[$candidato] !== $ventaId) {
            $candidato++;
        }

        return $candidato;
    }

    private function resolverNroAnitaOrigen(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        string $empresaCodigo,
        string $modoFacturacion,
        int $numeroErp,
        int $numeroCodigo,
    ): int {
        if ($numeroCodigo > 0 && $numeroCodigo !== $numeroErp) {
            return $numeroCodigo;
        }

        if ($this->existeVentaAnita($tipoAnita, $letra, $sucursal, $empresaCodigo, $modoFacturacion, $numeroErp)) {
            return $numeroErp;
        }

        if ($numeroCodigo > 0 && $this->existeVentaAnita($tipoAnita, $letra, $sucursal, $empresaCodigo, $modoFacturacion, $numeroCodigo)) {
            return $numeroCodigo;
        }

        return $numeroErp;
    }

    /**
     * @param  array<string, mixed>  $corr
     * @param  array<string, mixed>  $contexto
     */
    private function aplicarCorreccion(array $corr, array $contexto, bool $actualizarAnita): void
    {
        $ventaId = (int) $corr['venta_id'];
        $nuevo = (int) $corr['numero_nuevo'];
        $viejoErp = (int) $corr['numero_erp_actual'];
        $anitaOrigen = (int) $corr['anita_nro_origen'];

        Venta::query()->whereKey($ventaId)->update([
            'numerocomprobante' => $nuevo,
            'codigo' => (string) $corr['codigo_nuevo'],
        ]);

        $this->actualizarSnapshotsFacturaProceso($ventaId, (string) $corr['factura_nueva']);

        if ($actualizarAnita && $anitaOrigen !== $nuevo) {
            $this->actualizarNroComprobanteAnita(
                (string) $contexto['tipo_anita'],
                (string) $contexto['letra'],
                (string) $contexto['puntoventa_codigo'],
                (string) $contexto['empresa_codigo'],
                (string) ($contexto['modofacturacion'] ?? ''),
                $anitaOrigen,
                $nuevo,
            );
        }
    }

    private function actualizarSnapshotsFacturaProceso(int $ventaId, string $facturaNueva): void
    {
        $snapshots = GastronomiaCierreJornadaProcesoSnapshot::query()->get();
        foreach ($snapshots as $snapshot) {
            $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
            $cambiado = false;

            foreach (['factura_proceso_emision', 'factura_proceso_emision_recuperacion'] as $clave) {
                if (! isset($payload[$clave]) || ! is_array($payload[$clave])) {
                    continue;
                }
                $emision = $payload[$clave];
                foreach ($emision['facturas'] ?? [] as $idx => $fac) {
                    if (! is_array($fac) || (int) ($fac['venta_id'] ?? 0) !== $ventaId) {
                        continue;
                    }
                    $emision['facturas'][$idx]['factura'] = $facturaNueva;
                    $emision['facturas'][$idx]['numerocomprobante_forzado'] = VentaNumeracionEmpresaSupport::numeroDesdeCodigoVenta($facturaNueva);
                    $cambiado = true;
                }
                if ($cambiado) {
                    $payload[$clave] = $emision;
                }
            }

            if ($cambiado) {
                $snapshot->payload = $payload;
                $snapshot->save();
            }
        }
    }

    private function resolverPuntoventa(int $puntoventaId, int $empresaId): Puntoventa
    {
        $pv = Puntoventa::query()->whereKey($puntoventaId)->where('empresa_id', $empresaId)->first();
        if ($pv === null) {
            throw new InvalidArgumentException(
                'Punto de venta '.$puntoventaId.' no pertenece a empresa '.$empresaId.'.',
            );
        }

        if (($pv->modofacturacion ?? '') !== 'A') {
            throw new InvalidArgumentException('El PV '.$puntoventaId.' no es CAEA (mod A).');
        }

        return $pv;
    }

    private function existeVentaAnita(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        string $empresaCodigo,
        string $modoFacturacion,
        int $numero,
    ): bool {
        if ($numero <= 0) {
            return false;
        }

        $tipoVenta = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            $tipoAnita,
            $sucursal,
            $empresaCodigo,
            $modoFacturacion,
        );

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => 'ven_nro',
            'whereArmado' => " WHERE ven_tipo = '".$tipoVenta."' AND
                                    ven_letra = '".$letra."' AND
                                    ven_sucursal = '".$sucursal."' AND
                                    ven_nro = '".$numero."'
            ",
        ];
        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCallEscritura($data));

        return $fila !== null && isset($fila->ven_nro);
    }

    private function actualizarNroComprobanteAnita(
        string $tipoNumerador,
        string $letra,
        string $sucursal,
        string $empresaCodigo,
        string $modoFacturacion,
        int $nroAnitaActual,
        int $nroNuevo,
    ): void {
        if ($nroAnitaActual <= 0 || $nroNuevo <= 0 || $nroAnitaActual === $nroNuevo) {
            return;
        }

        $tipoVenta = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            $tipoNumerador,
            $sucursal,
            $empresaCodigo,
            $modoFacturacion,
        );

        $actualizaciones = [
            ['venta', 'ventas', 'ven', $tipoVenta],
            ['vencae', 'ventas', 'venc', $tipoNumerador],
            ['veng', 'ventas', 'veng', $tipoNumerador],
            ['subdiario', 'contab', 'subd', $tipoNumerador],
        ];

        foreach ($actualizaciones as [$tabla, $sistema, $prefijo, $tipo]) {
            $this->actualizarCampoNroAnita(
                $tabla,
                $sistema,
                $prefijo,
                $tipo,
                $letra,
                $sucursal,
                $nroAnitaActual,
                $nroNuevo,
                '',
                $tabla !== 'venta',
            );
        }

        $this->actualizarCampoNroAnita(
            'ctamov',
            'contab',
            'ctav',
            $tipoNumerador,
            $letra,
            $sucursal,
            $nroAnitaActual,
            $nroNuevo,
            " AND ctav_empresa='".$empresaCodigo."' ",
            true,
        );
    }

    private function actualizarCampoNroAnita(
        string $tabla,
        string $sistema,
        string $prefijo,
        string $tipo,
        string $letra,
        string $sucursal,
        int $nroActual,
        int $nroNuevo,
        string $whereExtra = '',
        bool $omitirSiFalla = false,
    ): void {
        $campoNro = $prefijo.'_nro';
        $campoTipo = $prefijo.'_tipo';
        $campoLetra = $prefijo.'_letra';
        $campoSucursal = $prefijo.'_sucursal';

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'valores' => $campoNro." = '".$nroNuevo."' ",
            'whereArmado' => ' WHERE '.$campoTipo." = '".$tipo."' AND "
                .$campoLetra." = '".$letra."' AND "
                .$campoSucursal." = '".$sucursal."' AND "
                .$campoNro." = '".$nroActual."' "
                .$whereExtra,
        ];

        try {
            $respuesta = $apiAnita->apiCallEscritura($data, $tabla.' update', 'gastronomia.caea_corregir_numeracion.anita');
            $error = ApiAnita::extraerMensajeError($respuesta);
            if ($error !== null) {
                throw new \RuntimeException($error);
            }
        } catch (\Throwable $e) {
            if ($omitirSiFalla) {
                Log::warning('gastronomia.caea_corregir_numeracion.anita_omitido', [
                    'tabla' => $tabla,
                    'nro_actual' => $nroActual,
                    'nro_nuevo' => $nroNuevo,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            throw new InvalidArgumentException(
                'Anita update '.$tabla.' '.$nroActual.'→'.$nroNuevo.': '.$e->getMessage(),
                0,
                $e,
            );
        }
    }
}
