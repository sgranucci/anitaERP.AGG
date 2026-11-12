<?php

namespace App\Console\Commands;

use App\Services\Contable\ConciliacionBancariaExtractoExcelImportService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExcelReferenciaSupport;
use Illuminate\Console\Command;

class ConciliacionBancariaImportarExtractoExcelCommand extends Command
{
    protected $signature = 'contable:conciliacion-bancaria-importar-extracto-excel
                            {ruta : Ruta al Excel Contaduría}
                            {--empresa= : ID empresa}
                            {--cuentacaja= : ID cuenta de caja}
                            {--codigo-cuentacaja= : Código Anita (ej. 1112)}
                            {--no-asignar-ib : No setear cuenta_interbanking si está vacía}';

    protected $description = 'Importa la solapa EXTRACTO del Excel Contaduría a interbanking_movimiento';

    public function handle(ConciliacionBancariaExtractoExcelImportService $service): int
    {
        $ruta = (string) $this->argument('ruta');
        if (! is_readable($ruta)) {
            $this->error('No se puede leer: '.$ruta);

            return self::FAILURE;
        }

        $codigo = trim((string) ($this->option('codigo-cuentacaja') ?: ''));
        if ($codigo === '') {
            $codigo = (string) (ConciliacionBancariaExcelReferenciaSupport::codigoCuentacajaDesdeNombreArchivo($ruta) ?? '');
        }

        $empresaId = (int) ($this->option('empresa') ?: 0);
        if ($empresaId <= 0 && $codigo !== '') {
            $empresaId = match ($codigo) {
                '127', '1112' => 1,
                '226', '2112' => 2,
                '326', '3112' => 3,
                default => 0,
            };
        }
        if ($empresaId <= 0) {
            $this->error('Indicá --empresa= (o un archivo con código 127/226/326/1112/2112/3112).');

            return self::FAILURE;
        }

        try {
            $res = $service->importar(
                $ruta,
                $empresaId,
                (int) ($this->option('cuentacaja') ?: 0) ?: null,
                $codigo !== '' ? $codigo : null,
                ! $this->option('no-asignar-ib'),
                true,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Importado %s → cuenta IB %s · %d fila(s)%s',
            $res['archivo'],
            $res['account_number'],
            $res['filas'],
            $res['cuenta_interbanking_asignada'] ? ' · IB asignada a cuentacaja' : '',
        ));

        return self::SUCCESS;
    }
}
