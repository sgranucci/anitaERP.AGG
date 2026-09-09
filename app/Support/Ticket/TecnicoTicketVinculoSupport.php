<?php

namespace App\Support\Ticket;

use App\Models\Ticket\Tecnico_Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico de vínculos técnico ↔ usuario (necesarios para «Tomar» en bandeja claim).
 */
class TecnicoTicketVinculoSupport
{
    /**
     * @param  Collection<int, Tecnico_Ticket>  $tecnicos
     * @return array{
     *     total: int,
     *     compartidos: int,
     *     suspendidos: int,
     *     sin_usuario: int,
     *     ok: int,
     *     conteo_por_usuario: array<int, int>
     * }
     */
    public static function resumen(Collection $tecnicos): array
    {
        $conteo = [];
        foreach ($tecnicos as $t) {
            $uid = (int) ($t->usuario_id ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $conteo[$uid] = ($conteo[$uid] ?? 0) + 1;
        }

        $compartidos = 0;
        $suspendidos = 0;
        $sinUsuario = 0;
        $ok = 0;

        foreach ($tecnicos as $t) {
            $uid = (int) ($t->usuario_id ?? 0);
            if ($uid <= 0) {
                $sinUsuario++;
                continue;
            }

            $usuario = $t->usuarios;
            $esSuspendido = $usuario && (int) ($usuario->suspendido ?? 0) === 1;
            $esCompartido = ($conteo[$uid] ?? 0) > 1;

            if ($esSuspendido) {
                $suspendidos++;
            }
            if ($esCompartido) {
                $compartidos++;
            }
            if (! $esSuspendido && ! $esCompartido) {
                $ok++;
            }
        }

        return [
            'total' => $tecnicos->count(),
            'compartidos' => $compartidos,
            'suspendidos' => $suspendidos,
            'sin_usuario' => $sinUsuario,
            'ok' => $ok,
            'conteo_por_usuario' => $conteo,
        ];
    }

    /**
     * Etiqueta de estado del vínculo para una ficha.
     *
     * @param  array<int, int>  $conteoPorUsuario
     * @return array{clase: string, texto: string, titulo: string}
     */
    public static function estadoFila(Tecnico_Ticket $tecnico, array $conteoPorUsuario): array
    {
        $uid = (int) ($tecnico->usuario_id ?? 0);
        if ($uid <= 0 || ! $tecnico->usuarios) {
            return [
                'clase' => 'danger',
                'texto' => 'Sin usuario',
                'titulo' => 'Sin usuario ERP vinculado: no podrá usar Tomar en la bandeja.',
            ];
        }

        $suspendido = (int) ($tecnico->usuarios->suspendido ?? 0) === 1;
        $compartido = ($conteoPorUsuario[$uid] ?? 0) > 1;

        if ($suspendido && $compartido) {
            return [
                'clase' => 'danger',
                'texto' => 'Compartido + suspendido',
                'titulo' => 'Varias fichas apuntan al mismo usuario y ese usuario está suspendido.',
            ];
        }
        if ($suspendido) {
            return [
                'clase' => 'warning',
                'texto' => 'Usuario suspendido',
                'titulo' => 'El usuario vinculado está suspendido.',
            ];
        }
        if ($compartido) {
            return [
                'clase' => 'warning',
                'texto' => 'Usuario compartido',
                'titulo' => 'Más de una ficha de técnico usa este mismo usuario en el listado.',
            ];
        }

        return [
            'clase' => 'success',
            'texto' => 'OK',
            'titulo' => 'Vínculo único con usuario operativo.',
        ];
    }

    /**
     * Usuarios activos con roles de mantenimiento/obras sin ficha propia en el área.
     *
     * @return Collection<int, object{id: int, nombre: string, usuario: string}>
     */
    public static function usuariosRolSinFichaUnica(int $areadestinoId = 2): Collection
    {
        $rolIds = DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Obras%')
                    ->orWhere('nombre', 'like', '%mant%')
                    ->orWhere('nombre', 'like', '%Mant%');
            })
            ->pluck('id');

        if ($rolIds->isEmpty()) {
            return collect();
        }

        $usuarioIds = DB::table('usuario_rol')
            ->whereIn('rol_id', $rolIds)
            ->pluck('usuario_id')
            ->unique()
            ->values();

        if ($usuarioIds->isEmpty()) {
            return collect();
        }

        $yaUnicos = DB::table('tecnico_ticket')
            ->select('usuario_id')
            ->where('areadestino_id', $areadestinoId)
            ->whereNotNull('usuario_id')
            ->groupBy('usuario_id')
            ->havingRaw('COUNT(*) = 1')
            ->pluck('usuario_id')
            ->all();

        return DB::table('usuario')
            ->whereIn('id', $usuarioIds)
            ->where('suspendido', 0)
            ->whereNotIn('id', $yaUnicos)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'usuario']);
    }
}
