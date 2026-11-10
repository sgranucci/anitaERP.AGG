<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas ON-LINE (suc. 67 Del Río) y BETSUR (suc. 68) del flash Crown,
 * según l-consolida.c formato Completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('flash_crown')) {
            return;
        }
        if (Schema::hasColumn('flash_crown', 'ol_win')) {
            return;
        }

        Schema::table('flash_crown', function (Blueprint $table) {
            $table->unsignedInteger('ol_att')->default(0)->after('coin_in_rul');
            $table->decimal('ol_drop', 16, 2)->default(0)->after('ol_att');
            $table->decimal('ol_coin_in', 16, 2)->default(0)->after('ol_drop');
            $table->decimal('ol_cage_bal', 16, 2)->default(0)->after('ol_coin_in');
            $table->decimal('ol_win', 16, 2)->default(0)->after('ol_cage_bal');
            $table->unsignedInteger('bs_att')->default(0)->after('ol_win');
            $table->decimal('bs_coin_in', 16, 2)->default(0)->after('bs_att');
            $table->decimal('bs_cage_bal', 16, 2)->default(0)->after('bs_coin_in');
            $table->decimal('bs_win', 16, 2)->default(0)->after('bs_cage_bal');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('flash_crown') || ! Schema::hasColumn('flash_crown', 'ol_win')) {
            return;
        }

        Schema::table('flash_crown', function (Blueprint $table) {
            $table->dropColumn([
                'ol_att', 'ol_drop', 'ol_coin_in', 'ol_cage_bal', 'ol_win',
                'bs_att', 'bs_coin_in', 'bs_cage_bal', 'bs_win',
            ]);
        });
    }
};
