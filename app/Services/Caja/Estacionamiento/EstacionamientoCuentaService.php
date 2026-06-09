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
            return $this->enriquecerCuentaParaApi(
                $existente->load([
                    'lineas.articulo',
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
                'lineas.articulo',
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
                'lineas.articulo',
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
                'lineas.articulo',
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
        $cuenta->loadMissing('lineas.articulo');

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
                'lineas.articulo',
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
        ?string $descripcion = null,
    ): CuentaEstacionamientoLinea {
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
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

        $articuloId = (int) ($item->articulo_id ?? 0);
        if ($articuloId <= 0) {
            throw new InvalidArgumentException(
                'El ítem «'.$item->nombre.'» no tiene artículo de stock vinculado (articulo_id). '
                .'Configure el artículo en el ABM de ítems de estacionamiento.'
            );
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
            'articulo_id' => $articuloId,
            'cantidad' => 1,
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

    public function cerrarCuenta(CuentaEstacionamiento $cuenta): void
    {
        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $cuenta->update(['estado' => CuentaEstacionamiento::ESTADO_CERRADA]);
    }

    public function marcarFacturada(CuentaEstacionamiento $cuenta, int $ventaId): void
    {
        $cuenta->update([
            'estado' => CuentaEstacionamiento::ESTADO_FACTURADA,
            'venta_id' => $ventaId,
        ]);
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
