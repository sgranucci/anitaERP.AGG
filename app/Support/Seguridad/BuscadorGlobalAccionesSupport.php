<?php

namespace App\Support\Seguridad;

use Illuminate\Support\Facades\Route;

/**
 * Altas desde el buscador. Solo entra si lo escrito pide crear
 * y el rol ya puede abrir esa pantalla.
 */
class BuscadorGlobalAccionesSupport
{
    public const LIMITE = 6;

    /** @var list<string> */
    private const VERBOS = ['nueva', 'nuevo', 'nuevas', 'nuevos', 'crear', 'alta', 'altas'];

    /** @var list<string> */
    private const VACIAS = ['de', 'del', 'un', 'una', 'el', 'la', 'los', 'las', 'al', 'en'];

    /**
     * @var list<array{clave: string, titulo: string, permiso: string, ruta: string, icono: string, terminos: list<string>}>
     */
    private const CATALOGO = [
        [
            'clave' => 'factura',
            'titulo' => 'Crear factura',
            'permiso' => 'crear-factura',
            'ruta' => 'crear_factura',
            'icono' => 'fa fa-file-text',
            'terminos' => ['factura', 'facturas', 'facturacion'],
        ],
        [
            'clave' => 'cliente',
            'titulo' => 'Crear cliente',
            'permiso' => 'crear-clientes',
            'ruta' => 'crear_cliente',
            'icono' => 'fa fa-user',
            'terminos' => ['cliente', 'clientes'],
        ],
        [
            'clave' => 'articulo',
            'titulo' => 'Crear artículo',
            'permiso' => 'crear-articulos',
            'ruta' => 'crear_articulo',
            'icono' => 'fa fa-cube',
            'terminos' => ['articulo', 'articulos', 'producto', 'productos', 'sku'],
        ],
        [
            'clave' => 'proveedor',
            'titulo' => 'Crear proveedor',
            'permiso' => 'crear-proveedor',
            'ruta' => 'crear_proveedor',
            'icono' => 'fa fa-truck',
            'terminos' => ['proveedor', 'proveedores'],
        ],
        [
            'clave' => 'ordencompra',
            'titulo' => 'Crear orden de compra',
            'permiso' => 'crear-ordencompra',
            'ruta' => 'crear_ordencompra',
            'icono' => 'fa fa-shopping-cart',
            'terminos' => ['oc', 'orden de compra', 'ordenes de compra', 'orden compra'],
        ],
        [
            'clave' => 'pedido',
            'titulo' => 'Crear pedido',
            'permiso' => 'crear-pedidos',
            'ruta' => 'crear_pedido',
            'icono' => 'fa fa-clipboard',
            'terminos' => ['pedido', 'pedidos'],
        ],
        [
            'clave' => 'remito',
            'titulo' => 'Crear remito',
            'permiso' => 'crear-remitos',
            'ruta' => 'crear_remito',
            'icono' => 'fa fa-file',
            'terminos' => ['remito', 'remitos'],
        ],
        [
            'clave' => 'cheque',
            'titulo' => 'Crear cheque',
            'permiso' => 'crear-cheque',
            'ruta' => 'crear_cheque',
            'icono' => 'fa fa-money',
            'terminos' => ['cheque', 'cheques', 'echeq'],
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function buscar(string $consultaNormalizada): array
    {
        if (! $this->pideAlta($consultaNormalizada)) {
            return [];
        }

        $resto = $this->resto($consultaNormalizada);
        $filas = [];
        foreach (self::CATALOGO as $alta) {
            if ($resto !== '' && ! $this->coincide($resto, $alta['terminos'])) {
                continue;
            }
            if (! can($alta['permiso'], false) || ! Route::has($alta['ruta'])) {
                continue;
            }
            $filas[] = [
                'titulo' => $alta['titulo'],
                'detalle' => 'Alta nueva',
                'url' => route($alta['ruta']),
                'icono' => $alta['icono'],
                'tipo' => 'accion',
                'clave' => 'accion:'.$alta['clave'],
            ];
            if (count($filas) >= self::LIMITE) {
                break;
            }
        }

        return $filas;
    }

    private function pideAlta(string $consulta): bool
    {
        foreach (preg_split('/\s+/', $consulta) ?: [] as $token) {
            if (in_array($token, self::VERBOS, true)) {
                return true;
            }
        }

        return false;
    }

    private function resto(string $consulta): string
    {
        $tokens = [];
        foreach (preg_split('/\s+/', $consulta) ?: [] as $token) {
            if ($token === '' || in_array($token, self::VERBOS, true) || in_array($token, self::VACIAS, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param  list<string>  $terminos
     */
    private function coincide(string $resto, array $terminos): bool
    {
        foreach ($terminos as $termino) {
            if ($resto === $termino) {
                return true;
            }
            if (mb_strlen($termino) < 4) {
                continue;
            }
            if (str_contains($resto, $termino)) {
                return true;
            }
        }

        return false;
    }
}
