<?php

namespace App\Support\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma pendmae / pendmov de un pedido El Bierzo (letra X, sucursal 1).
 * Cantidad Anita = kilos; piezas van en penv_pieza; la pesada en penv_kilos_reales.
 */
final class PedidoBierzoAnitaEscrituraSupport
{
    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     * @return array{campos: string, valores: string}
     */
    public static function cabeceraInsert(Pedido $pedido, array $clave, Collection $lineas, bool $alta): array
    {
        return RecepcionProveedorAnitaEscrituraSupport::insert(
            self::columnasCabecera($pedido, $clave, $lineas, true, $alta)
        );
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     */
    public static function cabeceraUpdateSet(Pedido $pedido, array $clave, Collection $lineas): string
    {
        return RecepcionProveedorAnitaEscrituraSupport::updateSet(
            self::columnasCabecera($pedido, $clave, $lineas, false, false)
        );
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function lineaInsert(
        Pedido_Articulo $linea,
        array $clave,
        int $orden,
        string $cliente6,
        int $vendedor,
        int $zona,
        int $reparto,
        int $fecha,
        int $fechaEntrega,
    ): array {
        $articulo = $linea->articulos;
        $incluye = self::incluyeImpuestoAnita((string) ($linea->incluyeimpuesto ?? ''));
        $precio = (float) ($linea->precio ?? 0);
        $kilo = (float) ($linea->kilo ?? 0);
        $pieza = (float) ($linea->pieza ?? 0);
        $pesada = (float) ($linea->pesada ?? 0);
        $moneda = (int) (optional($linea->monedas)->codigo ?? ($linea->moneda_id ?? 1));
        if ($moneda <= 0) {
            $moneda = 1;
        }

        $columnas = [
            'penv_cliente' => RecepcionProveedorAnitaEscrituraSupport::textoSql($cliente6, 6),
            'penv_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'penv_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'penv_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'penv_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'penv_orden' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($orden),
            'penv_articulo' => RecepcionProveedorAnitaEscrituraSupport::textoSql(
                RecepcionProveedorAnitaEscrituraSupport::skuAnita13((string) ($articulo->sku ?? '')),
                13
            ),
            'penv_desc' => RecepcionProveedorAnitaEscrituraSupport::textoSql(self::descripcion($articulo), 30),
            'penv_agrupacion' => RecepcionProveedorAnitaEscrituraSupport::agrupacionSql(
                (string) (optional($articulo->categorias)->codigo ?? '')
            ),
            'penv_unidad_medida' => RecepcionProveedorAnitaEscrituraSupport::textoSql(self::unidad($articulo), 5),
            'penv_cantidad' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($kilo),
            'penv_cantaentr' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($kilo),
            'penv_cantentr' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(0),
            'penv_cantfact' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(0),
            'penv_precio' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($precio),
            'penv_dto_art' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($linea->descuento ?? 0)),
            'penv_deposito' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
            'penv_tipo_iva' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(
                RecepcionProveedorAnitaEscrituraSupport::tipoIvaAnitaCodigo($articulo)
            ),
            'penv_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'penv_incl_impuesto' => RecepcionProveedorAnitaEscrituraSupport::textoSql($incluye, 1),
            'penv_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($moneda),
            'penv_vendedor' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($vendedor),
            'penv_zonavta' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($zona),
            'penv_zonamult' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
            'penv_partida' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
            'penv_fecha_ent' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaEntrega),
            'penv_pieza' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($pieza),
            'penv_fl_bonif' => RecepcionProveedorAnitaEscrituraSupport::textoSql($precio <= 0 ? '1' : '0', 1),
            'penv_reparto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($reparto),
            'penv_kilos_reales' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($pesada),
            'penv_piezas_reales' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($pesada > 0 ? $pieza : 0),
        ];

        return RecepcionProveedorAnitaEscrituraSupport::insert($columnas);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  Collection<int, Pedido_Articulo>  $lineas
     * @return array<string, string>
     */
    private static function columnasCabecera(
        Pedido $pedido,
        array $clave,
        Collection $lineas,
        bool $incluirClave,
        bool $alta,
    ): array {
        $cliente6 = self::cliente6($pedido);
        $fecha = self::fechaYmd($pedido->fecha);
        $fechaEntrega = self::fechaYmd($pedido->fechaentrega ?: $pedido->fecha);
        $ahora = Carbon::now();
        $pesada = 0.0;
        $neto = 0.0;
        $moneda = 1;
        foreach ($lineas as $linea) {
            $kilo = (float) ($linea->kilo ?? 0);
            $precio = (float) ($linea->precio ?? 0);
            $dto = (float) ($linea->descuento ?? 0);
            $pesada += (float) ($linea->pesada ?? 0);
            if ($precio > 0) {
                $neto += $kilo * $precio * (1 - ($dto / 100));
            }
            $codMon = (int) (optional($linea->monedas)->codigo ?? 0);
            if ($codMon > 0) {
                $moneda = $codMon;
            }
        }

        $leyenda = trim((string) ($pedido->leyenda ?? ''));
        $entrega = trim((string) ($pedido->lugarentrega ?? ''));
        $dtoIntegrado = trim((string) ($pedido->descuentointegrado ?? ''));

        $columnas = [
            'penm_cliente' => RecepcionProveedorAnitaEscrituraSupport::textoSql($cliente6, 6),
            'penm_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'penm_fecha_ent' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaEntrega),
            'penm_cond_vta' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(self::codigoEntero(optional($pedido->condicionesdeventa)->codigo)),
            'penm_deposito' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
            'penm_vendedor' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(self::vendedor($pedido)),
            'penm_zonavta' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(self::zona($pedido)),
            'penm_entrega' => self::textoOEspacio($entrega, 40),
            'penm_dto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($pedido->descuento ?? 0)),
            'penm_expreso' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(self::codigoEntero(optional($pedido->transportes)->codigo)),
            'penm_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($moneda),
            'penm_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(1),
            'penm_estado' => RecepcionProveedorAnitaEscrituraSupport::textoSql(self::estadoAnita($pedido), 1),
            'penm_leyenda' => self::textoOEspacio($leyenda, 40),
            'penm_dto_integrado' => self::textoOEspacio($dtoIntegrado, 1),
            'penm_neto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(round($neto, 2), 2),
            'penm_caja_reales' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($pedido->caja_reales ?? 0)),
            'penm_kg_reales' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($pesada),
            'penm_unid_reales' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(0),
        ];

        if ($alta) {
            $usuario = trim((string) (optional(auth()->user())->usuario ?? 'erp'));
            $columnas['penm_ref_tipo'] = self::textoOEspacio(' ', 1);
            $columnas['penm_ref_letra'] = self::textoOEspacio('', 1);
            $columnas['penm_ref_sucursal'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_ref_nro'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_o_compra'] = self::textoOEspacio('', 1);
            $columnas['penm_razon_susp'] = self::textoOEspacio('', 1);
            $columnas['penm_fecha_ing'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $ahora->format('Ymd'));
            $columnas['penm_hora_ing'] = RecepcionProveedorAnitaEscrituraSupport::textoSql($ahora->format('H:i:s'), 8);
            $columnas['penm_tipo_fact'] = self::textoOEspacio('', 1);
            $columnas['penm_letra_fact'] = self::textoOEspacio('', 1);
            $columnas['penm_sucursal_fact'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_nro_fact'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_cod_entrega'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_caja'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_abasto'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_tot_seguro'] = RecepcionProveedorAnitaEscrituraSupport::decimalSql(0);
            $columnas['penm_subzona'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_oblea'] = self::textoOEspacio('', 1);
            $columnas['penm_cant_modif'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql(0);
            $columnas['penm_usuario_alta'] = RecepcionProveedorAnitaEscrituraSupport::textoSql($usuario !== '' ? $usuario : 'erp', 10);
        }

        if ($incluirClave) {
            $columnas = array_merge([
                'penm_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
                'penm_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
                'penm_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
                'penm_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            ], $columnas);
        }

        return $columnas;
    }

    public static function cliente6(Pedido $pedido): string
    {
        $codigo = trim((string) (optional($pedido->clientes)->codigo ?? ''));
        if ($codigo === '') {
            throw new \RuntimeException('El pedido no tiene cliente para grabar en Anita.');
        }
        $anita = str_pad($codigo, 6, '0', STR_PAD_LEFT);
        if (strlen($anita) > 6) {
            throw new \RuntimeException('El código de cliente '.$codigo.' no entra en Anita (6 dígitos).');
        }

        return $anita;
    }

    public static function estadoAnita(Pedido $pedido): string
    {
        $etiqueta = trim((string) ($pedido->estadopedido ?? ''));
        if ($etiqueta === 'Suspendido') {
            return PedidoEstadosInterforming::CAB_SUSPENDIDO;
        }
        if ($etiqueta === 'Facturado') {
            return PedidoEstadosInterforming::CAB_FACTURADO;
        }
        if ($etiqueta === 'Anulado') {
            return PedidoEstadosInterforming::CAB_ANULADO;
        }

        return match (PedidoEstadoErpSupport::normalizarEstadoCabecera($pedido->estado ?? null, $etiqueta)) {
            PedidoEstadoErpSupport::ENTREGADO, PedidoEstadoErpSupport::CERRADO => PedidoEstadosInterforming::CAB_ENTREGADO,
            PedidoEstadoErpSupport::FACTURADO => PedidoEstadosInterforming::CAB_FACTURADO,
            PedidoEstadoErpSupport::ANULADO => PedidoEstadosInterforming::CAB_ANULADO,
            default => PedidoEstadosInterforming::CAB_PENTREGAR,
        };
    }

    private static function vendedor(Pedido $pedido): int
    {
        $codigo = optional($pedido->vendedores)->codigo ?? optional(optional($pedido->clientes)->vendedores)->codigo;

        return self::codigoEntero($codigo);
    }

    private static function zona(Pedido $pedido): int
    {
        $codigo = optional($pedido->zonavtas)->codigo ?? optional(optional($pedido->clientes)->zonavtas)->codigo;

        return self::codigoEntero($codigo);
    }

    private static function codigoEntero(mixed $codigo): int
    {
        return max(0, (int) $codigo);
    }

    /**
     * Un espacio en blanco no puede pasar por el sanitizador: lo recorta y Anita
     * recibe '' en columnas char que el pedido de escritorio graba con un blanco.
     */
    private static function textoOEspacio(string $value, int $maxLen): string
    {
        $value = trim($value);
        if ($value === '') {
            return "' '";
        }

        return RecepcionProveedorAnitaEscrituraSupport::textoSql($value, $maxLen);
    }

    private static function incluyeImpuestoAnita(string $valor): string
    {
        $valor = strtoupper(trim($valor));

        return in_array($valor, ['S', '1'], true) ? 'S' : 'N';
    }

    private static function descripcion(?Articulo $articulo): string
    {
        $desc = trim((string) ($articulo->descripcion ?? ''));

        return $desc !== '' ? $desc : ' ';
    }

    private static function unidad(?Articulo $articulo): string
    {
        $unidad = trim((string) (optional(optional($articulo)->unidadesdemedidas)->abreviatura ?? ''));

        return $unidad !== '' ? $unidad : 'kg';
    }

    private static function fechaYmd(mixed $fecha): int
    {
        if ($fecha instanceof \DateTimeInterface) {
            return (int) $fecha->format('Ymd');
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return (int) date('Ymd');
        }
        $ts = strtotime($texto);

        return $ts ? (int) date('Ymd', $ts) : (int) date('Ymd');
    }
}
