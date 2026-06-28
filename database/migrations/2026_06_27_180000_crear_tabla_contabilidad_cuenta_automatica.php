<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contabilidad_cuenta_automatica', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_contab_cuenta_auto_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('clave', 80);
            $table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->foreign('cuentacontable_id', 'fk_contab_cuenta_auto_cuenta')
                ->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['empresa_id', 'clave'], 'uk_contab_cuenta_auto_empresa_clave');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        $this->precargarDesdeModulos();
    }

    private function precargarDesdeModulos(): void
    {
        if (! Schema::hasTable('empresa')) {
            return;
        }

        $empresaIds = DB::table('empresa')->orderBy('id')->pluck('id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
                $cuentaId = null;

                if ($meta['modulo_tabla'] !== null
                    && $meta['modulo_columna'] !== null
                    && Schema::hasTable($meta['modulo_tabla'])
                    && Schema::hasColumn($meta['modulo_tabla'], $meta['modulo_columna'])) {
                    $moduloValor = DB::table($meta['modulo_tabla'])
                        ->where('empresa_id', $empresaId)
                        ->value($meta['modulo_columna']);
                    $cuentaId = $this->intOrNull($moduloValor);
                }

                if ($cuentaId === null && $meta['env_config'] !== null) {
                    $cuentaId = $this->intOrNull(config($meta['env_config']));
                }

                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function intOrNull(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    public function down(): void
    {
        Schema::dropIfExists('contabilidad_cuenta_automatica');
    }
};
