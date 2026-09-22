<?php

use App\Support\Caja\OppRenumeracionAnitaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numeración OPP configurable por empresa (sistema_numerador.numera_en_erp).
 * En Ferli queda activa y se corrigen las cuatro OPP que saltaron a la serie de cobranzas.
 */
return new class extends Migration
{
    /** @var array<int, array{0: int, 1: int}> pagoproveedor_id => [numero viejo, numero nuevo] */
    private const MAPA = [
        24149 => [77748, 45931],
        24150 => [77749, 45932],
        24151 => [77750, 45933],
        24152 => [77751, 45934],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('sistema_numerador', 'numera_en_erp')) {
            Schema::table('sistema_numerador', function (Blueprint $table) {
                $table->boolean('numera_en_erp')->default(false)->after('ultimo_numero');
            });
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $pendientes = $this->pendientes();
        if ($pendientes === []) {
            DB::table('sistema_numerador')
                ->where('codigo', 'caja.OPP')
                ->update(['numera_en_erp' => true]);

            return;
        }

        if (count($pendientes) !== count(self::MAPA)) {
            throw new RuntimeException(
                'La corrección de OPP Ferli quedó a medias: hay '.count($pendientes).' de '.count(self::MAPA).' órdenes con el número viejo.'
            );
        }

        $this->assertSinOtrasOppFueraDeSerie();

        foreach ($pendientes as $pagoId => $par) {
            [$viejo, $nuevo] = $par;
            $this->assertDestinoLibre($nuevo);
            OppRenumeracionAnitaSupport::renumerar($viejo, $nuevo);
            $this->actualizarErp($pagoId, $viejo, $nuevo);
        }

        DB::table('sistema_numerador')
            ->where('codigo', 'caja.OPP')
            ->update(['numera_en_erp' => true]);

        DB::table('sistema_numerador')
            ->where('codigo', 'caja.OPP')
            ->where('empresa_id', 1)
            ->update([
                'ultimo_numero' => 45934,
                'observacion' => 'Ferli: la OPP numera en el ERP. Serie retomada en 45934 el 22/09/2026.',
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('sistema_numerador', 'numera_en_erp')) {
            Schema::table('sistema_numerador', function (Blueprint $table) {
                $table->dropColumn('numera_en_erp');
            });
        }
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    private function pendientes(): array
    {
        $out = [];
        foreach (self::MAPA as $pagoId => $par) {
            $existe = DB::table('pagoproveedor')
                ->where('id', $pagoId)
                ->where('numerotransaccion', (string) $par[0])
                ->exists();
            if ($existe) {
                $out[$pagoId] = $par;
            }
        }

        return $out;
    }

    private function assertSinOtrasOppFueraDeSerie(): void
    {
        $permitidos = ['77745', '77748', '77749', '77750', '77751'];
        $tipoOppId = (int) DB::table('tipotransaccion_caja')->where('abreviatura', 'OPP')->value('id');
        $raros = DB::table('caja_movimiento')
            ->where('tipotransaccion_caja_id', $tipoOppId)
            ->whereRaw(SqlDialectSupport::coincideRegex('numerotransaccion'), ['^[0-9]+$'])
            ->whereRaw(SqlDialectSupport::castEntero('numerotransaccion').' > 45930')
            ->whereNotIn('numerotransaccion', $permitidos)
            ->pluck('numerotransaccion');

        if ($raros->isNotEmpty()) {
            throw new RuntimeException(
                'Hay otras OPP fuera de la serie 45930: '.$raros->implode(', ').'. No se renumeró.'
            );
        }
    }

    private function assertDestinoLibre(int $nuevo): void
    {
        $nro = (string) $nuevo;
        $tipoOppId = (int) DB::table('tipotransaccion_caja')->where('abreviatura', 'OPP')->value('id');
        $ocupada = DB::table('pagoproveedor')
            ->where('tipocomprobante', 'OPP')
            ->where('numerotransaccion', $nro)
            ->exists()
            || DB::table('caja_movimiento')
                ->where('tipotransaccion_caja_id', $tipoOppId)
                ->where('numerotransaccion', $nro)
                ->exists();
        if ($ocupada) {
            throw new RuntimeException('El número OPP '.$nuevo.' ya está usado en el ERP.');
        }
    }

    private function actualizarErp(int $pagoId, int $viejo, int $nuevo): void
    {
        $pago = DB::table('pagoproveedor')->where('id', $pagoId)->first();
        if ($pago === null || (string) $pago->numerotransaccion !== (string) $viejo) {
            throw new RuntimeException('La OP '.$pagoId.' ya no tiene el número '.$viejo.'.');
        }

        $cajaId = (int) ($pago->caja_movimiento_id ?? 0);
        $textoViejo = (string) $viejo;
        $textoNuevo = (string) $nuevo;

        DB::table('pagoproveedor')->where('id', $pagoId)->update([
            'numerotransaccion' => $textoNuevo,
            'detalle' => 'Orden de pago Nro. '.$textoNuevo,
            'updated_at' => now(),
        ]);

        if ($cajaId > 0) {
            DB::table('caja_movimiento')->where('id', $cajaId)->where('numerotransaccion', $textoViejo)->update([
                'numerotransaccion' => $textoNuevo,
                'detalle' => 'Orden de pago Nro. '.$textoNuevo,
                'updated_at' => now(),
            ]);
            $this->reemplazarTexto('caja_movimiento_estado', 'caja_movimiento_id', $cajaId, 'observacion', $textoViejo, $textoNuevo);
        }

        $this->reemplazarTexto('asiento', 'pagoproveedor_id', $pagoId, 'observacion', $textoViejo, $textoNuevo);

        $asientoIds = DB::table('asiento')->where('pagoproveedor_id', $pagoId)->pluck('id');
        foreach ($asientoIds as $asientoId) {
            $this->reemplazarTexto('asiento_movimiento', 'asiento_id', (int) $asientoId, 'observacion', $textoViejo, $textoNuevo);
        }

        $this->reemplazarTexto('pagoproveedor_estado', 'pagoproveedor_id', $pagoId, 'observacion', $textoViejo, $textoNuevo);
    }

    private function reemplazarTexto(
        string $tabla,
        string $clave,
        int $id,
        string $columna,
        string $viejo,
        string $nuevo,
    ): void {
        $filas = DB::table($tabla)->where($clave, $id)->get(['id', $columna]);
        foreach ($filas as $fila) {
            $texto = (string) ($fila->{$columna} ?? '');
            if ($texto === '' || ! str_contains($texto, $viejo)) {
                continue;
            }
            DB::table($tabla)->where('id', $fila->id)->update([
                $columna => str_replace($viejo, $nuevo, $texto),
            ]);
        }
    }
};
