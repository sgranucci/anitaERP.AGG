<?php

namespace App\Support\Seguridad;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cobranza;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Requisicion;
use App\Models\Ticket\Ticket;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Repositories\Ventas\VendedorRepository;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comprobantes del buscador: OC, pedido, factura, cheque, CP, OP, remito,
 * cobranza, requisición y ticket. Por número o por texto (tercero / observación).
 */
class BuscadorGlobalComprobantesSupport
{
    public const LIMITE_POR_TIPO = 4;

    public function __construct(
        private EmpresaRepository $empresaRepository,
        private VendedorRepository $vendedorRepository
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buscar(string $consulta): array
    {
        $q = trim($consulta);
        if (mb_strlen($q) < BuscadorGlobalSupport::MIN_CARACTERES) {
            return [];
        }

        $numero = $this->numeroConsulta($q);
        $filas = [];

        if ($this->permitido('listar-cheque', 'editar-cheque', 'actualizar-cheque')) {
            $filas = array_merge($filas, $this->cheques($q, $numero));
        }
        if ($this->permitido('listar-comprobante-proveedor', 'editar-comprobante-proveedor', 'actualizar-comprobante-proveedor')) {
            $filas = array_merge($filas, $this->comprobantesProveedor($q, $numero));
        }
        if ($this->permitido('listar-pagoproveedor', 'editar-pagoproveedor', 'actualizar-pagoproveedor')) {
            $filas = array_merge($filas, $this->pagosProveedor($q, $numero));
        }
        if ($this->permitido('listar-ordencompra', 'editar-ordencompra', 'actualizar-ordencompra')) {
            $filas = array_merge($filas, $numero !== null ? $this->ordenes($numero) : $this->ordenesPorProveedor($q));
        }
        if ($numero !== null && $this->permitido('listar-pedidos', 'editar-pedidos', 'actualizar-pedidos')) {
            $filas = array_merge($filas, $this->pedidos($q, $numero));
        }
        if ($numero !== null && $this->permitido('listar-factura', 'editar-factura', 'actualizar-factura')) {
            $filas = array_merge($filas, $this->facturas($numero));
        }
        if ($this->permitido('listar-remitos', 'editar-remitos', 'actualizar-remitos')) {
            $filas = array_merge($filas, $this->remitos($q, $numero));
        }
        if ($this->permitido('listar-cobranza', 'editar-cobranza', 'actualizar-cobranza')) {
            $filas = array_merge($filas, $this->cobranzas($q, $numero));
        }
        if ($this->permitido('listar-requisicion', 'editar-requisicion', 'actualizar-requisicion')) {
            $filas = array_merge($filas, $this->requisiciones($q, $numero));
        }
        if ($this->permitido('listar-ticket', 'editar-ticket', 'actualizar-ticket')) {
            $filas = array_merge($filas, $this->tickets($q, $numero));
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cheques(string $q, ?string $numero): array
    {
        $query = Cheque::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'cheque.proveedor_id')
            ->leftJoin('cliente', 'cliente.id', '=', 'cheque.cliente_id')
            ->leftJoin('banco', 'banco.id', '=', 'cheque.banco_id')
            ->select([
                'cheque.id',
                'cheque.numerocheque',
                'cheque.nro_echeq',
                'cheque.anombrede',
                'cheque.entregado',
                'cheque.monto',
                'cheque.fechapago',
                'cheque.origen',
                'proveedor.nombre as nombre_proveedor',
                'cliente.nombre as nombre_cliente',
                'banco.nombre as nombre_banco',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cheque.empresa_id');

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('cheque.numerocheque', $numero)
                    ->orWhere('cheque.nro_interno_anita', $numero)
                    ->orWhere('cheque.nro_echeq', 'like', $this->escapeLike($numero).'%');
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(cheque.numerocheque AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('cheque.anombrede', 'like', $like)
                    ->orWhere('cheque.entregado', 'like', $like)
                    ->orWhere('cheque.nro_echeq', 'like', $like)
                    ->orWhere('proveedor.nombre', 'like', $like)
                    ->orWhere('cliente.nombre', 'like', $like);
            }
        });
        $query->orderByDesc('cheque.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = trim((string) ($fila->numerocheque ?? ''));
            if ($nro === '' && trim((string) ($fila->nro_echeq ?? '')) !== '') {
                $nro = (string) $fila->nro_echeq;
            }
            $tercero = trim((string) ($fila->anombrede ?: $fila->nombre_proveedor ?: $fila->nombre_cliente ?: ''));
            $campos = [
                $this->campo('Número', $nro),
                $this->campo('Fecha pago', $this->fechaDmY($fila->fechapago)),
                $this->campo('Monto', $this->importe($fila->monto)),
                $this->campo('Banco', trim((string) ($fila->nombre_banco ?? ''))),
                $this->campo('Beneficiario', $tercero),
            ];
            $filas[] = $this->fila(
                'Cheque '.$nro,
                $this->detalle('Cheque', $nro, $tercero),
                route('editar_cheque', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-money',
                'cheque:'.(int) $fila->id,
                'Cheque',
                $campos
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function comprobantesProveedor(string $q, ?string $numero): array
    {
        $query = Comprobante_Proveedor::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'comprobante_proveedor.proveedor_id')
            ->leftJoin('tipotransaccion_compra', 'tipotransaccion_compra.id', '=', 'comprobante_proveedor.tipotransaccion_compra_id')
            ->select([
                'comprobante_proveedor.id',
                'comprobante_proveedor.letra',
                'comprobante_proveedor.sucursal',
                'comprobante_proveedor.numerocomprobante',
                'comprobante_proveedor.fechacomprobante',
                'comprobante_proveedor.total',
                'comprobante_proveedor.leyenda',
                'proveedor.nombre as nombretercero',
                'tipotransaccion_compra.abreviatura',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor.empresa_id');

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('comprobante_proveedor.numerocomprobante', (int) $numero);
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(comprobante_proveedor.numerocomprobante AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('proveedor.nombre', 'like', $like)
                    ->orWhere('proveedor.codigo', 'like', $this->escapeLike($q).'%')
                    ->orWhere('comprobante_proveedor.leyenda', 'like', $like)
                    ->orWhere('comprobante_proveedor.proveedor_nombre_eventual', 'like', $like);
            }
        });
        $query->orderByDesc('comprobante_proveedor.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $abrev = strtoupper(trim((string) ($fila->abreviatura ?? '')));
            $tipo = $abrev !== '' ? $abrev : 'CP';
            $visible = $this->numeroFiscal($fila->letra, $fila->sucursal, $fila->numerocomprobante);
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $campos = [
                $this->campo('Tipo', $tipo),
                $this->campo('Número', $visible),
                $this->campo('Fecha', $this->fechaDmY($fila->fechacomprobante)),
                $this->campo('Total', $this->importe($fila->total)),
                $this->campo('Proveedor', $tercero),
            ];
            $matchEn = null;
            if ($fila->leyenda && mb_stripos((string) $fila->leyenda, $q) !== false) {
                $matchEn = 'Observación';
                $campos[] = $this->campo('Observación', $this->recortar((string) $fila->leyenda, 80));
            }
            $filas[] = $this->fila(
                $tipo.' '.$visible,
                $this->detalle($tipo, $visible, $tercero),
                route('editar_comprobante_proveedor', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-file-text-o',
                'cp:'.(int) $fila->id,
                'Comp. proveedor',
                $campos,
                $matchEn
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagosProveedor(string $q, ?string $numero): array
    {
        $query = Pagoproveedor::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'pagoproveedor.proveedor_id')
            ->select([
                'pagoproveedor.id',
                'pagoproveedor.numerotransaccion',
                'pagoproveedor.fecha',
                'pagoproveedor.monto',
                'pagoproveedor.detalle',
                'pagoproveedor.tipocomprobante',
                'pagoproveedor.letra',
                'pagoproveedor.sucursal',
                'proveedor.nombre as nombretercero',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pagoproveedor.empresa_id');

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('pagoproveedor.numerotransaccion', (int) $numero);
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(pagoproveedor.numerotransaccion AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('proveedor.nombre', 'like', $like)
                    ->orWhere('proveedor.codigo', 'like', $this->escapeLike($q).'%')
                    ->orWhere('pagoproveedor.detalle', 'like', $like);
            }
        });
        $query->orderByDesc('pagoproveedor.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = (string) $fila->numerotransaccion;
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $campos = [
                $this->campo('Número', $nro),
                $this->campo('Fecha', $this->fechaDmY($fila->fecha)),
                $this->campo('Monto', $this->importe($fila->monto)),
                $this->campo('Proveedor', $tercero),
            ];
            $matchEn = null;
            if ($fila->detalle && mb_stripos((string) $fila->detalle, $q) !== false) {
                $matchEn = 'Detalle';
                $campos[] = $this->campo('Detalle', $this->recortar((string) $fila->detalle, 80));
            }
            $filas[] = $this->fila(
                'OP '.$nro,
                $this->detalle('Orden de pago', $nro, $tercero),
                route('editar_pagoproveedor', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-credit-card',
                'op:'.(int) $fila->id,
                'Orden de pago',
                $campos,
                $matchEn
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordenes(string $numero): array
    {
        $numeroInt = (int) $numero;
        $query = Ordencompra::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'proveedor.nombre as nombretercero',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        $this->porNumero($query, 'ordencompra.numeroordencompra', $numero, $numeroInt);
        $query->orderByRaw(
            'CASE WHEN ordencompra.numeroordencompra = ? THEN 0 ELSE 1 END',
            [$numeroInt]
        );
        $query->orderByDesc('ordencompra.id');
        $query->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = (string) $fila->numeroordencompra;
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                'OC '.$nro,
                $this->detalle('Orden de compra', $nro, $tercero),
                route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-shopping-cart',
                'oc:'.(int) $fila->id,
                'Orden de compra',
                [
                    $this->campo('Número', $nro),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Proveedor', $tercero),
                ]
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pedidos(string $consulta, string $numero): array
    {
        $numeroInt = (int) $numero;
        $prefijo = strlen($numero) >= 4;
        $crudo = trim($consulta);
        $query = Pedido::query()
            ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
            ->select([
                'pedido.id',
                'pedido.codigo',
                'pedido.fecha',
                'cliente.nombre as nombretercero',
            ]);
        KiloPedidoListadoFiltros::aplicarFiltroVendedorUsuarioEnQuery(
            $query,
            array_map('intval', (array) $this->vendedorRepository->leeVendedoresAsociados())
        );

        $query->where(function ($w) use ($numero, $numeroInt, $prefijo, $crudo) {
            $w->whereRaw('CAST(SUBSTRING_INDEX(pedido.codigo, \'-\', -1) AS UNSIGNED) = ?', [$numeroInt])
                ->orWhere('pedido.id', $numeroInt)
                ->orWhere('pedido.codigo', $crudo);
            if ($prefijo) {
                $w->orWhereRaw(
                    'CAST(CAST(SUBSTRING_INDEX(pedido.codigo, \'-\', -1) AS UNSIGNED) AS CHAR) LIKE ?',
                    [$numero.'%']
                );
                $w->orWhere('pedido.codigo', 'like', $this->escapeLike($crudo).'%');
            }
        });
        $query->orderByRaw(
            'CASE WHEN CAST(SUBSTRING_INDEX(pedido.codigo, \'-\', -1) AS UNSIGNED) = ? THEN 0 WHEN pedido.id = ? THEN 1 ELSE 2 END',
            [$numeroInt, $numeroInt]
        );
        $query->orderByDesc('pedido.id');
        $query->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $codigo = trim((string) $fila->codigo);
            $visible = $codigo;
            if (preg_match('/(\d+)$/', $codigo, $corte) === 1) {
                $sinCeros = ltrim($corte[1], '0');
                if ($sinCeros !== '') {
                    $visible = $sinCeros;
                }
            }
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                'Pedido '.$visible,
                $this->detalle('Pedido', $visible, $tercero),
                route('editar_pedido', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-clipboard',
                'pedido:'.(int) $fila->id,
                'Pedido',
                [
                    $this->campo('Número', $visible),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Cliente', $tercero),
                ]
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facturas(string $numero): array
    {
        $numeroInt = (int) $numero;
        $query = Venta::query()
            ->leftJoin('cliente', 'cliente.id', '=', 'venta.cliente_id')
            ->leftJoin('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->leftJoin('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->select([
                'venta.id',
                'venta.numerocomprobante',
                'venta.fecha',
                'venta.total',
                'cliente.nombre as nombretercero',
                'tipotransaccion.abreviatura',
                'puntoventa.codigo as codigopv',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'puntoventa.empresa_id');
        $this->porNumero($query, 'venta.numerocomprobante', $numero, $numeroInt);
        $query->orderByRaw(
            'CASE WHEN venta.numerocomprobante = ? THEN 0 ELSE 1 END',
            [$numeroInt]
        );
        $query->orderByDesc('venta.id');
        $query->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $abrev = strtoupper(trim((string) ($fila->abreviatura ?? '')));
            $tipo = $abrev !== '' ? $abrev : 'Factura';
            $pv = trim((string) ($fila->codigopv ?? ''));
            $nro = (string) $fila->numerocomprobante;
            $visible = $pv !== '' ? $pv.'-'.$nro : $nro;
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                $tipo.' '.$nro,
                $this->detalle($tipo, $visible, $tercero),
                route('editar_factura', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-file-text',
                'factura:'.(int) $fila->id,
                'Factura',
                [
                    $this->campo('Tipo', $tipo),
                    $this->campo('Número', $visible),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Total', $this->importe($fila->total ?? null)),
                    $this->campo('Cliente', $tercero),
                ]
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function remitos(string $q, ?string $numero): array
    {
        $query = Remito::query()
            ->leftJoin('cliente', 'cliente.id', '=', 'remito.cliente_id')
            ->select([
                'remito.id',
                'remito.numero',
                'remito.codigo',
                'remito.fecha',
                'cliente.nombre as nombretercero',
            ]);

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('remito.numero', (int) $numero)
                    ->orWhere('remito.codigo', 'like', '%'.$this->escapeLike($numero).'%');
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(remito.numero AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('cliente.nombre', 'like', $like)
                    ->orWhere('remito.codigo', 'like', $like)
                    ->orWhere('remito.leyenda', 'like', $like);
            }
        });
        $query->orderByDesc('remito.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = trim((string) ($fila->numero ?: $fila->codigo ?: $fila->id));
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                'Remito '.$nro,
                $this->detalle('Remito', $nro, $tercero),
                route('editar_remito', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-truck',
                'remito:'.(int) $fila->id,
                'Remito',
                [
                    $this->campo('Número', $nro),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Cliente', $tercero),
                ]
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cobranzas(string $q, ?string $numero): array
    {
        $query = Cobranza::query()
            ->leftJoin('cliente', 'cliente.id', '=', 'cobranza.cliente_id')
            ->select([
                'cobranza.id',
                'cobranza.numerotransaccion',
                'cobranza.fecha',
                'cobranza.monto',
                'cobranza.detalle',
                'cliente.nombre as nombretercero',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cobranza.empresa_id');

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('cobranza.numerotransaccion', (int) $numero);
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(cobranza.numerotransaccion AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('cliente.nombre', 'like', $like)
                    ->orWhere('cobranza.detalle', 'like', $like);
            }
        });
        $query->orderByDesc('cobranza.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = (string) $fila->numerotransaccion;
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                'Cobranza '.$nro,
                $this->detalle('Cobranza', $nro, $tercero),
                route('editar_cobranza', ['id' => (int) $fila->id]),
                'fa fa-usd',
                'cobranza:'.(int) $fila->id,
                'Cobranza',
                [
                    $this->campo('Número', $nro),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Monto', $this->importe($fila->monto)),
                    $this->campo('Cliente', $tercero),
                ]
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requisiciones(string $q, ?string $numero): array
    {
        $query = Requisicion::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->select([
                'requisicion.id',
                'requisicion.numerorequisicion',
                'requisicion.fecha',
                'requisicion.comentario',
                'requisicion.detalle',
                'proveedor.nombre as nombretercero',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'requisicion.empresa_id');

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('requisicion.numerorequisicion', (int) $numero)
                    ->orWhere('requisicion.id', (int) $numero);
                if (strlen($numero) >= 4) {
                    $w->orWhereRaw('CAST(requisicion.numerorequisicion AS CHAR) LIKE ?', [$numero.'%']);
                }
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('proveedor.nombre', 'like', $like)
                    ->orWhere('requisicion.comentario', 'like', $like)
                    ->orWhere('requisicion.detalle', 'like', $like);
            }
        });
        $query->orderByDesc('requisicion.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = (string) ($fila->numerorequisicion ?: $fila->id);
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $matchEn = null;
            $campos = [
                $this->campo('Número', $nro),
                $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                $this->campo('Proveedor', $tercero),
            ];
            foreach (['comentario' => 'Comentario', 'detalle' => 'Detalle'] as $attr => $label) {
                $texto = trim((string) ($fila->{$attr} ?? ''));
                if ($texto !== '' && mb_stripos($texto, $q) !== false) {
                    $matchEn = $label;
                    $campos[] = $this->campo($label, $this->recortar($texto, 80));
                    break;
                }
            }
            $filas[] = $this->fila(
                'Req. '.$nro,
                $this->detalle('Requisición', $nro, $tercero),
                route('editar_requisicion', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-list-alt',
                'requisicion:'.(int) $fila->id,
                'Requisición',
                $campos,
                $matchEn
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tickets(string $q, ?string $numero): array
    {
        $query = Ticket::query()
            ->select([
                'ticket.id',
                'ticket.fecha',
                'ticket.titulo',
                'ticket.comentario',
                'ticket.observacion',
                'ticket.estado_ticket',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'ticket.empresa_id', true);

        $like = '%'.$this->escapeLike($q).'%';
        $query->where(function ($w) use ($q, $numero, $like) {
            if ($numero !== null) {
                $w->where('ticket.id', (int) $numero);
            }
            if (preg_match('/\p{L}/u', $q) === 1 || $numero === null) {
                $w->orWhere('ticket.titulo', 'like', $like)
                    ->orWhere('ticket.comentario', 'like', $like)
                    ->orWhere('ticket.observacion', 'like', $like);
            }
        });
        $query->orderByDesc('ticket.id')->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $titulo = trim((string) ($fila->titulo ?: 'Ticket #'.$fila->id));
            $matchEn = null;
            $campos = [
                $this->campo('Nº', (string) $fila->id),
                $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                $this->campo('Estado', trim((string) ($fila->estado_ticket ?? ''))),
                $this->campo('Título', $titulo),
            ];
            foreach (['comentario' => 'Comentario', 'observacion' => 'Observación'] as $attr => $label) {
                $texto = trim((string) ($fila->{$attr} ?? ''));
                if ($texto !== '' && mb_stripos($texto, $q) !== false) {
                    $matchEn = $label;
                    $campos[] = $this->campo($label, $this->recortar($texto, 80));
                    break;
                }
            }
            $filas[] = $this->fila(
                'Ticket #'.$fila->id,
                $this->detalle('Ticket', (string) $fila->id, $titulo),
                route('edita_administracion_ticket', ['id' => (int) $fila->id]),
                'fa fa-ticket',
                'ticket:'.(int) $fila->id,
                'Ticket',
                $campos,
                $matchEn
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordenesPorProveedor(string $consulta): array
    {
        $q = trim($consulta);
        if (mb_strlen($q) < 3 || preg_match('/\p{L}/u', $q) !== 1) {
            return [];
        }

        $like = '%'.$this->escapeLike($q).'%';
        $prefijo = $this->escapeLike($q).'%';
        $query = Ordencompra::query()
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'proveedor.nombre as nombretercero',
            ]);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        $query->where(function ($w) use ($q, $like, $prefijo) {
            $w->where('proveedor.nombre', 'like', $prefijo)
                ->orWhere('proveedor.codigo', $q)
                ->orWhere('proveedor.codigo', 'like', $prefijo)
                ->orWhere('proveedor.nombre', 'like', $like)
                ->orWhere('proveedor.fantasia', 'like', $like);
        });
        $query->orderByRaw(
            'CASE WHEN proveedor.codigo = ? THEN 0 WHEN proveedor.nombre LIKE ? THEN 1 ELSE 2 END',
            [$q, $prefijo]
        );
        $query->orderByDesc('ordencompra.id');
        $query->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $nro = (string) $fila->numeroordencompra;
            $tercero = trim((string) ($fila->nombretercero ?? ''));
            $filas[] = $this->fila(
                'OC '.$nro,
                $this->detalle('Orden de compra', $nro, $tercero),
                route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-shopping-cart',
                'oc:'.(int) $fila->id,
                'Orden de compra',
                [
                    $this->campo('Número', $nro),
                    $this->campo('Fecha', $this->fechaDmY($fila->fecha ?? null)),
                    $this->campo('Proveedor', $tercero),
                ]
            );
        }

        return $filas;
    }

    private function porNumero(Builder $query, string $columna, string $numero, int $numeroInt): void
    {
        $query->where(function ($w) use ($columna, $numero, $numeroInt) {
            $w->where($columna, $numeroInt);
            if (strlen($numero) >= 4) {
                $w->orWhereRaw('CAST('.$columna.' AS CHAR) LIKE ?', [$numero.'%']);
            }
        });
    }

    private function numeroConsulta(string $consulta): ?string
    {
        if (preg_match_all('/\d{3,}/', $consulta, $grupos) < 1) {
            return null;
        }

        $mejor = '';
        foreach ($grupos[0] as $grupo) {
            if (strlen($grupo) > strlen($mejor)) {
                $mejor = $grupo;
            }
        }

        $sinCeros = ltrim($mejor, '0');

        return $sinCeros === '' ? null : $sinCeros;
    }

    /**
     * @param  list<array{label: string, valor: string}>  $campos
     * @return array<string, mixed>
     */
    private function fila(
        string $titulo,
        string $detalle,
        string $url,
        string $icono,
        string $clave,
        string $etiqueta,
        array $campos = [],
        ?string $matchEn = null,
        ?array $adjunto = null
    ): array {
        $campos = array_values(array_filter($campos, fn (array $c): bool => trim((string) ($c['valor'] ?? '')) !== ''));

        return [
            'titulo' => $titulo,
            'detalle' => $detalle,
            'url' => $url,
            'icono' => $icono,
            'nueva_pestana' => true,
            'tipo' => 'comprobante',
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'campos' => $campos,
            'match_en' => $matchEn,
            'adjunto' => $adjunto,
        ];
    }

    /**
     * @return array{label: string, valor: string}
     */
    private function campo(string $label, ?string $valor): array
    {
        return ['label' => $label, 'valor' => trim((string) $valor)];
    }

    private function detalle(string $tipo, string $numero, string $tercero): string
    {
        $detalle = $tipo.' · '.$numero;
        if ($tercero !== '') {
            $detalle .= ' · '.$tercero;
        }

        return $detalle;
    }

    private function numeroFiscal($letra, $sucursal, $numero): string
    {
        $letra = strtoupper(trim((string) $letra));
        $suc = str_pad((string) ((int) $sucursal), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) $numero), 8, '0', STR_PAD_LEFT);
        if ($letra !== '') {
            return $letra.' '.$suc.'-'.$nro;
        }

        return $suc.'-'.$nro;
    }

    private function fechaDmY($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private function importe($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, 2, ',', '.');
    }

    private function recortar(string $texto, int $max): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1).'…';
    }

    private function permitido(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if (can($slug, false)) {
                return true;
            }
        }

        return false;
    }

    private function escapeLike(string $valor): string
    {
        return addcslashes($valor, '%_\\');
    }
}
