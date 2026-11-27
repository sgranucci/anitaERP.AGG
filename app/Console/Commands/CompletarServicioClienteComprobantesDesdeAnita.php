<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Servicio;
use App\Support\Compras\AnitaCompraClienteSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Completa comprobante_proveedor.servicio_cliente (nro. de cliente / medidor) leyendo
 * compra.com_cliente de Anita en las facturas que ya vinieron de Anita (anita_nro_interno).
 *
 * Solo toca servicio_cliente y proveedor_servicio_id, y solo donde hoy están vacíos.
 * Sin --ejecutar es análisis: no escribe nada. Lectura de Anita únicamente.
 * Laravel Auditing no audita corridas por consola: lo que se grabe acá no queda en `audits`.
 */
class CompletarServicioClienteComprobantesDesdeAnita extends Command
{
    protected $signature = 'compras:completar-servicio-cliente-anita
                            {--proveedor= : Código de proveedor ERP (por defecto, todos los que tienen medidores en su ficha)}
                            {--empresa= : id de empresa ERP}
                            {--desde= : Fecha comprobante desde (Y-m-d)}
                            {--hasta= : Fecha comprobante hasta (Y-m-d)}
                            {--incluir-fuera-de-ficha : También graba nros. de cliente que no están en la ficha del proveedor}
                            {--ejecutar : Graba. Sin esto es solo análisis (dry-run)}';

    protected $description = 'Completa servicio_cliente de facturas importadas de Anita desde compra.com_cliente (dry-run por defecto)';

