<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Ordencompra_Articulo_Entrega;
use App\Models\Compras\Requisicion_Articulo;
use App\Support\Compras\AhorroLineaSupport;
use App\Models\Stock\Articulo;
use App\Support\Compras\OrdencompraUiConfigSupport;
use App\Support\Stock\ArticuloStockColorTalleSupport;

class Ordencompra_ArticuloRepository implements Ordencompra_ArticuloRepositoryInterface
{
    public function __construct(private Ordencompra_Articulo $model)
    {
    }

    public function createDesdeAnita(array $data)
    {
        return $this->model->create($data);
    }

    public function syncFromRequest(array $data, int $ordencompra_id): void
    {
        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            $this->model->where('ordencompra_id', $ordencompra_id)->delete();

            return;
        }

        $idsExistentes = $this->model->where('ordencompra_id', $ordencompra_id)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);
        $idsEntrantes = $data['ordencompra_articulo_ids'] ?? [];
        $idsAConservar = [];
        $aActualizar = [];
        $aInsertar = [];
        $entregasPorIndice = [];
        $mostrarPeso = OrdencompraUiConfigSupport::mostrarPesoArticulo();
        $entregaSemanal = OrdencompraUiConfigSupport::entregaSemanal();
        $pesosArticuloCache = [];

        $cargaAhorro = AhorroLineaSupport::tablaTieneColumnasAhorro('ordencompra_articulo');
        $fechaAhorro = substr((string) ($data['fecha'] ?? date('Y-m-d')), 0, 10);

        $n = count($data['articulo_ids']);
        for ($i = 0; $i < $n; $i++) {
            $articulo_id = $data['articulo_ids'][$i] ?? null;
            if ($articulo_id === null || $articulo_id === '') {
                continue;
            }
            $cantidad = (float) ($data['cantidades'][$i] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $precio = (float) ($data['precios'][$i] ?? 0);
            $cot = (float) ($data['cotizaciones_linea'][$i] ?? 1);
            if ($cot <= 0) {
                $cot = 1.0;
            }
            $colorId = (int) ($data['colores_id'][$i] ?? 0);
            $talleId = (int) ($data['talles_id'][$i] ?? 0);
            [$colorMov, $talleMov] = ArticuloStockColorTalleSupport::valoresMovimiento(
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null,
            );

            $fechaEntrega = $data['fechaentrega_articulos'][$i] ?? $data['fechaentrega'] ?? $data['fecha'] ?? date('Y-m-d');
            $entregas = $entregaSemanal
                ? $this->parseEntregasSemanal($data, $i)
                : null;
            if ($entregas !== null && $entregas !== []) {
                $sumaEntregas = 0.0;
                $fechas = [];
                foreach ($entregas as $ent) {
                    $sumaEntregas += (float) $ent['cantidad'];
                    $fechas[] = (string) $ent['fecha'];
                }
                if ($sumaEntregas > 0) {
                    $cantidad = $sumaEntregas;
                }
                sort($fechas);
                if ($fechas !== []) {
                    $fechaEntrega = $fechas[0];
                }
            }

            $payload = [
                'ordencompra_id' => $ordencompra_id,
                'fechaentrega' => $fechaEntrega,
                'articulo_id' => $articulo_id,
                'color_id' => $colorMov,
                'talle_id' => $talleMov,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'moneda_id' => $data['moneda_linea_ids'][$i] ?? $data['moneda_id'] ?? 1,
                'cotizacion' => $cot,
                'descuento' => isset($data['descuentos_linea'][$i]) ? (float) $data['descuentos_linea'][$i] : null,
                'cantidadalternativa' => (float) ($data['cantidadalternativas'][$i] ?? 0),
                'detalle' => $data['detalle_articulos'][$i] ?? '',
                'centrocostodestino_id' => $data['centrocostodestino_ids'][$i] ?? $data['centrocosto_id'],
                'partidagasto_id' => ! empty($data['partidagasto_ids'][$i]) ? $data['partidagasto_ids'][$i] : null,
                'capex_id' => ! empty($data['capex_ids'][$i]) ? $data['capex_ids'][$i] : null,
                'requisicion_articulo_id' => ! empty($data['requisicion_articulo_ids'][$i]) ? (int) $data['requisicion_articulo_ids'][$i] : null,
                'articulo_proveedor_id' => ! empty($data['articulo_proveedor_ids'][$i]) ? (int) $data['articulo_proveedor_ids'][$i] : null,
                'precio_origen_tipo' => ! empty($data['precio_origen_tipos'][$i]) ? (string) $data['precio_origen_tipos'][$i] : null,
                'precio_origen_ref_id' => isset($data['precio_origen_ref_ids'][$i]) && $data['precio_origen_ref_ids'][$i] !== '' && $data['precio_origen_ref_ids'][$i] !== null
                    ? (int) $data['precio_origen_ref_ids'][$i] : null,
                'precio_origen_etiqueta' => isset($data['precio_origen_etiquetas'][$i]) ? (string) $data['precio_origen_etiquetas'][$i] : null,
            ];

            if ($cargaAhorro) {
                $ahorroCols = $this->columnasAhorroLinea(
                    $data,
                    $i,
                    $precio,
                    (int) $payload['moneda_id'],
                    $cantidad,
                    $fechaAhorro,
                    $payload['requisicion_articulo_id'],
                    $this->esLineaNueva($idsEntrantes[$i] ?? null, $idsExistentesFlip),
                );
                if ($ahorroCols !== null) {
                    $payload = array_merge($payload, $ahorroCols);
                }
            }

            if ($mostrarPeso || array_key_exists('peso_unitarios', $data)) {
                $pesoUnit = $this->resolverPesoUnitarioLinea(
                    $data,
                    $i,
                    (int) $articulo_id,
                    $mostrarPeso,
                    $pesosArticuloCache,
                );
                $payload['peso_unitario'] = $pesoUnit;
                $payload['peso_total'] = ($pesoUnit !== null && $pesoUnit > 0 && $cantidad > 0)
                    ? round($pesoUnit * $cantidad, 6)
                    : null;
            }

            $idCandidato = $idsEntrantes[$i] ?? null;
            $idCandidato = ($idCandidato === null || $idCandidato === '') ? null : (int) $idCandidato;

            if ($idCandidato !== null && isset($idsExistentesFlip[$idCandidato])) {
                $aActualizar[$idCandidato] = $payload;
                $idsAConservar[] = $idCandidato;
                if ($entregas !== null) {
                    $entregasPorIndice['u:'.$idCandidato] = $entregas;
                }
            } else {
                $aInsertar[] = [
                    'payload' => $payload,
                    'entregas' => $entregas,
                ];
            }
        }

        $queryEliminar = $this->model->where('ordencompra_id', $ordencompra_id);
        if (! empty($idsAConservar)) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();

        foreach ($aActualizar as $id => $payload) {
            $registro = $this->model->where('id', $id)->where('ordencompra_id', $ordencompra_id)->first();
            if ($registro) {
                $registro->update($payload);
            }
            if (isset($entregasPorIndice['u:'.$id])) {
                $this->syncEntregasLinea((int) $id, $entregasPorIndice['u:'.$id]);
            }
        }

        foreach ($aInsertar as $item) {
            $registro = $this->model->create($item['payload']);
            if ($item['entregas'] !== null) {
                $this->syncEntregasLinea((int) $registro->id, $item['entregas']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{fecha: string, cantidad: float}>|null null = no sincronizar hijas
     */
    private function parseEntregasSemanal(array $data, int $i): ?array
    {
        if (! array_key_exists('entregas_semanal_json', $data)) {
            return null;
        }

        $raw = $data['entregas_semanal_json'][$i] ?? '[]';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fecha = trim((string) ($row['fecha'] ?? ''));
            $cant = (float) ($row['cantidad'] ?? 0);
            if ($fecha === '' || $cant <= 0) {
                continue;
            }
            $out[] = [
                'fecha' => $fecha,
                'cantidad' => $cant,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp($a['fecha'], $b['fecha']);
        });

        return $out;
    }

    /**
     * @param  list<array{fecha: string, cantidad: float}>  $entregas
     */
    private function syncEntregasLinea(int $ordencompraArticuloId, array $entregas): void
    {
        Ordencompra_Articulo_Entrega::query()
            ->where('ordencompra_articulo_id', $ordencompraArticuloId)
            ->delete();

        $orden = 1;
        foreach ($entregas as $ent) {
            Ordencompra_Articulo_Entrega::query()->create([
                'ordencompra_articulo_id' => $ordencompraArticuloId,
                'fecha' => $ent['fecha'],
                'cantidad' => $ent['cantidad'],
                'orden' => $orden++,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, float|null>  $cache
     */
    private function resolverPesoUnitarioLinea(
        array $data,
        int $i,
        int $articuloId,
        bool $mostrarPeso,
        array &$cache,
    ): ?float {
        if (array_key_exists('peso_unitarios', $data)) {
            $raw = $data['peso_unitarios'][$i] ?? null;
            if ($raw !== null && $raw !== '') {
                $peso = (float) $raw;

                return abs($peso) > 0.0000001 ? $peso : null;
            }
        }

        if (! $mostrarPeso || $articuloId <= 0) {
            return null;
        }

        if (! array_key_exists($articuloId, $cache)) {
            $cache[$articuloId] = Articulo::query()->whereKey($articuloId)->value('peso');
        }
        $pesoArt = $cache[$articuloId];
        if ($pesoArt === null || $pesoArt === '') {
            return null;
        }
        $peso = (float) $pesoArt;

        return abs($peso) > 0.0000001 ? $peso : null;
    }

    /**
     * @param  array<int|string, mixed>  $idsExistentesFlip
     */
    private function esLineaNueva(mixed $idEntrante, array $idsExistentesFlip): bool
    {
        $id = ($idEntrante === null || $idEntrante === '') ? null : (int) $idEntrante;

        return $id === null || ! isset($idsExistentesFlip[$id]);
    }

    /**
     * Columnas de ahorro de una línea de OC.
     *
     * - Si el formulario de OC mandó los campos de ahorro, se usan (y se recalcula con el precio,
     *   la moneda y la cantidad de la OC, y el TC de la fecha de la OC).
     * - Si no los mandó (wizard de OC desde requisición) o vinieron vacíos en una línea nueva
     *   ligada a una línea de requisición, se copian los datos de ahorro de esa línea.
     * - En cualquier otro caso (línea existente sin campos) no se toca el ahorro guardado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function columnasAhorroLinea(
        array $data,
        int $i,
        float $precio,
        int $monedaId,
        float $cantidad,
        string $fecha,
        mixed $requisicionArticuloId,
        bool $lineaNueva,
    ): ?array {
        $posteado = isset($data['preciooriginales']) && is_array($data['preciooriginales']);
        $entrada = null;

        if ($posteado) {
            $entrada = [
                'precio_anterior' => $data['preciooriginales'][$i] ?? null,
                'moneda_anterior_id' => $data['moneda_anterior_ids'][$i] ?? null,
                'origen_anterior' => $data['origen_anteriores'][$i] ?? null,
                'referencia_anterior' => $data['referencia_anteriores'][$i] ?? null,
                'tipoahorro_id' => $data['tipoahorro_ids'][$i] ?? null,
                'observacion' => $data['motivoahorros'][$i] ?? null,
            ];
            $sinDatos = (float) str_replace(',', '.', (string) ($entrada['precio_anterior'] ?? 0)) <= 0;
            if (! $sinDatos || ! $lineaNueva) {
                return AhorroLineaSupport::columnasParaGrabar($entrada, $precio, $monedaId, $cantidad, $fecha);
            }
        }

        $reqArtId = (int) $requisicionArticuloId;
        if ($reqArtId <= 0 || ! $lineaNueva) {
            return null;
        }

        $req = Requisicion_Articulo::query()->find($reqArtId);
        if ($req === null) {
            return null;
        }
        $precioAnterior = AhorroLineaSupport::precioAnteriorParaFormulario($req);
        if ($precioAnterior === '') {
            return null;
        }

        return AhorroLineaSupport::columnasParaGrabar([
            'precio_anterior' => $precioAnterior,
            // Sin moneda propia = la de la línea de requisición, que puede no ser la de la OC.
            'moneda_anterior_id' => $req->moneda_anterior_id ?: $req->moneda_id,
            'origen_anterior' => $req->origen_anterior,
            'referencia_anterior' => $req->referencia_anterior,
            'tipoahorro_id' => $req->tipoahorro_id,
            'observacion' => $req->motivoahorro,
        ], $precio, $monedaId, $cantidad, $fecha);
    }
}
