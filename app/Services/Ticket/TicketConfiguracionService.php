<?php

namespace App\Services\Ticket;

use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Ticket\Areadestino;
use App\Models\Ticket\Ticket_Configuracion_Areadestino;
use App\Models\Ticket\Ticket_Configuracion_Cc_Exclusion;
use App\Models\Ticket\Ticket_Configuracion_Centrocosto;
use App\Models\Ticket\Tecnico_Ticket;
use App\Support\Ticket\TicketModoOperacionSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketConfiguracionService
{
    /**
     * Grilla de centros de costo con flags de configuración de tickets.
     * Sin fila en config = comportamiento actual (flags en false).
     *
     * @return Collection<int, object{
     *     centrocosto_id: int,
     *     codigo: string|null,
     *     nombre: string,
     *     abreviatura: string|null,
     *     usuarios_activos: int,
     *     notificar_comentario_a_cc: bool
     * }>
     */
    public function filasCentrocosto(): Collection
    {
        $configs = Ticket_Configuracion_Centrocosto::query()
            ->get()
            ->keyBy('centrocosto_id');

        $conteos = Usuario::query()
            ->soloActivos()
            ->whereNotNull('centrocosto_id')
            ->selectRaw('centrocosto_id, COUNT(*) as total')
            ->groupBy('centrocosto_id')
            ->pluck('total', 'centrocosto_id');

        return Centrocosto::query()
            ->orderBy('nombre')
            ->get()
            ->map(function (Centrocosto $cc) use ($configs, $conteos) {
                /** @var Ticket_Configuracion_Centrocosto|null $cfg */
                $cfg = $configs->get($cc->id);

                return (object) [
                    'centrocosto_id' => (int) $cc->id,
                    'codigo' => $cc->codigo,
                    'nombre' => $cc->nombre,
                    'abreviatura' => $cc->abreviatura,
                    'usuarios_activos' => (int) ($conteos[$cc->id] ?? 0),
                    'notificar_comentario_a_cc' => (bool) ($cfg->notificar_comentario_a_cc ?? false),
                ];
            });
    }

    /**
     * Persiste flags por centro de costo.
     * Solo guarda filas con algún flag activo; si todo queda en false elimina la fila
     * para que “sin configuración” = comportamiento actual.
     *
     * @param  array<int|string, mixed>  $flagsPorCentrocosto  [centrocosto_id => bool|1|0|'1'|'0']
     */
    public function guardarNotificarComentarioACc(array $flagsPorCentrocosto): void
    {
        $centrocostoIds = Centrocosto::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $flagsNormalizados = [];

        foreach ($centrocostoIds as $ccId) {
            $flagsNormalizados[$ccId] = filter_var(
                $flagsPorCentrocosto[$ccId] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        }

        DB::transaction(function () use ($flagsNormalizados) {
            foreach ($flagsNormalizados as $ccId => $activo) {
                if ($activo) {
                    Ticket_Configuracion_Centrocosto::query()->updateOrCreate(
                        ['centrocosto_id' => $ccId],
                        ['notificar_comentario_a_cc' => true]
                    );
                    continue;
                }

                Ticket_Configuracion_Centrocosto::query()
                    ->where('centrocosto_id', $ccId)
                    ->delete();
            }
        });
    }

    public function debeNotificarComentarioACc(?int $centrocostoId): bool
    {
        if (! $centrocostoId) {
            return false;
        }

        return Ticket_Configuracion_Centrocosto::query()
            ->where('centrocosto_id', $centrocostoId)
            ->where('notificar_comentario_a_cc', true)
            ->exists();
    }

    /**
     * Emails CC: mismo centro de costo + misma empresa de origen del ticket.
     * Si no hay empresa_id en el ticket, usa las empresas del creador (fallback legacy).
     *
     * @return list<string>
     */
    public function emailsCcComentarioAdministracion(Usuario $creador, Usuario $autor, ?int $empresaIdOrigen = null): array
    {
        $centrocostoId = (int) ($creador->centrocosto_id ?? 0);
        if (! $this->debeNotificarComentarioACc($centrocostoId)) {
            return [];
        }

        $empresaIdsOrigen = [];
        if ($empresaIdOrigen && $empresaIdOrigen > 0) {
            $empresaIdsOrigen = [$empresaIdOrigen];
        } else {
            $empresaIdsOrigen = $this->empresaIdsOrigenTicket($creador);
        }

        if ($empresaIdsOrigen === []) {
            return [];
        }

        $excluirIds = self::unirIdsExclusionCc(
            [(int) $creador->id, (int) $autor->id],
            $this->idsExcluidosCcComentario()
        );

        $query = Usuario::query()
            ->soloActivos()
            ->where('centrocosto_id', $centrocostoId)
            ->whereNotIn('id', $excluirIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($q) use ($empresaIdsOrigen) {
                $q->whereHas('usuario_empresas', function ($e) use ($empresaIdsOrigen) {
                    $e->whereIn('empresa.id', $empresaIdsOrigen);
                })->orWhereDoesntHave('usuario_empresas');
            })
            ->orderBy('nombre');

        $emails = $query
            ->pluck('email')
            ->map(static fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $max = max(1, (int) config('ticket.notificacion_cc_max_destinatarios', 100));
        if (count($emails) > $max) {
            \Illuminate\Support\Facades\Log::warning('Ticket comentario: CC truncado por tope de destinatarios', [
                'centrocosto_id' => $centrocostoId,
                'empresa_ids' => $empresaIdsOrigen,
                'total' => count($emails),
                'max' => $max,
            ]);

            return array_values(array_slice($emails, 0, $max));
        }

        return $emails;
    }

    /**
     * @param  list<int|string>  ...$grupos
     * @return list<int>
     */
    public static function unirIdsExclusionCc(array ...$grupos): array
    {
        $ids = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<int>
     */
    public function idsExcluidosCcComentario(): array
    {
        return Ticket_Configuracion_Cc_Exclusion::query()
            ->pluck('usuario_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, object{
     *     usuario_id: int,
     *     usuario: string,
     *     nombre: string,
     *     email: string|null,
     *     centrocosto: string|null
     * }>
     */
    public function filasExclusionCc(): Collection
    {
        return Ticket_Configuracion_Cc_Exclusion::query()
            ->with(['usuario.centrocostos'])
            ->get()
            ->map(function (Ticket_Configuracion_Cc_Exclusion $fila) {
                $usuario = $fila->usuario;
                if (! $usuario) {
                    return null;
                }

                return (object) [
                    'usuario_id' => (int) $fila->usuario_id,
                    'usuario' => (string) ($usuario->usuario ?? ''),
                    'nombre' => (string) ($usuario->nombre ?? ''),
                    'email' => $usuario->email ?? null,
                    'centrocosto' => $usuario->centrocostos->nombre ?? null,
                ];
            })
            ->filter()
            ->sortBy(fn ($fila) => mb_strtolower($fila->nombre.' '.$fila->usuario))
            ->values();
    }

    /**
     * @return Collection<int, Usuario>
     */
    public function usuariosCandidatosExclusionCc(): Collection
    {
        $excluidos = $this->idsExcluidosCcComentario();

        return Usuario::query()
            ->soloActivos()
            ->with('centrocostos')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($excluidos !== [], fn ($q) => $q->whereNotIn('id', $excluidos))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @param  array<int|string, mixed>  $usuarioIds
     */
    public function guardarExclusionCc(array $usuarioIds): void
    {
        $ids = self::unirIdsExclusionCc($usuarioIds);
        if ($ids !== []) {
            $ids = Usuario::query()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        DB::transaction(function () use ($ids) {
            Ticket_Configuracion_Cc_Exclusion::query()->delete();
            foreach ($ids as $usuarioId) {
                Ticket_Configuracion_Cc_Exclusion::query()->create([
                    'usuario_id' => $usuarioId,
                ]);
            }
        });
    }

    /**
     * Empresas del usuario que originó el ticket (tabla usuario_empresa).
     *
     * @return list<int>
     */
    private function empresaIdsOrigenTicket(Usuario $creador): array
    {
        if (! $creador->relationLoaded('usuario_empresas')) {
            $creador->load('usuario_empresas:id');
        }

        return $creador->usuario_empresas
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Grilla de áreas destino con modo de operación.
     * Sin fila = dispatch (comportamiento actual de Sistemas).
     *
     * @return Collection<int, object{
     *     areadestino_id: int,
     *     nombre: string,
     *     modo_operacion: string,
     *     tecnicos: int
     * }>
     */
    public function filasAreadestino(): Collection
    {
        $configs = Ticket_Configuracion_Areadestino::query()
            ->get()
            ->keyBy('areadestino_id');

        $conteos = Tecnico_Ticket::query()
            ->selectRaw('areadestino_id, COUNT(*) as total')
            ->groupBy('areadestino_id')
            ->pluck('total', 'areadestino_id');

        return Areadestino::query()
            ->orderBy('nombre')
            ->get()
            ->map(function (Areadestino $area) use ($configs, $conteos) {
                /** @var Ticket_Configuracion_Areadestino|null $cfg */
                $cfg = $configs->get($area->id);
                $modo = TicketModoOperacionSupport::normalizarModo(
                    $cfg->modo_operacion ?? TicketModoOperacionSupport::MODO_DISPATCH
                );

                return (object) [
                    'areadestino_id' => (int) $area->id,
                    'nombre' => $area->nombre,
                    'modo_operacion' => $modo,
                    'tecnicos' => (int) ($conteos[$area->id] ?? 0),
                ];
            });
    }

    /**
     * @param  array<int|string, mixed>  $modosPorArea  [areadestino_id => dispatch|claim]
     */
    public function guardarModosAreadestino(array $modosPorArea): void
    {
        $areaIds = Areadestino::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($areaIds, $modosPorArea) {
            foreach ($areaIds as $areaId) {
                $modo = TicketModoOperacionSupport::normalizarModo(
                    isset($modosPorArea[$areaId]) ? (string) $modosPorArea[$areaId] : null
                );

                if ($modo === TicketModoOperacionSupport::MODO_DISPATCH) {
                    Ticket_Configuracion_Areadestino::query()
                        ->where('areadestino_id', $areaId)
                        ->delete();
                    continue;
                }

                Ticket_Configuracion_Areadestino::query()->updateOrCreate(
                    ['areadestino_id' => $areaId],
                    ['modo_operacion' => TicketModoOperacionSupport::MODO_CLAIM]
                );
            }
        });

        TicketModoOperacionSupport::forgetCache();
    }
}
