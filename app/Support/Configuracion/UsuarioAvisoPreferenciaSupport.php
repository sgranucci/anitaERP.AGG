<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\UsuarioAvisoPreferencia;
use App\Models\Configuracion\UsuarioAvisoPreferenciaTipo;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out por canal: sin fila = habilitado.
 * Prioridad: preferencia por tipo de aviso > preferencia global de canal.
 */
final class UsuarioAvisoPreferenciaSupport
{
    public const CANAL_MAIL = 'mail';

    public const CANAL_IN_APP = 'in_app';

    public const CANAL_WHATSAPP = 'whatsapp';

    /** Digest matutino de Mis aprobaciones (solo global). */
    public const CANAL_DIGEST = 'digest';

    /** @var list<string> */
    public const CANALES = [
        self::CANAL_MAIL,
        self::CANAL_IN_APP,
        self::CANAL_WHATSAPP,
        self::CANAL_DIGEST,
    ];

    /** Canales editables por tipo de aviso (sin digest). */
    /** @var list<string> */
    public const CANALES_POR_TIPO = [
        self::CANAL_MAIL,
        self::CANAL_IN_APP,
        self::CANAL_WHATSAPP,
    ];

    public static function permite(?int $usuarioId, string $canal, ?int $moduloAvisoTipoId = null): bool
    {
        if (! $usuarioId || $usuarioId <= 0) {
            return true;
        }

        if ($moduloAvisoTipoId && $moduloAvisoTipoId > 0 && Schema::hasTable('usuario_aviso_preferencia_tipo')) {
            $porTipo = UsuarioAvisoPreferenciaTipo::query()
                ->where('usuario_id', $usuarioId)
                ->where('modulo_aviso_tipo_id', $moduloAvisoTipoId)
                ->where('canal', $canal)
                ->first();
            if ($porTipo) {
                return (bool) $porTipo->habilitado;
            }
        }

        if (! Schema::hasTable('usuario_aviso_preferencia')) {
            return true;
        }

        $row = UsuarioAvisoPreferencia::query()
            ->where('usuario_id', $usuarioId)
            ->where('canal', $canal)
            ->first();

        if (! $row) {
            return true;
        }

        return (bool) $row->habilitado;
    }

    /**
     * @param  iterable<int|string>  $usuarioIds
     * @return list<int>
     */
    public static function filtrarIds(iterable $usuarioIds, string $canal, ?int $moduloAvisoTipoId = null): array
    {
        $out = [];
        $vistos = [];
        foreach ($usuarioIds as $id) {
            $usuarioId = (int) $id;
            if ($usuarioId <= 0 || isset($vistos[$usuarioId])) {
                continue;
            }
            $vistos[$usuarioId] = true;
            if (self::permite($usuarioId, $canal, $moduloAvisoTipoId)) {
                $out[] = $usuarioId;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public static function filtrarEmails(array $emails, ?int $moduloAvisoTipoId = null): array
    {
        if ($emails === [] || ! Schema::hasTable('usuario_aviso_preferencia')) {
            return $emails;
        }

        $mapa = Usuario::query()
            ->whereIn('email', $emails)
            ->get(['id', 'email'])
            ->keyBy(fn ($u) => strtolower((string) $u->email));

        $out = [];
        foreach ($emails as $email) {
            $key = strtolower($email);
            $usuario = $mapa->get($key);
            if (! $usuario) {
                $out[] = $email;
                continue;
            }
            if (self::permite((int) $usuario->id, self::CANAL_MAIL, $moduloAvisoTipoId)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, bool>
     */
    public static function mapaParaUsuario(int $usuarioId): array
    {
        $mapa = [];
        foreach (self::CANALES as $canal) {
            $mapa[$canal] = true;
        }
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_aviso_preferencia')) {
            return $mapa;
        }

        foreach (UsuarioAvisoPreferencia::query()->where('usuario_id', $usuarioId)->get() as $row) {
            $mapa[(string) $row->canal] = (bool) $row->habilitado;
        }

        return $mapa;
    }

    /**
     * @return array<int, array<string, bool>>  tipo_id => [canal => habilitado]
     */
    public static function mapaPorTipoParaUsuario(int $usuarioId): array
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_aviso_preferencia_tipo')) {
            return [];
        }

        $out = [];
        foreach (UsuarioAvisoPreferenciaTipo::query()->where('usuario_id', $usuarioId)->get() as $row) {
            $tipoId = (int) $row->modulo_aviso_tipo_id;
            if (! isset($out[$tipoId])) {
                $out[$tipoId] = [];
                foreach (self::CANALES_POR_TIPO as $canal) {
                    $out[$tipoId][$canal] = true;
                }
            }
            $out[$tipoId][(string) $row->canal] = (bool) $row->habilitado;
        }

        return $out;
    }

    /**
     * @param  array<string, bool|int|string>  $preferencias  canal => habilitado
     */
    public static function guardar(int $usuarioId, array $preferencias): void
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_aviso_preferencia')) {
            return;
        }

        foreach (self::CANALES as $canal) {
            if (! array_key_exists($canal, $preferencias)) {
                continue;
            }
            $habilitado = filter_var($preferencias[$canal], FILTER_VALIDATE_BOOLEAN);
            UsuarioAvisoPreferencia::query()->updateOrCreate(
                ['usuario_id' => $usuarioId, 'canal' => $canal],
                ['habilitado' => $habilitado]
            );
        }
    }

    /**
     * @param  array<int, array<string, bool|int|string>>  $porTipo  tipo_id => [canal => habilitado]
     */
    public static function guardarPorTipo(int $usuarioId, array $porTipo): void
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_aviso_preferencia_tipo')) {
            return;
        }

        foreach ($porTipo as $tipoId => $canales) {
            $tipoId = (int) $tipoId;
            if ($tipoId <= 0 || ! is_array($canales)) {
                continue;
            }
            foreach (self::CANALES_POR_TIPO as $canal) {
                if (! array_key_exists($canal, $canales)) {
                    continue;
                }
                $habilitado = filter_var($canales[$canal], FILTER_VALIDATE_BOOLEAN);
                // Solo persistir opt-outs o filas ya existentes; si todo true y no hay fila, no crear
                $existe = UsuarioAvisoPreferenciaTipo::query()
                    ->where('usuario_id', $usuarioId)
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->where('canal', $canal)
                    ->exists();
                if ($habilitado && ! $existe) {
                    continue;
                }
                UsuarioAvisoPreferenciaTipo::query()->updateOrCreate(
                    [
                        'usuario_id' => $usuarioId,
                        'modulo_aviso_tipo_id' => $tipoId,
                        'canal' => $canal,
                    ],
                    ['habilitado' => $habilitado]
                );
            }
        }
    }
}
