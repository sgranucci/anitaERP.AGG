<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_exclusion_percepcion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->string('tipo', 10);
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->decimal('porcentaje', 8, 4)->default(0);
            $table->date('desdefecha')->nullable();
            $table->date('hastafecha')->nullable();
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id', 'fk_cli_exclperc_cliente')
                ->references('id')->on('cliente')->onDelete('cascade');
            $table->foreign('provincia_id', 'fk_cli_exclperc_provincia')
                ->references('id')->on('provincia')->onDelete('restrict');
            $table->foreign('creousuario_id', 'fk_cli_exclperc_usuario')
                ->references('id')->on('usuario')->onDelete('restrict');
            $table->index(['cliente_id', 'tipo', 'provincia_id'], 'ix_cli_exclperc_cliente_tipo');
        });

        $this->migrarExclusionesIvaExistentes();
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_exclusion_percepcion');
    }

    private function migrarExclusionesIvaExistentes(): void
    {
        if (! Schema::hasColumn('cliente', 'desdefecha_exclusionpercepcioniva')
            && ! Schema::hasColumn('cliente', 'hastafecha_exclusionpercepcioniva')) {
            return;
        }

        $ahora = now();
        $lote = [];

        DB::table('cliente')
            ->select(['id', 'desdefecha_exclusionpercepcioniva', 'hastafecha_exclusionpercepcioniva'])
            ->where(function ($query) {
                $query->whereNotNull('desdefecha_exclusionpercepcioniva')
                    ->orWhereNotNull('hastafecha_exclusionpercepcioniva');
            })
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (&$lote, $ahora) {
                foreach ($filas as $fila) {
                    $desde = $this->fechaValidaONula($fila->desdefecha_exclusionpercepcioniva);
                    $hasta = $this->fechaValidaONula($fila->hastafecha_exclusionpercepcioniva);
                    if ($desde === null && $hasta === null) {
                        continue;
                    }
                    $lote[] = [
                        'cliente_id' => $fila->id,
                        'tipo' => 'IVA',
                        'provincia_id' => null,
                        'porcentaje' => 0,
                        'desdefecha' => $desde,
                        'hastafecha' => $hasta,
                        'creousuario_id' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                    if (count($lote) >= 500) {
                        DB::table('cliente_exclusion_percepcion')->insert($lote);
                        $lote = [];
                    }
                }
            });

        if ($lote !== []) {
            DB::table('cliente_exclusion_percepcion')->insert($lote);
        }
    }

    private function fechaValidaONula(mixed $valor): ?string
    {
        $texto = substr(trim((string) ($valor ?? '')), 0, 10);
        if ($texto === '' || $texto === '0000-00-00') {
            return null;
        }

        return $texto;
    }
};