    public function handle(): int
    {
        if (! AnitaCompraClienteSupport::disponible()) {
            $this->error('Este entorno (Ferli/Crown) no tiene com_cliente en la tabla compra de Anita.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $incluirFuera = (bool) $this->option('incluir-fuera-de-ficha');
        $this->info($ejecutar ? 'MODO EJECUTAR: se graba en comprobante_proveedor.' : 'DRY-RUN: no se escribe nada.');

        $proveedores = $this->proveedores();
        if ($proveedores->isEmpty()) {
            $this->warn('No hay proveedores para procesar.');

            return self::SUCCESS;
        }

        $tot = ['candidatas' => 0, 'a_actualizar' => 0, 'fuera_de_ficha' => 0, 'sin_nro_en_anita' => 0, 'sin_match_anita' => 0, 'grabadas' => 0];
        $fueraFicha = [];
        $detalleFuera = [];
        $filasTabla = [];

        foreach ($proveedores as $prov) {
            $candidatas = $this->candidatas((int) $prov->id);
            if ($candidatas->isEmpty()) {
                continue;
            }
            $tot['candidatas'] += $candidatas->count();

            try {
                $anita = $this->compraAnita($prov, $candidatas);
            } catch (Throwable $e) {
                $this->error('Proveedor '.$prov->codigo.': no se pudo leer Anita: '.$e->getMessage());

                continue;
            }

            $fichas = Proveedor_Servicio::query()->where('proveedor_id', $prov->id)->get(['id', 'empresa_id', 'cliente']);
            $plan = [];
            $stProv = ['a_actualizar' => 0, 'fuera_de_ficha' => 0, 'sin_nro' => 0, 'sin_match' => 0];

            foreach ($candidatas as $cp) {
                $fila = $this->filaAnitaDe($cp, $anita);
                if ($fila === null) {
                    $stProv['sin_match']++;

                    continue;
                }
                $cliente = AnitaCompraClienteSupport::extraerNroCliente($fila['com_cliente'] ?? '');
                if ($cliente === '') {
                    $stProv['sin_nro']++;

                    continue;
                }
                $ficha = $this->fichaPara($fichas, $cliente, (int) $cp->empresa_id);
                if ($ficha === null) {
                    $stProv['fuera_de_ficha']++;
                    $fueraFicha[$prov->codigo.' · '.$cliente] = ($fueraFicha[$prov->codigo.' · '.$cliente] ?? 0) + 1;
                    $detalleFuera[] = [
                        $prov->codigo,
                        (int) $cp->id,
                        (int) $cp->empresa_id,
                        substr((string) $cp->fechacomprobante, 0, 10),
                        trim($cp->letra.' '.$cp->sucursal.'-'.$cp->numerocomprobante),
                        $cliente,
                    ];
                    if (! $incluirFuera) {
                        continue;
                    }
                }
                $plan[] = ['id' => (int) $cp->id, 'cliente' => $cliente, 'ficha_id' => $ficha?->id, 'proveedor_servicio_id' => $cp->proveedor_servicio_id];
                $stProv['a_actualizar']++;
            }

            $tot['a_actualizar'] += $stProv['a_actualizar'];
            $tot['fuera_de_ficha'] += $stProv['fuera_de_ficha'];
            $tot['sin_nro_en_anita'] += $stProv['sin_nro'];
            $tot['sin_match_anita'] += $stProv['sin_match'];

            if ($ejecutar && $plan !== []) {
                $tot['grabadas'] += $this->grabar($plan);
            }

            $filasTabla[] = [
                $prov->codigo,
                mb_substr((string) $prov->nombre, 0, 28),
                $candidatas->count(),
                $stProv['a_actualizar'],
                $stProv['fuera_de_ficha'],
                $stProv['sin_nro'],
                $stProv['sin_match'],
            ];
        }

        $this->table(
            ['Prov.', 'Nombre', 'ERP sin nro. cliente', 'A completar', 'Fuera de ficha', 'Sin nro en Anita', 'Sin match Anita'],
            $filasTabla
        );
        $this->line('Total facturas ERP de Anita sin nro. de cliente: '.$tot['candidatas']);
        $this->line('A completar'.($incluirFuera ? ' (incluye fuera de ficha)' : ' (solo nros. que están en la ficha)').': '.$tot['a_actualizar']);
        $this->line('Nro. en Anita que NO está en la ficha del proveedor: '.$tot['fuera_de_ficha'].($incluirFuera ? '' : ' (no se tocan sin --incluir-fuera-de-ficha)'));
        $this->line('Sin nro. de cliente en Anita (solo hora de carga / vacío): '.$tot['sin_nro_en_anita']);
        $this->line('Sin coincidencia en Anita (nro. interno / letra / sucursal / número): '.$tot['sin_match_anita']);

        if ($fueraFicha !== []) {
            arsort($fueraFicha);
            $this->line('Nros. fuera de ficha (proveedor · valor: cantidad), primeros 30:');
            foreach (array_slice($fueraFicha, 0, 30, true) as $k => $n) {
                $this->line('  '.$k.': '.$n);
            }
        }

        if ($detalleFuera !== []) {
            $this->line('Facturas con nro. fuera de ficha:');
            $this->table(['Prov.', 'ID ERP', 'Empresa', 'Fecha', 'Comprobante', 'Nro. cliente Anita'], $detalleFuera);
        }

        if ($ejecutar) {
            $this->info('Grabadas: '.$tot['grabadas'].' (contra '.$tot['a_actualizar'].' previstas).');
        } else {
            $this->comment('Dry-run: no se escribió nada. Para grabar, repetir con --ejecutar y los mismos parámetros.');
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Proveedor>
     */
    private function proveedores()
    {
        $codigo = trim((string) $this->option('proveedor'));
        if ($codigo !== '') {
            return Proveedor::query()->where('codigo', $codigo)->get(['id', 'codigo', 'nombre']);
        }

        $ids = Proveedor_Servicio::query()->distinct()->pluck('proveedor_id');

        return Proveedor::query()->whereIn('id', $ids)->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function candidatas(int $proveedorId)
    {
        $q = DB::table('comprobante_proveedor')
            ->where('proveedor_id', $proveedorId)
            ->whereNotNull('anita_nro_interno')
            ->where(function ($w) {
                $w->whereNull('servicio_cliente')->orWhere('servicio_cliente', '');
            });

        if ((int) $this->option('empresa') > 0) {
            $q->where('empresa_id', (int) $this->option('empresa'));
        }
        if (trim((string) $this->option('desde')) !== '') {
            $q->whereDate('fechacomprobante', '>=', (string) $this->option('desde'));
        }
        if (trim((string) $this->option('hasta')) !== '') {
            $q->whereDate('fechacomprobante', '<=', (string) $this->option('hasta'));
        }

        return $q->get(['id', 'empresa_id', 'proveedor_servicio_id', 'letra', 'sucursal', 'numerocomprobante', 'anita_nro_interno', 'fechacomprobante']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $candidatas
     * @return array<int, list<array<string, mixed>>> por com_nro_interno
     */
    private function compraAnita(Proveedor $prov, $candidatas): array
    {
        $min = (string) $candidatas->min('fechacomprobante');
        $max = (string) $candidatas->max('fechacomprobante');
        $where = " WHERE com_proveedor = '".str_pad(trim((string) $prov->codigo), 6, '0', STR_PAD_LEFT)."'"
            .' AND com_fecha >= '.(int) str_replace('-', '', substr($min, 0, 10))
            .' AND com_fecha <= '.(int) str_replace('-', '', substr($max, 0, 10));

        $raw = (new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'compra',
            'campos' => 'com_nro_interno, com_letra, com_sucursal, com_nro, com_cliente',
            'whereArmado' => $where,
        ]);

        $porInterno = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $f = (array) $fila;
            $porInterno[(int) ($f['com_nro_interno'] ?? 0)][] = $f;
        }

        return $porInterno;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $anita
     * @return array<string, mixed>|null
     */
    private function filaAnitaDe(object $cp, array $anita): ?array
    {
        $letra = strtoupper(substr(trim((string) $cp->letra), 0, 1));
        foreach ($anita[(int) $cp->anita_nro_interno] ?? [] as $f) {
            $mismaLetra = strtoupper(substr(trim((string) ($f['com_letra'] ?? '')), 0, 1)) === $letra;
            if ($mismaLetra
                && (int) ($f['com_sucursal'] ?? -1) === (int) $cp->sucursal
                && (int) ($f['com_nro'] ?? -1) === (int) $cp->numerocomprobante) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Proveedor_Servicio>  $fichas
     */
    private function fichaPara($fichas, string $cliente, int $empresaId): ?Proveedor_Servicio
    {
        $coinciden = $fichas->filter(fn ($f) => mb_strtolower(trim((string) $f->cliente)) === mb_strtolower($cliente));
        if ($coinciden->isEmpty()) {
            return null;
        }

        return $coinciden->first(fn ($f) => (int) $f->empresa_id === $empresaId)
            ?? $coinciden->first(fn ($f) => $f->empresa_id === null)
            ?? $coinciden->first();
    }

    /**
     * @param  list<array{id: int, cliente: string, ficha_id: ?int, proveedor_servicio_id: mixed}>  $plan
     */
    private function grabar(array $plan): int
    {
        $n = 0;
        foreach ($plan as $p) {
            $set = ['servicio_cliente' => mb_substr($p['cliente'], 0, 255)];
            if ($p['ficha_id'] !== null && empty($p['proveedor_servicio_id'])) {
                $set['proveedor_servicio_id'] = $p['ficha_id'];
            }
            $n += DB::table('comprobante_proveedor')
                ->where('id', $p['id'])
                ->where(function ($w) {
                    $w->whereNull('servicio_cliente')->orWhere('servicio_cliente', '');
                })
                ->update($set);
        }

        return $n;
    }
}
