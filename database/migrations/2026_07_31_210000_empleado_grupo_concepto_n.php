<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * N grupos por empleado (sin límite). Migra emp_grp1/2/3 fijos → pivot.
 * Los códigos Anita (grupo_concepto_*_codigo) se conservan solo como espejo de sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_grupo_concepto_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('grupo_concepto_id');
            $table->unsignedInteger('orden')->default(0);
            $table->string('origen', 20)->default('manual'); // manual|sync_anita
            $table->timestamps();

            $table->unique(['empleado_id', 'grupo_concepto_id'], 'emp_grupo_conc_uq');
            $table->index(['empleado_id', 'orden'], 'emp_grupo_conc_ord_idx');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->cascadeOnDelete();
            $table->foreign('grupo_concepto_id')->references('id')->on('grupo_concepto_sueldos')->cascadeOnDelete();
        });

        // Migrar slots 1/2/3 → pivot
        $ahora = now();
        $rows = DB::table('empleado_sueldos')
            ->select('id', 'grupo_concepto_1_id', 'grupo_concepto_2_id', 'grupo_concepto_3_id')
            ->where(function ($q) {
                $q->whereNotNull('grupo_concepto_1_id')
                    ->orWhereNotNull('grupo_concepto_2_id')
                    ->orWhereNotNull('grupo_concepto_3_id');
            })
            ->get();

        $lote = [];
        foreach ($rows as $e) {
            $orden = 0;
            foreach ([1, 2, 3] as $slot) {
                $gid = $e->{'grupo_concepto_'.$slot.'_id'} ?? null;
                if (! $gid) {
                    continue;
                }
                $orden++;
                $lote[] = [
                    'empleado_id' => $e->id,
                    'grupo_concepto_id' => (int) $gid,
                    'orden' => $orden,
                    'origen' => 'sync_anita',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            if (count($lote) >= 500) {
                $this->insertIgnorandoDuplicados($lote);
                $lote = [];
            }
        }
        if ($lote !== []) {
            $this->insertIgnorandoDuplicados($lote);
        }

        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->dropForeign(['grupo_concepto_1_id']);
            $table->dropForeign(['grupo_concepto_2_id']);
            $table->dropForeign(['grupo_concepto_3_id']);
            $table->dropColumn(['grupo_concepto_1_id', 'grupo_concepto_2_id', 'grupo_concepto_3_id']);
        });
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_concepto_1_id')->nullable()->after('art_id');
            $table->unsignedBigInteger('grupo_concepto_2_id')->nullable()->after('grupo_concepto_1_id');
            $table->unsignedBigInteger('grupo_concepto_3_id')->nullable()->after('grupo_concepto_2_id');
            $table->foreign('grupo_concepto_1_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
            $table->foreign('grupo_concepto_2_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
            $table->foreign('grupo_concepto_3_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
        });

        // Restaurar hasta 3 grupos por empleado (los de menor orden).
        $empleados = DB::table('empleado_grupo_concepto_sueldos')
            ->orderBy('empleado_id')->orderBy('orden')->orderBy('id')
            ->get(['empleado_id', 'grupo_concepto_id']);
        $porEmp = [];
        foreach ($empleados as $r) {
            $porEmp[$r->empleado_id][] = (int) $r->grupo_concepto_id;
        }
        foreach ($porEmp as $empId => $gids) {
            $upd = [
                'grupo_concepto_1_id' => $gids[0] ?? null,
                'grupo_concepto_2_id' => $gids[1] ?? null,
                'grupo_concepto_3_id' => $gids[2] ?? null,
            ];
            DB::table('empleado_sueldos')->where('id', $empId)->update($upd);
        }

        Schema::dropIfExists('empleado_grupo_concepto_sueldos');
    }

    /** @param  list<array<string, mixed>>  $lote */
    private function insertIgnorandoDuplicados(array $lote): void
    {
        // Evitar choque unique si el mismo grupo estaba en dos slots.
        $vistos = [];
        $limpio = [];
        foreach ($lote as $r) {
            $k = $r['empleado_id'].':'.$r['grupo_concepto_id'];
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $limpio[] = $r;
        }
        if ($limpio !== []) {
            DB::table('empleado_grupo_concepto_sueldos')->insert($limpio);
        }
    }
};
