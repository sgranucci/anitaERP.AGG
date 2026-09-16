<?php

use App\Models\Admin\Rol;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna depósitos técnicos 403/404/405/406 a usuarios con rol Tecnico-sala,
 * acotados a las empresas del usuario, para restringir requisición de sala.
 */
return new class extends Migration
{
    public function up(): void
    {
        $codigos = config('sala.tecnico_sala_depositos_codigos', ['403', '404', '405', '406']);
        $codigos = array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            is_array($codigos) ? $codigos : []
        )));

        if ($codigos === []) {
            return;
        }

        $rolId = Rol::query()->where('nombre', 'Tecnico-sala')->value('id');
        if (! $rolId) {
            return;
        }

        $depositos = Depmae::query()
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'empresa_id']);

        if ($depositos->isEmpty()) {
            return;
        }

        $usuarios = Usuario::query()
            ->whereHas('roles', static fn ($q) => $q->where('rol.id', $rolId))
            ->with(['usuario_empresas:id', 'depositosAutorizados:id'])
            ->get(['id']);

        foreach ($usuarios as $usuario) {
            $empresaIds = $usuario->usuario_empresas
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($empresaIds === []) {
                continue;
            }

            $nuevosIds = $depositos
                ->filter(static fn ($dep) => in_array((int) $dep->empresa_id, $empresaIds, true))
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($nuevosIds === []) {
                continue;
            }

            $actuales = $usuario->depositosAutorizados
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $merged = array_values(array_unique(array_merge($actuales, $nuevosIds)));
            sort($merged);

            $actualesSorted = $actuales;
            sort($actualesSorted);

            if ($merged === $actualesSorted) {
                continue;
            }

            $usuario->depositosAutorizados()->sync($merged);
        }
    }

    public function down(): void
    {
        $codigos = config('sala.tecnico_sala_depositos_codigos', ['403', '404', '405', '406']);
        $codigos = array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            is_array($codigos) ? $codigos : []
        )));

        if ($codigos === []) {
            return;
        }

        $rolId = Rol::query()->where('nombre', 'Tecnico-sala')->value('id');
        if (! $rolId) {
            return;
        }

        $depositoIds = Depmae::query()
            ->whereIn('codigo', $codigos)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($depositoIds === []) {
            return;
        }

        $usuarioIds = DB::table('usuario_rol')
            ->where('rol_id', $rolId)
            ->pluck('usuario_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($usuarioIds === []) {
            return;
        }

        DB::table('usuario_deposito')
            ->whereIn('usuario_id', $usuarioIds)
            ->whereIn('deposito_id', $depositoIds)
            ->delete();
    }
};
