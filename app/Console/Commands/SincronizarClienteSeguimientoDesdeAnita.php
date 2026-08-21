<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Articulo_Suspendido;
use App\Models\Ventas\Cliente_Seguimiento;
use App\Repositories\Ventas\ClienteRepository;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Repara el historial de seguimiento (movscli) y los artículos suspendidos (stksuspcli)
 * de Anita: agrega los renglones que faltan y saca las copias que dejaron los imports
 * repetidos. No toca las filas cargadas a mano en el ERP.
 */
class SincronizarClienteSeguimientoDesdeAnita extends Command
{
    protected $signature = 'cliente:sincronizar-seguimiento-anita
                            {--codigo= : Procesar un solo cliente por código Anita (clim_cliente)}
                            {--usuario= : ID usuario a grabar en creousuario_id (default: primer usuario)}
                            {--sin-seguimiento : No procesar movscli}
                            {--sin-articulos : No procesar stksuspcli}
                            {--purgar-colgados : Borra las filas cuyo cliente_id ya no existe en la tabla cliente}
                            {--purgar-mal-asignados : Borra el seguimiento que en Anita pertenece a otro cliente}
                            {--purgar-desactualizados : Borra versiones viejas de notas editadas o dadas de baja en Anita (previas a la columna anita_orden)}
                            {--ejecutar : Persiste los cambios. Sin este flag solo informa (dry-run)}';

    protected $description = 'Sincroniza seguimiento (movscli) y artículos suspendidos (stksuspcli) de clientes desde Anita, sin duplicar. Dry-run por defecto.';

    public function handle(ClienteRepository $clienteRepository): int
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            $this->error('Este comando aplica solo a EL BIERZO (movscli/stksuspcli). EMPRESA actual: '.config('app.empresa'));

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $simular = ! $ejecutar;

        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $this->info($simular
            ? 'DRY-RUN: no se escribe nada. Agregue --ejecutar para persistir.'
            : 'EJECUTANDO: se van a persistir los cambios.');

        $codigo = $this->option('codigo');
        $codigo = is_string($codigo) ? ltrim(trim($codigo), '0') : '';

        try {
            $seguimientoAnita = $this->option('sin-seguimiento')
                ? []
                : $this->leerAgrupadoPorCliente('movscli', 'movsc_cliente', 'movsc_cliente,movsc_orden,movsc_fecha,movsc_observacion');

            $articulosAnita = $this->option('sin-articulos')
                ? []
                : $this->leerAgrupadoPorCliente('stksuspcli', 'stksc_cliente', 'stksc_cliente,stksc_articulo');
        } catch (\Throwable $e) {
            $this->error('Error leyendo Anita: '.$e->getMessage());

            return self::FAILURE;
        }

        // array_keys devuelve int cuando la clave es numérica: comparar siempre como string
        $codigos = array_values(array_unique(array_map('strval', array_merge(
            array_keys($seguimientoAnita),
            array_keys($articulosAnita)
        ))));
        sort($codigos, SORT_NATURAL);

        if ($codigo !== '') {
            $codigos = array_values(array_filter($codigos, static fn ($c) => $c === $codigo));
            if ($codigos === []) {
                $this->warn("El cliente {$codigo} no tiene renglones en movscli ni en stksuspcli.");

                return self::SUCCESS;
            }
        }

        $total = [
            'clientes' => 0,
            'sin_cliente_erp' => 0,
            'seg_creados' => 0,
            'seg_actualizados' => 0,
            'seg_duplicados' => 0,
            'art_creados' => 0,
            'art_duplicados' => 0,
            'art_sin_articulo' => 0,
        ];
        $detalle = [];

        $barra = $this->output->createProgressBar(count($codigos));
        $barra->start();

        foreach ($codigos as $cod) {
            $barra->advance();

            $cliente = Cliente::query()->where('codigo', $cod)->first();
            if ($cliente === null) {
                $total['sin_cliente_erp']++;

                continue;
            }

            $total['clientes']++;

            $seg = ['creados' => 0, 'actualizados' => 0, 'duplicados_borrados' => 0];
            $art = ['creados' => 0, 'duplicados_borrados' => 0, 'sin_articulo' => 0];

            if (isset($seguimientoAnita[$cod])) {
                $seg = $clienteRepository->sincronizarSeguimientoDesdeAnita(
                    $cliente,
                    $seguimientoAnita[$cod],
                    $usuarioId,
                    $simular
                );
            }

            if (isset($articulosAnita[$cod])) {
                $art = $clienteRepository->sincronizarArticuloSuspendidoDesdeAnita(
                    $cliente,
                    $articulosAnita[$cod],
                    $usuarioId,
                    $simular
                );
            }

            $total['seg_creados'] += $seg['creados'];
            $total['seg_actualizados'] += $seg['actualizados'];
            $total['seg_duplicados'] += $seg['duplicados_borrados'];
            $total['art_creados'] += $art['creados'];
            $total['art_duplicados'] += $art['duplicados_borrados'];
            $total['art_sin_articulo'] += $art['sin_articulo'];

            if ($seg['creados'] || $seg['duplicados_borrados'] || $art['creados'] || $art['duplicados_borrados']) {
                $detalle[] = [
                    $cod,
                    $cliente->nombre,
                    $seg['creados'],
                    $seg['duplicados_borrados'],
                    $art['creados'],
                    $art['duplicados_borrados'],
                ];
            }
        }

