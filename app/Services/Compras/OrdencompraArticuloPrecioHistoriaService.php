<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Ordencompra_Articulo_Precio_Historia;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Compras\OrdencompraArticuloPrecioHistoriaOrigen;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class OrdencompraArticuloPrecioHistoriaService
{
    public function registrar(
        Ordencompra_Articulo $ocArt,
        float $precioAnterior,
        float $precioNuevo,
        Recepcion_Proveedor $recepcion,
        ?Recepcion_Proveedor_Articulo $linea,
        string $origen,
        ?string $comentario = null,
        ?int $usuarioId = null,
    ): Ordencompra_Articulo_Precio_Historia {
        $comentarioFinal = trim((string) ($comentario ?? ''));
        if ($comentarioFinal === '' && $linea !== null) {
            $comentarioFinal = trim((string) ($linea->comentario_precio ?? ''));
        }

        return Ordencompra_Articulo_Precio_Historia::create([
            'ordencompra_id' => (int) $ocArt->ordencompra_id,
            'ordencompra_articulo_id' => (int) $ocArt->id,
            'articulo_id' => (int) ($ocArt->articulo_id ?? 0) ?: null,
            'precio_anterior' => $precioAnterior,
            'precio_nuevo' => $precioNuevo,
            'recepcion_proveedor_id' => (int) $recepcion->id,
            'recepcion_proveedor_articulo_id' => $linea !== null ? (int) $linea->id : null,
            'origen' => $origen,
            'comentario' => $comentarioFinal !== '' ? $comentarioFinal : null,
            'usuario_id' => $this->resolverUsuarioId($usuarioId, $recepcion),
            'fecha' => Carbon::now(),
        ]);
    }

    /**
     * @param  list<array{sku: string, precio_anterior: float, precio_nuevo: float}>  $cambios
     */
    public function registrarResumenLegajo(
        Ordencompra $oc,
        Recepcion_Proveedor $recepcion,
        array $cambios,
        string $origen,
        ?int $usuarioId = null,
    ): void {
        if ($cambios === []) {
            return;
        }

        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);
        if ($sectorId <= 0) {
            return;
        }

        $lineasLeyenda = [];
        foreach ($cambios as $cambio) {
            $lineasLeyenda[] = sprintf(
                '%s: %s → %s',
                $cambio['sku'],
                $this->formatearPrecio($cambio['precio_anterior']),
                $this->formatearPrecio($cambio['precio_nuevo'])
            );
        }

        $docRecepcion = $this->documentoRecepcion($recepcion);
        $origenEtiqueta = OrdencompraArticuloPrecioHistoriaOrigen::etiqueta($origen);

        Ordencompra_Historia::create([
            'ordencompra_id' => (int) $oc->id,
            'sector_legajocompra_id' => $sectorId,
            'fecha' => Carbon::now(),
            'observacion' => 'Actualización de precio desde recepción ('.$origenEtiqueta.')',
            'leyenda' => $docRecepcion.': '.implode('; ', $lineasLeyenda),
            'creousuario_id' => $this->resolverUsuarioId($usuarioId, $recepcion),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorOrdencompra(int $ordencompraId): array
    {
        $filas = Ordencompra_Articulo_Precio_Historia::query()
            ->where('ordencompra_id', $ordencompraId)
            ->with([
                'articulos:id,sku,nombre',
                'usuarios:id,nombre',
                'recepcion_proveedores:id,numerorecepcion,anita_tipo,anita_letra,anita_sucursal,anita_nro,tipo',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $items = [];
        foreach ($filas as $fila) {
            $rec = $fila->recepcion_proveedores;
            $items[] = [
                'id' => (int) $fila->id,
                'fecha' => $fila->fecha?->format('Y-m-d H:i:s'),
                'sku' => $fila->articulos?->sku,
                'descripcion' => $fila->articulos?->nombre,
                'precio_anterior' => (float) $fila->precio_anterior,
                'precio_nuevo' => (float) $fila->precio_nuevo,
                'origen' => (string) $fila->origen,
                'origen_etiqueta' => OrdencompraArticuloPrecioHistoriaOrigen::etiqueta((string) $fila->origen),
                'comentario' => $fila->comentario,
                'usuario' => $fila->usuarios?->nombre,
                'recepcion_id' => $rec ? (int) $rec->id : null,
                'recepcion_documento' => $rec ? $this->documentoRecepcion($rec) : null,
                'recepcion_url' => $rec ? route('editar_recepcion_proveedor', ['id' => $rec->id]) : null,
            ];
        }

        return $items;
    }

    private function resolverUsuarioId(?int $usuarioId, Recepcion_Proveedor $recepcion): int
    {
        if ($usuarioId !== null && $usuarioId > 0) {
            return $usuarioId;
        }

        $authId = Auth::id();
        if ($authId) {
            return (int) $authId;
        }

        return (int) ($recepcion->creousuario_id ?? 1);
    }

    private function documentoRecepcion(Recepcion_Proveedor $rec): string
    {
        $prefijo = $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? 'DEV' : 'COM';

        if ($rec->anita_tipo && $rec->anita_sucursal && $rec->anita_nro) {
            return sprintf(
                '%s %s %c %d-%d',
                $prefijo,
                $rec->anita_tipo,
                $rec->anita_letra ?? ' ',
                $rec->anita_sucursal,
                $rec->anita_nro
            );
        }

        $nro = $rec->numerorecepcion ?: $rec->id;

        return $prefijo.' #'.$nro;
    }

    private function formatearPrecio(float $valor): string
    {
        return number_format($valor, 4, ',', '.');
    }
}
