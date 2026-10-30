<?php

use App\Models\Caja\TesoreriaCrownComprobante;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién armó la reversa. Las que ya existen se completan con el alta de audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_comprobante', 'creado_usuario_id')) {
            Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->foreignId('creado_usuario_id')->nullable()->after('revierte_id')->constrained('usuario');
            });
        }

        $ids = DB::table('tesoreria_crown_comprobante')
            ->whereNotNull('revierte_id')
            ->whereNull('creado_usuario_id')
            ->pluck('id');
        if ($ids->isEmpty() || ! Schema::hasTable('audits')) {
            return;
        }

        $altas = DB::table('audits')
            ->where('auditable_type', TesoreriaCrownComprobante::class)
            ->where('event', 'created')
            ->whereIn('auditable_id', $ids)
            ->orderBy('id')
            ->get(['auditable_id', 'user_id']);
        $visto = [];
        foreach ($altas as $alta) {
            $id = (int) $alta->auditable_id;
            if (isset($visto[$id]) || (int) $alta->user_id <= 0) {
                continue;
            }
            $visto[$id] = true;
            DB::table('tesoreria_crown_comprobante')->where('id', $id)->update([
                'creado_usuario_id' => (int) $alta->user_id,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasColumn('tesoreria_crown_comprobante', 'creado_usuario_id')) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_usuario_id');
        });
    }
};
