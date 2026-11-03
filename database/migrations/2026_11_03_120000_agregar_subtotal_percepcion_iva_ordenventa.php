<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La orden de venta parte del bruto y suma percepciones e IVA.
 * El monto histórico pasa a ser el subtotal; el total final sigue en monto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenventa')) {
            return;
        }

        $agregoSubtotal = ! Schema::hasColumn('ordenventa', 'subtotal');

        if ($agregoSubtotal) {
            Schema::table('ordenventa', function (Blueprint $table) {
                $table->decimal('subtotal', 22, 4)->default(0)->after('monto');
            });
        }
        if (! Schema::hasColumn('ordenventa', 'percepcion')) {
            Schema::table('ordenventa', function (Blueprint $table) {
                $table->decimal('percepcion', 22, 4)->default(0)->after('subtotal');
            });
        }
        if (! Schema::hasColumn('ordenventa', 'iva')) {
            Schema::table('ordenventa', function (Blueprint $table) {
                $table->decimal('iva', 22, 4)->default(0)->after('percepcion');
            });
        }

        if ($agregoSubtotal) {
            DB::table('ordenventa')->update(['subtotal' => DB::raw('monto')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordenventa')) {
            return;
        }

        Schema::table('ordenventa', function (Blueprint $table) {
            if (Schema::hasColumn('ordenventa', 'iva')) {
                $table->dropColumn('iva');
            }
            if (Schema::hasColumn('ordenventa', 'percepcion')) {
                $table->dropColumn('percepcion');
            }
            if (Schema::hasColumn('ordenventa', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
