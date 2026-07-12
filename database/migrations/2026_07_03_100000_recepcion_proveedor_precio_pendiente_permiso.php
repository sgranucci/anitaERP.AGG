<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito precio pendiente en recepción COM + permisos modificar precio recepción / OC.
 */
return new class extends Migration
{
    private const MENU_RECEPCION = 'stock/recepcion-proveedor';

    private const MENU_OC = 'compras/ordencompra';

    private const PERMISO_RECEPCION = 'modificar-precio-recepcion-proveedor';

    private const PERMISO_OC = 'modificar-precio-ordencompra';

    /** Roles de Adrian Lovera, Gustavo Enriquez, Nicolas Armoa, Diego Mercali, Maximilano Medina */
    private const ROLES_MODIFICAR_PRECIO_OC = [
        'Sup-Gastronomia',
        'op-Logistica',
    ];

    /** Compras: pueden modificar precios en recepción y en OC */
    private const ROLES_COMPRAS_EXTRA = [
        'Enc-compras',
        'Op-Compras',
        'administrador',
    ];

    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'fl_precio_pendiente_aprobacion')) {
                $table->boolean('fl_precio_pendiente_aprobacion')->default(false)->after('fl_precio_diferencia');
            }
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'precio_solicitado')) {
                $table->decimal('precio_solicitado', 18, 6)->nullable()->after('precio_ordencompra');
            }
        });

        $permisoRecepcionId = $this->upsertPermiso(
            self::MENU_RECEPCION,
            self::PERMISO_RECEPCION,
            'Modificar precio en recepción de proveedor'
        );
        $permisoOcId = $this->upsertPermiso(
            self::MENU_OC,
            self::PERMISO_OC,
            'Modificar precio en orden de compra (recepciones pendientes)'
        );

        $rolIdsOc = $this->resolverRolIds(array_merge(self::ROLES_MODIFICAR_PRECIO_OC, self::ROLES_COMPRAS_EXTRA));
        $rolIdsRecepcion = $this->resolverRolIds(self::ROLES_COMPRAS_EXTRA);

        foreach ($rolIdsOc as $rolId) {
            $this->asignarPermisoRol($permisoOcId, $rolId);
        }
        foreach ($rolIdsRecepcion as $rolId) {
            $this->asignarPermisoRol($permisoRecepcionId, $rolId);
        }

        $this->seedModuloAvisos();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $menuUrl, string $slug, string $nombre): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /** @param list<string> $nombres */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function seedModuloAvisos(): void
    {
        $now = now();

        $tipos = [
            [
                'codigo' => 'recepcion_proveedor_precio_pendiente_compras',
                'nombre' => 'Recepción borrador — precio pendiente aprobación OC',
                'descripcion' => 'Aviso a compras cuando un usuario sin permiso de modificar precios cargó una recepción con precios distintos a la OC.',
                'mail_asunto' => 'Recepción Nº {numero_recepcion} — solicitud cambio precio OC {numero_oc}',
                'mail_texto' => "El usuario {usuario_recepcion} cargó en borrador la recepción Nº {numero_recepcion} del proveedor {proveedor} (OC {numero_oc}) con precios de factura/remito distintos a la orden de compra.\n\nResumen:\n{resumen_diferencias}\n\nComentarios:\n{comentario_precio}\n\nActualice los precios en la OC y aplique los precios solicitados desde la solapa Recepciones de la orden de compra.",
            ],
            [
                'codigo' => 'recepcion_proveedor_precio_liberado',
                'nombre' => 'Recepción — precios OC actualizados, confirmar COM',
                'descripcion' => 'Aviso al usuario que cargó la recepción cuando compras actualizó la OC y puede confirmar la COM.',
                'mail_asunto' => 'OC {numero_oc} actualizada — puede confirmar recepción Nº {numero_recepcion}',
                'mail_texto' => "Compras actualizó los precios de la orden de compra {numero_oc}. La recepción Nº {numero_recepcion} del proveedor {proveedor} ya puede confirmarse.\n\nIngrese al ERP y confirme la COM, o consulte el detalle con el enlace de abajo.",
            ],
        ];

        foreach ($tipos as $tipo) {
            $existente = DB::table('modulo_aviso_tipo')
                ->where('modulo', 'stock')
                ->where('codigo', $tipo['codigo'])
                ->first();

            if ($existente) {
                continue;
            }

            DB::table('modulo_aviso_tipo')->insert([
                'modulo' => 'stock',
                'codigo' => $tipo['codigo'],
                'nombre' => $tipo['nombre'],
                'descripcion' => $tipo['descripcion'],
                'activo' => true,
                'mail_asunto' => $tipo['mail_asunto'],
                'mail_texto' => $tipo['mail_texto'],
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $tipoComprasId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_precio_pendiente_compras')
            ->value('id') ?? 0);

        if ($tipoComprasId > 0) {
            $emailsCompras = ['ablanco@grupoagg.com'];
            foreach ($emailsCompras as $email) {
                if (! DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoComprasId)
                    ->where('email', $email)
                    ->exists()
                ) {
                    DB::table('modulo_aviso_destinatario')->insert([
                        'modulo_aviso_tipo_id' => $tipoComprasId,
                        'email' => $email,
                        'usuario_id' => null,
                        'empresa_id' => null,
                        'centrocosto_id' => null,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'precio_solicitado')) {
                $table->dropColumn('precio_solicitado');
            }
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'fl_precio_pendiente_aprobacion')) {
                $table->dropColumn('fl_precio_pendiente_aprobacion');
            }
        });

        foreach ([self::PERMISO_RECEPCION, self::PERMISO_OC] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        foreach (['recepcion_proveedor_precio_pendiente_compras', 'recepcion_proveedor_precio_liberado'] as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'stock')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId > 0) {
                DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
                DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
