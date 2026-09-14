<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\CotGuia;
use App\Models\Ventas\CotGuiaLinea;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CotGuiaRepository
{
    public function siguienteNumero(): int
    {
        $max = (int) CotGuia::query()->max('numero');

        return $max + 1;
    }

    public function find(int $id): ?CotGuia
    {
        return CotGuia::query()
            ->with(['lineas', 'transportes', 'cotSesionEnvio'])
            ->find($id);
    }

    public function findPorNumero(int $numero): ?CotGuia
    {
        if ($numero < 1) {
            return null;
        }

        return CotGuia::query()
            ->with(['lineas', 'transportes', 'cotSesionEnvio'])
            ->where('numero', $numero)
            ->first();
    }

    /**
     * @param  array{fecha?:string,texto?:string}  $filtros
     */
    public function consultar(array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = CotGuia::query()
            ->with(['transportes'])
            ->withCount('lineas')
            ->orderByDesc('numero');

        if (! empty($filtros['fecha'])) {
            $q->whereDate('fecha', $filtros['fecha']);
        }

        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            if (ctype_digit($texto)) {
                $q->where('numero', (int) $texto);
            } else {
                $q->where(function ($w) use ($texto) {
                    $w->where('dominio', 'like', '%'.$texto.'%')
                        ->orWhere('cuit_chofer', 'like', '%'.$texto.'%')
                        ->orWhere('estado', 'like', '%'.$texto.'%');
                });
            }
        }

        return $paginar ? $q->paginate(15) : $q->limit(100)->get();
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $lineas
     */
    public function guardar(array $cabecera, array $lineas, ?int $guiaId = null): CotGuia
    {
        return DB::transaction(function () use ($cabecera, $lineas, $guiaId) {
            if ($guiaId !== null && $guiaId > 0) {
                $guia = CotGuia::query()->lockForUpdate()->findOrFail($guiaId);
                if (! $guia->esBorrador()) {
                    throw new \InvalidArgumentException('Solo se pueden editar guías en borrador.');
                }
            } else {
                $numero = (int) ($cabecera['numero'] ?? 0);
                if ($numero < 1) {
                    $numero = $this->siguienteNumero();
                }
                $guia = CotGuia::query()->create([
                    'numero' => $numero,
                    'fecha' => $cabecera['fecha'],
                    'transporte_id' => $cabecera['transporte_id'] ?? null,
                    'cuit_chofer' => $cabecera['cuit_chofer'] ?? null,
                    'dominio' => $cabecera['dominio'] ?? null,
                    'estado' => CotGuia::ESTADO_BORRADOR,
                    'usuario_id' => Auth::id(),
                ]);
            }

            $guia->update([
                'fecha' => $cabecera['fecha'],
                'transporte_id' => $cabecera['transporte_id'] ?? null,
                'cuit_chofer' => $cabecera['cuit_chofer'] ?? null,
                'dominio' => $cabecera['dominio'] ?? null,
                'usuario_id' => Auth::id(),
            ]);

            $this->sincronizarLineas($guia, $lineas);

            return $guia->fresh(['lineas', 'transportes', 'cotSesionEnvio']) ?? $guia;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineas(CotGuia $guia, array $lineas): void
    {
        $idsConservar = [];
        $orden = 1;

        foreach ($lineas as $fila) {
            $tipo = strtoupper(trim((string) ($fila['tipo'] ?? '')));
            $letra = strtoupper(trim((string) ($fila['letra'] ?? '')));
            $sucursal = (int) ($fila['sucursal'] ?? 0);
            $numero = (int) ($fila['numero'] ?? 0);
            if ($tipo === '' || $letra === '' || $numero < 1) {
                continue;
            }

            $payload = [
                'cot_guia_id' => $guia->id,
                'orden' => $orden++,
                'tipo' => substr($tipo, 0, 3),
                'letra' => substr($letra, 0, 1),
                'sucursal' => $sucursal,
                'numero' => $numero,
                'cliente_codigo' => $this->nullableStr($fila['cliente_codigo'] ?? null, 20),
                'cliente_nombre' => $this->nullableStr($fila['cliente_nombre'] ?? null, 80),
                'bultos' => (float) ($fila['bultos'] ?? 0),
                'cantidad' => (float) ($fila['cantidad'] ?? 0),
                'valor_declarado' => (float) ($fila['valor_declarado'] ?? 0),
                'transporte_id' => ((int) ($fila['transporte_id'] ?? 0)) ?: null,
                'transporte_codigo' => $this->nullableStr($fila['transporte_codigo'] ?? null, 20),
                'entrega' => $this->nullableStr($fila['entrega'] ?? null, 120),
                'venta_id' => ((int) ($fila['venta_id'] ?? 0)) ?: null,
            ];

            $lineaId = (int) ($fila['id'] ?? 0);
            if ($lineaId > 0) {
                $linea = CotGuiaLinea::query()
                    ->where('cot_guia_id', $guia->id)
                    ->whereKey($lineaId)
                    ->first();
                if ($linea !== null) {
                    $linea->update($payload);
                    $idsConservar[] = $linea->id;

                    continue;
                }
            }

            $nueva = CotGuiaLinea::query()->create($payload);
            $idsConservar[] = $nueva->id;
        }

        $borrar = CotGuiaLinea::query()->where('cot_guia_id', $guia->id);
        if ($idsConservar !== []) {
            $borrar->whereNotIn('id', $idsConservar);
        }
        EloquentAuditDeleteSupport::each($borrar);
    }

    public function marcarEnviada(CotGuia $guia, int $sesionId): void
    {
        $guia->update([
            'estado' => CotGuia::ESTADO_ENVIADA,
            'cot_sesion_envio_id' => $sesionId,
        ]);
    }

    private function nullableStr(mixed $valor, int $max): ?string
    {
        $txt = trim((string) ($valor ?? ''));
        if ($txt === '') {
            return null;
        }

        return mb_substr($txt, 0, $max);
    }
}
