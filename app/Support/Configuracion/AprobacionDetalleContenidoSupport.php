<?php

namespace App\Support\Configuracion;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\PropuestaPago;
use App\Models\Compras\Requisicion;
use App\Models\Contable\Asiento;
use App\Models\Contable\Centrocosto;
use App\Models\Sala\RequisicionSala;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Stock\Articulo;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use App\Models\Ventas\PedidoInterforming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Arma el contenido que el aprobador necesita ver en el panel de Mis aprobaciones:
 * cabecera útil (quién pidió, centro de costo, comentario) y renglones del documento.
 */
final class AprobacionDetalleContenidoSupport
{
    private const MAX_RENGLONES = 40;

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    public static function desdeArbol(string $tipo, object $documento): ?array
    {
        $tipo = strtoupper(trim($tipo));

        return match ($tipo) {
            'RE' => $documento instanceof Requisicion ? self::requisicion($documento) : null,
            'OC', 'SU' => $documento instanceof Ordencompra ? self::ordencompra($documento) : null,
            'RS' => $documento instanceof RequisicionSala ? self::requisicionSala($documento) : null,
            'PE' => $documento instanceof PedidoInterforming ? self::pedido($documento) : null,
            'SP' => $documento instanceof Solicitudpago ? self::solicitudPago($documento) : null,
            'PP' => $documento instanceof PropuestaPago ? self::propuestaPago($documento) : null,
            'AR' => $documento instanceof Articulo ? self::articulo($documento) : null,
            default => null,
        };
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    public static function desdeAsiento(?Asiento $asiento): ?array
    {
        if (! $asiento) {
            return null;
        }

        self::preparar($asiento, ['asiento_movimientos.cuentacontables', 'asiento_movimientos.centrocostos', 'asiento_movimientos.monedas']);

        $notas = [];
        self::nota($notas, 'Observación', (string) ($asiento->observacion ?? ''));

        $renglones = [];
        foreach (self::coleccion($asiento, 'asiento_movimientos') as $mov) {
            $cuenta = self::rel($mov, 'cuentacontables');
            $titulo = self::codigoNombre($cuenta);
            if ($titulo === '') {
                $titulo = 'Cuenta';
            }
            $abrev = self::abreviatura(self::rel($mov, 'monedas'));
            $monto = (float) ($mov->monto ?? 0);
            $lineas = [];
            $cc = self::codigoNombre(self::rel($mov, 'centrocostos'));
            if ($cc !== '') {
                $lineas[] = 'Centro de costo '.$cc;
            }
            $obs = trim((string) ($mov->observacion ?? ''));
            if ($obs !== '') {
                $lineas[] = $obs;
            }
            $renglones[] = self::renglon($titulo, '', self::dinero($monto).($abrev !== '' ? ' '.$abrev : ''), $lineas);
        }

        return self::empaquetar('Movimientos del asiento', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    public static function desdeTransferencia(?Transferencia_Mercaderia $tm): ?array
    {
        if (! $tm) {
            return null;
        }

        self::preparar($tm, [
            'articulos.articuloOrigen',
            'articulos.articuloDestino',
            'depositoOrigen',
            'depositoDestino',
            'centrocostoDestino',
        ]);

        $notas = [];
        $origen = self::codigoNombre(self::rel($tm, 'depositoOrigen'));
        $destino = self::codigoNombre(self::rel($tm, 'depositoDestino'));
        if ($origen !== '' || $destino !== '') {
            self::nota($notas, 'Depósitos', trim($origen.' → '.$destino, ' →'));
        }
        $cc = self::codigoNombre(self::rel($tm, 'centrocostoDestino'));
        self::nota($notas, 'Centro de costo destino', $cc);
        self::nota($notas, 'Observación', (string) ($tm->observacion ?? ''));

        $renglones = [];
        foreach (self::coleccion($tm, 'articulos') as $lin) {
            $art = self::rel($lin, 'articuloOrigen') ?: self::rel($lin, 'articuloDestino');
            $titulo = self::tituloArticulo($art, trim((string) ($lin->numeroparte ?? '')));
            $cant = (float) ($lin->cantidad_origen ?? $lin->cantidad_destino ?? 0);
            $lineas = [];
            $parte = trim((string) ($lin->numeroparte ?? ''));
            if ($parte !== '' && ! str_contains($titulo, $parte)) {
                $lineas[] = 'Parte '.$parte;
            }
            $renglones[] = self::renglon($titulo, self::cant($cant), '', $lineas);
        }

        return self::empaquetar('Artículos a transferir', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    public static function desdeIndumentaria(?Solicitud_Prenda_Sueldos $solicitud): ?array
    {
        if (! $solicitud) {
            return null;
        }

        self::preparar($solicitud, ['empleado', 'solicitante', 'articulos.prenda', 'articulos.articulo', 'articulos.color', 'articulos.talle']);

        $notas = [];
        $empleado = self::rel($solicitud, 'empleado');
        $legajo = trim((string) ($empleado->legajo ?? ''));
        $nombre = trim((string) ($empleado->nombre ?? ''));
        self::nota($notas, 'Empleado', trim(($legajo !== '' ? 'Legajo '.$legajo.' · ' : '').$nombre));
        self::nota($notas, 'Solicitó', self::nombre(self::rel($solicitud, 'solicitante')));
        self::nota($notas, 'Observación', (string) ($solicitud->observacion ?? ''));

        $renglones = [];
        foreach (self::coleccion($solicitud, 'articulos') as $lin) {
            $prenda = self::rel($lin, 'prenda');
            $art = self::rel($lin, 'articulo');
            $titulo = trim((string) ($prenda->descripcion ?? ''));
            if ($titulo === '') {
                $titulo = self::tituloArticulo($art, trim((string) ($lin->sku ?? '')));
            }
            $lineas = [];
            $sku = trim((string) ($lin->sku ?? $art->sku ?? ''));
            if ($sku !== '' && ! str_contains($titulo, $sku)) {
                $lineas[] = 'SKU '.$sku;
            }
            $variante = self::variante(self::rel($lin, 'color'), self::rel($lin, 'talle'));
            if ($variante !== '') {
                $lineas[] = $variante;
            }
            $renglones[] = self::renglon($titulo, self::cant((float) ($lin->cantidad ?? 0)), '', $lineas);
        }

        return self::empaquetar('Prendas pedidas', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    public static function desdePrestamo(?Prestamo $prestamo): ?array
    {
        if (! $prestamo) {
            return null;
        }

        self::preparar($prestamo, ['items.articulos', 'solicitante', 'destinatarioUsuario', 'depositoOrigen', 'depositoDestino']);

        $notas = [];
        self::nota($notas, 'Solicitó', self::nombre(self::rel($prestamo, 'solicitante')));
        self::nota($notas, 'Destinatario', self::nombre(self::rel($prestamo, 'destinatarioUsuario')));
        $origen = self::codigoNombre(self::rel($prestamo, 'depositoOrigen'));
        $destino = self::codigoNombre(self::rel($prestamo, 'depositoDestino'));
        if ($origen !== '' || $destino !== '') {
            self::nota($notas, 'Depósitos', trim($origen.' → '.$destino, ' →'));
        }
        self::nota($notas, 'Observaciones', (string) ($prestamo->observaciones ?? ''));

        $renglones = [];
        foreach (self::coleccion($prestamo, 'items') as $lin) {
            $titulo = trim((string) ($lin->descripcion ?? ''));
            if ($titulo === '') {
                $titulo = self::tituloArticulo(self::rel($lin, 'articulos'), '');
            }
            $lineas = [];
            $serie = trim((string) ($lin->nro_serie ?? ''));
            if ($serie !== '') {
                $lineas[] = 'Serie '.$serie;
            }
            $obs = trim((string) ($lin->observaciones ?? ''));
            if ($obs !== '') {
                $lineas[] = $obs;
            }
            $renglones[] = self::renglon($titulo, self::cant((float) ($lin->cantidad ?? 0)), '', $lineas);
        }

        return self::empaquetar('Bienes de la salida', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function requisicion(Requisicion $req): ?array
    {
        self::preparar($req, [
            'usuarios',
            'empresas',
            'centrocostos',
            'proveedores',
            'formapagos',
            'requisicion_archivos',
            'requisicion_articulos.articulos',
            'requisicion_articulos.monedas',
            'requisicion_articulos.centrocostos_destino',
            'requisicion_articulos.partidagastos',
            'requisicion_articulos.capexs',
            'requisicion_articulos.proveedores',
            'requisicion_articulos.color',
            'requisicion_articulos.talle',
        ]);

        $notas = [];
        self::nota($notas, 'Solicitó', self::nombre(self::rel($req, 'usuarios')));
        self::nota($notas, 'Empresa', self::nombre(self::rel($req, 'empresas')));
        self::nota($notas, 'Centro de costo', self::codigoNombre(self::rel($req, 'centrocostos')));
        self::nota($notas, 'Centro de costo destino', self::centroDestinoArbol($req));
        self::nota($notas, 'Proveedor', self::nombre(self::rel($req, 'proveedores')));
        self::nota($notas, 'Forma de pago', self::nombre(self::rel($req, 'formapagos')));
        self::nota($notas, 'Comentario', (string) ($req->comentario ?? ''));
        self::nota($notas, 'Detalle', (string) ($req->detalle ?? ''));
        $adjuntos = self::coleccion($req, 'requisicion_archivos')->count();
        if ($adjuntos > 0) {
            self::nota($notas, 'Adjuntos', $adjuntos === 1 ? '1 archivo' : $adjuntos.' archivos');
        }

        $renglones = [];
        foreach (self::coleccion($req, 'requisicion_articulos') as $lin) {
            $renglones[] = self::renglonCompra($lin);
        }

        return self::empaquetar('Renglones de la requisición', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function ordencompra(Ordencompra $oc): ?array
    {
        self::preparar($oc, [
            'usuarios',
            'empresas',
            'proveedores',
            'ordencompra_articulos.articulos',
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos',
            'ordencompra_articulos.capexs',
            'ordencompra_articulos.color',
            'ordencompra_articulos.talle',
        ]);

        $notas = [];
        if ((bool) ($oc->es_suscripcion ?? false)) {
            self::nota($notas, 'Suscripción', (string) ($oc->suscripcion_nombre ?? ''));
        }
        self::nota($notas, 'Solicitó', self::nombre(self::rel($oc, 'usuarios')));
        self::nota($notas, 'Empresa', self::nombre(self::rel($oc, 'empresas')));
        $proveedor = self::nombre(self::rel($oc, 'proveedores'));
        if ($proveedor === '') {
            $proveedor = trim((string) ($oc->suscripcion_proveedor_nombre ?? ''));
        }
        self::nota($notas, 'Proveedor', $proveedor);
        self::nota($notas, 'Lugar de entrega', (string) ($oc->lugarentrega ?? ''));
        self::nota($notas, 'Comentario', (string) ($oc->comentario ?? ''));
        self::nota($notas, 'Detalle', (string) ($oc->detalle ?? ''));

        $renglones = [];
        foreach (self::coleccion($oc, 'ordencompra_articulos') as $lin) {
            $renglones[] = self::renglonCompra($lin);
        }

        $titulo = (bool) ($oc->es_suscripcion ?? false)
            ? 'Renglones de la suscripción'
            : 'Renglones de la orden de compra';

        return self::empaquetar($titulo, $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function requisicionSala(RequisicionSala $req): ?array
    {
        self::preparar($req, [
            'solicitante',
            'usuarios',
            'empresas',
            'centrocostos',
            'depositos',
            'zona_salas',
            'prioridad_salas',
            'requisicion_sala_articulos.articulos',
        ]);

        $notas = [];
        $solicitante = self::nombre(self::rel($req, 'solicitante'));
        if ($solicitante === '') {
            $solicitante = self::nombre(self::rel($req, 'usuarios'));
        }
        self::nota($notas, 'Solicitó', $solicitante);
        self::nota($notas, 'Empresa', self::nombre(self::rel($req, 'empresas')));
        self::nota($notas, 'Centro de costo', self::codigoNombre(self::rel($req, 'centrocostos')));
        self::nota($notas, 'Depósito', self::codigoNombre(self::rel($req, 'depositos')));
        self::nota($notas, 'Zona', self::nombre(self::rel($req, 'zona_salas')));
        self::nota($notas, 'Prioridad', self::nombre(self::rel($req, 'prioridad_salas')));
        self::nota($notas, 'Comentario', (string) ($req->comentario ?? ''));
        self::nota($notas, 'Detalle', (string) ($req->detalle ?? ''));

        $renglones = [];
        foreach (self::coleccion($req, 'requisicion_sala_articulos') as $lin) {
            $titulo = self::tituloArticulo(self::rel($lin, 'articulos'), trim((string) ($lin->detalle ?? '')));
            $lineas = [];
            $detalle = trim((string) ($lin->detalle ?? ''));
            if ($detalle !== '' && $detalle !== $titulo) {
                $lineas[] = $detalle;
            }
            $precio = (float) ($lin->precio ?? 0);
            $cant = (float) ($lin->cantidad ?? 0);
            $renglones[] = self::renglon(
                $titulo,
                self::cantidadPrecio($cant, $precio, ''),
                $precio > 0 ? self::dinero($cant * $precio) : '',
                $lineas
            );
        }

        return self::empaquetar('Renglones de la requisición de sala', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function pedido(PedidoInterforming $pedido): ?array
    {
        self::preparar($pedido, ['clientes', 'moneda', 'pedido_articulos.articulos', 'pedido_articulos.monedas']);

        $notas = [];
        self::nota($notas, 'Cliente', self::nombre(self::rel($pedido, 'clientes')));
        self::nota($notas, 'Orden de compra', (string) ($pedido->orden_compra ?? ''));
        self::nota($notas, 'Leyenda', (string) ($pedido->leyenda ?? ''));

        $abrevCab = self::abreviatura(self::rel($pedido, 'moneda'));
        $renglones = [];
        foreach (self::coleccion($pedido, 'pedido_articulos') as $lin) {
            $titulo = self::tituloArticulo(self::rel($lin, 'articulos'), trim((string) ($lin->descripcion_aux ?? '')));
            $abrev = self::abreviatura(self::rel($lin, 'monedas'));
            if ($abrev === '') {
                $abrev = $abrevCab;
            }
            $cant = (float) ($lin->cantidad ?? 0);
            $precio = (float) ($lin->precio ?? 0);
            $lineas = [];
            $obs = trim((string) ($lin->observacion ?? ''));
            if ($obs !== '') {
                $lineas[] = $obs;
            }
            $oc = trim((string) ($lin->orden_compra ?? ''));
            if ($oc !== '') {
                $lineas[] = 'OC '.$oc;
            }
            $renglones[] = self::renglon(
                $titulo,
                self::cantidadPrecio($cant, $precio, $abrev),
                $precio > 0 ? trim(self::dinero($cant * $precio).' '.$abrev) : '',
                $lineas
            );
        }

        return self::empaquetar('Renglones del pedido', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function solicitudPago(Solicitudpago $sp): ?array
    {
        self::preparar($sp, [
            'proveedores',
            'conceptos',
            'centrocostos',
            'formapagosol',
            'monedas',
            'cuentas.cuentacontables',
            'cuentas.centrocostos',
        ]);

        $notas = [];
        self::nota($notas, 'Proveedor', self::nombre(self::rel($sp, 'proveedores')));
        self::nota($notas, 'Concepto', self::nombre(self::rel($sp, 'conceptos')));
        self::nota($notas, 'Centro de costo', self::codigoNombre(self::rel($sp, 'centrocostos')));
        self::nota($notas, 'Forma de pago', self::nombre(self::rel($sp, 'formapagosol')));
        self::nota($notas, 'Detalle', (string) ($sp->detalle ?? ''));

        $abrev = self::abreviatura(self::rel($sp, 'monedas'));
        $renglones = [];
        foreach (self::coleccion($sp, 'cuentas') as $cuenta) {
            $titulo = self::codigoNombre(self::rel($cuenta, 'cuentacontables'));
            if ($titulo === '') {
                $titulo = 'Cuenta';
            }
            $lineas = [];
            $cc = self::codigoNombre(self::rel($cuenta, 'centrocostos'));
            if ($cc !== '') {
                $lineas[] = 'Centro de costo '.$cc;
            }
            $lado = strtoupper(trim((string) ($cuenta->debe_haber ?? '')));
            if ($lado === 'D') {
                $lineas[] = 'Debe';
            } elseif ($lado === 'H') {
                $lineas[] = 'Haber';
            }
            $monto = (float) ($cuenta->monto ?? 0);
            $renglones[] = self::renglon(
                $titulo,
                '',
                trim(self::dinero($monto).' '.$abrev),
                $lineas
            );
        }

        return self::empaquetar('Solicitud de pago', $notas, $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function propuestaPago(PropuestaPago $pp): ?array
    {
        self::preparar($pp, ['lineas.proveedores', 'lineas.monedas', 'monedas']);

        $abrevCab = self::abreviatura(self::rel($pp, 'monedas'));
        $renglones = [];
        foreach (self::coleccion($pp, 'lineas') as $lin) {
            if (isset($lin->incluido) && ! $lin->incluido) {
                continue;
            }
            $titulo = self::nombre(self::rel($lin, 'proveedores'));
            if ($titulo === '') {
                $titulo = 'Pago';
            }
            $abrev = self::abreviatura(self::rel($lin, 'monedas'));
            if ($abrev === '') {
                $abrev = $abrevCab;
            }
            $lineas = [];
            $detalle = trim((string) ($lin->detalle_pago ?? ''));
            if ($detalle !== '') {
                $lineas[] = $detalle;
            }
            $vto = $lin->fechavencimiento ?? null;
            if ($vto) {
                try {
                    $lineas[] = 'Vence '.\Carbon\Carbon::parse($vto)->format('d/m/Y');
                } catch (\Throwable) {
                    $lineas[] = 'Vence '.(string) $vto;
                }
            }
            $renglones[] = self::renglon(
                $titulo,
                '',
                trim(self::dinero((float) ($lin->monto_propuesto ?? 0)).' '.$abrev),
                $lineas
            );
        }

        return self::empaquetar('Pagos propuestos', [], $renglones);
    }

    /**
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function articulo(Articulo $art): ?array
    {
        $notas = [];
        self::nota($notas, 'SKU', (string) ($art->sku ?? ''));
        self::nota($notas, 'Descripción', (string) ($art->descripcion ?? ''));
        self::nota($notas, 'Detalle', (string) ($art->detalle ?? ''));

        return self::empaquetar('Artículo a aprobar', $notas, []);
    }

    /**
     * @return array{titulo: string, cantidad: string, importe: string, lineas: list<string>}
     */
    private static function renglonCompra(Model $lin): array
    {
        $art = self::rel($lin, 'articulos');
        $detalle = trim((string) ($lin->detalle ?? ''));
        $titulo = self::tituloArticulo($art, $detalle);
        $abrev = self::abreviatura(self::rel($lin, 'monedas'));
        $cant = (float) ($lin->cantidad ?? 0);
        $precio = (float) ($lin->precio ?? 0);
        $lineas = [];
        if ($detalle !== '' && $detalle !== $titulo) {
            $lineas[] = $detalle;
        }
        $cc = self::codigoNombre(self::rel($lin, 'centrocostos_destino'));
        if ($cc !== '') {
            $lineas[] = 'Destino '.$cc;
        }
        $partida = self::codigoNombre(self::rel($lin, 'partidagastos'));
        if ($partida !== '') {
            $lineas[] = 'Partida '.$partida;
        }
        $capex = self::nombre(self::rel($lin, 'capexs'));
        if ($capex !== '') {
            $lineas[] = 'Capex '.$capex;
        }
        $proveedor = self::nombre(self::rel($lin, 'proveedores'));
        if ($proveedor !== '') {
            $lineas[] = 'Proveedor '.$proveedor;
        }
        $variante = self::variante(self::rel($lin, 'color'), self::rel($lin, 'talle'));
        if ($variante !== '') {
            $lineas[] = $variante;
        }

        return self::renglon(
            $titulo,
            self::cantidadPrecio($cant, $precio, $abrev),
            $precio > 0 ? trim(self::dinero($cant * $precio).' '.$abrev) : '',
            $lineas
        );
    }

    /**
     * @param  list<array{label: string, valor: string}>  $notas
     * @param  list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>  $renglones
     * @return array{titulo: string, notas: list<array{label: string, valor: string}>, renglones: list<array{titulo: string, cantidad: string, importe: string, lineas: list<string>}>, truncado: ?string}|null
     */
    private static function empaquetar(string $titulo, array $notas, array $renglones): ?array
    {
        $truncado = null;
        $total = count($renglones);
        if ($total > self::MAX_RENGLONES) {
            $resto = $total - self::MAX_RENGLONES;
            $renglones = array_slice($renglones, 0, self::MAX_RENGLONES);
            $truncado = 'Hay '.$resto.' renglón'.($resto === 1 ? '' : 'es').' más. Abrí el documento para ver el resto.';
        }

        if ($notas === [] && $renglones === []) {
            return null;
        }

        return [
            'titulo' => $titulo,
            'notas' => array_values($notas),
            'renglones' => array_values($renglones),
            'truncado' => $truncado,
        ];
    }

    /**
     * @param  list<string>  $lineas
     * @return array{titulo: string, cantidad: string, importe: string, lineas: list<string>}
     */
    private static function renglon(string $titulo, string $cantidad, string $importe, array $lineas): array
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            $titulo = 'Sin descripción';
        }

        return [
            'titulo' => $titulo,
            'cantidad' => trim($cantidad),
            'importe' => trim($importe),
            'lineas' => array_values(array_filter(array_map('trim', $lineas), fn ($l) => $l !== '')),
        ];
    }

    /**
     * @param  list<array{label: string, valor: string}>  $notas
     */
    private static function nota(array &$notas, string $label, string $valor): void
    {
        $valor = trim($valor);
        if ($valor === '') {
            return;
        }
        $notas[] = ['label' => $label, 'valor' => $valor];
    }

    /**
     * @param  list<string>  $relations
     */
    private static function preparar(Model $model, array $relations): void
    {
        if ($model->exists && $relations !== []) {
            $model->loadMissing($relations);
        }
    }

    private static function coleccion(Model $model, string $relation): Collection
    {
        $value = self::rel($model, $relation);

        return collect($value ?? []);
    }

    private static function rel(Model $model, string $relation): mixed
    {
        if ($model->relationLoaded($relation)) {
            return $model->getRelation($relation);
        }
        if (! $model->exists || ! $model->isRelation($relation)) {
            return null;
        }
        $model->loadMissing($relation);

        return $model->relationLoaded($relation) ? $model->getRelation($relation) : null;
    }

    private static function centroDestinoArbol(Requisicion $req): string
    {
        $id = (int) ($req->centrocostodestino_arbol_id ?? 0);
        if ($id <= 0 || ! $req->exists) {
            return '';
        }
        $cc = Centrocosto::query()->find($id);

        return self::codigoNombre($cc);
    }

    private static function tituloArticulo(mixed $art, string $fallback): string
    {
        $sku = trim((string) ($art->sku ?? ''));
        $desc = trim((string) ($art->descripcion ?? ''));
        $titulo = trim($sku.($sku !== '' && $desc !== '' ? ' · ' : '').$desc);

        return $titulo !== '' ? $titulo : $fallback;
    }

    private static function codigoNombre(mixed $modelo): string
    {
        if (! $modelo) {
            return '';
        }
        $codigo = trim((string) ($modelo->codigo ?? ''));
        $nombre = self::nombre($modelo);
        if ($codigo !== '' && $nombre !== '' && $codigo !== $nombre) {
            return $codigo.' · '.$nombre;
        }

        return $nombre !== '' ? $nombre : $codigo;
    }

    private static function nombre(mixed $modelo): string
    {
        if (! $modelo) {
            return '';
        }
        foreach (['nombre', 'descripcion', 'detalle', 'fantasia'] as $campo) {
            $valor = trim((string) ($modelo->{$campo} ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    private static function abreviatura(mixed $moneda): string
    {
        return trim((string) ($moneda->abreviatura ?? ''));
    }

    private static function variante(mixed $color, mixed $talle): string
    {
        $partes = array_filter([
            self::nombre($color) !== '' ? 'Color '.self::nombre($color) : '',
            self::nombre($talle) !== '' ? 'Talle '.self::nombre($talle) : '',
        ]);

        return implode(' · ', $partes);
    }

    private static function cantidadPrecio(float $cantidad, float $precio, string $abrev): string
    {
        $txt = self::cant($cantidad);
        if ($precio > 0) {
            $txt .= ' × '.self::dinero($precio);
            if ($abrev !== '') {
                $txt .= ' '.$abrev;
            }
        }

        return $txt;
    }

    private static function cant(float $n): string
    {
        if (abs($n - round($n)) < 0.00001) {
            return number_format($n, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    private static function dinero(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }
}
