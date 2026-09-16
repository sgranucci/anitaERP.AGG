<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Uif\Cliente_Uif;
use App\Repositories\Uif\Cliente_UifRepository;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SincronizarClienteUifDesdeAnita extends Command
{
    protected $signature = 'cliente-uif:sincronizar-anita
                            {--codigo= : Importar/actualizar un cliente por inroclienteid Anita}
                            {--desde= : ID inicial del rango (inroclienteid Anita, o cliente_uif.id con --por-id-local)}
                            {--hasta= : ID final del rango (inclusive)}
                            {--por-id-local : Interpretar --desde/--hasta como cliente_uif.id del ERP}
                            {--limite=200 : Máximo de clientes en lista remota (0 = todos, sincronización total)}
                            {--force : Confirma sync masivo o por rango (sin esto se aborta)}
                            {--usuario= : ID usuario para creousuario_id (default: primer usuario)}';

    protected $description = 'Importa o actualiza clientes UIF desde Anita. En update no pisa geo ya cargada en el ERP. Sync masivo requiere --force.';

    public function handle(Cliente_UifRepositoryInterface $clienteUifRepository): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        /** @var Cliente_UifRepository $clienteUifRepository */
        $codigo = $this->option('codigo');
        $codigo = is_string($codigo) ? trim($codigo) : '';

        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        $tieneDesde = $desde !== null && $desde !== '';
        $tieneHasta = $hasta !== null && $hasta !== '';

        if ($tieneDesde xor $tieneHasta) {
            $this->error('Debe indicar --desde y --hasta juntos.');

            return self::FAILURE;
        }

        $esUno = $codigo !== '';
        $esRangoOMasivo = ! $esUno;
        if ($esRangoOMasivo && ! (bool) $this->option('force')) {
            $this->error('Sync masivo/rango bloqueado: el ERP es fuente de verdad en geo.');
            $this->line('Use --codigo=N para un cliente, o --force si realmente necesita un lote.');
            $this->line('Nota: en update Anita solo completa geo vacía; no pisa país/provincia/localidad ya cargados.');

            return self::FAILURE;
        }

        try {
            if ($esUno) {
                $this->comment('Geo ERP ya cargada no se pisa; Anita solo completa vacíos.');

                return $this->sincronizarUno($clienteUifRepository, (int) $codigo);
            }

            if ($tieneDesde && $tieneHasta) {
                $this->warn('Sync por rango con --force. Geo ERP cargada no se pisa.');

                return $this->sincronizarRango(
                    $clienteUifRepository,
                    (int) $desde,
                    (int) $hasta,
                    (bool) $this->option('por-id-local')
                );
            }

            $this->warn('Sync masivo con --force. Geo ERP cargada no se pisa.');
            $limite = $this->limiteSincronizacion();
            $mensaje = $limite > 0
                ? "Sincronizando clientes UIF desde Anita (primeros {$limite} de la lista remota)…"
                : 'Sincronizando todos los clientes UIF desde Anita (lista remota completa)…';
            $this->info($mensaje);
            $clienteUifRepository->sincronizarConAnita($limite > 0 ? $limite : null);
            $this->info('Sincronización finalizada.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function sincronizarUno(Cliente_UifRepositoryInterface $repository, int $inroclienteid): int
    {
        if ($inroclienteid <= 0) {
            $this->error('codigo inválido.');

            return self::FAILURE;
        }

        $this->info("Importando cliente UIF Anita inroclienteid={$inroclienteid}…");
        $repository->traerRegistroDeAnita($inroclienteid);
        $this->info('Cliente procesado.');

        return self::SUCCESS;
    }

    private function sincronizarRango(
        Cliente_UifRepositoryInterface $repository,
        int $desde,
        int $hasta,
        bool $porIdLocal
    ): int {
        if ($desde <= 0 || $hasta <= 0) {
            $this->error('desde y hasta deben ser enteros positivos.');

            return self::FAILURE;
        }

        if ($desde > $hasta) {
            $this->error('desde no puede ser mayor que hasta.');

            return self::FAILURE;
        }

        $idsAnita = $porIdLocal
            ? $this->inroclienteidsDesdeRangoLocal($desde, $hasta)
            : range($desde, $hasta);

        if ($idsAnita === []) {
            $this->warn('No hay clientes UIF en el rango indicado.');

            return self::SUCCESS;
        }

        $etiqueta = $porIdLocal
            ? "cliente_uif.id {$desde}–{$hasta}"
            : "inroclienteid {$desde}–{$hasta}";

        $this->info("Sincronizando {$etiqueta} ({$this->conteo($idsAnita)} clientes). Puede tardar varios minutos…");

        $ok = 0;
        $err = 0;
        $total = count($idsAnita);

        foreach ($idsAnita as $i => $inroclienteid) {
            try {
                $repository->traerRegistroDeAnita($inroclienteid);
                $ok++;
            } catch (Throwable $e) {
                $err++;
                $this->warn("Error inroclienteid {$inroclienteid}: {$e->getMessage()}");
            }

            if (($i + 1) % 10 === 0 || ($i + 1) === $total) {
                $this->line('['.date('H:i:s')."] Procesados ".($i + 1)."/{$total}");
            }
        }

        $this->info("Finalizado: ok={$ok}, err={$err}.");

        return $err > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function inroclienteidsDesdeRangoLocal(int $desde, int $hasta): array
    {
        return Cliente_Uif::query()
            ->whereBetween('id', [$desde, $hasta])
            ->whereNotNull('inroclienteid')
            ->where('inroclienteid', '>', 0)
            ->orderBy('id')
            ->pluck('inroclienteid')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function conteo(array $ids): int
    {
        return count($ids);
    }

    private function limiteSincronizacion(): int
    {
        $limite = $this->option('limite');
        if ($limite === null || $limite === '') {
            return 200;
        }

        return (int) $limite;
    }
}
