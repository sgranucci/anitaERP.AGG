<?php

namespace App\Services\Ventas\GastronomiaCrown;

use App\Models\Stock\Articulo;
use App\Models\Stock\CategoriaAreaComanda;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\SubcategoriaAreaComanda;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComanda;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComandaEvento;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComandaLinea;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuentaEvento;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuentaLinea;
use App\Models\Ventas\MesaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Support\Stock\FormulaArticuloSubformulaPosSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownKdsPreparaSupport;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPrintSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaSalaSupport;
use App\Support\Ventas\Vianda\ViandaPrecioSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GastronomiaCrownPosService
{
    public function __construct(
        private GastronomiaCuentaService $catalogoService,
        private GastronomiaCrownFacturaEmisionService $facturaEmisionService,
        private GastronomiaJornadaService $jornadaService,
    ) {
    }

    /**
     * Bloquea operación del POS Crown si no hay jornada abierta (cuando jornada_obligatoria=true).
     */
    public function exigirJornadaOperativa(ConfiguracionPuntoventaGastronomia $cfg): void
    {
        if (! filter_var(config('gastronomia.jornada_obligatoria', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $salaId = $cfg->sala_id !== null ? (int) $cfg->sala_id : null;
        $this->jornadaService->exigirJornadaAbierta((int) $cfg->empresa_id, $salaId > 0 ? $salaId : null);
    }

    public function resolverConfig(Request $request): ?ConfiguracionPuntoventaGastronomia
    {
        $idPc = GastronomiaIdentificadorPc::resolver($request);

        return ConfiguracionPuntoventaGastronomia::query()
            ->where('identificador_pc', $idPc)
            ->first();
    }

    public function abrirMesa(
        ConfiguracionPuntoventaGastronomia $cfg,
        int $mesaId,
        array $extra = [],
    ): GastroCrownCuenta {
        $this->exigirJornadaOperativa($cfg);

        $mesa = MesaGastronomia::query()
            ->where('id', $mesaId)
            ->where('empresa_id', $cfg->empresa_id)
            ->first();
        if (! $mesa) {
            throw new InvalidArgumentException('Mesa no encontrada para la empresa.');
        }
        if ($cfg->ubicacion_id && (int) $mesa->ubicacion_id !== (int) $cfg->ubicacion_id) {
            throw new InvalidArgumentException('La mesa no pertenece a la ubicación de este punto de venta.');
        }

        $existente = GastroCrownCuenta::query()
            ->where('mesa_gastronomia_id', $mesaId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->first();
        if ($existente) {
            return $existente->load(['lineas', 'mesa', 'mozo']);
        }

        return DB::transaction(function () use ($cfg, $mesa, $extra) {
            $cuenta = GastroCrownCuenta::query()->create([
                'empresa_id' => $cfg->empresa_id,
                'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                'identificador_pc' => $cfg->identificador_pc,
                'modo_pos' => $cfg->modoPosEfectivo(),
                'sala_id' => $cfg->sala_id ?: $mesa->sala_id,
                'mesa_gastronomia_id' => $mesa->id,
                'mozo_gastronomia_id' => $extra['mozo_gastronomia_id'] ?? null,
                'cubiertos' => max(0, (int) ($extra['cubiertos'] ?? ($cfg->pide_cubiertos ? 1 : 0))),
                'estado' => GastroCrownCuenta::ESTADO_ABIERTA,
                'listaprecio_id' => $cfg->listaprecio_id,
                'usuario_apertura_id' => Auth::id(),
            ]);
            $this->eventoCuenta($cuenta, 'open', 'Apertura mesa '.$mesa->codigo);

            return $cuenta->load(['lineas', 'mesa', 'mozo']);
        });
    }

    public function abrirCuentaLibre(
        ConfiguracionPuntoventaGastronomia $cfg,
        array $extra = [],
    ): GastroCrownCuenta {
        $this->exigirJornadaOperativa($cfg);

        return DB::transaction(function () use ($cfg, $extra) {
            $cuenta = GastroCrownCuenta::query()->create([
                'empresa_id' => $cfg->empresa_id,
                'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                'identificador_pc' => $cfg->identificador_pc,
                'modo_pos' => $cfg->modoPosEfectivo() ?: GastronomiaCrownPosModos::SALA,
                'sala_id' => $cfg->sala_id,
                'mozo_gastronomia_id' => $extra['mozo_gastronomia_id'] ?? null,
                'cubiertos' => max(0, (int) ($extra['cubiertos'] ?? 1)),
                'estado' => GastroCrownCuenta::ESTADO_ABIERTA,
                'listaprecio_id' => $cfg->listaprecio_id,
                'usuario_apertura_id' => Auth::id(),
            ]);
            $this->eventoCuenta($cuenta, 'open', 'Apertura cuenta libre');

            return $cuenta->load(['lineas', 'mesa', 'mozo']);
        });
    }

    public function cuentaAbierta(
        int $cuentaId,
        int $empresaId,
        ?ConfiguracionPuntoventaGastronomia $cfg = null,
    ): GastroCrownCuenta {
        $cuenta = GastroCrownCuenta::query()
            ->with(['lineas.areaComanda', 'mesa', 'mozo', 'comandas'])
            ->where('id', $cuentaId)
            ->where('empresa_id', $empresaId)
            ->first();
        if (! $cuenta) {
            throw new InvalidArgumentException('Cuenta no encontrada.');
        }
        if (! in_array($cuenta->estado, [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO], true)) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        if ($cfg) {
            $this->alinearListaPrecioConPuestoSiCorresponde($cuenta, $cfg);
        }

        return $cuenta;
    }

    /**
     * Si el puesto cambió de lista después de abrir la cuenta y el operador no eligió
     * otra (herramienta Lista / hotel), alinea la cuenta a la lista vigente del PV.
     * Evita tickear contra una lista vieja (ej. B-CARRO) tras corregir el puesto a A-PUBLICO.
     */
    public function alinearListaPrecioConPuestoSiCorresponde(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
    ): void {
        $listaPuesto = (int) ($cfg->listaprecio_id ?: 0);
        if ($listaPuesto <= 0) {
            return;
        }
        $listaCuenta = (int) ($cuenta->listaprecio_id ?: 0);
        if ($listaCuenta === $listaPuesto) {
            return;
        }

        $listaHotel = (int) ($cfg->listaprecio_hotel_id ?: 0);
        if ($listaHotel > 0
            && $listaCuenta === $listaHotel
            && trim((string) ($cuenta->habitacion ?? '')) !== '') {
            return;
        }

        $cambioManual = GastroCrownCuentaEvento::query()
            ->where('gastro_crown_cuenta_id', $cuenta->id)
            ->where('evento', 'lista_precio')
            ->exists();
        if ($cambioManual) {
            return;
        }

        $anterior = $listaCuenta;
        $cuenta->listaprecio_id = $listaPuesto;
        $cuenta->save();
        // Evento distinto a lista_precio (herramienta): no bloquea realineaciones futuras del puesto.
        $this->eventoCuenta(
            $cuenta,
            'lista_precio_puesto',
            'Alineada con puesto #'.$listaPuesto
            .($anterior > 0 ? ' (antes #'.$anterior.')' : '')
        );
    }

    /**
     * Artículo del catálogo del PV por SKU (fallback: SKU exacto en el maestro).
     */
    public function resolverArticuloPorSku(ConfiguracionPuntoventaGastronomia $cfg, string $sku): ?Articulo
    {
        $skuIngresado = trim($sku);
        $sku = $this->normalizarSkuCatalogo($skuIngresado);
        $articulo = $this->catalogoService->buscarArticuloCatalogoPorSku($cfg, $sku);
        if ($articulo) {
            return $articulo;
        }

        $articulo = Articulo::query()
            ->whereRaw('UPPER(sku) = ?', [mb_strtoupper($sku, 'UTF-8')])
            ->first();
        if ($articulo) {
            return $articulo;
        }

        // Crown / SKU numérico: si el armado AGG (V+dígitos) no matchea, probar el código tal cual.
        if ($sku !== $skuIngresado && $skuIngresado !== '') {
            $articulo = $this->catalogoService->buscarArticuloCatalogoPorSku($cfg, $skuIngresado);
            if ($articulo) {
                return $articulo;
            }

            return Articulo::query()
                ->whereRaw('UPPER(sku) = ?', [mb_strtoupper($skuIngresado, 'UTF-8')])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string|int, mixed>|null  $opcionales  orden => selección de opcional de fórmula
     */
    public function agregarLineaPorSku(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $sku,
        float $cantidad = 1.0,
        ?string $observacion = null,
        ?int $areaComandaId = null,
        ?bool $prepara = null,
        ?array $opcionales = null,
    ): GastroCrownCuentaLinea {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('Cantidad inválida.');
        }

        $articulo = $this->resolverArticuloPorSku($cfg, $sku);
        if (! $articulo) {
            throw new InvalidArgumentException('Artículo no encontrado: '.$sku);
        }

        $noFacturable = ArticuloSeleccionOperativaSupport::mensajeNoFacturable($articulo);
        if ($noFacturable !== null) {
            throw new InvalidArgumentException($noFacturable);
        }

        $this->alinearListaPrecioConPuestoSiCorresponde($cuenta, $cfg);
        $listaId = (int) ($cuenta->listaprecio_id ?: $cfg->listaprecio_id);
        if ($listaId <= 0) {
            throw new InvalidArgumentException('El puesto no tiene lista de precios.');
        }
        // Sin fila de precio vigente = error. Precio 0 explícito = sin cargo (cortesía / invitado).
        if (! ViandaPrecioSupport::tienePrecioVigente((int) $articulo->id, $listaId, now()->toDateString())) {
            $nombreLista = Listaprecio::query()->whereKey($listaId)->value('nombre');
            $etiquetaLista = $nombreLista
                ? trim((string) $nombreLista).' (#'.$listaId.')'
                : '#'.$listaId;
            throw new InvalidArgumentException(
                'El artículo no tiene precio vigente en la lista '.$etiquetaLista
                .'. Cargalo en Precios (0 = sin cargo) o cambiá la lista de la cuenta.'
            );
        }
        $precio = ViandaPrecioSupport::precioDesdeLista((int) $articulo->id, $listaId, now()->toDateString());
        $areaId = $areaComandaId ?: $this->resolverAreaComandaId($articulo);
        if ($areaId) {
            $areaOk = AreaComandaGastronomia::query()
                ->where('id', $areaId)
                ->where(function ($q) use ($cfg) {
                    $q->where('empresa_id', $cfg->empresa_id)->orWhereNull('empresa_id');
                })
                ->exists();
            if (! $areaOk) {
                throw new InvalidArgumentException('Área / KDS inválida para la empresa.');
            }
        }
        $preparaLinea = $prepara ?? GastronomiaCrownKdsPreparaSupport::preparaDesdeArticulo($articulo);
        $orden = (int) ($cuenta->lineas()->max('orden') ?? 0) + 1;

        $linea = GastroCrownCuentaLinea::query()->create([
            'gastro_crown_cuenta_id' => $cuenta->id,
            'orden' => $orden,
            'articulo_id' => $articulo->id,
            'sku' => $articulo->sku,
            'descripcion' => $articulo->descripcion,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'descuento_importe' => 0,
            'observacion' => $observacion,
            'marcado' => false,
            'es_separador' => false,
            'prepara' => $preparaLinea,
            'area_comanda_gastronomia_id' => $areaId,
        ]);

        if ($opcionales !== null && $opcionales !== []) {
            $linea->opcionales_json = $opcionales;
            $linea->save();
        }

        $this->eventoCuenta(
            $cuenta,
            'add_item',
            $articulo->sku.' x '.$cantidad
            .($preparaLinea ? '' : ' [no prepara]')
            .($areaId ? ' KDS#'.$areaId : '')
        );

        return $linea;
    }

    /**
     * @return list<array{id:int,codigo:?string,nombre:string,salida_id:?int}>
     */
    public function listarAreasKds(ConfiguracionPuntoventaGastronomia $cfg): array
    {
        return AreaComandaGastronomia::query()
            ->where(function ($q) use ($cfg) {
                $q->where('empresa_id', $cfg->empresa_id)->orWhereNull('empresa_id');
            })
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'salida_id'])
            ->map(fn (AreaComandaGastronomia $a) => [
                'id' => (int) $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'salida_id' => $a->salida_id ? (int) $a->salida_id : null,
            ])
            ->all();
    }

    public function actualizarDestinoLinea(
        GastroCrownCuenta $cuenta,
        GastroCrownCuentaLinea $linea,
        ConfiguracionPuntoventaGastronomia $cfg,
        ?int $areaComandaId,
        ?bool $prepara = null,
    ): void {
        if ((int) $linea->gastro_crown_cuenta_id !== (int) $cuenta->id) {
            throw new InvalidArgumentException('La línea no pertenece a la cuenta.');
        }
        if ($linea->fired_at) {
            throw new InvalidArgumentException('Ítem ya comandado: no se puede cambiar el KDS.');
        }
        if ($areaComandaId !== null) {
            if ($areaComandaId === 0) {
                $linea->area_comanda_gastronomia_id = null;
            } else {
                $ok = AreaComandaGastronomia::query()
                    ->where('id', $areaComandaId)
                    ->where(function ($q) use ($cfg) {
                        $q->where('empresa_id', $cfg->empresa_id)->orWhereNull('empresa_id');
                    })
                    ->exists();
                if (! $ok) {
                    throw new InvalidArgumentException('Área / KDS inválida.');
                }
                $linea->area_comanda_gastronomia_id = $areaComandaId;
            }
        }
        if ($prepara !== null) {
            $linea->prepara = $prepara;
        }
        $linea->save();
        $this->eventoCuenta(
            $cuenta,
            'kds',
            'Línea '.$linea->id
            .' → área '.($linea->area_comanda_gastronomia_id ?: 'sin')
            .' · '.($linea->prepara ? 'prepara' : 'no prepara')
        );
    }

    public function actualizarCantidadLinea(
        GastroCrownCuenta $cuenta,
        GastroCrownCuentaLinea $linea,
        float $cantidad,
    ): void {
        if ((int) $linea->gastro_crown_cuenta_id !== (int) $cuenta->id) {
            throw new InvalidArgumentException('La línea no pertenece a la cuenta.');
        }
        if ($linea->es_separador) {
            throw new InvalidArgumentException('El separador no tiene cantidad.');
        }
        if ($linea->fired_at) {
            throw new InvalidArgumentException('Ítem ya comandado: no se puede cambiar la cantidad.');
        }
        if ($cantidad < 0.001) {
            throw new InvalidArgumentException('La cantidad no puede ser menor al mínimo permitido.');
        }

        $linea->cantidad = $cantidad;
        $linea->save();
        $this->eventoCuenta($cuenta, 'cantidad', 'Línea '.$linea->id.' → '.$cantidad);
    }

    public function cambiarListaPrecio(GastroCrownCuenta $cuenta, int $listaPrecioId): void
    {
        if ($listaPrecioId <= 0) {
            throw new InvalidArgumentException('Lista de precio inválida.');
        }
        $cuenta->listaprecio_id = $listaPrecioId;
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'lista_precio', 'Lista #'.$listaPrecioId);
    }

    public function agregarSeparador(GastroCrownCuenta $cuenta): GastroCrownCuentaLinea
    {
        $orden = (int) ($cuenta->lineas()->max('orden') ?? 0) + 1;

        return GastroCrownCuentaLinea::query()->create([
            'gastro_crown_cuenta_id' => $cuenta->id,
            'orden' => $orden,
            'descripcion' => '-------------',
            'cantidad' => 0,
            'precio' => 0,
            'es_separador' => true,
        ]);
    }

    /**
     * Opcionales de fórmula de la línea (se guardan en `modifiers`).
     *
     * @param  array<string|int, mixed>  $opcionales
     */
    public function actualizarOpcionalesLinea(
        GastroCrownCuenta $cuenta,
        GastroCrownCuentaLinea $linea,
        array $opcionales,
    ): void {
        if ((int) $linea->gastro_crown_cuenta_id !== (int) $cuenta->id) {
            throw new InvalidArgumentException('La línea no pertenece a la cuenta.');
        }
        if ($linea->fired_at) {
            throw new InvalidArgumentException('Ítem ya comandado: no se pueden cambiar los opcionales.');
        }

        $linea->opcionales_json = $opcionales;
        $linea->save();

        $this->eventoCuenta(
            $cuenta,
            'opcionales',
            'Línea '.$linea->id.' · '.count($opcionales).' opcional/es'
        );
    }

    public function actualizarObservacionLinea(GastroCrownCuentaLinea $linea, string $obs): void
    {
        $linea->observacion = mb_substr(trim($obs), 0, 255);
        $linea->save();
    }

    public function setMarcado(GastroCrownCuentaLinea $linea, bool $marcado): void
    {
        $linea->marcado = $marcado;
        $linea->save();
    }

    public function voidLinea(GastroCrownCuenta $cuenta, GastroCrownCuentaLinea $linea): void
    {
        if ((int) $linea->gastro_crown_cuenta_id !== (int) $cuenta->id) {
            throw new InvalidArgumentException('La línea no pertenece a la cuenta.');
        }
        if ($linea->fired_at) {
            throw new InvalidArgumentException('Ítem ya comandado: requiere anulación con marcha (próxima iteración).');
        }
        $desc = $linea->descripcion;
        $linea->delete();
        $this->eventoCuenta($cuenta, 'void_item', $desc);
    }

    public function anularCuenta(GastroCrownCuenta $cuenta): void
    {
        $fireadas = $cuenta->lineas()->whereNotNull('fired_at')->exists();
        if ($fireadas) {
            throw new InvalidArgumentException('Hay ítems ya comandados; no se puede borrar la mesa sin void de cocina.');
        }

        DB::transaction(function () use ($cuenta) {
            $cuenta->lineas()->delete();
            $cuenta->estado = GastroCrownCuenta::ESTADO_ANULADA;
            $cuenta->cerrada_at = now();
            $cuenta->save();
            $this->eventoCuenta($cuenta, 'anular', 'Cuenta anulada');
        });
    }

    /**
     * Cierra la cuenta sin facturar (paridad AGG / canjes).
     * Libera la mesa; conserva líneas para auditoría.
     */
    public function cerrarSinFacturar(GastroCrownCuenta $cuenta): void
    {
        if (! in_array($cuenta->estado, [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO], true)) {
            throw new InvalidArgumentException('La cuenta no está abierta.');
        }

        DB::transaction(function () use ($cuenta) {
            $cuenta->estado = GastroCrownCuenta::ESTADO_CERRADA;
            $cuenta->cerrada_at = now();
            $cuenta->save();
            $this->eventoCuenta($cuenta, 'cerrar_sin_facturar', 'Cerrada sin facturar');
        });
    }

    /**
     * @return int cantidad cerrada
     */
    public function cerrarTodasSinFacturar(ConfiguracionPuntoventaGastronomia $cfg): int
    {
        $ids = GastroCrownCuenta::query()
            ->where('empresa_id', $cfg->empresa_id)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->tap(function ($q) use ($cfg) {
                GastronomiaSalaSupport::aplicarFiltroSiCorresponde(
                    $q,
                    $cfg->sala_id ? (int) $cfg->sala_id : null
                );
            })
            ->orderBy('id')
            ->limit(200)
            ->pluck('id');

        $n = 0;
        foreach ($ids as $id) {
            $cuenta = GastroCrownCuenta::query()->find((int) $id);
            if (! $cuenta) {
                continue;
            }
            try {
                $this->cerrarSinFacturar($cuenta);
                $n++;
            } catch (\Throwable) {
                // sigue con las demás
            }
        }

        return $n;
    }

    /**
     * Marcha: una comanda por área (paridad Anita COM_lista_comanda).
     *
     * @return list<GastroCrownComanda>
     */
    public function marchar(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $tipo = GastroCrownComanda::TIPO_NORMAL,
        bool $soloMarcados = false,
    ): array {
        if ($cfg->politica_comanda === GastronomiaCrownPosModos::POLITICA_SIN_COMANDA) {
            throw new InvalidArgumentException('Este puesto no emite comandas.');
        }

        // Solo ítems que preparan van a KDS / térmica (paridad AGG No preparar).
        $lineas = $cuenta->lineas()
            ->where('es_separador', false)
            ->where('prepara', true)
            ->where(function ($q) use ($tipo) {
                if ($tipo === GastroCrownComanda::TIPO_POSTRES) {
                    $q->whereNull('fired_postre_at');
                } else {
                    $q->whereNull('fired_at');
                }
            })
            ->when($soloMarcados, fn ($q) => $q->where('marcado', true))
            ->orderBy('orden')
            ->get();

        if ($lineas->isEmpty()) {
            throw new InvalidArgumentException('No hay ítems pendientes de marcha (o todos son “no prepara”).');
        }

        $grupos = $lineas->groupBy(fn (GastroCrownCuentaLinea $l) => (int) ($l->area_comanda_gastronomia_id ?: 0));

        return DB::transaction(function () use ($cuenta, $cfg, $tipo, $grupos) {
            $comandas = [];
            foreach ($grupos as $areaId => $lineasArea) {
                $area = $areaId > 0
                    ? AreaComandaGastronomia::query()->find($areaId)
                    : null;
                $salidaId = $area?->salida_id
                    ?: $cfg->salida_comanda_id
                    ?: null;

                $numero = $this->siguienteNumeroComanda((int) $cuenta->empresa_id);
                $comanda = GastroCrownComanda::query()->create([
                    'empresa_id' => $cuenta->empresa_id,
                    'gastro_crown_cuenta_id' => $cuenta->id,
                    'numero' => $numero,
                    'tipo' => $tipo,
                    'area_comanda_gastronomia_id' => $area?->id,
                    'salida_id' => $salidaId,
                    'mesa_gastronomia_id' => $cuenta->mesa_gastronomia_id,
                    'mozo_gastronomia_id' => $cuenta->mozo_gastronomia_id,
                    'estado' => GastroCrownComanda::ESTADO_FIRED,
                    'canal_impresion' => GastronomiaCrownPrintSupport::CANAL_AMBOS,
                    'usuario_id' => Auth::id(),
                    'fired_at' => now(),
                ]);

                $ord = 1;
                foreach ($lineasArea as $linea) {
                    GastroCrownComandaLinea::query()->create([
                        'gastro_crown_comanda_id' => $comanda->id,
                        'gastro_crown_cuenta_linea_id' => $linea->id,
                        'orden' => $ord++,
                        'sku' => $linea->sku,
                        'descripcion' => $linea->descripcion,
                        'cantidad' => $linea->cantidad,
                        'observacion' => $linea->observacion,
                        'prepara' => (bool) $linea->prepara,
                    ]);
                    if ($tipo === GastroCrownComanda::TIPO_POSTRES) {
                        $linea->fired_postre_at = now();
                    } else {
                        $linea->fired_at = now();
                    }
                    // Vincula siempre: el POS muestra el estado KDS (preparando/listo/entregado).
                    $linea->gastro_crown_comanda_id = $comanda->id;
                    $linea->marcado = false;
                    $linea->save();
                }

                GastroCrownComandaEvento::query()->create([
                    'gastro_crown_comanda_id' => $comanda->id,
                    'evento' => 'fired',
                    'usuario_id' => Auth::id(),
                    'detalle' => 'Marcha '.$tipo,
                    'created_at' => now(),
                ]);

                $print = GastronomiaCrownPrintSupport::imprimirComanda(
                    $comanda->fresh(['lineas', 'mesa', 'mozo', 'areaComanda', 'cuenta']),
                    GastronomiaCrownPrintSupport::CANAL_AMBOS
                );
                GastroCrownComandaEvento::query()->create([
                    'gastro_crown_comanda_id' => $comanda->id,
                    'evento' => ($print['ok'] ?? false) ? 'print_ok' : 'print_fail',
                    'usuario_id' => Auth::id(),
                    'detalle' => $print['mensaje'] ?? null,
                    'payload' => $print,
                    'created_at' => now(),
                ]);

                $comandas[] = $comanda;
            }

            $this->eventoCuenta($cuenta, 'fire', 'Marcha '.$tipo.' ('.count($comandas).' ticket/s)');

            return $comandas;
        });
    }

    /**
     * @return array{subtotal: float, descuento: float, total: float, pct: float}
     */
    public function totalesCuenta(GastroCrownCuenta $cuenta): array
    {
        $cuenta->loadMissing('lineas');
        $subtotal = 0.0;
        foreach ($cuenta->lineas as $l) {
            if ($l->es_separador) {
                continue;
            }
            $subtotal += (float) $l->cantidad * (float) $l->precio - (float) $l->descuento_importe;
        }
        $pct = max(0.0, min(100.0, (float) ($cuenta->porcentaje_descuento ?? 0)));
        $descuento = round($subtotal * ($pct / 100.0), 2);
        $total = round(max(0.0, $subtotal - $descuento), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => $descuento,
            'total' => $total,
            'pct' => $pct,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarArticuloParaPos(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $sku,
        ?int $listaprecioId = null,
    ): ?array {
        $sku = $this->normalizarSkuCatalogo($sku);
        $articulo = $this->catalogoService->buscarArticuloCatalogoPorSku($cfg, $sku);
        if (! $articulo) {
            $articulo = Articulo::query()
                ->whereRaw('UPPER(sku) = ?', [mb_strtoupper(trim($sku), 'UTF-8')])
                ->first(['id', 'sku', 'descripcion', 'subcategoria_id']);
        }
        if (! $articulo) {
            return null;
        }

        $listaId = (int) ($listaprecioId ?: $cfg->listaprecio_id);
        $fecha = now()->toDateString();
        $tienePrecio = $listaId > 0
            && ViandaPrecioSupport::tienePrecioVigente((int) $articulo->id, $listaId, $fecha);
        $precio = $tienePrecio
            ? ViandaPrecioSupport::precioDesdeLista((int) $articulo->id, $listaId, $fecha)
            : 0.0;

        $areaId = $this->resolverAreaComandaId($articulo);

        return [
            'id' => (int) $articulo->id,
            'sku' => $articulo->sku,
            'descripcion' => $articulo->descripcion,
            'precio' => (float) $precio,
            'tiene_precio_vigente' => $tienePrecio,
            'listaprecio_id' => $listaId,
            'prepara' => GastronomiaCrownKdsPreparaSupport::preparaDesdeArticulo($articulo),
            'area_comanda_gastronomia_id' => $areaId,
            'foto_url' => GastronomiaCrownKdsPreparaSupport::urlFotoArticulo($articulo),
        ];
    }

    public function aplicarDescuentoMesa(GastroCrownCuenta $cuenta, float $porcentaje, ?string $detalle = null): void
    {
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new InvalidArgumentException('El descuento debe estar entre 0 y 100 %.');
        }
        $cuenta->porcentaje_descuento = round($porcentaje, 2);
        $cuenta->detalle_descuento = $detalle ? mb_substr(trim($detalle), 0, 80) : $cuenta->detalle_descuento;
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'descuento', 'Dto '.$porcentaje.'%'.($detalle ? ' — '.$detalle : ''));
    }

    /**
     * F8 / carga_dto_autorizante: tipo Invitados|Staff|Descuento + autorizante + CC + cliente + código dto.
     *
     * @param  array{
     *     tipo_descuento:string,
     *     autorizante_usuario_id:int,
     *     centrocosto_id:int,
     *     cliente_interno_descuento_id:int,
     *     descuento_gastronomia_id?:int|null,
     *     descuento_codigo?:string|null
     * }  $datos
     */
    public function aplicarDescuentoAutorizante(GastroCrownCuenta $cuenta, array $datos): void
    {
        $tipo = (string) ($datos['tipo_descuento'] ?? '');
        if (! in_array($tipo, [
            GastroCrownCuenta::TIPO_DTO_INVITADOS,
            GastroCrownCuenta::TIPO_DTO_STAFF,
            GastroCrownCuenta::TIPO_DTO_DESCUENTO,
        ], true)) {
            throw new InvalidArgumentException('Tipo de descuento inválido (Invitados / Staff / Descuento).');
        }

        $autorizanteId = (int) ($datos['autorizante_usuario_id'] ?? 0);
        if ($autorizanteId <= 0) {
            throw new InvalidArgumentException('Indique el autorizante (no puede facturar sin autorizante).');
        }
        $autorizante = UsuarioOperativoSupport::query()
            ->whereKey($autorizanteId)
            ->first(['id', 'nombre', 'usuario']);
        if ($autorizante === null) {
            throw new InvalidArgumentException('Autorizante inexistente o no operativo.');
        }

        $ccostoId = (int) ($datos['centrocosto_id'] ?? 0);
        if ($ccostoId <= 0) {
            throw new InvalidArgumentException('Indique el centro de costo.');
        }
        $ccosto = Centrocosto::query()->find($ccostoId, ['id', 'codigo', 'nombre']);
        if ($ccosto === null) {
            throw new InvalidArgumentException('Centro de costos inexistente.');
        }

        $clienteInternoId = (int) ($datos['cliente_interno_descuento_id'] ?? 0);
        if ($clienteInternoId <= 0) {
            throw new InvalidArgumentException('Indique el cliente interno del descuento.');
        }
        $clienteInterno = Cliente::query()->find($clienteInternoId, ['id', 'codigo', 'nombre']);
        if ($clienteInterno === null) {
            throw new InvalidArgumentException('Cliente interno inexistente.');
        }

        $descuento = null;
        $pct = 100.0;
        $detalle = strtoupper($tipo);

        if ($tipo === GastroCrownCuenta::TIPO_DTO_DESCUENTO) {
            $descuento = $this->resolverDescuentoGastronomia(
                isset($datos['descuento_gastronomia_id']) ? (int) $datos['descuento_gastronomia_id'] : null,
                isset($datos['descuento_codigo']) ? (string) $datos['descuento_codigo'] : null,
            );
            if ($descuento === null) {
                throw new InvalidArgumentException('Indique el código de descuento.');
            }
            if (stripos((string) $descuento->nombre, '@GST') !== false) {
                throw new InvalidArgumentException('No puede utilizar descuento GST manualmente.');
            }
            $pct = $this->porcentajeDesdeDescuento($descuento, $this->totalesCuenta($cuenta)['subtotal']);
            $detalle = trim((string) $descuento->codigo.' — '.$descuento->nombre);
        } else {
            $tipoConsumo = $tipo === GastroCrownCuenta::TIPO_DTO_STAFF
                ? DescuentoGastronomia::TIPO_CONSUMO_STAFF
                : DescuentoGastronomia::TIPO_CONSUMO_INVITACION;
            $descuento = DescuentoGastronomia::query()
                ->where('tipo_consumo', $tipoConsumo)
                ->where('tipovalor', DescuentoGastronomia::TIPO_PORCENTAJE)
                ->where('valor', '>=', 100)
                ->orderBy('codigo')
                ->first();
            $pct = 100.0;
        }

        $cuenta->tipo_descuento = $tipo;
        $cuenta->autorizante_usuario_id = (int) $autorizante->id;
        $cuenta->autorizante_nombre = mb_substr(trim((string) ($autorizante->nombre ?: $autorizante->usuario)), 0, 60);
        $cuenta->centrocosto_id = (int) $ccosto->id;
        $cuenta->cliente_interno_descuento_id = (int) $clienteInterno->id;
        $cuenta->descuento_gastronomia_id = $descuento?->id;
        $cuenta->porcentaje_descuento = round($pct, 2);
        $cuenta->detalle_descuento = mb_substr($detalle, 0, 80);
        $cuenta->save();

        $this->eventoCuenta(
            $cuenta,
            'descuento',
            'F8 '.$detalle.' · Aut. '.$cuenta->autorizante_nombre
            .' · CC '.$ccosto->codigo
            .' · Cli '.$clienteInterno->codigo
        );
    }

    public function resolverDescuentoGastronomia(?int $id, ?string $codigo): ?DescuentoGastronomia
    {
        if ($id !== null && $id > 0) {
            return DescuentoGastronomia::query()->find($id);
        }
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        return DescuentoGastronomia::query()
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo);
                if (ctype_digit($codigo)) {
                    $q->orWhere('codigo', str_pad($codigo, 2, '0', STR_PAD_LEFT))
                        ->orWhere('id', (int) $codigo);
                }
            })
            ->first();
    }

    private function porcentajeDesdeDescuento(DescuentoGastronomia $descuento, float $subtotal): float
    {
        $valor = (float) ($descuento->valor ?? 0);
        $tipo = (string) ($descuento->tipovalor ?? DescuentoGastronomia::TIPO_PORCENTAJE);

        if ($tipo === DescuentoGastronomia::TIPO_IMPORTE) {
            if ($subtotal <= 0.0 || $valor <= 0.0) {
                return 0.0;
            }

            return min(100.0, round($valor / $subtotal * 100.0, 2));
        }

        return max(0.0, min(100.0, $valor));
    }

    public function asignarCliente(GastroCrownCuenta $cuenta, string $codigoCliente): void
    {
        $codigo = trim($codigoCliente);
        if ($codigo === '') {
            $cuenta->cliente_id = null;
            $cuenta->save();
            $this->eventoCuenta($cuenta, 'cliente', 'Cliente quitado');

            return;
        }

        $cliente = Cliente::query()
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo);
                if (ctype_digit($codigo)) {
                    $q->orWhere('id', (int) $codigo);
                }
            })
            ->first(['id', 'codigo', 'nombre']);
        if (! $cliente) {
            throw new InvalidArgumentException('Cliente no encontrado: '.$codigo);
        }
        $cuenta->cliente_id = $cliente->id;
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'cliente', $cliente->codigo.' — '.$cliente->nombre);
    }

    public function asignarHotel(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg, string $habitacion, ?string $reserva = null): void
    {
        if (! $cfg->habilita_hotel) {
            throw new InvalidArgumentException('Hotel no habilitado en este punto de venta.');
        }
        $hab = trim($habitacion);
        if ($hab === '') {
            throw new InvalidArgumentException('Indique el número de habitación.');
        }
        $cuenta->habitacion = mb_substr($hab, 0, 20);
        $cuenta->reserva_hotel = $reserva ? mb_substr(trim($reserva), 0, 30) : null;
        if ($cfg->listaprecio_hotel_id) {
            $cuenta->listaprecio_id = $cfg->listaprecio_hotel_id;
        }
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'hotel', 'Hab. '.$cuenta->habitacion.($cuenta->reserva_hotel ? ' res.'.$cuenta->reserva_hotel : ''));
    }

    public function asignarGst(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg, string $tarjeta): void
    {
        if (! $cfg->habilita_canje_gst) {
            throw new InvalidArgumentException('GST/Wigos no habilitado en este punto de venta.');
        }
        $t = trim($tarjeta);
        if ($t === '') {
            throw new InvalidArgumentException('Indique la tarjeta GST / track.');
        }
        $cuenta->tarjeta_gst = mb_substr($t, 0, 30);
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'gst', 'Tarjeta '.$cuenta->tarjeta_gst);
    }

    public function transferirMesa(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg, int $mesaDestinoId): void
    {
        $mesa = MesaGastronomia::query()
            ->where('id', $mesaDestinoId)
            ->where('empresa_id', $cfg->empresa_id)
            ->first();
        if (! $mesa) {
            throw new InvalidArgumentException('Mesa destino no encontrada.');
        }
        $ocupada = GastroCrownCuenta::query()
            ->where('mesa_gastronomia_id', $mesaDestinoId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->where('id', '!=', $cuenta->id)
            ->exists();
        if ($ocupada) {
            throw new InvalidArgumentException('La mesa destino ya tiene una cuenta abierta.');
        }
        $origen = $cuenta->mesa_gastronomia_id;
        $cuenta->mesa_gastronomia_id = $mesa->id;
        if ($mesa->sala_id) {
            $cuenta->sala_id = $mesa->sala_id;
        }
        $cuenta->save();
        $this->eventoCuenta($cuenta, 'transfer', 'Mesa '.$origen.' → '.$mesa->id.' ('.$mesa->codigo.')');
    }

    /**
     * Cobro con emisión fiscal ARCA (CAE / CAEA) y cierre de la cuenta.
     *
     * Con `gastronomia.crown.emision_fiscal_habilitada` en false solo cierra la cuenta (sin venta).
     *
     * @param  array<string, mixed>  $extra
     * @return array{cuenta: GastroCrownCuenta, totales: array<string, float>, emision: array<string, mixed>}
     */
    public function cobrar(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $tipoCobro,
        array $extra = [],
    ): array {
        if ($cfg->solo_marcha) {
            throw new InvalidArgumentException('Este puesto es solo marcha; no cobra.');
        }

        $this->exigirJornadaOperativa($cfg);

        if (! in_array($tipoCobro, [
            GastroCrownCuenta::COBRO_EFECTIVO,
            GastroCrownCuenta::COBRO_FACTURA_SOLA,
            GastroCrownCuenta::COBRO_INTERNA,
            GastroCrownCuenta::COBRO_HOTEL,
            GastroCrownCuenta::COBRO_MEDIOS,
        ], true)) {
            throw new InvalidArgumentException('Tipo de cobro inválido: '.$tipoCobro);
        }

        $lineasCobrables = $cuenta->lineas()->where('es_separador', false)->count();
        if ($lineasCobrables === 0) {
            throw new InvalidArgumentException('La cuenta no tiene ítems para cobrar.');
        }

        $mensajeInactivos = $this->facturaEmisionService->mensajeArticulosInactivos($cuenta);
        if ($mensajeInactivos !== null) {
            throw new InvalidArgumentException($mensajeInactivos);
        }

        if ($tipoCobro === GastroCrownCuenta::COBRO_INTERNA) {
            $this->prepararInterna($cuenta, $cfg, $extra);
        } elseif ($tipoCobro === GastroCrownCuenta::COBRO_HOTEL) {
            $this->prepararHotel($cuenta, $cfg, $extra);
        }

        if (! filter_var(config('gastronomia.crown.emision_fiscal_habilitada', true), FILTER_VALIDATE_BOOLEAN)) {
            return $this->cerrarCobro($cuenta, $tipoCobro, $extra) + ['emision' => ['sin_emision_fiscal' => true]];
        }

        $emision = $this->facturaEmisionService->emitirDesdeCuenta($cuenta, $cfg, $tipoCobro, $extra);
        if (! empty($emision['error'])) {
            throw new InvalidArgumentException((string) $emision['error']);
        }

        $cuenta = $cuenta->fresh(['lineas', 'mesa', 'mozo', 'cliente']) ?? $cuenta;

        return [
            'cuenta' => $cuenta,
            'totales' => $emision['totales'] ?? $this->totalesCuenta($cuenta),
            'emision' => $emision,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function prepararInterna(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg, array $extra): void
    {
        if (! empty($extra['tarjeta_gst'])) {
            $this->asignarGst($cuenta, $cfg, (string) $extra['tarjeta_gst']);
            $cuenta->refresh();

            return;
        }

        if ($cfg->habilita_canje_gst && empty($cuenta->tarjeta_gst) && empty($extra['sin_gst'])) {
            throw new InvalidArgumentException('Indique tarjeta GST o marque sin_gst=1 para interna sin canje.');
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function prepararHotel(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg, array $extra): void
    {
        $hab = (string) ($extra['habitacion'] ?? $cuenta->habitacion ?? '');
        $this->asignarHotel($cuenta, $cfg, $hab, $extra['reserva_hotel'] ?? $cuenta->reserva_hotel);
        $cuenta->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{cuenta: GastroCrownCuenta, totales: array<string, float>}
     */
    private function cerrarCobro(GastroCrownCuenta $cuenta, string $tipoCobro, array $extra = []): array
    {
        $totales = $this->totalesCuenta($cuenta);

        DB::transaction(function () use ($cuenta, $tipoCobro, $totales, $extra) {
            $cuenta->estado = GastroCrownCuenta::ESTADO_CERRADA;
            $cuenta->tipo_cobro = $tipoCobro;
            $cuenta->importe_cobrado = $totales['total'];
            $cuenta->cerrada_at = now();
            if (! empty($extra['observacion_cobro'])) {
                $cuenta->observacion = mb_substr(
                    trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').$extra['observacion_cobro']),
                    0,
                    255
                );
            }
            $cuenta->save();
            $this->eventoCuenta(
                $cuenta,
                'pay',
                strtoupper($tipoCobro).' $ '.number_format($totales['total'], 2, ',', '.')
            );
        });

        return [
            'cuenta' => $cuenta->fresh(['lineas', 'mesa', 'mozo', 'cliente']),
            'totales' => $totales,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarCuenta(GastroCrownCuenta $cuenta): array
    {
        $cuenta->loadMissing(['lineas.areaComanda', 'lineas.comanda', 'mesa', 'mozo', 'cliente']);
        $totales = $this->totalesCuenta($cuenta);
        $detalleOpcionales = $this->detalleOpcionalesPorLinea($cuenta->lineas);
        $lineas = [];
        foreach ($cuenta->lineas as $l) {
            $imp = (float) $l->cantidad * (float) $l->precio - (float) $l->descuento_importe;
            $opcDetalle = $detalleOpcionales[(int) $l->id] ?? [];
            $kdsEstado = $this->estadoKdsLinea($l);
            $lineas[] = [
                'id' => (int) $l->id,
                'orden' => (int) $l->orden,
                'sku' => $l->sku,
                'descripcion' => $l->descripcion,
                'cantidad' => (float) $l->cantidad,
                'precio' => (float) $l->precio,
                'importe' => round($imp, 2),
                'observacion' => $l->observacion,
                'marcado' => (bool) $l->marcado,
                'es_separador' => (bool) $l->es_separador,
                'prepara' => (bool) ($l->prepara ?? true),
                'fired' => $l->fired_at !== null,
                'fired_postre' => $l->fired_postre_at !== null,
                'kds_estado' => $kdsEstado,
                'kds_estado_label' => $this->etiquetaEstadoKds($kdsEstado),
                'area_id' => $l->area_comanda_gastronomia_id ? (int) $l->area_comanda_gastronomia_id : null,
                'area' => $l->areaComanda->nombre ?? null,
                'area_codigo' => $l->areaComanda->codigo ?? null,
                'comanda_id' => $l->gastro_crown_comanda_id ? (int) $l->gastro_crown_comanda_id : null,
                'articulo_id' => $l->articulo_id ? (int) $l->articulo_id : null,
                'opcionales' => $l->opcionales_json,
                'opcionales_detalle' => $opcDetalle,
                'tiene_opcionales' => $opcDetalle !== [],
            ];
        }

        return [
            'id' => $cuenta->id,
            'estado' => $cuenta->estado,
            'tipo_cobro' => $cuenta->tipo_cobro,
            'modo_pos' => $cuenta->modo_pos,
            'listaprecio_id' => $cuenta->listaprecio_id ? (int) $cuenta->listaprecio_id : null,
            'mesa_id' => $cuenta->mesa_gastronomia_id,
            'mesa' => $cuenta->mesa
                ? trim(($cuenta->mesa->codigo ? $cuenta->mesa->codigo.' — ' : '').$cuenta->mesa->nombre)
                : null,
            'mozo_id' => $cuenta->mozo_gastronomia_id,
            'mozo' => $cuenta->mozo->nombre ?? null,
            'cliente_id' => $cuenta->cliente_id,
            'cliente' => $cuenta->cliente
                ? trim(($cuenta->cliente->codigo ? $cuenta->cliente->codigo.' — ' : '').$cuenta->cliente->nombre)
                : null,
            'cubiertos' => (int) $cuenta->cubiertos,
            'habitacion' => $cuenta->habitacion,
            'reserva_hotel' => $cuenta->reserva_hotel,
            'tarjeta_gst' => $cuenta->tarjeta_gst,
            'porcentaje_descuento' => (float) ($cuenta->porcentaje_descuento ?? 0),
            'detalle_descuento' => $cuenta->detalle_descuento,
            'descuento_gastronomia_id' => $cuenta->descuento_gastronomia_id
                ? (int) $cuenta->descuento_gastronomia_id
                : null,
            'tipo_descuento' => $cuenta->tipo_descuento,
            'cliente_interno_descuento_id' => $cuenta->cliente_interno_descuento_id
                ? (int) $cuenta->cliente_interno_descuento_id
                : null,
            'autorizante_usuario_id' => $cuenta->autorizante_usuario_id
                ? (int) $cuenta->autorizante_usuario_id
                : null,
            'autorizante_nombre' => $cuenta->autorizante_nombre,
            'centrocosto_id' => $cuenta->centrocosto_id ? (int) $cuenta->centrocosto_id : null,
            'observacion' => $cuenta->observacion,
            'subtotal' => $totales['subtotal'],
            'descuento' => $totales['descuento'],
            'total' => $totales['total'],
            'importe_cobrado' => $cuenta->importe_cobrado !== null ? (float) $cuenta->importe_cobrado : null,
            'venta_id' => $cuenta->venta_id ? (int) $cuenta->venta_id : null,
            'lineas' => $lineas,
        ];
    }

    /**
     * Los 4 dígitos del POS de AGG se arman como prefijo + sufijo (V + 0123).
     * Un SKU ya completo se deja igual.
     */
    private function normalizarSkuCatalogo(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '' || ! preg_match('/^\d+$/', $sku)) {
            return $sku;
        }
        if (GastronomiaSkuCatalogoSupport::digitosSufijo() <= 0) {
            return $sku;
        }

        $armado = GastronomiaSkuCatalogoSupport::skuDesdeSufijoDigitos($sku);

        return $armado !== '' ? $armado : $sku;
    }

    /**
     * @param  iterable<int, GastroCrownCuentaLinea>  $lineas
     * @return array<int, list<array{orden: string, sku: ?string, descripcion: ?string}>>
     */
    private function detalleOpcionalesPorLinea(iterable $lineas): array
    {
        $idsArticulo = [];
        $idsFormula = [];
        foreach ($lineas as $linea) {
            foreach ($linea->opcionales_json as $valor) {
                $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
                if ($decoded === null) {
                    continue;
                }
                if ($decoded['tipo'] === 'articulo') {
                    $idsArticulo[$decoded['id']] = true;
                } else {
                    $idsFormula[$decoded['id']] = true;
                }
            }
        }

        $articulos = $idsArticulo === []
            ? collect()
            : Articulo::query()->whereIn('id', array_keys($idsArticulo))->get(['id', 'sku', 'descripcion'])->keyBy('id');
        $formulas = $idsFormula === []
            ? collect()
            : Formula_Articulo::query()->with('articulos')->whereIn('id', array_keys($idsFormula))->get()->keyBy('id');

        $out = [];
        foreach ($lineas as $linea) {
            $detalle = [];
            foreach ($linea->opcionales_json as $orden => $valor) {
                $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
                if ($decoded === null) {
                    continue;
                }
                if ($decoded['tipo'] === 'articulo') {
                    $art = $articulos->get($decoded['id']);
                    $detalle[] = [
                        'orden' => (string) $orden,
                        'sku' => $art->sku ?? null,
                        'descripcion' => $art->descripcion ?? null,
                    ];
                } else {
                    $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional(
                        $formulas->get($decoded['id']),
                        $decoded['id'],
                    );
                    $detalle[] = [
                        'orden' => (string) $orden,
                        'sku' => $etiqueta['sku'] ?? null,
                        'descripcion' => $etiqueta['descripcion'] ?? null,
                    ];
                }
            }
            usort($detalle, fn (array $a, array $b): int => strnatcmp($a['orden'], $b['orden']));
            $out[(int) $linea->id] = $detalle;
        }

        return $out;
    }

    private function resolverAreaComandaId(Articulo $articulo): ?int
    {
        // Crown: Anita stkagr.stka_area vive en categoría (no hay subcategorías).
        if (EntornoEmpresaSupport::esCrown()) {
            $catId = (int) ($articulo->categoria_id ?? 0);
            if ($catId <= 0) {
                return null;
            }

            $areaId = CategoriaAreaComanda::query()
                ->where('categoria_id', $catId)
                ->orderBy('id')
                ->value('area_comanda_gastronomia_id');

            return $areaId ? (int) $areaId : null;
        }

        $subId = (int) ($articulo->subcategoria_id ?? 0);
        if ($subId <= 0) {
            return null;
        }

        $areaId = SubcategoriaAreaComanda::query()
            ->where('subcategoria_id', $subId)
            ->orderBy('id')
            ->value('area_comanda_gastronomia_id');

        return $areaId ? (int) $areaId : null;
    }

    /**
     * Estado del ticket KDS vinculado a la línea (null si aún no se marchó).
     */
    private function estadoKdsLinea(GastroCrownCuentaLinea $linea): ?string
    {
        if ($linea->es_separador || ! $linea->prepara) {
            return null;
        }
        if ($linea->fired_at === null && $linea->fired_postre_at === null) {
            return null;
        }

        $estado = $linea->comanda?->estado;
        if (is_string($estado) && $estado !== '') {
            return $estado;
        }

        // Marchada sin ticket (área vacía / comanda borrada): asumir enviada.
        return GastroCrownComanda::ESTADO_FIRED;
    }

    private function etiquetaEstadoKds(?string $estado): ?string
    {
        return match ($estado) {
            GastroCrownComanda::ESTADO_FIRED => 'En KDS',
            GastroCrownComanda::ESTADO_PREPARING => 'Preparando',
            GastroCrownComanda::ESTADO_READY => 'Listo',
            GastroCrownComanda::ESTADO_DELIVERED => 'Entregado',
            GastroCrownComanda::ESTADO_VOID => 'Anulada',
            default => null,
        };
    }

    private function siguienteNumeroComanda(int $empresaId): string
    {
        $ultimo = GastroCrownComanda::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->value('numero');
        $n = is_numeric($ultimo) ? ((int) $ultimo) + 1 : 1;

        return str_pad((string) $n, 8, '0', STR_PAD_LEFT);
    }

    private function eventoCuenta(GastroCrownCuenta $cuenta, string $evento, ?string $detalle = null): void
    {
        GastroCrownCuentaEvento::query()->create([
            'gastro_crown_cuenta_id' => $cuenta->id,
            'evento' => $evento,
            'usuario_id' => Auth::id(),
            'detalle' => $detalle,
            'created_at' => now(),
        ]);
    }
}
