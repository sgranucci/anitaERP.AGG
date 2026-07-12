<?php

namespace App\Imports\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Talle;
use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class PrecioImport implements OnEachRow, WithHeadingRow, WithMultipleSheets
{
    /** @var array<int, array<int, list<string>>>|null */
    private ?array $heading;

    private string $formato;

    private ?int $listaprecioId;

    private string $colSku;

    private ?string $colDescripcion;

    private string $colPrecio;

    public int $filasLeidas = 0;

    public int $filasOmitidas = 0;

    public int $filasDuplicadas = 0;

    public int $preciosCreados = 0;

    public int $preciosActualizados = 0;

    /** @var list<string> */
    public array $skusGrabados = [];

    /** @var array<string, true> */
    private array $clavesYaGrabadas = [];

    /**
     * @param  array<int, array<int, list<string>>>|null  $heading  Encabezados (formato listas).
     */
    public function __construct(
        private string $fechavigencia,
        private int $monedaId,
        ?array $heading = null,
        string $formato = PrecioImportColumnasSupport::FORMATO_SIMPLE,
        ?int $listaprecioId = null,
        ?string $colSku = null,
        ?string $colDescripcion = null,
        ?string $colPrecio = null,
        private int $filaEncabezado = 1,
        private int $hojaIndice = 0,
    ) {
        $this->heading = $heading;
        $this->formato = $formato;
        $this->listaprecioId = $listaprecioId;
        $this->colSku = $colSku ?? PrecioImportColumnasSupport::COL_SKU_DEFAULT;
        $this->colDescripcion = $colDescripcion !== null && trim($colDescripcion) !== ''
            ? $colDescripcion
            : PrecioImportColumnasSupport::COL_DESCRIPCION_DEFAULT;
        $this->colPrecio = $colPrecio ?? PrecioImportColumnasSupport::COL_PRECIO_DEFAULT;
    }

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        if ($this->filaAsociativaVacia($data)) {
            return;
        }

        $this->filasLeidas++;

        if ($this->formato === PrecioImportColumnasSupport::FORMATO_SIMPLE) {
            $this->procesarFilaSimple($data);

            return;
        }

        $this->procesarFilaListas($data);
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            'filas_leidas' => $this->filasLeidas,
            'filas_omitidas' => $this->filasOmitidas,
            'filas_duplicadas' => $this->filasDuplicadas,
            'precios_creados' => $this->preciosCreados,
            'precios_actualizados' => $this->preciosActualizados,
            'precios_grabados' => $this->preciosCreados + $this->preciosActualizados,
            'articulos_distintos' => count($this->clavesYaGrabadas),
            'skus_grabados' => array_values(array_unique($this->skusGrabados)),
        ];
    }

    /** Compatibilidad con mensajes previos. */
    public function filasProcesadas(): int
    {
        return $this->preciosCreados + $this->preciosActualizados;
    }

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [$this->hojaIndice => $this];
    }

    public function hojaIndiceUsada(): int
    {
        return $this->hojaIndice;
    }

    public function headingRow(): int
    {
        return max(1, $this->filaEncabezado);
    }

    public function filaEncabezadoUsada(): int
    {
        return $this->headingRow();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function procesarFilaSimple(array $row): void
    {
        $sku = trim((string) PrecioImportColumnasSupport::valorColumnaSku($row, $this->colSku));
        if ($sku === '') {
            $this->filasOmitidas++;

            return;
        }

        $precioRaw = PrecioImportColumnasSupport::valorColumnaPrecio($row, $this->colPrecio);
        $precio = $this->normalizarPrecio($precioRaw);
        if ($precio === null || $precio == 0.0) {
            $this->filasOmitidas++;

            return;
        }

        $articulo = Articulo::query()
            ->select('id', 'nofactura')
            ->where('sku', $sku)
            ->first();

        if (! $articulo || (string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
            $this->filasOmitidas++;

            return;
        }

        // col_descripcion es informativa en el Excel; la búsqueda es solo por SKU.

        if ($this->listaprecioId === null || $this->listaprecioId < 1) {
            $this->filasOmitidas++;

            return;
        }

        $this->grabarPrecio((int) $articulo->id, $this->listaprecioId, $precio, $sku);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function procesarFilaListas(array $row): void
    {
        $sku = trim((string) ($row['articulo'] ?? ''));
        if ($sku === '') {
            $this->filasOmitidas++;

            return;
        }

        $articulo = Articulo::query()
            ->select('id', 'nofactura')
            ->where('sku', $sku)
            ->first();

        if (! $articulo || (string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
            $this->filasOmitidas++;

            return;
        }

        foreach ($this->heading ?? [] as $lineaEncabezado) {
            foreach ($lineaEncabezado[0] as $nombreColumna) {
                $nombreInicial = substr((string) $nombreColumna, 0, 2);

                if ($nombreInicial !== 'L_' && $nombreInicial !== 'l_') {
                    continue;
                }

                $codigoLista = str_replace($nombreInicial, '', (string) $nombreColumna);
                $listaprecio = Listaprecio::query()->select('id')->where('codigo', $codigoLista)->first();

                if (! $listaprecio) {
                    continue;
                }

                $claveFila = $this->resolverClaveFilaListas($row, (string) $nombreColumna);
                $precio = $this->normalizarPrecio($claveFila !== null ? $row[$claveFila] : null);
                if ($precio === null || $precio == 0.0) {
                    continue;
                }

                $this->grabarPrecio(
                    (int) $articulo->id,
                    (int) $listaprecio->id,
                    $precio,
                    $sku
                );
            }
        }
    }

    private function grabarPrecio(int $articuloId, int $listaprecioId, float $precio, string $sku): void
    {
        $fechavigencia = $this->parseFechaVigencia();
        $clave = $articuloId.'|'.$listaprecioId.'|'.$fechavigencia->format('Y-m-d');
        $duplicadoEnArchivo = isset($this->clavesYaGrabadas[$clave]);

        $existente = Precio::query()
            ->where('articulo_id', $articuloId)
            ->where('listaprecio_id', $listaprecioId)
            ->whereDate('fechavigencia', $fechavigencia)
            ->first();

        $payload = [
            'articulo_id' => $articuloId,
            'listaprecio_id' => $listaprecioId,
            'fechavigencia' => $fechavigencia,
            'moneda_id' => $this->monedaId,
            'precio' => $precio,
            'precioanterior' => 0,
            'usuarioultcambio_id' => Auth::id(),
        ];

        if ($existente) {
            $existente->update($payload);
            if (! $duplicadoEnArchivo) {
                $this->preciosActualizados++;
            }
        } else {
            Precio::create($payload);
            if (! $duplicadoEnArchivo) {
                $this->preciosCreados++;
            }
        }

        if ($duplicadoEnArchivo) {
            $this->filasDuplicadas++;
        } else {
            $this->clavesYaGrabadas[$clave] = true;
            $this->skusGrabados[] = $sku;
        }

        $this->propagarPrecioEnPedidosYMovimientos($articuloId, $listaprecioId, $precio);
    }

    private function propagarPrecioEnPedidosYMovimientos(int $articuloId, int $listaprecioId, float $precio): void
    {
        $listaprecio = Listaprecio::query()->find($listaprecioId);

        DB::table('pedido_combinacion')
            ->where('articulo_id', $articuloId)
            ->update(['precio' => $precio]);

        $desdetalle = null;
        $hastatalle = null;
        if ($listaprecio) {
            $desdetalle = Talle::query()->select('id')->where('nombre', $listaprecio->desdetalle)->first();
            $hastatalle = Talle::query()->select('id')->where('nombre', $listaprecio->hastatalle)->first();
        }

        if ($desdetalle && $hastatalle) {
            DB::table('pedido_combinacion_talle')
                ->join('pedido_combinacion', 'pedido_combinacion_talle.pedido_combinacion_id', '=', 'pedido_combinacion.id')
                ->where('pedido_combinacion.articulo_id', $articuloId)
                ->whereBetween('pedido_combinacion_talle.talle_id', [$desdetalle->id, $hastatalle->id])
                ->update(['pedido_combinacion_talle.precio' => $precio]);

            DB::table('articulo_movimiento_talle')
                ->join('articulo_movimiento', 'articulo_movimiento_talle.articulo_movimiento_id', '=', 'articulo_movimiento.id')
                ->where('articulo_movimiento.articulo_id', $articuloId)
                ->whereBetween('articulo_movimiento_talle.talle_id', [$desdetalle->id, $hastatalle->id])
                ->update(['articulo_movimiento_talle.precio' => $precio]);
        }

        DB::table('articulo_movimiento')
            ->where('articulo_id', $articuloId)
            ->update(['precio' => $precio]);
    }

    private function parseFechaVigencia(): Carbon
    {
        $fecha = trim($this->fechavigencia);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        }

        return Carbon::createFromFormat('d-m-Y', $fecha)->startOfDay();
    }

    private function normalizarPrecio(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[^\d,.-]/', '', (string) $valor);
        if ($texto === '' || $texto === null) {
            return null;
        }

        return (float) str_replace(',', '.', $texto);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolverClaveFilaListas(array $row, string $nombreColumna): ?string
    {
        if (array_key_exists($nombreColumna, $row)) {
            return $nombreColumna;
        }

        $normalizado = PrecioImportColumnasSupport::normalizarNombreColumna($nombreColumna);
        foreach (array_keys($row) as $key) {
            if (PrecioImportColumnasSupport::normalizarNombreColumna((string) $key) === $normalizado) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function filaAsociativaVacia(array $row): bool
    {
        foreach ($row as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
