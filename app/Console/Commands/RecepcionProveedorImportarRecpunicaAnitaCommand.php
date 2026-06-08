<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_ParteUnica;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecpunicaAnitaBridgeSupport;
use Illuminate\Console\Command;

class RecepcionProveedorImportarRecpunicaAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:importar-recpunica-anita
                            {--desde=2025-01-01 : Fecha ISO; filtra recepciones importadas desde recepmae.recm_fecha}
                            {--nro= : Filtrar por recpu_nro (numerorecepcion)}
                            {--sucursal= : Filtrar por recpu_sucursal (código empresa)}
                            {--dry-run : Solo muestra contadores sin grabar}';

    protected $description = 'Importa recpunica desde Anita hacia articulo_parte_unica y recepcion_proveedor_parte_unica';

    public function handle(): int
    {
        $nro = $this->option('nro');
        $sucursal = $this->option('sucursal');
        $desdeIso = (string) $this->option('desde');
        $dryRun = (bool) $this->option('dry-run');

        $fechaDesdeAnita = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($desdeIso);
        $clavesRecepcion = $this->clavesRecepcionDesdeFecha($fechaDesdeAnita, $sucursal, $nro);

        if ($clavesRecepcion === []) {
            $this->warn('Sin recepciones ERP/Anita para el rango indicado. Ejecute recepcion-proveedor:importar-desde-anita primero.');

            return self::SUCCESS;
        }

        $this->info('Claves recepción en scope: '.count($clavesRecepcion));

        $importados = 0;
        $omitidos = 0;
        $sinRecepcion = 0;
        $filasAnita = 0;

        $procesar = function (array $filas) use ($clavesRecepcion, $dryRun, &$importados, &$omitidos, &$sinRecepcion, &$filasAnita) {
            foreach ($filas as $fila) {
                $filasAnita++;
                $tipo = trim((string) ($fila->recpu_tipo ?? 'COM'));
                $letra = trim((string) ($fila->recpu_letra ?? 'X'));
                $suc = (int) ($fila->recpu_sucursal ?? 0);
                $nroRec = (int) ($fila->recpu_nro ?? 0);
                $linea = (int) ($fila->recpu_linea ?? 0);
                $numeroparte = (int) ($fila->recpu_id ?? 0);
                $sku = trim((string) ($fila->recpu_articulo ?? ''));

                $clave = $suc.'-'.$nroRec;
                if (! isset($clavesRecepcion[$clave])) {
                    continue;
                }

                if ($numeroparte <= 0) {
                    $omitidos++;

                    continue;
                }

                $articulo = Articulo::query()->where('sku', ltrim($sku, '0'))->first();
                if ($articulo) {
                    $apuExistente = Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->first();
                    if ($apuExistente) {
                        if ((int) $apuExistente->articulo_id !== (int) $articulo->id && ! $dryRun) {
                            $apuExistente->update(['articulo_id' => $articulo->id]);
                        }
                    } elseif (! $dryRun) {
                        Articulo_ParteUnica::create([
                            'articulo_id' => $articulo->id,
                            'numeroparte' => $numeroparte,
                        ]);
                    }
                }

                if (Recepcion_Proveedor_ParteUnica::query()->where('numeroparte', $numeroparte)->exists()) {
                    $omitidos++;

                    continue;
                }

                $empresaIds = Empresa::query()->where('codigo', (string) $suc)->pluck('id')->all();
                $recepcion = Recepcion_Proveedor::query()
                    ->where('numerorecepcion', $nroRec)
                    ->where(function ($q) use ($suc, $empresaIds) {
                        $q->where('anita_sucursal', $suc);
                        if ($empresaIds !== []) {
                            $q->orWhereIn('empresa_id', $empresaIds);
                        }
                    })
                    ->first();

                if (! $recepcion) {
                    $sinRecepcion++;

                    continue;
                }

                $lineaRecep = $recepcion->recepcion_proveedor_articulos()
                    ->where(function ($q) use ($linea) {
                        $q->where('penvp_orden', $linea)->orWhere('orden', $linea);
                    })
                    ->first();

                if (! $lineaRecep && $articulo) {
                    $lineaRecep = $recepcion->recepcion_proveedor_articulos()
                        ->where('articulo_id', $articulo->id)
                        ->first();
                }

                if (! $lineaRecep) {
                    $sinRecepcion++;

                    continue;
                }

                if (! $dryRun) {
                    Recepcion_Proveedor_ParteUnica::create([
                        'recepcion_proveedor_id' => $recepcion->id,
                        'recepcion_proveedor_articulo_id' => $lineaRecep->id,
                        'numeroparte' => $numeroparte,
                    ]);

                    $recepcion->update([
                        'anita_tipo' => $tipo,
                        'anita_letra' => $letra,
                        'anita_sucursal' => $suc,
                        'anita_nro' => $nroRec,
                    ]);
                }

                $importados++;
            }
        };

        if ($nro !== null && $nro !== '' && $sucursal !== null && $sucursal !== '') {
            $filas = RecepcionProveedorAnitaImportSupport::listarRecpunicaPorRecepcion('COM', 'X', (int) $sucursal, (int) $nro);
            $procesar($filas);
        } else {
            $this->info('Consultando recpunica COM/X en Anita…');
            $filas = RecpunicaAnitaBridgeSupport::listarDesdeAnita(" WHERE recpu_tipo = 'COM' AND recpu_letra = 'X'");
            $procesar($filas);
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Filas Anita evaluadas', $filasAnita],
            ['Vinculadas recepción', $importados],
            ['Omitidas', $omitidos],
            ['Sin recepción/línea ERP', $sinRecepcion],
        ]);

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function clavesRecepcionDesdeFecha(int $fechaDesdeAnita, mixed $sucursal, mixed $nro): array
    {
        $claves = [];

        $query = Recepcion_Proveedor::query()
            ->where('fecha', '>=', RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($fechaDesdeAnita))
            ->where('anita_tipo', 'COM');

        if ($sucursal !== null && $sucursal !== '') {
            $query->where('anita_sucursal', (int) $sucursal);
        }
        if ($nro !== null && $nro !== '') {
            $query->where('numerorecepcion', (int) $nro);
        }

        foreach ($query->get(['anita_sucursal', 'numerorecepcion']) as $row) {
            $suc = (int) ($row->anita_sucursal ?? 0);
            $num = (int) ($row->numerorecepcion ?? 0);
            if ($suc > 0 && $num > 0) {
                $claves[$suc.'-'.$num] = true;
            }
        }

        if ($claves !== []) {
            return $claves;
        }

        $cabeceras = RecepcionProveedorAnitaImportSupport::listarRecepmae($fechaDesdeAnita);
        foreach ($cabeceras as $cab) {
            $suc = (int) ($cab->recm_sucursal ?? 0);
            $num = (int) ($cab->recm_nro ?? 0);
            if ($sucursal !== null && $sucursal !== '' && $suc !== (int) $sucursal) {
                continue;
            }
            if ($nro !== null && $nro !== '' && $num !== (int) $nro) {
                continue;
            }
            if ($suc > 0 && $num > 0) {
                $claves[$suc.'-'.$num] = true;
            }
        }

        return $claves;
    }
}
