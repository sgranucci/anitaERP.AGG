<?php

namespace App\Console\Commands;

use App\Support\Ventas\VentaPruebaBorradoSupport;
use Illuminate\Console\Command;

/**
 * Borra ventas de prueba del ERP (cascada) y opcionalmente Anita + remitos.
 * Sin --ejecutar solo informa (dry-run).
 */
class BorrarVentasPruebaCommand extends Command
{
    protected $signature = 'ventas:borrar-ventas-prueba
                            {--ids= : IDs de venta separados por coma}
                            {--fecha= : Fecha Y-m-d (venta.fecha)}
                            {--pv= : Códigos de punto de venta (ej. 8,15)}
                            {--anita : También borra en Anita/Informix}
                            {--remitos : También borra remitos vinculados}
                            {--ejecutar : Persiste el borrado. Sin este flag solo informa}';

    protected $description = 'Borra ventas de prueba (ERP + opc. Anita/remitos). Dry-run por defecto.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $idsOpt = trim((string) $this->option('ids'));
        $fecha = trim((string) $this->option('fecha'));
        $pvOpt = trim((string) $this->option('pv'));
        $conAnita = (bool) $this->option('anita');
        $conRemitos = (bool) $this->option('remitos');
        $ejecutar = (bool) $this->option('ejecutar');

        $idsExplicitos = $idsOpt === ''
            ? []
            : (preg_split('/\s*,\s*/', $idsOpt, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $codigosPv = $pvOpt === ''
            ? []
            : (preg_split('/\s*,\s*/', $pvOpt, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if ($idsExplicitos === [] && $fecha === '') {
            $this->error('Indicá --ids=… o --fecha=YYYY-MM-DD (opcional --pv=8,15).');

            return self::FAILURE;
        }

        $ids = VentaPruebaBorradoSupport::resolverIds(
            $fecha !== '' ? $fecha : null,
            $codigosPv,
            $idsExplicitos
        );

        if ($ids === []) {
            $this->warn('No hay ventas que coincidan con el criterio.');

            return self::SUCCESS;
        }

        $ids = VentaPruebaBorradoSupport::ordenarParaBorrado($ids);
        $hijas = VentaPruebaBorradoSupport::contarHijas($ids, $conRemitos);
        $filas = VentaPruebaBorradoSupport::listarResumen($ids);

        $this->info('Criterio: '.($idsExplicitos !== []
            ? 'ids explícitos ('.count($ids).')'
            : 'fecha='.$fecha.($codigosPv !== [] ? ' pv='.implode(',', $codigosPv) : ' (todos los PV)')));
        $this->info('Anita: '.($conAnita ? 'SÍ' : 'NO').' | Remitos: '.($conRemitos ? 'SÍ' : 'NO'));
        $this->newLine();

        $this->table(
            ['tabla', 'filas'],
            collect($hijas)->map(static fn ($n, $t) => [$t, $n])->values()->all()
        );

        $this->table(
            ['id', 'fecha', 'comp', 'pv', 'cliente', 'total', 'cae', 'remito', 'origen'],
            array_map(static function (array $f): array {
                return [
                    $f['id'],
                    $f['fecha'],
                    $f['codigo'] ?? '',
                    $f['pv'] ?? '',
                    mb_substr((string) ($f['cliente'] ?? ''), 0, 28),
                    number_format((float) ($f['total'] ?? 0), 2, ',', '.'),
                    $f['cae'] ? 'sí' : '',
                    $f['remito_id'] ?? '',
                    $f['venta_origen_id'] ?? '',
                ];
            }, $filas)
        );

        if (! $ejecutar) {
            $this->comment('Dry-run. Nada se borró. Para persistir agregá --ejecutar');

            return self::SUCCESS;
        }

        $resultado = VentaPruebaBorradoSupport::eliminarVarias($ids, $conAnita, $conRemitos);
        foreach ($resultado['ok'] as $id) {
            $this->line("OK venta_id={$id}");
        }
        foreach ($resultado['fallidas'] as $id => $msg) {
            $this->error("FALLÓ venta_id={$id}: {$msg}");
        }

        $ok = count($resultado['ok']);
        $this->info("Eliminadas {$ok}/".count($ids).' ventas.');

        return $ok === count($ids) ? self::SUCCESS : self::FAILURE;
    }
}
