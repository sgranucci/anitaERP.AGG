<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Seguridad\Usuario;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitante de la OC distinto de quien la carga.
 * Solo opera cuando OrdencompraUiConfigSupport::solicitanteEditable() es true (El Bierzo).
 */
final class OrdencompraSolicitanteSupport
{
    /**
     * Valores del campo en el formulario (alta, edición o redisplay tras error).
     *
     * @return array{id: int|string, codigo: string, nombre: string}
     */
    public static function valoresFormulario(?Ordencompra $oc): array
    {
        $empresaId = (int) old('empresa_id', $oc->empresa_id ?? 0);
        $idPedido = old('solicitante_usuario_id');
        if ($idPedido !== null && $idPedido !== '') {
            $pedido = self::usuarioVisible((int) $idPedido);
            if ($pedido) {
                return self::desdeUsuario($pedido);
            }
        }

        if ($oc) {
            $guardado = (int) ($oc->solicitante_usuario_id ?? 0);
            if ($guardado > 0) {
                $usuario = $oc->relationLoaded('solicitantes') && $oc->solicitantes
                    ? $oc->solicitantes
                    : self::usuarioVisible($guardado);
                if ($usuario) {
                    return self::desdeUsuario($usuario);
                }
            }

            if ($oc->relationLoaded('usuarios') && $oc->usuarios) {
                return self::desdeUsuario($oc->usuarios);
            }

            $creador = self::usuarioVisible((int) ($oc->creousuario_id ?? 0));
            if ($creador) {
                return self::desdeUsuario($creador);
            }
        }

        $id = self::idPrecarga((int) Auth::id(), $empresaId);
        $usuario = self::usuarioVisible($id);

        return $usuario
            ? self::desdeUsuario($usuario)
            : ['id' => '', 'codigo' => '', 'nombre' => ''];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int, error: ?string, explicito: bool}
     */
    public static function resolverParaGuardar(array $payload, int $empresaId): array
    {
        if (! array_key_exists('solicitante_usuario_id', $payload)) {
            return [
                'id' => self::idPrecarga((int) Auth::id(), $empresaId),
                'error' => null,
                'explicito' => false,
            ];
        }

        $id = (int) $payload['solicitante_usuario_id'];
        if ($id <= 0) {
            return [
                'id' => 0,
                'error' => 'Indicá el solicitante.',
                'explicito' => true,
            ];
        }

        $usuario = UsuarioOperativoSupport::find($id);
        if (! $usuario) {
            return [
                'id' => 0,
                'error' => 'El solicitante no existe o está suspendido.',
                'explicito' => true,
            ];
        }

        if (! UsuarioOperativoSupport::validoParaEmpresa($usuario, $empresaId > 0 ? $empresaId : null)) {
            return [
                'id' => 0,
                'error' => 'El solicitante no pertenece a la empresa de la orden de compra.',
                'explicito' => true,
            ];
        }

        return [
            'id' => (int) $usuario->id,
            'error' => null,
            'explicito' => true,
        ];
    }

    public static function recordar(int $usuarioCargaId, int $solicitanteId): void
    {
        if ($usuarioCargaId <= 0 || $solicitanteId <= 0) {
            return;
        }
        if (! Schema::hasTable('ordencompra_solicitante_preferencia')) {
            return;
        }

        $ahora = now();
        $existe = DB::table('ordencompra_solicitante_preferencia')
            ->where('usuario_id', $usuarioCargaId)
            ->exists();

        if ($existe) {
            DB::table('ordencompra_solicitante_preferencia')
                ->where('usuario_id', $usuarioCargaId)
                ->update([
                    'solicitante_usuario_id' => $solicitanteId,
                    'updated_at' => $ahora,
                ]);

            return;
        }

        DB::table('ordencompra_solicitante_preferencia')->insert([
            'usuario_id' => $usuarioCargaId,
            'solicitante_usuario_id' => $solicitanteId,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public static function idPrecarga(int $usuarioCargaId, int $empresaId): int
    {
        $preferido = self::leerPreferencia($usuarioCargaId);
        if ($preferido > 0) {
            $usuario = UsuarioOperativoSupport::find($preferido);
            if ($usuario && UsuarioOperativoSupport::validoParaEmpresa($usuario, $empresaId > 0 ? $empresaId : null)) {
                return (int) $usuario->id;
            }
        }

        return $usuarioCargaId > 0 ? $usuarioCargaId : 0;
    }

    /**
     * Recuerda el default del que carga solo cuando eligió el solicitante a propósito.
     * Una OC vieja sin solicitante guardado no pisa la preferencia si se graba tal cual.
     */
    public static function debeRecordar(bool $explicito, bool $esAlta, ?int $anteriorId, int $nuevoId, int $creoUsuarioId): bool
    {
        if (! $explicito || $nuevoId <= 0) {
            return false;
        }
        if ($esAlta) {
            return true;
        }
        if ($anteriorId !== null && $anteriorId > 0) {
            return true;
        }

        return $nuevoId !== $creoUsuarioId;
    }

    private static function leerPreferencia(int $usuarioCargaId): int
    {
        if ($usuarioCargaId <= 0 || ! Schema::hasTable('ordencompra_solicitante_preferencia')) {
            return 0;
        }

        return (int) (DB::table('ordencompra_solicitante_preferencia')
            ->where('usuario_id', $usuarioCargaId)
            ->value('solicitante_usuario_id') ?? 0);
    }

    private static function usuarioVisible(int $id): ?Usuario
    {
        if ($id <= 0) {
            return null;
        }

        return UsuarioOperativoSupport::find($id) ?? Usuario::query()->find($id);
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    private static function desdeUsuario(Usuario $usuario): array
    {
        return [
            'id' => (int) $usuario->id,
            'codigo' => (string) ($usuario->usuario ?? ''),
            'nombre' => (string) ($usuario->nombre ?? ''),
        ];
    }
}