        $barra->finish();
        $this->newLine(2);

        if ($detalle !== [] && (count($detalle) <= 40 || $codigo !== '')) {
            $this->table(
                ['Código', 'Cliente', 'Seg. a crear', 'Seg. duplicados', 'Art. a crear', 'Art. duplicados'],
                $detalle
            );
        } elseif ($detalle !== []) {
            $this->line('Clientes con diferencias: '.count($detalle).' (use --codigo para ver el detalle de uno).');
        }

        $this->info("Clientes procesados: {$total['clientes']} (sin ficha en el ERP: {$total['sin_cliente_erp']}).");
        $this->info("Seguimiento — a crear: {$total['seg_creados']}; a actualizar (texto/orden Anita): {$total['seg_actualizados']}; duplicados a borrar: {$total['seg_duplicados']}.");
        $this->info("Artículos suspendidos — a crear: {$total['art_creados']}; duplicados a borrar: {$total['art_duplicados']}; SKU inexistente en el ERP: {$total['art_sin_articulo']}.");

        if ($codigo === '') {
            $this->purgarColgados($simular);
            $this->purgarMalAsignados($seguimientoAnita, $simular);
            $this->purgarDesactualizados($simular);
            $this->purgarArticulosSuspendidosSinAnita($articulosAnita, $simular);
        }

        if ($simular) {
            $this->warn('DRY-RUN: no se escribió nada. Revise los números y vuelva a correr con --ejecutar.');
        }

