<?php

namespace App\Services\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Models\Sala\CumplimientoRequisicionSalaArticulo;
use App\Models\Sala\CumplimientoRequisicionSalaTransferencia;
use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaArticulo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CumplimientoRequisicionSalaBackfillService
{
    /**
     * @return array{creados: int, omitidos: int, detalle: list<string>}
     */
    public function ejecutar(): array
    {
        $creados = 0;
        $omitidos = 0;
        $detalle = [];

        $transferencias = $this->transferenciasCumpleSinVincular();
        $grupos = $this->agruparPorEvento($transferencias);

        foreach ($grupos as $grupo) {
            /** @var Collection<int, Transferencia_Mercaderia> $grupo */
            $primera = $grupo->first();
            if (! $primera) {
                continue;
            }

            $numerorequisicion = $this->parsearNumeroRequisicion((string) ($primera->observacion ?? ''));
            if ($numerorequisicion <= 0) {
                $omitidos++;
                $detalle[] = 'Omitido TM #'.$primera->id.': no se pudo leer número de requisición.';

                continue;
            }

            $req = RequisicionSala::query()->where('numerorequisicion', $numerorequisicion)->first();
            if (! $req) {
                $omitidos++;
                $detalle[] = 'Omitido TM #'.$primera->id.': requisición #'.$numerorequisicion.' no encontrada.';

                continue;
            }

            $lineasCumple = [];
            foreach ($grupo as $tm) {
                $tm->loadMissing('articulos');
                foreach ($tm->articulos as $tmLinea) {
                    $match = $this->resolverLineaRequisicion($req, $tm, $tmLinea, $grupo);
                    if ($match === null) {
                        continue;
                    }
                    $lineasCumple[$match['requisicion_sala_articulo_id']] = $match;
                }
            }

            if ($lineasCumple === []) {
                $omitidos++;
                $detalle[] = 'Omitido evento TM #'.$primera->id.': sin líneas de requisición coincidentes.';

                continue;
            }

            $revertida = $grupo->contains(static fn (Transferencia_Mercaderia $t): bool => $t->estado === TransferenciaMercaderiaEstados::REVERTIDA
                || (int) ($t->transferencia_revertido_por_id ?? 0) > 0);

            $numero = (int) (CumplimientoRequisicionSala::query()->max('numero') ?? 0) + 1;
            $fecha = Carbon::parse($primera->created_at ?? $primera->fecha ?? now());

            $cabecera = CumplimientoRequisicionSala::query()->create([
                'numero' => $numero,
                'fecha' => $fecha,
                'usuario_id' => (int) ($primera->usuario_origen_id ?? 1),
                'empresa_id' => (int) ($primera->empresa_id ?? $req->empresa_id) ?: null,
                'leyenda' => 'Reconstruido desde transferencia(s) histórica(s)',
                'estado' => $revertida ? CumplimientoRequisicionSala::ESTADO_REVERTIDO : CumplimientoRequisicionSala::ESTADO_ACTIVO,
                'revertido_por_id' => $revertida ? (int) ($primera->usuario_origen_id ?? 1) : null,
                'revertido_en' => $revertida ? Carbon::parse($grupo->max('updated_at') ?? $fecha) : null,
                'observacion_reversion' => $revertida ? 'Backfill: transferencia revertida en stock' : null,
            ]);

            foreach ($lineasCumple as $snap) {
                CumplimientoRequisicionSalaArticulo::query()->create(array_merge($snap, [
                    'cumplimiento_requisicion_sala_id' => $cabecera->id,
                ]));
            }

            foreach ($grupo as $tm) {
                CumplimientoRequisicionSalaTransferencia::query()->create([
                    'cumplimiento_requisicion_sala_id' => $cabecera->id,
                    'transferencia_mercaderia_id' => (int) $tm->id,
                ]);
            }

            $creados++;
            $tmIds = $grupo->pluck('id')->implode(', ');
            $detalle[] = 'Cumplimiento #'.$numero.' (id '.$cabecera->id.') — req #'.$numerorequisicion.' — TM: '.$tmIds
                .($revertida ? ' [REVERTIDO]' : '');
        }

        return compact('creados', 'omitidos', 'detalle');
    }

    /** @return Collection<int, Transferencia_Mercaderia> */
    private function transferenciasCumpleSinVincular(): Collection
    {
        $vinculados = DB::table('cumplimiento_requisicion_sala_transferencia')->pluck('transferencia_mercaderia_id')->all();

        return Transferencia_Mercaderia::query()
            ->with('articulos')
            ->where(function ($q) {
                $q->where('observacion', 'like', '%Cumple requisici%n sala%')
                    ->orWhere('observacion', 'like', '%Cumple requisici&oacute;n sala%');
            })
            ->when($vinculados !== [], fn ($q) => $q->whereNotIn('id', $vinculados))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Transferencia_Mercaderia>  $todas
     * @return list<Collection<int, Transferencia_Mercaderia>>
     */
    private function agruparPorEvento(Collection $todas): array
    {
        $grupos = [];
        foreach ($todas as $tm) {
            $nro = $this->parsearNumeroRequisicion((string) ($tm->observacion ?? ''));
            $clave = $nro.'|'.Carbon::parse($tm->created_at)->format('Y-m-d H:i:s');
            $grupos[$clave][] = $tm;
        }

        return array_map(fn (array $items) => collect($items)->values(), array_values($grupos));
    }

    private function parsearNumeroRequisicion(string $observacion): int
    {
        $texto = html_entity_decode(strip_tags($observacion), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/Cumple requisici[oó]n sala #(\d+)/iu', $texto, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param  Collection<int, Transferencia_Mercaderia>  $grupo
     * @return array<string, mixed>|null
     */
    private function resolverLineaRequisicion(
        RequisicionSala $req,
        Transferencia_Mercaderia $tm,
        $tmLinea,
        Collection $grupo
    ): ?array {
        $articuloId = (int) ($tmLinea->articulo_origen_id ?? 0);
        $npuTm = trim((string) ($tmLinea->numeroparte ?? ''));
        $cantidad = abs((float) ($tmLinea->cantidad_origen ?? 0));
        if ($articuloId <= 0 || $cantidad <= 0) {
            return null;
        }

        $candidatos = RequisicionSalaArticulo::query()
            ->where('requisicion_sala_id', $req->id)
            ->where('articulo_id', $articuloId)
            ->get();

        $linea = null;
        if ($npuTm !== '') {
            $linea = $candidatos->first(fn (RequisicionSalaArticulo $r) => trim((string) ($r->numeroparte ?? '')) === $npuTm
                || trim((string) ($r->numeroparte ?? '')) === ltrim($npuTm, '0')
                || (ctype_digit($npuTm) && (int) $r->numeroparte === (int) $npuTm));
        }

        if ($linea === null && $candidatos->count() === 1) {
            $linea = $candidatos->first();
        }

        if ($linea === null) {
            foreach ($candidatos as $candidato) {
                if ($this->auditCumpleLinea((int) $candidato->id, (string) $tm->created_at) !== null) {
                    $linea = $candidato;
                    break;
                }
            }
        }

        if ($linea === null) {
            $revertida = $tm->estado === TransferenciaMercaderiaEstados::REVERTIDA
                || (int) ($tm->transferencia_revertido_por_id ?? 0) > 0;
            if ($revertida) {
                $linea = $candidatos->first(fn (RequisicionSalaArticulo $r) => (float) ($r->cantidadentregada ?? 0) < (float) $r->cantidad);
            } else {
                $linea = $candidatos->first(fn (RequisicionSalaArticulo $r) => (float) ($r->cantidadentregada ?? 0) > 0)
                    ?? $candidatos->first(fn (RequisicionSalaArticulo $r) => (float) ($r->cantidadentregada ?? 0) < (float) $r->cantidad);
            }
        }

        if ($linea === null) {
            $linea = $candidatos->sortBy('id')->first();
        }

        if (! $linea) {
            return null;
        }

        return $this->armarSnapshotLinea($linea, $tm, $cantidad, $npuTm);
    }

    /** @return array<string, mixed> */
    private function armarSnapshotLinea(
        RequisicionSalaArticulo $linea,
        Transferencia_Mercaderia $tm,
        float $cantidad,
        string $npuTm
    ): array {
        $audit = $this->auditCumpleLinea((int) $linea->id, (string) $tm->created_at);
        $old = $audit ? json_decode((string) $audit->old_values, true) : [];
        $new = $audit ? json_decode((string) $audit->new_values, true) : [];

        if (! is_array($old)) {
            $old = [];
        }
        if (! is_array($new)) {
            $new = [];
        }

        $antesEntregada = (float) ($old['cantidadentregada'] ?? 0);
        $despuesEntregada = isset($new['cantidadentregada']) ? (float) $new['cantidadentregada'] : $antesEntregada + $cantidad;
        $pendienteAntes = max(0, (float) $linea->cantidad - $antesEntregada);

        $estadoLinea = (string) ($new['estado'] ?? ($despuesEntregada >= (float) $linea->cantidad ? 'E' : 'A'));
        $fechaEntrega = $new['fecha_entrega'] ?? Carbon::parse($tm->created_at)->format('Y-m-d');

        return [
            'requisicion_sala_id' => (int) $linea->requisicion_sala_id,
            'requisicion_sala_articulo_id' => (int) $linea->id,
            'articulo_id' => (int) $linea->articulo_id,
            'cantidad_entrega' => $cantidad,
            'cantidad_pendiente_antes' => $pendienteAntes,
            'cantidadentregada_antes' => $antesEntregada,
            'deposito_origen_id' => (int) ($new['deposito_origen_id'] ?? $tm->deposito_origen_id ?? 0) ?: null,
            'tecnico_laboratorio_id' => $new['tecnico_laboratorio_id'] ?? $linea->tecnico_laboratorio_id,
            'numeroparte' => $npuTm !== '' ? $npuTm : ($new['numeroparte'] ?? $linea->numeroparte),
            'uid' => $linea->uid,
            'destino' => (string) ($linea->destino ?? ''),
            'estado_linea' => $estadoLinea !== '' ? $estadoLinea : 'E',
            'estadoparcial' => $new['estadoparcial'] ?? null,
            'fecha_entrega' => $fechaEntrega,
            'numeroremito' => $new['numeroremito'] ?? $linea->numeroremito,
            'nombreresponsable' => $new['nombreresponsable'] ?? $linea->nombreresponsable,
            'estado_linea_antes' => (string) ($old['estado'] ?? $linea->estado ?? ' '),
            'estadoparcial_antes' => $old['estadoparcial'] ?? $linea->estadoparcial,
            'fecha_entrega_antes' => $old['fecha_entrega'] ?? $linea->fecha_entrega,
            'numeroremito_antes' => $old['numeroremito'] ?? $linea->numeroremito,
            'nombreresponsable_antes' => $old['nombreresponsable'] ?? $linea->nombreresponsable,
            'tecnico_laboratorio_id_antes' => $old['tecnico_laboratorio_id'] ?? $linea->tecnico_laboratorio_id,
            'deposito_origen_id_antes' => $old['deposito_origen_id'] ?? $linea->deposito_origen_id,
            'numeroparte_antes' => $old['numeroparte'] ?? $linea->numeroparte,
        ];
    }

    private function auditCumpleLinea(int $lineaId, string $tmCreatedAt): ?object
    {
        $ts = Carbon::parse($tmCreatedAt)->format('Y-m-d H:i:s');

        return DB::table('audits')
            ->where('auditable_type', 'like', '%RequisicionSalaArticulo%')
            ->where('auditable_id', $lineaId)
            ->where('event', 'updated')
            ->where('created_at', $ts)
            ->first();
    }
}
