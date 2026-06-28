<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamientoLinea;
use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Models\Ventas\Cliente;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoListaPrecioResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EstacionamientoCuentaService
{
    public function __construct(
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
        private readonly EstacionamientoPvService $pvService,
        private readonly EstacionamientoListaPrecioResolver $listaPrecioResolver,
    ) {
    }

    public function resolverConfiguracionPv(?Request $request = null, ?int $empresaId = null): ?ConfiguracionPuntoventaEstacionamiento
    {
        return $this->pvService->resolverConfiguracionPv($request, $empresaId);
    }

    public function obtenerOCrearCuentaActiva(
        ConfiguracionPuntoventaEstacionamiento $cfg,
        ?Request $request = null,
    ): CuentaEstacionamiento {
        $this->jornadaService->exigirJornadaSiConfigurada((int) $cfg->empresa_id);

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $empresaId = (int) $cfg->empresa_id;

        $existente = CuentaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $pc)
            ->where('estado', CuentaEstacionamiento::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->first();

        if ($existente) {
            $this->sincronizarContextoOperativoCuentaAbierta($existente, $empresaId, $pc);

            return $this->enriquecerCuentaParaApi(
                $existente->fresh()->load([
                    'lineas.itemEstacionamiento',
                    'lineas.itemEstacionamiento',
                    'cliente',
                    'categoriaAutomovil',
                    'descuentoEstacionamiento.cliente',
                    'clienteInternoDescuento',
                    'configuracionPuntoventa',
                ])
            );
        }

        $jornada = config('estacionamiento.jornada_obligatoria', true)
            ? $this->jornadaService->jornadaAbierta($empresaId)
            : null;

        $turno = EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()
            ? $this->turnoOperativoService->turnoHabilitadoEnPc($pc)
            : null;

        $cuenta = CuentaEstacionamiento::create([
            'empresa_id' => $empresaId,
            'identificador_pc' => $pc,
            'estado' => CuentaEstacionamiento::ESTADO_ABIERTA,
            'configuracion_puntoventa_estacionamiento_id' => $cfg->id,
            'jornada_estacionamiento_id' => $jornada?->id,
            'turno_operativo_estacionamiento_id' => $turno?->id,
        ]);

        return $this->enriquecerCuentaParaApi(
            $cuenta->load([
                'lineas.itemEstacionamiento',
                'lineas.itemEstacionamiento',
                'cliente',
                'categoriaAutomovil',
                'descuentoEstacionamiento.cliente',
                'clienteInternoDescuento',
                'configuracionPuntoventa',
            ])
        );
    }

    public function cuentaConLineas(int $id): CuentaEstacionamiento
    {
        $cuenta = CuentaEstacionamiento::query()
            ->with([
                'lineas.itemEstacionamiento',
                'lineas.itemEstacionamiento',
                'cliente',
                'categoriaAutomovil',
                'descuentoEstacionamiento.cliente',
                'clienteInternoDescuento',
                'configuracionPuntoventa',
            ])
            ->findOrFail($id);

        return $this->enriquecerCuentaParaApi($cuenta);
    }

    public function cuentaConLineasSinEnriquecer(int $id): CuentaEstacionamiento
    {
        return CuentaEstacionamiento::query()
            ->with([
                'lineas.itemEstacionamiento',
                'lineas.itemEstacionamiento',
                'cliente',
                'categoriaAutomovil',
                'descuentoEstacionamiento.cliente',
                'clienteInternoDescuento',
                'configuracionPuntoventa',
            ])
            ->findOrFail($id);
    }

    /** Campos solo para JSON del POS; no existen en cuenta_estacionamiento. */
    private const ATRIBUTOS_API_VIRTUALES = [
        'subtotal_estimado',
        'total_facturar_ars',
        'sin_cobranza',
        'factura_cortesia',
        'factura_consumidor_final',
        'receptor_factura_nombre',
    ];

    public function enriquecerCuentaParaApi(CuentaEstacionamiento $cuenta): CuentaEstacionamiento
    {
        $cuenta->loadMissing('lineas.itemEstacionamiento');

        $receptor = app(EstacionamientoReceptorFacturacionService::class);
        $preview = app(EstacionamientoFacturaEmisionService::class)->previewTotalesParaCuenta($cuenta);
        $extras = [
            'subtotal_estimado' => $receptor->estimarSubtotalFactura($cuenta),
            'total_facturar_ars' => (float) ($preview['total'] ?? 0),
            'sin_cobranza' => ! empty($preview['sin_cobranza']),
            'factura_cortesia' => ! empty($preview['factura_cortesia']),
            'factura_consumidor_final' => $receptor->facturaComoConsumidorFinal($cuenta),
        ];
        $extras['receptor_factura_nombre'] = $extras['factura_consumidor_final']
            ? $receptor->nombreConsumidorFinalFactura()
            : ($cuenta->cliente->nombre ?? null);

        foreach ($extras as $key => $value) {
            $cuenta->setAttribute($key, $value);
            if (method_exists($cuenta, 'syncOriginalAttribute')) {
                $cuenta->syncOriginalAttribute($key);
            }
        }

        return $cuenta;
    }

    public function validarCabeceraOperativa(CuentaEstacionamiento $cuenta): void
    {
        if ((int) ($cuenta->categoria_automovil_estacionamiento_id ?? 0) <= 0) {
            throw new InvalidArgumentException(
                'Debe indicar la categoría del vehículo antes de cargar ítems.'
            );
        }
    }

    public function actualizarCabecera(CuentaEstacionamiento $cuenta, array $datos): CuentaEstacionamiento
    {
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        foreach (self::ATRIBUTOS_API_VIRTUALES as $virtual) {
            $cuenta->offsetUnset($virtual);
        }

        $patch = [];
        foreach (
            [
                'categoria_automovil_estacionamiento_id',
                'patente',
                'cliente_id',
                'descuento_estacionamiento_id',
                'cliente_interno_descuento_id',
                'factura_receptor_nombre',
                'factura_receptor_documento',
                'factura_receptor_domicilio',
                'factura_receptor_tipodocumento_id',
                'ticket_estacionamiento_id',
            ] as $campo
        ) {
            if (array_key_exists($campo, $datos)) {
                $valor = $datos[$campo];
                $patch[$campo] = ($valor === '' || $valor === null) ? null : $valor;
            }
        }

        if (array_key_exists('cliente_id', $patch) && $patch['cliente_id']) {
            $receptorSvc = app(EstacionamientoReceptorFacturacionService::class);
            $internoDesc = array_key_exists('cliente_interno_descuento_id', $patch)
                ? (int) ($patch['cliente_interno_descuento_id'] ?? 0)
                : (int) ($cuenta->cliente_interno_descuento_id ?? 0);
            $patch['cliente_id'] = $receptorSvc->normalizarClienteIdFacturacion(
                (int) $patch['cliente_id'],
                $internoDesc,
            );
        }

        if (array_key_exists('descuento_estacionamiento_id', $patch) && empty($patch['descuento_estacionamiento_id'])) {
            $patch['cliente_interno_descuento_id'] = null;
        }

        if (array_key_exists('descuento_estacionamiento_id', $patch) && ! empty($patch['descuento_estacionamiento_id'])) {
            if (empty($patch['cliente_interno_descuento_id']) && empty($cuenta->cliente_interno_descuento_id)) {
                $clienteDesc = $this->resolverClienteInternoDescuentoPorDefecto();
                if ($clienteDesc) {
                    $patch['cliente_interno_descuento_id'] = $clienteDesc;
                }
            }
        }

        $cuenta->fill($patch);

        $descuentoId = (int) ($cuenta->descuento_estacionamiento_id ?? 0);
        $clienteInternoId = (int) ($cuenta->cliente_interno_descuento_id ?? 0);
        if ($descuentoId > 0 && $clienteInternoId <= 0) {
            throw new InvalidArgumentException(
                'Con descuento estacionamiento debe indicar el cliente interno (quien invita o centro de costos). '
                .'No es el cliente de la factura.'
            );
        }

        $cuenta->save();

        return $this->enriquecerCuentaParaApi(
            $cuenta->fresh([
                'lineas.itemEstacionamiento',
                'lineas.itemEstacionamiento',
                'cliente',
                'categoriaAutomovil',
                'descuentoEstacionamiento.cliente',
                'clienteInternoDescuento',
            ])
        );
    }

    public function agregarLinea(
        CuentaEstacionamiento $cuenta,
        int $itemEstacionamientoId,
        float $cantidad = 1,
        ?string $descripcion = null,
    ): CuentaEstacionamientoLinea {
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if ($cantidad <= 0) {
            throw new InvalidArgumentException('Cantidad inválida.');
        }

        $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado(
            EstacionamientoIdentificadorPc::resolver(),
            (int) $cuenta->empresa_id,
        );

        $this->validarCabeceraOperativa($cuenta);

        $item = ItemEstacionamiento::query()
            ->where('id', $itemEstacionamientoId)
            ->where('empresa_id', (int) $cuenta->empresa_id)
            ->where('estado', ItemEstacionamiento::ESTADO_ACTIVO)
            ->first();

        if (! $item) {
            throw new InvalidArgumentException('El ítem de estacionamiento no existe o no está activo.');
        }

        $precioData = $this->listaPrecioResolver->precioItem(
            (int) $cuenta->empresa_id,
            (int) $cuenta->categoria_automovil_estacionamiento_id,
            $itemEstacionamientoId,
        );

        $maxNum = (int) DB::table('cuenta_estacionamiento_linea')
            ->where('cuenta_estacionamiento_id', $cuenta->id)
            ->max('numero_linea');

        return CuentaEstacionamientoLinea::create([
            'cuenta_estacionamiento_id' => $cuenta->id,
            'numero_linea' => $maxNum + 1,
            'item_estacionamiento_id' => $itemEstacionamientoId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioData['precio'],
            'descripcion' => $descripcion !== null && trim($descripcion) !== ''
                ? trim($descripcion)
                : $item->nombre,
            'lista_precio_estacionamiento_item_id' => $precioData['lista_precio_estacionamiento_item_id'],
        ]);
    }

    public function eliminarLinea(CuentaEstacionamientoLinea $linea): void
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $linea->delete();
    }

    public function actualizarCantidadLinea(CuentaEstacionamientoLinea $linea, float $cantidad): CuentaEstacionamiento
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado(
            EstacionamientoIdentificadorPc::resolver(),
            (int) $cuenta->empresa_id,
        );

        if ($cantidad < 0.0001) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $linea->update(['cantidad' => $cantidad]);

        return $this->cuentaConLineas((int) $cuenta->id);
    }

    public function cerrarSinFacturar(CuentaEstacionamiento $cuenta): void
    {
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $cuenta->update(['estado' => CuentaEstacionamiento::ESTADO_CERRADA]);
    }

    /** @see cerrarSinFacturar */
    public function cerrarCuenta(CuentaEstacionamiento $cuenta): void
    {
        $this->cerrarSinFacturar($cuenta);
    }

    public function marcarFacturada(
        CuentaEstacionamiento $cuenta,
        int $ventaId,
        ?int $turnoOperativoEstacionamientoId = null,
    ): void {
        $patch = [
            'estado' => CuentaEstacionamiento::ESTADO_FACTURADA,
            'venta_id' => $ventaId,
        ];
        if ($turnoOperativoEstacionamientoId !== null && $turnoOperativoEstacionamientoId > 0) {
            $patch['turno_operativo_estacionamiento_id'] = $turnoOperativoEstacionamientoId;
        }

        $cuenta->update($patch);
    }

    /**
     * Si la cuenta quedó abierta de un turno anterior, al retomarla en el POS
     * debe reflejar jornada y turno habilitado actuales (evita operador/turno stale).
     */
    private function sincronizarContextoOperativoCuentaAbierta(
        CuentaEstacionamiento $cuenta,
        int $empresaId,
        string $pc,
    ): void {
        $patch = [];

        if (config('estacionamiento.jornada_obligatoria', true)) {
            $jornada = $this->jornadaService->jornadaAbierta($empresaId);
            if ($jornada !== null && (int) ($cuenta->jornada_estacionamiento_id ?? 0) !== (int) $jornada->id) {
                $patch['jornada_estacionamiento_id'] = (int) $jornada->id;
            }
        }

        if (EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
            $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
            if ($turno !== null && (int) ($cuenta->turno_operativo_estacionamiento_id ?? 0) !== (int) $turno->id) {
                $patch['turno_operativo_estacionamiento_id'] = (int) $turno->id;
            }
        }

        if ($patch !== []) {
            $cuenta->update($patch);
        }
    }

    private function resolverClienteInternoDescuentoPorDefecto(): ?int
    {
        $codigo = trim((string) config('estacionamiento.cliente_descuento_codigo', '501'));
        if ($codigo === '') {
            return null;
        }

        $id = (int) (Cliente::query()->where('codigo', $codigo)->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
