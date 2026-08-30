<?php

namespace App\Providers;

use App;
use App\Models\Admin\Menu;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Pedido_Combinacion_Estado;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Observers\Contable\AsientoObserver;
use App\Observers\Contable\Asiento_MovimientoObserver;
use App\Observers\Stock\Articulo_MovimientoObserver;
use App\Observers\Stock\MovimientoStockObserver;
use App\Observers\Stock\Transferencia_MercaderiaObserver;
use App\Observers\Ventas\OrdentrabajoObserver;
use App\Observers\Ventas\Ordentrabajo_TareaObserver;
use App\Observers\Ventas\Pedido_Combinacion_EstadoObserver;
use App\Observers\Ventas\Pedido_CombinacionObserver;
use App\Observers\Ventas\VentaObserver;
use App\Observers\Ventas\Venta_EmisionObserver;
use App\Support\AyudaManuales;
use App\Support\Seguridad\BarraTareasSupport;
use App\Support\Console\ProteccionComandosDestructivosProduccion;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ProteccionComandosDestructivosProduccion::registrar();

        Paginator::useBootstrap();

        View::composer('theme.lte.aside', function ($view) {
            $nivelActual = 0;
            $menus = Menu::getMenu(true, $nivelActual);
            $menus = \App\Support\Caja\Estacionamiento\EstacionamientoModuloSupport::filtrarMenuAside($menus);
            $menus = \App\Support\Caja\Bingo\BingoModuloSupport::filtrarMenuAside($menus);
            $menus = \App\Support\Caja\CajaRecepcionPcSupport::filtrarMenuAside($menus);
            $view->with('menusComposer', $menus);

            if (auth()->check()) {
                $barraTareas = app(BarraTareasSupport::class);
                $view->with('barraTareasMenuIds', $barraTareas->idsAnclados());
            } else {
                $view->with('barraTareasMenuIds', []);
            }
        });
        View::composer('theme.lte.footer', function ($view) {
            if (auth()->check()) {
                $barraTareas = app(BarraTareasSupport::class);
                $view->with('barraTareasAnclados', $barraTareas->ancladosResueltos());
            } else {
                $view->with('barraTareasAnclados', []);
            }
        });
        View::composer('theme.lte.header', function ($view) {
            $view->with('urlCentroAyuda', AyudaManuales::urlCentroAyuda());
        });
        View::composer(['ventas.cliente.editar'], function ($view) {
            $view->with('suitecrmHabilitado', \App\Support\SuitecrmPermiso::integracionActiva());
        });
        View::share('theme', 'lte');

        App::setLocale('es');
        Carbon::setLocale('es');

        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        if ($fromAddress !== '') {
            Mail::alwaysFrom($fromAddress, $fromName !== '' ? $fromName : 'Anita ERP');
        }
        $replyAddress = (string) config('mail.reply_to.address');
        if ($replyAddress !== '') {
            Mail::alwaysReplyTo(
                $replyAddress,
                (string) (config('mail.reply_to.name') ?: 'Anita ERP')
            );
        }

        Pedido_Combinacion::observe(Pedido_CombinacionObserver::class);
        Ordentrabajo_Tarea::observe(Ordentrabajo_TareaObserver::class);
        Pedido_Combinacion_Estado::observe(Pedido_Combinacion_EstadoObserver::class);
        Articulo_Movimiento::observe(Articulo_MovimientoObserver::class);
        MovimientoStock::observe(MovimientoStockObserver::class);
        Transferencia_Mercaderia::observe(Transferencia_MercaderiaObserver::class);
        Venta::observe(VentaObserver::class);
        Venta_Emision::observe(Venta_EmisionObserver::class);
        Ordentrabajo::observe(OrdentrabajoObserver::class);
        Asiento_Movimiento::observe(Asiento_MovimientoObserver::class);
        Asiento::observe(AsientoObserver::class);

        $url = env('APP_URL');
        if (is_string($url) && str_contains($url, 'https')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->bind(
            'App\Repositories\Admin\UsuarioRepositoryInterface',
            'App\Repositories\Admin\UsuarioRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\RepositoryInterface',
            'App\Repositories\Configuracion\CondicionivaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ClienteRepositoryInterface',
            'App\Repositories\Ventas\ClienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_EntregaRepositoryInterface',
            'App\Repositories\Ventas\Cliente_EntregaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_ArchivoRepositoryInterface',
            'App\Repositories\Ventas\Cliente_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_Cm05RepositoryInterface',
            'App\Repositories\Ventas\Cliente_Cm05Repository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_Exclusion_PercepcionRepositoryInterface',
            'App\Repositories\Ventas\Cliente_Exclusion_PercepcionRepository',
        );

        $this->app->bind(
            'App\Queries\Ventas\ClienteQueryInterface',
            'App\Queries\Ventas\ClienteQuery',
        );

        $this->app->bind(
            'App\Queries\Ventas\Cliente_ComisionQueryInterface',
            'App\Queries\Ventas\Cliente_ComisionQuery',
        );

        $this->app->bind(
            'App\Queries\Ventas\Cliente_EntregaQueryInterface',
            'App\Queries\Ventas\Cliente_EntregaQuery',
        );

        $this->app->bind(
            'App\Repositories\Ventas\VendedorRepositoryInterface',
            'App\Repositories\Ventas\VendedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\VendedorasociadoRepositoryInterface',
            'App\Repositories\Ventas\VendedorasociadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ZonavtaRepositoryInterface',
            'App\Repositories\Ventas\ZonavtaRepository',
        );

        $this->app->bind(
            'App\Queries\Ventas\OrdentrabajoQueryInterface',
            'App\Queries\Ventas\OrdentrabajoQuery',
        );

        $this->app->bind(
            'App\Queries\Contable\AsientoQueryInterface',
            'App\Queries\Contable\AsientoQuery',
        );

        $this->app->bind(
            'App\Queries\Caja\Caja_MovimientoQueryInterface',
            'App\Queries\Caja\Caja_MovimientoQuery',
        );

        $this->app->bind(
            'App\Repositories\Ventas\OrdentrabajoRepositoryInterface',
            'App\Repositories\Ventas\OrdentrabajoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface',
            'App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\VentaRepositoryInterface',
            'App\Repositories\Ventas\VentaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\VentaSerieNumeradorRepositoryInterface',
            'App\Repositories\Ventas\VentaSerieNumeradorRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Venta_EmisionRepositoryInterface',
            'App\Repositories\Ventas\Venta_EmisionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Venta_ImpuestoRepositoryInterface',
            'App\Repositories\Ventas\Venta_ImpuestoRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Impuesto_CuentacontableRepositoryInterface',
            'App\Repositories\Configuracion\Impuesto_CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Venta_ExportacionRepositoryInterface',
            'App\Repositories\Ventas\Venta_ExportacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface',
            'App\Repositories\Ventas\Cliente_CuentacorrienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_Cuentacorriente_AplicacionRepositoryInterface',
            'App\Repositories\Ventas\Cliente_Cuentacorriente_AplicacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface',
            'App\Repositories\Ventas\Ordentrabajo_TareaRepository',
        );

        $this->app->bind(
            'App\Queries\Stock\ArticuloQueryInterface',
            'App\Queries\Stock\ArticuloQuery',
        );

        $this->app->bind(
            'App\Queries\Stock\Articulo_MovimientoQueryInterface',
            'App\Queries\Stock\Articulo_MovimientoQuery',
        );

        $this->app->bind(
            'App\Repositories\Stock\ArticuloRepositoryInterface',
            'App\Repositories\Stock\ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_CajaRepositoryInterface',
            'App\Repositories\Stock\Articulo_CajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_EstadoRepositoryInterface',
            'App\Repositories\Stock\Articulo_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_ArchivoRepositoryInterface',
            'App\Repositories\Stock\Articulo_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_CuentacontableRepositoryInterface',
            'App\Repositories\Stock\Articulo_CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_ProveedorRepositoryInterface',
            'App\Repositories\Stock\Articulo_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Formula_ArticuloRepositoryInterface',
            'App\Repositories\Stock\Formula_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Formula_Articulo_EstadoRepositoryInterface',
            'App\Repositories\Stock\Formula_Articulo_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Formula_Articulo_HijoRepositoryInterface',
            'App\Repositories\Stock\Formula_Articulo_HijoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Formula_Articulo_ArchivoRepositoryInterface',
            'App\Repositories\Stock\Formula_Articulo_ArchivoRepository',
        );

        $this->app->bind(
            'App\Queries\Stock\FormulaArticuloQueryInterface',
            'App\Queries\Stock\FormulaArticuloQuery',
        );

        $this->app->bind(
            'App\Queries\Stock\PrecioQueryInterface',
            'App\Queries\Stock\PrecioQuery',
        );

        $this->app->bind(
            'App\Repositories\Stock\LoteRepositoryInterface',
            'App\Repositories\Stock\LoteRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\MovimientoStockRepositoryInterface',
            'App\Repositories\Stock\MovimientoStockRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface',
            'App\Repositories\Stock\Tipotransaccion_StockRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_CostoRepositoryInterface',
            'App\Repositories\Stock\Articulo_CostoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TransporteRepositoryInterface',
            'App\Repositories\Ventas\TransporteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface',
            'App\Repositories\Ventas\MotivocierrepedidoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TipoempresaClienteRepositoryInterface',
            'App\Repositories\Ventas\TipoempresaClienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface',
            'App\Repositories\Ventas\TiposuspensionclienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\IncotermRepositoryInterface',
            'App\Repositories\Ventas\IncotermRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\CuentacajaRepositoryInterface',
            'App\Repositories\Caja\CuentacajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ConceptogastoRepositoryInterface',
            'App\Repositories\Caja\ConceptogastoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Conceptogasto_CuentacontableRepositoryInterface',
            'App\Repositories\Caja\Conceptogasto_CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\OrigenvoucherRepositoryInterface',
            'App\Repositories\Caja\OrigenvoucherRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\TalonariovoucherRepositoryInterface',
            'App\Repositories\Caja\TalonariovoucherRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\TalonariorendicionRepositoryInterface',
            'App\Repositories\Caja\TalonariorendicionRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\TipocuentacajaRepositoryInterface',
            'App\Repositories\Caja\TipocuentacajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\MediopagoRepositoryInterface',
            'App\Repositories\Caja\MediopagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\UsocuentacajaRepositoryInterface',
            'App\Repositories\Caja\UsocuentacajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ClienteVipCajaRepositoryInterface',
            'App\Repositories\Caja\ClienteVipCajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\TicketCanjeCajaRepositoryInterface',
            'App\Repositories\Caja\TicketCanjeCajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\CategoriaAutomovilRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\CategoriaAutomovilRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\ItemEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\ItemEstacionamientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Bingo\BingoCartonRepositoryInterface',
            'App\Repositories\Caja\Bingo\BingoCartonRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Flash\FlashCajaRepositoryInterface',
            'App\Repositories\Caja\Flash\FlashCajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Flash\FlashParametroRepositoryInterface',
            'App\Repositories\Caja\Flash\FlashParametroRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\AperturaGastoRepositoryInterface',
            'App\Repositories\Caja\AperturaGastoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ConceptoPerdidaRepositoryInterface',
            'App\Repositories\Caja\ConceptoPerdidaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ImputacionPerdidaRepositoryInterface',
            'App\Repositories\Caja\ImputacionPerdidaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\PerdidaPersonalRepositoryInterface',
            'App\Repositories\Caja\PerdidaPersonalRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\RendicionMaquinaRepositoryInterface',
            'App\Repositories\Caja\RendicionMaquinaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\RemesaRepositoryInterface',
            'App\Repositories\Caja\RemesaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\CotizacionTesoreriaRepositoryInterface',
            'App\Repositories\Caja\CotizacionTesoreriaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Bingo\BingoConceptoRendicionRepositoryInterface',
            'App\Repositories\Caja\Bingo\BingoConceptoRendicionRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Bingo\JornadaBingoRepositoryInterface',
            'App\Repositories\Caja\Bingo\JornadaBingoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Bingo\TurnoBingoRepositoryInterface',
            'App\Repositories\Caja\Bingo\TurnoBingoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Bingo\ConfiguracionPuntoventaBingoRepositoryInterface',
            'App\Repositories\Caja\Bingo\ConfiguracionPuntoventaBingoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoItemRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoItemRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\TurnoEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\TurnoEstacionamientoRepository',
        );
        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\DescuentoEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\DescuentoEstacionamientoRepository',
        );
        $this->app->bind(
            'App\Repositories\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoRepositoryInterface',
            'App\Repositories\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\BancoRepositoryInterface',
            'App\Repositories\Caja\BancoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\CajaRepositoryInterface',
            'App\Repositories\Caja\CajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ChequeRepositoryInterface',
            'App\Repositories\Caja\ChequeRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\ChequeraRepositoryInterface',
            'App\Repositories\Caja\ChequeraRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Caja_AsignacionRepositoryInterface',
            'App\Repositories\Caja\Caja_AsignacionRepository',
        );

        $this->app->bind(
            'App\Queries\Caja\Caja_AsignacionQueryInterface',
            'App\Queries\Caja\Caja_AsignacionQuery',
        );

        $this->app->bind(
            'App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface',
            'App\Repositories\Caja\Tipotransaccion_CajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Estadocheque_BancoRepositoryInterface',
            'App\Repositories\Caja\Estadocheque_BancoRepository',
        );

        // Ingesta de facturas por correo: driver según config (imap; futuro graph)
        $this->app->bind(
            \App\Support\Compras\PrecargaProveedor\Mail\MailboxLectorInterface::class,
            function () {
                $driver = (string) config('precarga_comprobante_mail.driver', 'imap');

                return match ($driver) {
                    'imap' => app(\App\Support\Compras\PrecargaProveedor\Mail\ImapMailboxLector::class),
                    default => throw new \RuntimeException("Driver de casilla no soportado: {$driver}"),
                };
            },
        );

        $this->app->bind(
            'App\Repositories\Compras\RequisicionRepositoryInterface',
            'App\Repositories\Compras\RequisicionRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\OrdencompraRepositoryInterface',
            'App\Repositories\Compras\OrdencompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface',
            'App\Repositories\Compras\Ordencompra_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Ordencompra_ArticuloRepositoryInterface',
            'App\Repositories\Compras\Ordencompra_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Ordencompra_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Ordencompra_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_EstadoRepositoryInterface',
            'App\Repositories\Compras\Requisicion_EstadoRepository',
        );
        $this->app->bind(
            'App\Repositories\Compras\CumplimientoRequisicionCompraRepositoryInterface',
            'App\Repositories\Compras\CumplimientoRequisicionCompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_ArticuloRepositoryInterface',
            'App\Repositories\Compras\Requisicion_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Requisicion_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_PresupuestoRepositoryInterface',
            'App\Repositories\Compras\Requisicion_PresupuestoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_Presupuesto_ArticuloRepositoryInterface',
            'App\Repositories\Compras\Requisicion_Presupuesto_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Requisicion_Presupuesto_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Requisicion_Presupuesto_ArchivoRepository',
        );

        $this->app->bind(
            'App\Queries\Compras\RequisicionQueryInterface',
            'App\Queries\Compras\RequisicionQuery',
        );

        $this->app->bind(
            'App\Repositories\Compras\Listaprecio_ProveedorRepositoryInterface',
            'App\Repositories\Compras\Listaprecio_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Listaprecio_Proveedor_EstadoRepositoryInterface',
            'App\Repositories\Compras\Listaprecio_Proveedor_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepositoryInterface',
            'App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Listaprecio_Proveedor_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Listaprecio_Proveedor_ArchivoRepository',
        );

        $this->app->bind(
            'App\Queries\Compras\Listaprecio_ProveedorQueryInterface',
            'App\Queries\Compras\Listaprecio_ProveedorQuery',
        );

        $this->app->bind(
            'App\Repositories\Compras\CondicionpagoRepositoryInterface',
            'App\Repositories\Compras\CondicionpagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\CondicionpagocuotaRepositoryInterface',
            'App\Repositories\Compras\CondicionpagocuotaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\CondicioncompraRepositoryInterface',
            'App\Repositories\Compras\CondicioncompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\CondicionentregaRepositoryInterface',
            'App\Repositories\Compras\CondicionentregaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\TipoempresaRepositoryInterface',
            'App\Repositories\Compras\TipoempresaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Tiposervicio_ProveedorRepositoryInterface',
            'App\Repositories\Compras\Tiposervicio_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\EncuestaRepositoryInterface',
            'App\Repositories\Compras\EncuestaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Encuesta_PreguntaRepositoryInterface',
            'App\Repositories\Compras\Encuesta_PreguntaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\RetenciongananciaRepositoryInterface',
            'App\Repositories\Compras\RetenciongananciaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Retencionganancia_EscalaRepositoryInterface',
            'App\Repositories\Compras\Retencionganancia_EscalaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\RetencionivaRepositoryInterface',
            'App\Repositories\Compras\RetencionivaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\RetencionsussRepositoryInterface',
            'App\Repositories\Compras\RetencionsussRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\RetencionIIBBRepositoryInterface',
            'App\Repositories\Compras\RetencionIIBBRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\RetencionIIBB_CondicionRepositoryInterface',
            'App\Repositories\Compras\RetencionIIBB_CondicionRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\TiposuspensionproveedorRepositoryInterface',
            'App\Repositories\Compras\TiposuspensionproveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\ProveedorRepositoryInterface',
            'App\Repositories\Compras\ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_FormapagoRepositoryInterface',
            'App\Repositories\Compras\Proveedor_FormapagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_ServicioRepositoryInterface',
            'App\Repositories\Compras\Proveedor_ServicioRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_ExclusionRepositoryInterface',
            'App\Repositories\Compras\Proveedor_ExclusionRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Proveedor_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_Documento_FiscalRepositoryInterface',
            'App\Repositories\Compras\Proveedor_Documento_FiscalRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_EncuestaRepositoryInterface',
            'App\Repositories\Compras\Proveedor_EncuestaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_Encuesta_PreguntaRepositoryInterface',
            'App\Repositories\Compras\Proveedor_Encuesta_PreguntaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface',
            'App\Repositories\Compras\Proveedor_CuentacorrienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\PagoproveedorRepositoryInterface',
            'App\Repositories\Compras\PagoproveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\PropuestaPagoRepositoryInterface',
            'App\Repositories\Compras\PropuestaPagoRepository',
        );

        $this->app->bind(
            'App\Queries\Compras\ProveedorQueryInterface',
            'App\Queries\Compras\ProveedorQuery',
        );

        $this->app->bind(
            'App\Repositories\Compras\Columna_IvacompraRepositoryInterface',
            'App\Repositories\Compras\Columna_IvacompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Concepto_IvacompraRepositoryInterface',
            'App\Repositories\Compras\Concepto_IvacompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Concepto_Ivacompra_CondicionivaRepositoryInterface',
            'App\Repositories\Compras\Concepto_Ivacompra_CondicionivaRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface',
            'App\Repositories\Compras\Tipotransaccion_CompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Tipotransaccion_Compra_CentrocostoRepositoryInterface',
            'App\Repositories\Compras\Tipotransaccion_Compra_CentrocostoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface',
            'App\Repositories\Compras\Tipotransaccion_Compra_Concepto_IvacompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface',
            'App\Repositories\Compras\Precarga_Comprobante_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface',
            'App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface',
            'App\Repositories\Compras\Comprobante_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Comprobante_Proveedor_ConceptoRepositoryInterface',
            'App\Repositories\Compras\Comprobante_Proveedor_ConceptoRepository',
        );

        $this->app->bind(
            'App\Repositories\Compras\Comprobante_Proveedor_ArchivoRepositoryInterface',
            'App\Repositories\Compras\Comprobante_Proveedor_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\FormapagoRepositoryInterface',
            'App\Repositories\Ventas\FormapagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TipotransaccionRepositoryInterface',
            'App\Repositories\Ventas\TipotransaccionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\PuntoventaRepositoryInterface',
            'App\Repositories\Ventas\PuntoventaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\MesaGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\MesaGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\UbicacionGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\DescuentoGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\DescuentoGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\MozoGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\MozoGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ClienteVipGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\ClienteVipGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\AreaComandaGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\AreaComandaGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TotemWaitryGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\TotemWaitryGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\MaquinavendingRepositoryInterface',
            'App\Repositories\Ventas\MaquinavendingRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ViandaTipoMenuRepositoryInterface',
            'App\Repositories\Ventas\ViandaTipoMenuRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ViandaUsuarioRepositoryInterface',
            'App\Repositories\Ventas\ViandaUsuarioRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ConfiguracionTerminalViandaRepositoryInterface',
            'App\Repositories\Ventas\ConfiguracionTerminalViandaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\MaquinavendingRendicionRepositoryInterface',
            'App\Repositories\Ventas\MaquinavendingRendicionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\TurnoGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\TurnoGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CategoriafidelidadGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\CategoriafidelidadGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CategoriafidelidadArticuloGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\CategoriafidelidadArticuloGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CategoriafidelidadEntregaGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\CategoriafidelidadEntregaGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface',
            'App\Repositories\Ventas\JornadaGastronomiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\TareaRepositoryInterface',
            'App\Repositories\Produccion\TareaRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\OperacionRepositoryInterface',
            'App\Repositories\Produccion\OperacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\EmpleadoRepositoryInterface',
            'App\Repositories\Produccion\EmpleadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\MovimientoOrdentrabajoRepositoryInterface',
            'App\Repositories\Produccion\MovimientoOrdentrabajoRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\SalidaRepositoryInterface',
            'App\Repositories\Configuracion\SalidaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\UbicacionImpresoraRepositoryInterface',
            'App\Repositories\Configuracion\UbicacionImpresoraRepository',
        );
        $this->app->bind(
            'App\Repositories\Configuracion\SistemaNumeradorRepositoryInterface',
            'App\Repositories\Configuracion\SistemaNumeradorRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\UsoSalidaImpresoraRepositoryInterface',
            'App\Repositories\Configuracion\UsoSalidaImpresoraRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\SeteosalidaRepositoryInterface',
            'App\Repositories\Configuracion\SeteosalidaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_IibbRepositoryInterface',
            'App\Repositories\Configuracion\Padron_IibbRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface',
            'App\Repositories\Configuracion\Padron_Iibb_TasaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_Iibb_ArbaRepositoryInterface',
            'App\Repositories\Configuracion\Padron_Iibb_ArbaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_Iibb_CabaRepositoryInterface',
            'App\Repositories\Configuracion\Padron_Iibb_CabaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepositoryInterface',
            'App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\PadronarbaRepositoryInterface',
            'App\Repositories\Configuracion\PadronarbaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\PadroncabaRepositoryInterface',
            'App\Repositories\Configuracion\PadroncabaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\MonedaRepositoryInterface',
            'App\Repositories\Configuracion\MonedaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\CotizacionRepositoryInterface',
            'App\Repositories\Configuracion\CotizacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\TipodocumentoRepositoryInterface',
            'App\Repositories\Configuracion\TipodocumentoRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface',
            'App\Repositories\Configuracion\Actividad_ArcaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\ModeloetiquetaRepositoryInterface',
            'App\Repositories\Configuracion\ModeloetiquetaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\SeteoModeloetiquetaRepositoryInterface',
            'App\Repositories\Configuracion\SeteoModeloetiquetaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Retencion_CobranzaRepositoryInterface',
            'App\Repositories\Configuracion\Retencion_CobranzaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Retencion_Cobranza_CuentacontableRepositoryInterface',
            'App\Repositories\Configuracion\Retencion_Cobranza_CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Sicore_ConfigRepositoryInterface',
            'App\Repositories\Contable\Sicore_ConfigRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Sicore_Config_CuentaRepositoryInterface',
            'App\Repositories\Contable\Sicore_Config_CuentaRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Iibb_Presentacion_ConfigRepositoryInterface',
            'App\Repositories\Contable\Iibb_Presentacion_ConfigRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Iibb_Presentacion_Config_CuentaRepositoryInterface',
            'App\Repositories\Contable\Iibb_Presentacion_Config_CuentaRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Suss_Presentacion_ConfigRepositoryInterface',
            'App\Repositories\Contable\Suss_Presentacion_ConfigRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Suss_Presentacion_Config_CuentaRepositoryInterface',
            'App\Repositories\Contable\Suss_Presentacion_Config_CuentaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\OficinacompraRepositoryInterface',
            'App\Repositories\Configuracion\OficinacompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\PeriodicidadcompraRepositoryInterface',
            'App\Repositories\Configuracion\PeriodicidadcompraRepository',
        );

        $this->app->bind(
            'App\Queries\Configuracion\CotizacionQueryInterface',
            'App\Queries\Configuracion\CotizacionQuery',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface',
            'App\Repositories\Configuracion\Cotizacion_MonedaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Arbolaprobacion_OcTriggerRepositoryInterface',
            'App\Repositories\Configuracion\Arbolaprobacion_OcTriggerRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface',
            'App\Repositories\Configuracion\ArbolaprobacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Arbolaprobacion_NivelRepositoryInterface',
            'App\Repositories\Configuracion\Arbolaprobacion_NivelRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface',
            'App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\ClasematerialRepositoryInterface',
            'App\Repositories\Stock\ClasematerialRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\LineamaterialRepositoryInterface',
            'App\Repositories\Stock\LineamaterialRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\GestioncompraRepositoryInterface',
            'App\Repositories\Stock\GestioncompraRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\MaterialcapelladaRepositoryInterface',
            'App\Repositories\Stock\MaterialcapelladaRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\MaterialavioRepositoryInterface',
            'App\Repositories\Stock\MaterialavioRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_MovimientoRepositoryInterface',
            'App\Repositories\Stock\Articulo_MovimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_Movimiento_TalleRepositoryInterface',
            'App\Repositories\Stock\Articulo_Movimiento_TalleRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface',
            'App\Repositories\Stock\Articulo_Saldo_DepositoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Cuentacontable_Saldo_MesRepositoryInterface',
            'App\Repositories\Contable\Cuentacontable_Saldo_MesRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Deposito_AdministradorRepositoryInterface',
            'App\Repositories\Stock\Deposito_AdministradorRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\DepmaeRepositoryInterface',
            'App\Repositories\Stock\DepmaeRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\PrestamoRepositoryInterface',
            'App\Repositories\Stock\PrestamoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface',
            'App\Repositories\Stock\Recepcion_ProveedorRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\RecuentoRepositoryInterface',
            'App\Repositories\Stock\RecuentoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Recuento_ItemRepositoryInterface',
            'App\Repositories\Stock\Recuento_ItemRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\Recuento_ArchivoRepositoryInterface',
            'App\Repositories\Stock\Recuento_ArchivoRepository',
        );

        $this->app->bind(
            'App\Services\Ventas\PedidoService',
        );

        $this->app->bind(
            'App\Repositories\Ventas\PedidoRepositoryInterface',
            'App\Repositories\Ventas\PedidoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface',
            'App\Repositories\Ventas\Pedido_CombinacionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface',
            'App\Repositories\Ventas\Pedido_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_Articulo_CajaRepositoryInterface',
            'App\Repositories\Ventas\Pedido_Articulo_CajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_Combinacion_EstadoRepositoryInterface',
            'App\Repositories\Ventas\Pedido_Combinacion_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_Articulo_EstadoRepositoryInterface',
            'App\Repositories\Ventas\Pedido_Articulo_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface',
            'App\Repositories\Ventas\Pedido_Combinacion_TalleRepository',
        );

        $this->app->bind(
            'App\Queries\Ventas\PedidoQueryInterface',
            'App\Queries\Ventas\PedidoQuery',
        );

        $this->app->bind(
            'App\Services\Ventas\RemitoService',
        );

        $this->app->bind(
            'App\Repositories\Ventas\RemitoRepositoryInterface',
            'App\Repositories\Ventas\RemitoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Remito_ArticuloRepositoryInterface',
            'App\Repositories\Ventas\Remito_ArticuloRepository',
        );

        $this->app->bind(
            'App\Queries\Ventas\RemitoQueryInterface',
            'App\Queries\Ventas\RemitoQuery',
        );

        $this->app->bind(
            'App\Services\Ventas\RemitoListadoPdfService',
        );

        $this->app->bind(
            'App\Services\Ventas\AsignacionRemitoFacturaService',
        );

        $this->app->bind(
            'App\Queries\Ventas\Pedido_CombinacionQueryInterface',
            'App\Queries\Ventas\Pedido_CombinacionQuery',
        );

        $this->app->bind(
            'App\Queries\Ventas\Pedido_ArticuloQueryInterface',
            'App\Queries\Ventas\Pedido_ArticuloQuery',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\CondicionivaRepositoryInterface',
            'App\Repositories\Configuracion\CondicionivaRepository',
        );

        $this->app->bind(
            'App\Services\Configuracion\IIBBService',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\CondicionIIBBRepositoryInterface',
            'App\Repositories\Configuracion\CondicionIIBBRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\EmpresaRepositoryInterface',
            'App\Repositories\Configuracion\EmpresaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\ProvinciaRepositoryInterface',
            'App\Repositories\Configuracion\ProvinciaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\PaisRepositoryInterface',
            'App\Repositories\Configuracion\PaisRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\LocalidadRepositoryInterface',
            'App\Repositories\Configuracion\LocalidadRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\FeriadoRepositoryInterface',
            'App\Repositories\Configuracion\FeriadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\ImpuestoRepositoryInterface',
            'App\Repositories\Configuracion\ImpuestoRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\RegimenPercepcionRepositoryInterface',
            'App\Repositories\Configuracion\RegimenPercepcionRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_MipymeRepositoryInterface',
            'App\Repositories\Configuracion\Padron_MipymeRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Padron_ExclusionpercepcionivaRepositoryInterface',
            'App\Repositories\Configuracion\Padron_ExclusionpercepcionivaRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Retencionimpositiva_ArcaRepositoryInterface',
            'App\Repositories\Contable\Retencionimpositiva_ArcaRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Provincia_TasaiibbRepositoryInterface',
            'App\Repositories\Configuracion\Provincia_TasaiibbRepository',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\Provincia_CuentacontableiibbRepositoryInterface',
            'App\Repositories\Configuracion\Provincia_CuentacontableiibbRepository',
        );

        $this->app->bind(
            'App\Services\Configuracion\ImpuestoService',
        );

        $this->app->bind(
            'App\Repositories\Contable\CentrocostoRepositoryInterface',
            'App\Repositories\Contable\CentrocostoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\BienUsoRepositoryInterface',
            'App\Repositories\Contable\BienUsoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\CuentacontableRepositoryInterface',
            'App\Repositories\Contable\CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Cuentacontable_CentrocostoRepositoryInterface',
            'App\Repositories\Contable\Cuentacontable_CentrocostoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\TipoasientoRepositoryInterface',
            'App\Repositories\Contable\TipoasientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\AsientoRepositoryInterface',
            'App\Repositories\Contable\AsientoRepository',
        );

        // Una sola lectura Anita del período (mes) compartida entre mayor por concepto y EFE.
        $this->app->singleton(
            \App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader::class
        );

        $this->app->bind(
            'App\Repositories\Contable\Asiento_MovimientoRepositoryInterface',
            'App\Repositories\Contable\Asiento_MovimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Asiento_ArchivoRepositoryInterface',
            'App\Repositories\Contable\Asiento_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Contable\Usuario_CuentacontableRepositoryInterface',
            'App\Repositories\Contable\Usuario_CuentacontableRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\TiposervicioterrestreRepositoryInterface',
            'App\Repositories\Receptivo\TiposervicioterrestreRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\ServicioterrestreRepositoryInterface',
            'App\Repositories\Receptivo\ServicioterrestreRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\Proveedor_ServicioterrestreRepositoryInterface',
            'App\Repositories\Receptivo\Proveedor_ServicioterrestreRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\IdiomaRepositoryInterface',
            'App\Repositories\Receptivo\IdiomaRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\MovilRepositoryInterface',
            'App\Repositories\Receptivo\MovilRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\GuiaRepositoryInterface',
            'App\Repositories\Receptivo\GuiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\Guia_IdiomaRepositoryInterface',
            'App\Repositories\Receptivo\Guia_IdiomaRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\Comision_ServicioterrestreRepositoryInterface',
            'App\Repositories\Receptivo\Comision_ServicioterrestreRepository',
        );

        $this->app->bind(
            'App\Repositories\Receptivo\ReservaRepositoryInterface',
            'App\Repositories\Receptivo\ReservaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\VoucherRepositoryInterface',
            'App\Repositories\Caja\VoucherRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Voucher_GuiaRepositoryInterface',
            'App\Repositories\Caja\Voucher_GuiaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Voucher_ReservaRepositoryInterface',
            'App\Repositories\Caja\Voucher_ReservaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Voucher_FormapagoRepositoryInterface',
            'App\Repositories\Caja\Voucher_FormapagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\RendicionreceptivoRepositoryInterface',
            'App\Repositories\Caja\RendicionreceptivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Rendicionreceptivo_Caja_MovimientoRepositoryInterface',
            'App\Repositories\Caja\Rendicionreceptivo_Caja_MovimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Rendicionreceptivo_VoucherRepositoryInterface',
            'App\Repositories\Caja\Rendicionreceptivo_VoucherRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Rendicionreceptivo_FormapagoRepositoryInterface',
            'App\Repositories\Caja\Rendicionreceptivo_FormapagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Rendicionreceptivo_ComisionRepositoryInterface',
            'App\Repositories\Caja\Rendicionreceptivo_ComisionRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Rendicionreceptivo_AdelantoRepositoryInterface',
            'App\Repositories\Caja\Rendicionreceptivo_AdelantoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Caja_MovimientoRepositoryInterface',
            'App\Repositories\Caja\Caja_MovimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface',
            'App\Repositories\Caja\Caja_Movimiento_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface',
            'App\Repositories\Caja\Caja_Movimiento_CuentacajaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Caja_Movimiento_ArchivoRepositoryInterface',
            'App\Repositories\Caja\Caja_Movimiento_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\CobranzaRepositoryInterface',
            'App\Repositories\Caja\CobranzaRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Cobranza_EstadoRepositoryInterface',
            'App\Repositories\Caja\Cobranza_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Cobranza_ComprobanteRepositoryInterface',
            'App\Repositories\Caja\Cobranza_ComprobanteRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Cobranza_ArchivoRepositoryInterface',
            'App\Repositories\Caja\Cobranza_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Caja\Cobranza_RetencionRepositoryInterface',
            'App\Repositories\Caja\Cobranza_RetencionRepository',
        );

        $this->app->bind(
            'App\Queries\Caja\CobranzaQueryInterface',
            'App\Queries\Caja\CobranzaQuery',
        );

        // Modulo de tickets
        $this->app->bind(
            'App\Repositories\Ticket\Turno_TicketRepositoryInterface',
            'App\Repositories\Ticket\Turno_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\AreadestinoRepositoryInterface',
            'App\Repositories\Ticket\AreadestinoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Tarea_TicketRepositoryInterface',
            'App\Repositories\Ticket\Tarea_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Sector_TicketRepositoryInterface',
            'App\Repositories\Ticket\Sector_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Tecnico_TicketRepositoryInterface',
            'App\Repositories\Ticket\Tecnico_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Categoria_TicketRepositoryInterface',
            'App\Repositories\Ticket\Categoria_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Subcategoria_TicketRepositoryInterface',
            'App\Repositories\Ticket\Subcategoria_TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\TicketRepositoryInterface',
            'App\Repositories\Ticket\TicketRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Ticket_EstadoRepositoryInterface',
            'App\Repositories\Ticket\Ticket_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Ticket_ArchivoRepositoryInterface',
            'App\Repositories\Ticket\Ticket_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Ticket_TareaRepositoryInterface',
            'App\Repositories\Ticket\Ticket_TareaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Ticket_ArticuloRepositoryInterface',
            'App\Repositories\Ticket\Ticket_ArticuloRepository',
        );

        $this->app->bind(
            'App\Repositories\Ticket\Ticket_Tarea_NovedadRepositoryInterface',
            'App\Repositories\Ticket\Ticket_Tarea_NovedadRepository',
        );

        $this->app->bind(
            'App\Queries\Ticket\TicketQueryInterface',
            'App\Queries\Ticket\TicketQuery',
        );

        $this->app->bind(
            'App\Repositories\Configuracion\SalaRepositoryInterface',
            'App\Repositories\Configuracion\SalaRepository',
        );

        // Modulo de Sala
        $this->app->bind(
            'App\Repositories\Sala\ZonaSalaRepositoryInterface',
            'App\Repositories\Sala\ZonaSalaRepository',
        );

        $this->app->bind(
            'App\Repositories\Sala\PrioridadSalaRepositoryInterface',
            'App\Repositories\Sala\PrioridadSalaRepository',
        );

        $this->app->bind(
            'App\Repositories\Sala\TecnicoLaboratorioRepositoryInterface',
            'App\Repositories\Sala\TecnicoLaboratorioRepository',
        );

        $this->app->bind(
            'App\Repositories\Sala\RequisicionSalaRepositoryInterface',
            'App\Repositories\Sala\RequisicionSalaRepository',
        );
        $this->app->bind(
            'App\Repositories\Sala\RequisicionSalaArticuloRepositoryInterface',
            'App\Repositories\Sala\RequisicionSalaArticuloRepository',
        );
        $this->app->bind(
            'App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface',
            'App\Repositories\Sala\RequisicionSalaEstadoRepository',
        );
        $this->app->bind(
            'App\Repositories\Sala\RequisicionSalaArchivoRepositoryInterface',
            'App\Repositories\Sala\RequisicionSalaArchivoRepository',
        );
        $this->app->bind(
            'App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface',
            'App\Repositories\Sala\CumplimientoRequisicionSalaRepository',
        );
        $this->app->bind(
            'App\Queries\Sala\RequisicionSalaQueryInterface',
            'App\Queries\Sala\RequisicionSalaQuery',
        );

        // Modulo UIF
        $this->app->bind(
            'App\Repositories\Uif\Actividad_UifRepositoryInterface',
            'App\Repositories\Uif\Actividad_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Pais_UifRepositoryInterface',
            'App\Repositories\Uif\Pais_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Pep_UifRepositoryInterface',
            'App\Repositories\Uif\Pep_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\So_UifRepositoryInterface',
            'App\Repositories\Uif\So_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Provincia_UifRepositoryInterface',
            'App\Repositories\Uif\Provincia_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Frecuencia_UifRepositoryInterface',
            'App\Repositories\Uif\Frecuencia_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Juego_UifRepositoryInterface',
            'App\Repositories\Uif\Juego_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Inusualidad_UifRepositoryInterface',
            'App\Repositories\Uif\Inusualidad_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Monto_UifRepositoryInterface',
            'App\Repositories\Uif\Monto_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Factorriesgo_UifRepositoryInterface',
            'App\Repositories\Uif\Factorriesgo_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Puntaje_UifRepositoryInterface',
            'App\Repositories\Uif\Puntaje_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Localidad_UifRepositoryInterface',
            'App\Repositories\Uif\Localidad_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Profesion_UifRepositoryInterface',
            'App\Repositories\Uif\Profesion_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Nivelsocioeconomico_UifRepositoryInterface',
            'App\Repositories\Uif\Nivelsocioeconomico_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Estadocivil_UifRepositoryInterface',
            'App\Repositories\Uif\Estadocivil_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_Archivo_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_Archivo_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_Premio_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_Premio_Archivo_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_Premio_Archivo_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_Riesgo_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_Riesgo_UifRepository',
        );

        $this->app->bind(
            'App\Repositories\Uif\Cliente_Congelado_UifRepositoryInterface',
            'App\Repositories\Uif\Cliente_Congelado_UifRepository',
        );

        // Modulo ordenes de venta
        $this->app->bind(
            'App\Repositories\Ordenventa\OrdenventaRepositoryInterface',
            'App\Repositories\Ordenventa\OrdenventaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Ordenventa_CuotaRepositoryInterface',
            'App\Repositories\Ordenventa\Ordenventa_CuotaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Ordenventa_EstadoRepositoryInterface',
            'App\Repositories\Ordenventa\Ordenventa_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Ordenventa_ArchivoRepositoryInterface',
            'App\Repositories\Ordenventa\Ordenventa_ArchivoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Ordenventa_ConceptoRepositoryInterface',
            'App\Repositories\Ordenventa\Ordenventa_ConceptoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Concepto_OrdenventaRepositoryInterface',
            'App\Repositories\Ordenventa\Concepto_OrdenventaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ordenventa\Concepto_Cuentacontable_OrdenventaRepositoryInterface',
            'App\Repositories\Ordenventa\Concepto_Cuentacontable_OrdenventaRepository',
        );

        $this->app->bind(
            'App\Queries\Ordenventa\OrdenventaQueryInterface',
            'App\Queries\Ordenventa\OrdenventaQuery',
        );

        // Modulo solicitudes de pago
        $this->app->bind(
            'App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface',
            'App\Repositories\Solicitudpago\Sector_SolicitudpagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Solicitudpago\FormapagosolRepositoryInterface',
            'App\Repositories\Solicitudpago\FormapagosolRepository',
        );

        $this->app->bind(
            'App\Repositories\Solicitudpago\Concepto_SolicitudpagoRepositoryInterface',
            'App\Repositories\Solicitudpago\Concepto_SolicitudpagoRepository',
        );

        $this->app->bind(
            'App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface',
            'App\Repositories\Solicitudpago\SolicitudpagoRepository',
        );

        // Modulo sueldos y jornales
        $this->app->bind(
            'App\Repositories\Sueldos\Nombrebase_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Nombrebase_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Categoria_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Categoria_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Obrasocial_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Obrasocial_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Sindicato_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Sindicato_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Fallocaja_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Fallocaja_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Agrupamiento_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Agrupamiento_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Lugartrabajo_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Lugartrabajo_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Motivoegreso_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Motivoegreso_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Art_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Art_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Vacacion_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Vacacion_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Concepto_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Concepto_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Parametro_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Parametro_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Antiguedad_Tabla_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Antiguedad_Tabla_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Acumulador_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Acumulador_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Ganancia_Linea_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Ganancia_Linea_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Liquidacion_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Liquidacion_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Novedad_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Grupo_Concepto_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Grupo_Concepto_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Tipo_Ausencia_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Tipo_Ausencia_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Tipo_Sancion_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Tipo_Sancion_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Motivo_Sancion_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Motivo_Sancion_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Empleado_Sancion_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Empleado_Sancion_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Prenda_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Prenda_SueldosRepository',
        );

        $this->app->bind(
            'App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface',
            'App\Repositories\Sueldos\Empleado_SueldosRepository',
        );

        // Bierzo

        $this->app->bind(
            'App\Repositories\Ventas\AbastoRepositoryInterface',
            'App\Repositories\Ventas\AbastoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CoeficienteRepositoryInterface',
            'App\Repositories\Ventas\CoeficienteRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_SeguimientoRepositoryInterface',
            'App\Repositories\Ventas\Cliente_SeguimientoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\Cliente_Articulo_SuspendidoRepositoryInterface',
            'App\Repositories\Ventas\Cliente_Articulo_SuspendidoRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\DistribuidorRepositoryInterface',
            'App\Repositories\Ventas\DistribuidorRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CobradorRepositoryInterface',
            'App\Repositories\Ventas\CobradorRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CamionRepositoryInterface',
            'App\Repositories\Ventas\CamionRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\ComprobanteImpresionProgramaRepositoryInterface',
            'App\Repositories\Ventas\ComprobanteImpresionProgramaRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\CaiRepositoryInterface',
            'App\Repositories\Ventas\CaiRepository',
        );

        $this->app->bind(
            'App\Repositories\Ventas\DescuentoventaRepositoryInterface',
            'App\Repositories\Ventas\DescuentoventaRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\EnvasesenasaRepositoryInterface',
            'App\Repositories\Stock\EnvasesenasaRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\CodigosenasaRepositoryInterface',
            'App\Repositories\Stock\CodigosenasaRepository',
        );

        // Produccion

        $this->app->bind(
            'App\Repositories\Produccion\TipoproduccionRepositoryInterface',
            'App\Repositories\Produccion\TipoproduccionRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\SectorselladoRepositoryInterface',
            'App\Repositories\Produccion\SectorselladoRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\SalaproduccionRepositoryInterface',
            'App\Repositories\Produccion\SalaproduccionRepository',
        );

        // Modulo de presupuesto
        $this->app->bind(
            'App\Repositories\Presupuesto\PresupuestoRepositoryInterface',
            'App\Repositories\Presupuesto\PresupuestoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Presupuesto_EscenarioRepositoryInterface',
            'App\Repositories\Presupuesto\Presupuesto_EscenarioRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\CapexRepositoryInterface',
            'App\Repositories\Presupuesto\CapexRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Capex_PartidaRepositoryInterface',
            'App\Repositories\Presupuesto\Capex_PartidaRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Capex_Partida_MontoRepositoryInterface',
            'App\Repositories\Presupuesto\Capex_Partida_MontoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Capex_EstadoRepositoryInterface',
            'App\Repositories\Presupuesto\Capex_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Capex_ArchivoRepositoryInterface',
            'App\Repositories\Presupuesto\Capex_ArchivoRepository',
        );

        $this->app->bind(
            'App\Queries\Presupuesto\CapexQueryInterface',
            'App\Queries\Presupuesto\CapexQuery',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\PartidagastoRepositoryInterface',
            'App\Repositories\Presupuesto\PartidagastoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Partidagasto_MontoRepositoryInterface',
            'App\Repositories\Presupuesto\Partidagasto_MontoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Partidagasto_EstadoRepositoryInterface',
            'App\Repositories\Presupuesto\Partidagasto_EstadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Presupuesto\Partidagasto_ArchivoRepositoryInterface',
            'App\Repositories\Presupuesto\Partidagasto_ArchivoRepository',
        );

        $this->app->bind(
            'App\Queries\Presupuesto\PartidagastoQueryInterface',
            'App\Queries\Presupuesto\PartidagastoQuery',
        );

        // Frasle

        // Produccion

        $this->app->bind(
            'App\Repositories\Produccion\LineallenadoRepositoryInterface',
            'App\Repositories\Produccion\LineallenadoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\TipoproductoRepositoryInterface',
            'App\Repositories\Stock\TipoproductoRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\CapacidadRepositoryInterface',
            'App\Repositories\Stock\CapacidadRepository',
        );

        $this->app->bind(
            'App\Repositories\Stock\TipoliquidoRepositoryInterface',
            'App\Repositories\Stock\TipoliquidoRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\ProvienebinRepositoryInterface',
            'App\Repositories\Produccion\ProvienebinRepository',
        );

        $this->app->bind(
            'App\Repositories\Produccion\OrdenproduccionRepositoryInterface',
            'App\Repositories\Produccion\OrdenproduccionRepository',
        );

        $this->app->bind(
            'App\Repositories\Seguridad\IngresoProveedorRepositoryInterface',
            'App\Repositories\Seguridad\IngresoProveedorRepository',
        );

        $this->registrarPlataformaIa();
    }

    /**
     * Plataforma IA transversal (config/ai.php): drivers, gateway, política y registro de skills.
     */
    private function registrarPlataformaIa(): void
    {
        $this->app->singleton(\App\Services\Ai\AiPolicy::class);
        $this->app->singleton(\App\Services\Ai\AiDecisionLogger::class);

        // Drivers de modelo: agregar aquí cada nuevo backend.
        $this->app->tag([
            \App\Services\Ai\Drivers\OllamaAiDriver::class,
            \App\Services\Ai\Drivers\HttpAiDriver::class,
        ], 'ai.drivers');

        $this->app->singleton(\App\Services\Ai\AiGateway::class, function ($app) {
            return new \App\Services\Ai\AiGateway(
                $app->tagged('ai.drivers'),
                $app->make(\App\Services\Ai\AiPolicy::class),
            );
        });

        // Skills concretas: etiquetar con 'ai.skills' a medida que se implementen.
        $this->app->tag([
            \App\Services\Compras\Ai\ExtraerFacturaProveedorSkill::class,
            \App\Services\Caja\Ai\ExtraerComprobanteIvaCajaSkill::class,
            \App\Services\Stock\Ai\ExtraerRemitoRecepcionSkill::class,
            \App\Services\Contable\Ai\SugerirParesConciliacionBancariaSkill::class,
            \App\Services\Configuracion\Ai\ExplicarContextoArbolAprobacionSkill::class,
            \App\Services\Ventas\Ai\ExplicarDiferenciasConciliacionTurnoGastronomiaSkill::class,
            \App\Services\Ai\ConsultarContextoOperativoSkill::class,
            \App\Services\Ai\SugerirPedidoConsumoSectorSkill::class,
        ], 'ai.skills');

        $this->app->singleton(\App\Services\Ai\Skills\AiSkillRegistry::class, function ($app) {
            return new \App\Services\Ai\Skills\AiSkillRegistry(
                $app->tagged('ai.skills'),
                $app->make(\App\Services\Ai\AiPolicy::class),
            );
        });
    }
}
