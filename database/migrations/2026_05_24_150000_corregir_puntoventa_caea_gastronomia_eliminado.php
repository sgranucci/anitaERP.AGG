<?php

use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Reasigna puntoventa_caea_id huérfanos (PV eliminado) al CAEA activo de la misma empresa.
   */
  public function up(): void
  {
    if (! Schema::hasTable('configuracion_puntoventa_gastronomia')) {
      return;
    }

    $configs = DB::table('configuracion_puntoventa_gastronomia')->get([
      'id',
      'empresa_id',
      'puntoventa_caea_id',
    ]);

    foreach ($configs as $cfg) {
      $caeaId = (int) $cfg->puntoventa_caea_id;
      if ($caeaId <= 0) {
        continue;
      }

      $existe = Puntoventa::query()->whereKey($caeaId)->exists();
      if ($existe) {
        continue;
      }

      $reemplazo = Puntoventa::query()
        ->where('empresa_id', (int) $cfg->empresa_id)
        ->where('nombre', 'like', '%CAEA%')
        ->orderBy('id')
        ->value('id');

      if (! $reemplazo) {
        continue;
      }

      DB::table('configuracion_puntoventa_gastronomia')
        ->where('id', (int) $cfg->id)
        ->update(['puntoventa_caea_id' => (int) $reemplazo]);
    }
  }

  public function down(): void
  {
    // No revertir: el PV CAEA anterior ya no existe.
  }
};