        return self::SUCCESS;
    }

    /**
     * Filas que quedaron apuntando a un cliente_id inexistente: no se ven en ninguna
     * pantalla y el import las dejó al recrearse la tabla cliente con otros ids.
     */
    private function purgarColgados(bool $simular): void
    {
        $modelos = [
            'seguimiento' => Cliente_Seguimiento::class,
            'artículos suspendidos' => Cliente_Articulo_Suspendido::class,
        ];

        foreach ($modelos as $etiqueta => $clase) {
            $query = $clase::query()->whereNotIn(
                'cliente_id',
                Cliente::query()->select('id')
            );

            $cantidad = (clone $query)->count();
            if ($cantidad === 0) {
                continue;
            }

            if (! $this->option('purgar-colgados')) {
                $this->warn("Hay {$cantidad} filas de {$etiqueta} con cliente_id inexistente. Use --purgar-colgados para borrarlas.");

                continue;
            }

            if ($simular) {
                $this->warn("A borrar por cliente_id inexistente ({$etiqueta}): {$cantidad}.");

                continue;
            }

            $borradas = EloquentAuditDeleteSupport::each($query);
            $this->info("Borradas por cliente_id inexistente ({$etiqueta}): {$borradas}.");
        }
    }

    /**
     * Seguimiento que quedó visible en la ficha de un cliente al que no le corresponde:
     * el cliente no tiene renglones en movscli y el texto es idéntico al de otro cliente Anita.
     *
     * @param  array<string, list<object>>  $seguimientoAnita
     */
    private function purgarMalAsignados(array $seguimientoAnita, bool $simular): void
    {
        if ($seguimientoAnita === []) {
            return;
        }

        $porTexto = [];
        foreach ($seguimientoAnita as $codigoAnita => $filas) {
            foreach ($filas as $fila) {
                $fecha = $this->fechaAnita($fila->movsc_fecha ?? null);
                if ($fecha === null) {
                    continue;
                }
                $porTexto[$fecha.'|'.trim((string) ($fila->movsc_observacion ?? ''))][] = (string) $codigoAnita;
            }
        }

        $idsConAnita = Cliente::query()
            ->whereIn('codigo', array_map('strval', array_keys($seguimientoAnita)))
            ->pluck('id')
            ->all();

        $sospechosas = Cliente_Seguimiento::query()
            ->whereNotIn('cliente_id', $idsConAnita)
            ->whereHas('clientes')
            ->with('clientes:id,codigo')
            ->get();

        $aBorrar = $sospechosas->filter(function (Cliente_Seguimiento $fila) use ($porTexto) {
            $clave = $fila->fecha.'|'.trim((string) $fila->observacion);
            $duenios = $porTexto[$clave] ?? null;

            return $duenios !== null
                && ! in_array((string) $fila->clientes?->codigo, $duenios, true);
        });

        if ($aBorrar->isEmpty()) {
            return;
        }

        if (! $this->option('purgar-mal-asignados')) {
            $this->warn("Hay {$aBorrar->count()} filas de seguimiento que en Anita son de otro cliente. Use --purgar-mal-asignados para borrarlas.");

            return;
        }

        if ($simular) {
            $this->warn("A borrar por pertenecer a otro cliente (seguimiento): {$aBorrar->count()}.");

            return;
        }

        $aBorrar->each(fn (Cliente_Seguimiento $fila) => $fila->delete());
        $this->info("Borradas por pertenecer a otro cliente (seguimiento): {$aBorrar->count()}.");
    }

    /**
     * Filas del import viejo que no matchean ningún renglón de Anita: quedaron cuando la nota
     * se editó o se dio de baja en Anita y el ERP no tenía forma de reconocerla (sin anita_orden).
     * Solo alcanza a lo cargado antes de hoy: una carga manual nueva nunca entra acá.
     */
    private function purgarDesactualizados(bool $simular): void
    {
        $clientesConAnita = Cliente_Seguimiento::query()
            ->whereNotNull('anita_orden')
            ->select('cliente_id')
            ->distinct();

        $query = Cliente_Seguimiento::query()
            ->whereNull('anita_orden')
            ->whereIn('cliente_id', $clientesConAnita)
            ->where('created_at', '<', now()->startOfDay());

        $cantidad = (clone $query)->count();
        if ($cantidad === 0) {
            return;
        }

        if (! $this->option('purgar-desactualizados')) {
            $this->warn("Hay {$cantidad} filas de seguimiento sin correlato en Anita (versiones viejas de notas editadas). Use --purgar-desactualizados para borrarlas.");

            return;
        }

        if ($simular) {
            $this->warn("A borrar por estar desactualizadas contra Anita (seguimiento): {$cantidad}.");

            return;
        }

        $borradas = EloquentAuditDeleteSupport::each($query);
        $this->info("Borradas por estar desactualizadas contra Anita (seguimiento): {$borradas}.");
    }

    /**
     * Artículos suspendidos que no existen en `stksuspcli`: quedaron pegados al cliente
     * equivocado cuando la tabla cliente se recreó con otros ids, o son copias repetidas.
     * Solo alcanza a lo cargado antes de hoy.
     *
     * @param  array<string, list<object>>  $articulosAnita
     */
    private function purgarArticulosSuspendidosSinAnita(array $articulosAnita, bool $simular): void
    {
        if ($articulosAnita === []) {
            return;
        }

        $paresAnita = [];
        foreach ($articulosAnita as $codigoAnita => $filas) {
            foreach ($filas as $fila) {
                $sku = ltrim(trim((string) ($fila->stksc_articulo ?? '')), '0');
                if ($sku !== '') {
                    $paresAnita[$codigoAnita.'|'.$sku] = true;
                }
            }
        }

        $vistos = [];
        $aBorrar = Cliente_Articulo_Suspendido::query()
            ->where('created_at', '<', now()->startOfDay())
            ->whereHas('clientes')
            ->whereHas('articulos')
            ->with(['clientes:id,codigo', 'articulos:id,sku'])
            ->orderBy('id')
            ->get()
            ->filter(function (Cliente_Articulo_Suspendido $fila) use ($paresAnita, &$vistos) {
                $par = ltrim(trim((string) $fila->clientes?->codigo), '0')
                    .'|'.ltrim(trim((string) $fila->articulos?->sku), '0');

                if (! isset($paresAnita[$par])) {
                    return true;
                }

                if (isset($vistos[$par])) {
                    return true;
                }
                $vistos[$par] = true;

                return false;
            });

        if ($aBorrar->isEmpty()) {
            return;
        }

        if (! $this->option('purgar-desactualizados')) {
            $this->warn("Hay {$aBorrar->count()} filas de artículos suspendidos sin correlato en Anita. Use --purgar-desactualizados para borrarlas.");

            return;
        }

        if ($simular) {
            $this->warn("A borrar por no existir en Anita (artículos suspendidos): {$aBorrar->count()}.");

            return;
        }

        $aBorrar->each(fn (Cliente_Articulo_Suspendido $fila) => $fila->delete());
        $this->info("Borradas por no existir en Anita (artículos suspendidos): {$aBorrar->count()}.");
    }

    private function fechaAnita($valor): ?string
    {
        $valor = trim((string) $valor);

        return preg_match('/^\d{8}$/', $valor) === 1
            ? substr($valor, 0, 4).'-'.substr($valor, 4, 2).'-'.substr($valor, 6, 2)
            : null;
    }

    /**
     * @return array<string, list<object>>
     */
    private function leerAgrupadoPorCliente(string $tabla, string $campoCliente, string $campos): array
    {
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas((string) $api->apiCall([
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => 'ventas',
            'campos' => $campos,
            'whereArmado' => ' ',
        ]));

        $agrupado = [];
        foreach ($filas as $fila) {
            $cod = ltrim(trim((string) ($fila->{$campoCliente} ?? '')), '0');
            if ($cod === '') {
                continue;
            }
            $agrupado[$cod][] = $fila;
        }

        $this->line(sprintf('Anita %s: %d renglones en %d clientes.', $tabla, count($filas), count($agrupado)));

        return $agrupado;
    }
}
