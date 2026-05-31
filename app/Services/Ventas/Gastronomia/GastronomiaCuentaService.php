<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\MesaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GastronomiaCuentaService
{
    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
    ) {
    }
    /**
     * Configuración PV de esta terminal (empresa, ubicación, listas, puntos de venta).
     * La empresa operativa sale de este registro, no del .env.
     */
    public function resolverConfiguracionPv(?Request $request = null, ?int $empresaId = null): ?ConfiguracionPuntoventaGastronomia
    {
        $pc = GastronomiaIdentificadorPc::resolver($request);

        $query = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $pc)
            ->with(['ubicacion', 'puntoventaCae', 'puntoventaCaea', 'salidaFactura', 'listaprecio', 'depositoVenta', 'depositoInsumos', 'tipotransaccion', 'empresa']);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            return null;
        }

        if ($configs->count() > 1) {
            $empresas = $configs->pluck('empresa_id')->unique()->implode(', ');

            throw new InvalidArgumentException(
                'Hay más de una configuración PV gastronomía para identificador_pc '.$pc
                .' (empresas: '.$empresas.'). Debe existir una sola fila por terminal'
                .($empresaId !== null && $empresaId > 0 ? ' y empresa.' : '. Indique empresa si opera en varias.')
            );
        }

        return $configs->first();
    }

    /**
     * Empresas del usuario que tienen configuración PV gastronomía en la terminal indicada.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>
     */
    public function empresasConPvEnTerminal(string $pc, $empresasAsignadas)
    {
        if ($empresasAsignadas->isEmpty()) {
            return collect();
        }

        $idsConPv = ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $pc)
            ->whereIn('empresa_id', $empresasAsignadas->pluck('id'))
            ->pluck('empresa_id')
            ->unique()
            ->values();

        return $empresasAsignadas
            ->whereIn('id', $idsConPv)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresasAsignadas
     * @return \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>
     */
    public function empresasSinPvEnTerminal(string $pc, $empresasAsignadas)
    {
        $operables = $this->empresasConPvEnTerminal($pc, $empresasAsignadas);

        return $empresasAsignadas
            ->whereNotIn('id', $operables->pluck('id'))
            ->values();
    }

    /**
     * Catálogo gastronomía: artículos con prefijo SKU configurado.
     * No filtra por articulo.empresa_id (la empresa de facturación viene del PV).
     * El precio se resuelve aparte con la lista del PV ({@see ConfiguracionPuntoventaGastronomia::listaprecio_id}).
     */
    public function queryArticulosCatalogo(ConfiguracionPuntoventaGastronomia $cfg, ?string $termino = null, int $limite = 80)
    {
        $prefijo = GastronomiaSkuCatalogoSupport::prefijo();
        $digitosSufijo = GastronomiaSkuCatalogoSupport::digitosSufijo();

        $query = Articulo::query();
        GastronomiaSkuCatalogoSupport::aplicarScopeFormatoCatalogo($query, $prefijo, $digitosSufijo);

        $termino = trim((string) $termino);
        if ($termino !== '') {
            GastronomiaSkuCatalogoSupport::aplicarFiltroTerminoCatalogo($query, $termino, $prefijo, $digitosSufijo);
        }

        return $query->orderBy('sku')->limit($limite);
    }

    public function buscarArticuloCatalogoPorSku(ConfiguracionPuntoventaGastronomia $cfg, string $sku): ?Articulo
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return $this->queryArticulosCatalogo($cfg, null, 1)
            ->whereRaw('UPPER(sku) = ?', [mb_strtoupper($sku, 'UTF-8')])
            ->first(['id', 'sku', 'descripcion']);
    }

    /**
     * @return Collection<int, MesaGastronomia>
     */
    public function listarMesasUbicacion(?int $ubicacionId): Collection
    {
        if ($ubicacionId === null || $ubicacionId === 0) {
            return new Collection;
        }

        return MesaGastronomia::query()
            ->where('ubicacion_id', $ubicacionId)
            ->orderBy('numeromesa')
            ->get();
    }

    public function mesasConOcupacion(Collection $mesas): array
    {
        $ids = $mesas->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $ocupadas = CuentaGastronomia::query()
            ->whereIn('mesa_gastronomia_id', $ids)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->pluck('mesa_gastronomia_id')
            ->unique()
            ->flip();

        return $mesas->map(function (MesaGastronomia $m) use ($ocupadas) {
            return [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'numeromesa' => $m->numeromesa,
                'codigo' => $m->codigo,
                'ocupada' => $ocupadas->has($m->id),
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, CuentaGastronomia>
     */
    public function listarCuentasLibresActivasPc(?Request $request = null): Collection
    {
        $cfg = $this->resolverConfiguracionPv($request);
        if (! $cfg) {
            return new Collection;
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);

        return CuentaGastronomia::query()
            ->where('empresa_id', $cfg->empresa_id)
            ->where('tipo', CuentaGastronomia::TIPO_CUENTA)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->where('identificador_pc', $pc)
            ->orderByDesc('id')
            ->with(['lineas.articulo', 'cliente', 'mozo'])
            ->get();
    }

    /**
     * @param  array{cubiertos?: mixed, mozo_gastronomia_id?: mixed}  $datosApertura
     * @return array{cubiertos: int, mozo_gastronomia_id: ?int}
     */
    public function resolverDatosAperturaCuenta(array $datosApertura, int $empresaId): array
    {
        $defaultCubiertos = max(0, (int) config('gastronomia.cubiertos_default_al_abrir', 1));
        $cubiertosObligatorio = (bool) config('gastronomia.cubiertos_obligatorio_al_abrir', true);
        $mozoObligatorio = (bool) config('gastronomia.mozo_obligatorio_al_abrir', true);

        $cubiertosRaw = $datosApertura['cubiertos'] ?? null;
        if ($cubiertosRaw === null || $cubiertosRaw === '') {
            $cubiertos = $defaultCubiertos;
        } else {
            $cubiertos = max(0, (int) $cubiertosRaw);
            if ($cubiertos === 0 && ! $cubiertosObligatorio) {
                $cubiertos = $defaultCubiertos;
            }
        }

        if ($cubiertosObligatorio && $cubiertos <= 0) {
            throw new InvalidArgumentException('Debe indicar la cantidad de cubiertos al abrir la cuenta.');
        }

        $mozoId = null;
        if (array_key_exists('mozo_gastronomia_id', $datosApertura)) {
            $mozoRaw = $datosApertura['mozo_gastronomia_id'];
            if ($mozoRaw !== null && $mozoRaw !== '' && (int) $mozoRaw > 0) {
                $mozoId = (int) $mozoRaw;
            }
        }

        if ($mozoObligatorio && ! $mozoId) {
            throw new InvalidArgumentException('Debe indicar el mozo al abrir la cuenta.');
        }

        if ($mozoId) {
            $mozo = MozoGastronomia::query()
                ->where('id', $mozoId)
                ->where('empresa_id', $empresaId)
                ->first();
            if (! $mozo) {
                throw new InvalidArgumentException('El mozo indicado no existe para esta empresa.');
            }
        }

        return [
            'cubiertos' => $cubiertos,
            'mozo_gastronomia_id' => $mozoId,
        ];
    }

    public function validarCabeceraOperativa(CuentaGastronomia $cuenta): void
    {
        $cubiertosObligatorio = (bool) config('gastronomia.cubiertos_obligatorio_al_abrir', true);
        $mozoObligatorio = (bool) config('gastronomia.mozo_obligatorio_al_abrir', true);

        if ($cubiertosObligatorio && (int) $cuenta->cubiertos <= 0) {
            throw new InvalidArgumentException(
                'Debe indicar cubiertos en la cuenta antes de cargar consumos.'
            );
        }

        if ($mozoObligatorio && ! $cuenta->mozo_gastronomia_id) {
            throw new InvalidArgumentException(
                'Debe indicar el mozo en la cuenta antes de cargar consumos.'
            );
        }
    }

    public function abrirMesa(int $mesaId, int $empresaId, ConfiguracionPuntoventaGastronomia $cfg, array $datosApertura = []): CuentaGastronomia
    {
        $this->jornadaService->exigirJornadaSiConfigurada($empresaId);

        $mesa = MesaGastronomia::query()->where('id', $mesaId)->where('empresa_id', $empresaId)->firstOrFail();

        $exist = CuentaGastronomia::query()
            ->where('mesa_gastronomia_id', $mesaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->exists();

        if ($exist) {
            throw new InvalidArgumentException('La mesa ya tiene una cuenta abierta.');
        }

        $apertura = $this->resolverDatosAperturaCuenta($datosApertura, $empresaId);

        return CuentaGastronomia::create([
            'tipo' => CuentaGastronomia::TIPO_MESA,
            'empresa_id' => $empresaId,
            'mesa_gastronomia_id' => $mesa->id,
            'mozo_gastronomia_id' => $apertura['mozo_gastronomia_id'],
            'cubiertos' => $apertura['cubiertos'],
            'estado' => CuentaGastronomia::ESTADO_ABIERTA,
            'identificador_pc' => null,
            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
        ]);
    }

    public function abrirCuentaLibre(int $empresaId, ConfiguracionPuntoventaGastronomia $cfg, array $datosApertura = []): CuentaGastronomia
    {
        $this->jornadaService->exigirJornadaSiConfigurada($empresaId);

        $pc = GastronomiaIdentificadorPc::resolver();
        $apertura = $this->resolverDatosAperturaCuenta($datosApertura, $empresaId);

        return CuentaGastronomia::create([
            'tipo' => CuentaGastronomia::TIPO_CUENTA,
            'empresa_id' => $empresaId,
            'mesa_gastronomia_id' => null,
            'mozo_gastronomia_id' => $apertura['mozo_gastronomia_id'],
            'cubiertos' => $apertura['cubiertos'],
            'estado' => CuentaGastronomia::ESTADO_ABIERTA,
            'identificador_pc' => $pc,
            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
        ]);
    }

    public function cuentaConLineas(int $id): CuentaGastronomia
    {
        $cuenta = CuentaGastronomia::query()
            ->with([
                'lineas.articulo',
                'mesa',
                'mozo',
                'cliente',
                'descuentoGastronomia.cliente',
                'clienteInternoDescuento',
                'configuracionPuntoventa',
            ])
            ->findOrFail($id);

        return $this->enriquecerCuentaParaApi($cuenta);
    }

    /** Campos solo para JSON del POS; no existen en cuenta_gastronomia. */
    private const ATRIBUTOS_API_VIRTUALES = [
        'subtotal_estimado',
        'factura_consumidor_final',
        'receptor_factura_nombre',
    ];

    public function enriquecerCuentaParaApi(CuentaGastronomia $cuenta): CuentaGastronomia
    {
        $receptor = app(GastronomiaReceptorFacturacionService::class);
        $extras = [
            'subtotal_estimado' => $receptor->estimarSubtotalFactura($cuenta),
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

        $this->enriquecerLineasConOpcionales($cuenta);

        return $cuenta;
    }

    /**
     * Adjunta a cada línea `opcionales_detalle` con SKU/descripción del artículo
     * elegido por orden, para que el POS lo muestre debajo del consumo.
     */
    private function enriquecerLineasConOpcionales(CuentaGastronomia $cuenta): void
    {
        $lineas = $cuenta->lineas ?? null;
        if ($lineas === null || (method_exists($lineas, 'isEmpty') && $lineas->isEmpty())) {
            return;
        }

        $idsOpcionales = [];
        foreach ($lineas as $linea) {
            $map = $linea->opcionales_json;
            if (!is_array($map)) {
                continue;
            }
            foreach ($map as $articuloId) {
                $articuloId = (int) $articuloId;
                if ($articuloId > 0) {
                    $idsOpcionales[$articuloId] = true;
                }
            }
        }

        $articulosOpcionales = [];
        if (!empty($idsOpcionales)) {
            $articulosOpcionales = Articulo::query()
                ->whereIn('id', array_keys($idsOpcionales))
                ->get(['id', 'sku', 'descripcion'])
                ->keyBy('id');
        }

        foreach ($lineas as $linea) {
            $map = $linea->opcionales_json;
            if (!is_array($map) || empty($map)) {
                $linea->setAttribute('opcionales_detalle', []);
                continue;
            }

            $detalle = [];
            foreach ($map as $orden => $articuloId) {
                $articuloId = (int) $articuloId;
                if ($articuloId <= 0) {
                    continue;
                }
                $art = $articulosOpcionales[$articuloId] ?? null;
                $detalle[] = [
                    'orden' => (string) $orden,
                    'articulo_id' => $articuloId,
                    'sku' => $art->sku ?? null,
                    'descripcion' => $art->descripcion ?? null,
                ];
            }
            usort($detalle, fn ($a, $b) => strnatcmp($a['orden'], $b['orden']));
            $linea->setAttribute('opcionales_detalle', $detalle);
            if (method_exists($linea, 'syncOriginalAttribute')) {
                $linea->syncOriginalAttribute('opcionales_detalle');
            }
        }
    }

    public function cuentaConLineasSinEnriquecer(int $id): CuentaGastronomia
    {
        return CuentaGastronomia::query()
            ->with([
                'lineas.articulo',
                'mesa',
                'mozo',
                'cliente',
                'descuentoGastronomia.cliente',
                'clienteInternoDescuento',
                'configuracionPuntoventa',
            ])
            ->findOrFail($id);
    }

    public function actualizarCabecera(CuentaGastronomia $cuenta, array $datos): CuentaGastronomia
    {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        foreach (self::ATRIBUTOS_API_VIRTUALES as $virtual) {
            $cuenta->offsetUnset($virtual);
        }

        $patch = [];
        foreach (
            [
                'mozo_gastronomia_id',
                'cliente_id',
                'descuento_gastronomia_id',
                'cliente_interno_descuento_id',
                'factura_receptor_nombre',
                'factura_receptor_documento',
                'factura_receptor_domicilio',
                'factura_receptor_tipodocumento_id',
            ] as $campo
        ) {
            if (array_key_exists($campo, $datos)) {
                $valor = $datos[$campo];
                $patch[$campo] = ($valor === '' || $valor === null) ? null : $valor;
            }
        }
        if (array_key_exists('cliente_id', $patch) && $patch['cliente_id']) {
            $receptorSvc = app(GastronomiaReceptorFacturacionService::class);
            $internoDesc = array_key_exists('cliente_interno_descuento_id', $patch)
                ? (int) ($patch['cliente_interno_descuento_id'] ?? 0)
                : (int) ($cuenta->cliente_interno_descuento_id ?? 0);
            $patch['cliente_id'] = $receptorSvc->normalizarClienteIdFacturacion(
                (int) $patch['cliente_id'],
                $internoDesc,
            );
        }
        if (isset($datos['cubiertos'])) {
            $patch['cubiertos'] = (int) $datos['cubiertos'];
        }

        if (array_key_exists('descuento_gastronomia_id', $patch) && empty($patch['descuento_gastronomia_id'])) {
            $patch['cliente_interno_descuento_id'] = null;
        }

        $cuenta->fill($patch);

        $descuentoId = (int) ($cuenta->descuento_gastronomia_id ?? 0);
        $clienteInternoId = (int) ($cuenta->cliente_interno_descuento_id ?? 0);
        if ($descuentoId > 0 && $clienteInternoId <= 0) {
            throw new InvalidArgumentException(
                'Con descuento gastronomía debe indicar el cliente interno (quien invita o centro de costos). '
                .'No es el cliente de la factura.'
            );
        }

        $cuenta->save();

        return $this->enriquecerCuentaParaApi(
            $cuenta->fresh(['lineas.articulo', 'cliente', 'mozo', 'mesa', 'descuentoGastronomia.cliente', 'clienteInternoDescuento'])
        );
    }

    /**
     * @param  array<int|string, int|null>  $opcionalesPorOrden  ej. ["1" => 123, "2" => 456]
     */
    public function agregarLinea(
        CuentaGastronomia $cuenta,
        int $articuloId,
        float $cantidad,
        float $precioUnitario,
        array $opcionalesPorOrden = [],
        float $descuentoLineaPct = 0.
    ): CuentaGastronomiaLinea {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede cargar consumos en esta cuenta desde esta PC.');
        }

        $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado(
            GastronomiaIdentificadorPc::resolver(),
            (int) $cuenta->empresa_id,
        );

        $this->validarCabeceraOperativa($cuenta);

        $maxNum = (int) DB::table('cuenta_gastronomia_linea')->where('cuenta_gastronomia_id', $cuenta->id)->max('numero_linea');

        return CuentaGastronomiaLinea::create([
            'cuenta_gastronomia_id' => $cuenta->id,
            'articulo_id' => $articuloId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'descuento_linea_pct' => $descuentoLineaPct,
            'opcionales_json' => $opcionalesPorOrden === [] ? null : $opcionalesPorOrden,
            'numero_linea' => $maxNum + 1,
        ]);
    }

    public function cerrarSinFacturar(CuentaGastronomia $cuenta): void
    {
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        $cuenta->update(['estado' => CuentaGastronomia::ESTADO_CERRADA]);
    }

    public function marcarFacturada(CuentaGastronomia $cuenta, int $ventaId): void
    {
        $cuenta->update([
            'estado' => CuentaGastronomia::ESTADO_FACTURADA,
            'venta_id' => $ventaId,
        ]);
    }

    /**
     * Solo la PC creadora puede borrar líneas de cuenta libre; mesas compartidas permiten desde misma ubicación.
     */
    public function puedeEditarLineas(CuentaGastronomia $cuenta, ?string $identificadorPcActual = null): bool
    {
        $identificadorPcActual ??= GastronomiaIdentificadorPc::resolver();
        if ($cuenta->tipo === CuentaGastronomia::TIPO_MESA) {
            return true;
        }

        return (string) $cuenta->identificador_pc === $identificadorPcActual;
    }

    public function eliminarLinea(CuentaGastronomiaLinea $linea): void
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede modificar consumos de esta cuenta en esta PC.');
        }

        $linea->delete();
    }

    public function actualizarCantidadLinea(CuentaGastronomiaLinea $linea, float $cantidad): CuentaGastronomia
    {
        $cuenta = $linea->cuenta;
        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if (! $this->puedeEditarLineas($cuenta)) {
            throw new InvalidArgumentException('No puede modificar consumos de esta cuenta en esta PC.');
        }

        if ($cantidad < 0.0001) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $linea->update(['cantidad' => $cantidad]);

        return $this->cuentaConLineas($cuenta->id);
    }
}
