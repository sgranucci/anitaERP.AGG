<?php

namespace App\Support\Seguridad;

use App\Models\Compras\Ordencompra;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Repositories\Ventas\VendedorRepository;
use App\Support\Ventas\KiloPedidoListadoFiltros;

/**
 * Comprobantes del buscador: orden de compra, pedido y factura, por número.
 * La orden de compra también se busca por proveedor cuando el texto no es un número.
 * El mismo número puede salir en más de un tipo. Cada fila dice cuál es.
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
        $numero = $this->numeroConsulta($consulta);
        $filas = [];
        if ($numero === null) {
            if ($this->permitido('listar-ordencompra', 'editar-ordencompra', 'actualizar-ordencompra')) {
                return $this->ordenesPorProveedor($consulta);
            }

            return [];
        }

        if ($this->permitido('listar-ordencompra', 'editar-ordencompra', 'actualizar-ordencompra')) {
            $filas = array_merge($filas, $this->ordenes($numero));
        }
        if ($this->permitido('listar-pedidos', 'editar-pedidos', 'actualizar-pedidos')) {
            $filas = array_merge($filas, $this->pedidos($consulta, $numero));
        }
        if ($this->permitido('listar-factura', 'editar-factura', 'actualizar-factura')) {
            $filas = array_merge($filas, $this->facturas($numero));
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
            $detalle = 'Orden de compra · '.$nro;
            if ($tercero !== '') {
                $detalle .= ' · '.$tercero;
            }
            $filas[] = $this->fila(
                'OC '.$nro,
                $detalle,
                route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-shopping-cart',
                'oc:'.(int) $fila->id
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
            $detalle = 'Pedido · '.$visible;
            if ($tercero !== '') {
                $detalle .= ' · '.$tercero;
            }
            $filas[] = $this->fila(
                'Pedido '.$visible,
                $detalle,
                route('editar_pedido', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-clipboard',
                'pedido:'.(int) $fila->id
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
            $detalle = $tipo.' · '.$visible;
            if ($tercero !== '') {
                $detalle .= ' · '.$tercero;
            }
            $filas[] = $this->fila(
                $tipo.' '.$nro,
                $detalle,
                route('editar_factura', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-file-text',
                'factura:'.(int) $fila->id
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
            $detalle = 'Orden de compra · '.$nro;
            if ($tercero !== '') {
                $detalle .= ' · '.$tercero;
            }
            $filas[] = $this->fila(
                'OC '.$nro,
                $detalle,
                route('editar_ordencompra', [
                    'id' => (int) $fila->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'fa fa-shopping-cart',
                'oc:'.(int) $fila->id
            );
        }

        return $filas;
    }

    private function porNumero($query, string $columna, string $numero, int $numeroInt): void
    {
        $query->where(function ($w) use ($columna, $numero, $numeroInt) {
            $w->where($columna, $numeroInt);
            if (strlen($numero) >= 4) {
                $w->orWhereRaw('CAST('.$columna.' AS CHAR) LIKE ?', [$numero.'%']);
            }
        });
    }

    /**
     * El grupo de dígitos más largo. «PED-X-00001-00513365» usa 513365.
     */
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
     * @return array<string, mixed>
     */
    private function fila(string $titulo, string $detalle, string $url, string $icono, string $clave): array
    {
        return [
            'titulo' => $titulo,
            'detalle' => $detalle,
            'url' => $url,
            'icono' => $icono,
            'nueva_pestana' => true,
            'tipo' => 'comprobante',
            'clave' => $clave,
        ];
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
