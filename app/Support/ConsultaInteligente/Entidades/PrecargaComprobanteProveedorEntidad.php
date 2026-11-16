<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteProveedorListadoFiltros;
use App\Support\ConsultaInteligente\EntidadSemantica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PrecargaComprobanteProveedorEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'precarga_comprobante_proveedor';
    }

    public function nombre(): string
    {
        return 'Precargas de factura';
    }

    public function descripcion(): string
    {
        return 'Precargas Document AI / mail: estado, proveedor, OC y total.';
    }

    public function icono(): string
    {
        return 'fa-file-invoice';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#B9770E';
    }

    public function permisoListar(): string
    {
        return 'listar-precarga-proveedores';
    }

    public function rutaNativa(): string
    {
        return 'precarga_comprobante_proveedor';
    }

    public function recurso(): string
    {
        return 'compras.precarga_comprobante_proveedor';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (PrecargaComprobanteProveedorListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return [
            'id', 'fechafactura', 'nombreproveedor', 'nombreempresa',
            'abreviaturatipotransaccion_compra', 'letra', 'sucursal', 'numerocomprobante',
            'total', 'estado',
        ];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'fechafactura' => 'Fecha',
            'nombreproveedor' => 'Proveedor',
            'nombreempresa' => 'Empresa',
            'abreviaturatipotransaccion_compra' => 'Tipo',
            'letra' => 'Letra',
            'sucursal' => 'Suc.',
            'numerocomprobante' => 'Número',
            'total' => 'Total',
            'estado' => 'Estado',
            'numeroordencompra' => 'OC',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if ($key === 'fechafactura' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }
        if ($key === 'total' && is_numeric($v)) {
            return e(number_format((float) $v, 2, ',', '.'));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        return PrecargaComprobanteProveedorListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return PrecargaComprobanteProveedorListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(Precarga_Comprobante_ProveedorRepositoryInterface::class)
            ->leePrecargaComprobanteProveedor($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-precarga-proveedores', false)) {
            return null;
        }

        return route('editar_precarga_comprobante_proveedor', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'pendientes',
                'titulo' => 'Precargas pendientes',
                'descripcion' => 'Estado PENDIENTE (listas para cargar)',
                'query' => [
                    'consultar' => 1,
                    'estado' => PrecargaComprobanteEstados::PENDIENTE,
                ],
            ],
            [
                'clave' => 'generadas',
                'titulo' => 'Precargas generadas',
                'descripcion' => 'Ya tienen comprobante ERP',
                'query' => [
                    'consultar' => 1,
                    'estado' => PrecargaComprobanteEstados::GENERADA,
                ],
            ],
            [
                'clave' => 'pendiente_entrega',
                'titulo' => 'Pendiente de entrega',
                'descripcion' => 'Retenidas hasta que llegue mercadería',
                'query' => [
                    'consultar' => 1,
                    'estado_todas' => 1,
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'estado',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => PrecargaComprobanteEstados::PENDIENTE_ENTREGA,
                ],
            ],
        ];
    }
}
