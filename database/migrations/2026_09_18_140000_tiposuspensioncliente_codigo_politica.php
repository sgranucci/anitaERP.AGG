<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código estable de política comercial en tiposuspensioncliente.
 * El nombre sigue siendo el motivo; el código define el tope de circuito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tiposuspensioncliente', 'codigo')) {
            Schema::table('tiposuspensioncliente', function (Blueprint $table) {
                $table->string('codigo', 20)->nullable()->after('nombre');
                $table->index('codigo', 'idx_tiposuspensioncliente_codigo');
            });
        }

        foreach (DB::table('tiposuspensioncliente')->orderBy('id')->get(['id', 'nombre', 'codigo']) as $tipo) {
            if (trim((string) ($tipo->codigo ?? '')) !== '') {
                continue;
            }
            $codigo = $this->codigoDesdeNombre((string) ($tipo->nombre ?? ''));
            if ($codigo === null) {
                continue;
            }
            DB::table('tiposuspensioncliente')->where('id', $tipo->id)->update(['codigo' => $codigo]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tiposuspensioncliente', 'codigo')) {
            return;
        }

        Schema::table('tiposuspensioncliente', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_tiposuspensioncliente_codigo');
            } catch (\Throwable) {
            }
            $table->dropColumn('codigo');
        });
    }

    private function codigoDesdeNombre(string $nombre): ?string
    {
        $n = strtoupper($this->sinAcentos($nombre));
        if ($n === '') {
            return null;
        }
        if (str_contains($n, 'MOROSO')) {
            return 'MOROSO';
        }
        if (str_contains($n, 'PROFORMA') || str_contains($n, 'NO FACTURAR') || str_contains($n, 'NO_FACTURAR')) {
            return 'PROFORMA';
        }
        if (str_contains($n, 'SUSPENDIDO') || str_contains($n, 'BLOQUEADO') || str_contains($n, 'APOCRIF') || str_contains($n, 'APOC')) {
            return 'BLOQUEADO';
        }

        return null;
    }

    private function sinAcentos(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return $convertido !== false ? $convertido : $texto;
    }
};
