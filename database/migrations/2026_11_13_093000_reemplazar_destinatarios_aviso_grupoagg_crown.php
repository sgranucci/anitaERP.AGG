<?php

use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En Crown los seeds de AGG dejaron mails @grupoagg.com en avisos.
 * Se borran y se reemplazan por usuarios locales según el aviso;
 * si no hay equivalente claro, va a cchiappero.
 */
return new class extends Migration
{
    private const FALLBACK = 'cchiappero';

    /**
     * Logins Crown por codigo de aviso (modulo/codigo).
     * Sin entrada → FALLBACK.
     *
     * @var array<string, list<string>>
     */
    private const MAPA = [
        // Compras / cuentas a pagar (antes ablanco)
        'compras/contrato_validacion_abono_pendiente' => ['eflores', 'jcofre'],
        'compras/ordencompra_alertas_abiertas' => ['eflores', 'jcofre'],
        'compras/suscripcion_comprobante_escalamiento' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_articulo_extra' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_cantidad_diferencia' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_faltante_oc' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_linea_rechazada' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_parte_unica' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_precio_diferencia' => ['eflores', 'jcofre'],
        'stock/recepcion_proveedor_precio_pendiente_compras' => ['eflores', 'jcofre'],
        // Laboratorio: sin rol local → cchiappero
        'stock/recepcion_proveedor_laboratorio' => ['cchiappero'],
        // Capital humano
        'sueldos/empleado_alta_provisoria' => ['rmerino'],
        'sueldos/libreta_sanitaria_pendiente' => ['rmerino'],
        // Tecnología
        'ticket/alta_tecnologia' => ['bsepulveda'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoIdsConGrupoagg = $this->tipoIdsConGrupoagg();
        if ($tipoIdsConGrupoagg === []) {
            return;
        }

        $query = ModuloAvisoDestinatario::query()->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com'])
                ->orWhereIn('usuario_id', function ($sub) {
                    $sub->select('id')
                        ->from('usuario')
                        ->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com']);
                });
        });

        EloquentAuditDeleteSupport::each($query);

        foreach ($tipoIdsConGrupoagg as $tipoId) {
            $clave = $this->claveTipo($tipoId);
            $logins = self::MAPA[$clave] ?? [self::FALLBACK];
            foreach ($logins as $login) {
                $this->agregarUsuario($tipoId, $login);
            }

            if (! $this->tieneDestinatarioActivo($tipoId)) {
                $this->agregarUsuario($tipoId, self::FALLBACK);
            }
        }
    }

    public function down(): void
    {
        // No se restauran mails de AGG en Crown.
    }

    /** @return list<int> */
    private function tipoIdsConGrupoagg(): array
    {
        return ModuloAvisoDestinatario::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com'])
                    ->orWhereIn('usuario_id', function ($sub) {
                        $sub->select('id')
                            ->from('usuario')
                            ->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com']);
                    });
            })
            ->distinct()
            ->pluck('modulo_aviso_tipo_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private function claveTipo(int $tipoId): string
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return '';
        }

        $tipo = DB::table('modulo_aviso_tipo')->where('id', $tipoId)->first(['modulo', 'codigo']);
        if ($tipo === null) {
            return '';
        }

        return strtolower(trim((string) $tipo->modulo)).'/'.strtolower(trim((string) $tipo->codigo));
    }

    private function tieneDestinatarioActivo(int $tipoId): bool
    {
        return DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('activo', true)
            ->exists();
    }

    private function agregarUsuario(int $tipoId, string $login): void
    {
        if (! Schema::hasTable('usuario')) {
            return;
        }

        $usuario = DB::table('usuario')
            ->where('usuario', $login)
            ->where('suspendido', false)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->first(['id', 'email']);

        if ($usuario === null) {
            if ($login !== self::FALLBACK) {
                $this->agregarUsuario($tipoId, self::FALLBACK);
            }

            return;
        }

        $email = strtolower(trim((string) $usuario->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($usuario, $email) {
                $q->where('usuario_id', (int) $usuario->id)
                    ->orWhereRaw('LOWER(email) = ?', [$email]);
            })
            ->exists();

        if ($existe) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuario, $email) {
                    $q->where('usuario_id', (int) $usuario->id)
                        ->orWhereRaw('LOWER(email) = ?', [$email]);
                })
                ->update([
                    'activo' => true,
                    'updated_at' => now(),
                ]);

            return;
        }

        $now = now();
        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => $email,
            'usuario_id' => (int) $usuario->id,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
