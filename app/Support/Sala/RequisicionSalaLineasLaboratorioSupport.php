<?php

namespace App\Support\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaArticulo;
use App\Models\Sala\RequisicionSalaEstado;
use App\Models\Stock\Depmae;
use Illuminate\Support\Collection;

final class RequisicionSalaLineasLaboratorioSupport
{
    /** @var list<string> */
    public const DESTINOS_TRANSFERIBLES = ['R', 'D'];

    public static function tieneLineasReparacionDevolucion(RequisicionSala $req): bool
    {
        return self::lineasTransferibles($req)->isNotEmpty();
    }

    /**
     * @return Collection<int, RequisicionSalaArticulo>
     */
    public static function lineasTransferibles(RequisicionSala $req): Collection
    {
        $req->loadMissing(['requisicion_sala_articulos.articulos']);

        return $req->requisicion_sala_articulos->filter(function (RequisicionSalaArticulo $articulo) {
            $destino = strtoupper((string) ($articulo->destino ?? ''));
            if (! in_array($destino, self::DESTINOS_TRANSFERIBLES, true)) {
                return false;
            }
            $cantidad = (float) ($articulo->cantidad ?? 0);
            $articuloId = (int) ($articulo->articulo_id ?? 0);

            return $articuloId > 0 && $cantidad > 0;
        })->values();
    }

    /**
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    public static function payloadLineasTransferencia(RequisicionSala $req): array
    {
        $lineas = [];
        foreach (self::lineasTransferibles($req) as $articulo) {
            $lineas[] = [
                'articulo_id' => (int) $articulo->articulo_id,
                'cantidad' => (float) $articulo->cantidad,
            ];
        }

        return $lineas;
    }

    public static function detalleLineasTexto(RequisicionSala $req): string
    {
        $partes = [];
        foreach (self::lineasTransferibles($req) as $linea) {
            $sku = (string) (optional($linea->articulos)->sku ?? $linea->articulo_id);
            $desc = (string) (optional($linea->articulos)->descripcion ?? '');
            $destino = strtoupper((string) ($linea->destino ?? ''));
            $etiquetaDestino = $destino === 'R' ? 'REPARACIÓN' : ($destino === 'D' ? 'DEVOLUCIÓN' : $destino);
            $partes[] = sprintf(
                '- %s %s — cant. %.4f (%s)',
                $sku,
                $desc,
                (float) ($linea->cantidad ?? 0),
                $etiquetaDestino
            );
        }

        return $partes !== [] ? implode("\n", $partes) : '—';
    }

    public static function generaraTransferenciaLaboratorioAlAprobar(RequisicionSala $req, ?string $estadoTrasAprobar): bool
    {
        if ($estadoTrasAprobar === null || $estadoTrasAprobar === '') {
            return false;
        }
        $nombreAprobada = RequisicionSalaEstado::$enumEstado[array_search('A', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        if ($estadoTrasAprobar !== $nombreAprobada) {
            return false;
        }

        return self::tieneLineasReparacionDevolucion($req);
    }

    public static function etiquetaDepositoLaboratorio(): string
    {
        $codigo = trim((string) config('sala.requisicion_deposito_laboratorio_codigo', '406'));
        if ($codigo === '') {
            return '—';
        }
        $dep = Depmae::query()->where('codigo', $codigo)->first();
        if (! $dep) {
            return 'Depósito '.$codigo;
        }

        return Depmae::etiquetaDesdePartes($dep->codigo, $dep->nombre, $dep->id);
    }
}
