<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PrecioActualizacionCategoriaService
{
  /**
   * @return array{
   *   categoria_id: int,
   *   categoria_nombre: string,
   *   listaprecio_id: ?int,
   *   listaprecio_nombre: ?string,
   *   fecha_referencia: string,
   *   nueva_fechavigencia: string,
   *   porcentaje: float,
   *   articulos_facturables: int,
   *   precios_a_actualizar: int,
   *   muestra: array<int, array<string, mixed>>
   * }
   */
  public function previsualizar(
    int $categoriaId,
    ?int $listaprecioId,
    string $fechaReferencia,
    string $nuevaFechavigencia,
    float $porcentaje
  ): array {
    $categoria = Categoria::query()->findOrFail($categoriaId);
    $listaprecio = $listaprecioId !== null && $listaprecioId > 0
      ? Listaprecio::query()->find($listaprecioId)
      : null;

    $articuloIds = $this->articuloIdsFacturablesEnCategoria($categoriaId);
    $vigentes = $this->preciosVigentesPorArticulos(
      $articuloIds,
      $listaprecioId,
      $fechaReferencia
    );

    $muestra = [];
    foreach (array_slice($vigentes, 0, 15) as $row) {
      $muestra[] = $this->formatearFilaPreview($row, $porcentaje);
    }

    return [
      'categoria_id' => $categoriaId,
      'categoria_nombre' => (string) $categoria->nombre,
      'listaprecio_id' => $listaprecio?->id,
      'listaprecio_nombre' => $listaprecio?->nombre,
      'fecha_referencia' => $fechaReferencia,
      'nueva_fechavigencia' => $nuevaFechavigencia,
      'porcentaje' => $porcentaje,
      'articulos_facturables' => count($articuloIds),
      'precios_a_actualizar' => count($vigentes),
      'muestra' => $muestra,
    ];
  }

  /**
   * @return array{creados: int, omitidos: int}
   */
  public function aplicar(
    int $categoriaId,
    ?int $listaprecioId,
    string $fechaReferencia,
    string $nuevaFechavigencia,
    float $porcentaje
  ): array {
    $articuloIds = $this->articuloIdsFacturablesEnCategoria($categoriaId);
    $vigentes = $this->preciosVigentesPorArticulos(
      $articuloIds,
      $listaprecioId,
      $fechaReferencia
    );

    $nuevaFecha = Carbon::parse($nuevaFechavigencia)->format('Y-m-d');
    $usuarioId = Auth::id();
    $creados = 0;
    $omitidos = 0;

    foreach ($vigentes as $row) {
      $precioActual = (float) $row['precio'];
      $precioNuevo = $this->calcularPrecio($precioActual, $porcentaje);

      if ($precioNuevo <= 0) {
        $omitidos++;

        continue;
      }

      $fvExistente = Carbon::parse($row['fechavigencia'])->format('Y-m-d');
      if ($fvExistente === $nuevaFecha && abs($precioActual - $precioNuevo) < 0.0001) {
        $omitidos++;

        continue;
      }

      $existeMismaVigencia = Precio::query()
        ->where('articulo_id', $row['articulo_id'])
        ->where('listaprecio_id', $row['listaprecio_id'])
        ->whereDate('fechavigencia', $nuevaFecha)
        ->exists();

      if ($existeMismaVigencia) {
        $omitidos++;

        continue;
      }

      Precio::create([
        'articulo_id' => $row['articulo_id'],
        'listaprecio_id' => $row['listaprecio_id'],
        'fechavigencia' => $nuevaFecha,
        'moneda_id' => $row['moneda_id'],
        'precio' => $precioNuevo,
        'precioanterior' => $precioActual,
        'usuarioultcambio_id' => $usuarioId,
      ]);
      $creados++;
    }

    return [
      'creados' => $creados,
      'omitidos' => $omitidos,
    ];
  }

  /**
   * @return array<int, int>
   */
  private function articuloIdsFacturablesEnCategoria(int $categoriaId): array
  {
    $q = Articulo::query()
      ->select('articulo.id')
      ->where('articulo.categoria_id', $categoriaId);

    PrecioSoloFacturableSupport::aplicarFiltroQuery($q, 'articulo.nofactura');

    return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
  }

  /**
   * @param  array<int, int>  $articuloIds
   * @return array<int, array<string, mixed>>
   */
  private function preciosVigentesPorArticulos(
    array $articuloIds,
    ?int $listaprecioId,
    string $fechaReferencia
  ): array {
    if ($articuloIds === []) {
      return [];
    }

    $q = Precio::query()
      ->select([
        'precio.id',
        'precio.articulo_id',
        'precio.listaprecio_id',
        'precio.fechavigencia',
        'precio.precio',
        'precio.moneda_id',
        'articulo.sku',
        'articulo.descripcion',
        'articulo.detalle',
        'listaprecio.nombre as listaprecio_nombre',
      ])
      ->join('articulo', 'articulo.id', '=', 'precio.articulo_id')
      ->join('listaprecio', 'listaprecio.id', '=', 'precio.listaprecio_id')
      ->whereIn('precio.articulo_id', $articuloIds);
    PrecioListaVigenteSupport::aplicarFiltroVigenteEnQuery($q, $fechaReferencia, 'precio', $articuloIds, $listaprecioId);

    $rows = $q->orderBy('articulo.sku')->orderBy('listaprecio.nombre')->get();

    return $rows->map(function ($row) {
      return [
        'id' => (int) $row->id,
        'articulo_id' => (int) $row->articulo_id,
        'listaprecio_id' => (int) $row->listaprecio_id,
        'fechavigencia' => Carbon::parse($row->fechavigencia)->format('Y-m-d'),
        'precio' => (float) $row->precio,
        'moneda_id' => (int) $row->moneda_id,
        'sku' => (string) $row->sku,
        'descripcion' => $this->descripcionArticulo($row),
        'listaprecio_nombre' => (string) $row->listaprecio_nombre,
      ];
    })->all();
  }

  private function calcularPrecio(float $precioActual, float $porcentaje): float
  {
    return round($precioActual * (1 + ($porcentaje / 100)), 2);
  }

  /**
   * @param  array<string, mixed>  $row
   * @return array<string, mixed>
   */
  private function formatearFilaPreview(array $row, float $porcentaje): array
  {
    $precioNuevo = $this->calcularPrecio((float) $row['precio'], $porcentaje);

    return [
      'sku' => $row['sku'],
      'descripcion' => $row['descripcion'],
      'listaprecio_nombre' => $row['listaprecio_nombre'],
      'precio_actual' => (float) $row['precio'],
      'precio_nuevo' => $precioNuevo,
    ];
  }

  private function descripcionArticulo(object $row): string
  {
    $descripcion = trim((string) ($row->descripcion ?? ''));
    if ($descripcion === '') {
      $descripcion = trim((string) ($row->detalle ?? ''));
    }
    if ($descripcion === '') {
      $descripcion = (string) ($row->sku ?? '');
    }

    return $descripcion;
  }
}
