<?php

namespace App\Services\Ventas\Vianda;

use App\Models\Stock\Articulo;
use App\Support\Ventas\Vianda\ViandaPrecioSupport;
use App\Models\Ventas\ConfiguracionTerminalVianda;
use App\Models\Ventas\ViandaConsumo;
use App\Models\Ventas\ViandaConsumoLinea;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaUsuario;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\Vianda\ViandaConsumoLimiteDiarioSupport;
use App\Support\Ventas\ViandaDiaSemanaSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class ViandaProcesoService
{
    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly ViandaStockService $stockService,
        private readonly ViandaVoucherService $voucherService,
    ) {
    }

    public function resolverTerminal(Request $request): ?ConfiguracionTerminalVianda
    {
        $identificador = GastronomiaIdentificadorPc::resolver($request);

        return ConfiguracionTerminalVianda::resolverPorTerminal($identificador);
    }

    /**
     * @return array{jornada_abierta:bool,jornada_id:?int,fecha_jornada:?string,mensaje:?string}
     */
    public function estadoJornada(ConfiguracionTerminalVianda $cfg): array
    {
        $jornada = $this->jornadaService->jornadaAbierta((int) $cfg->empresa_id);

        return [
            'jornada_abierta' => $jornada !== null,
            'jornada_id' => $jornada?->id,
            'fecha_jornada' => $jornada?->fecha_jornada?->format('d/m/Y'),
            'mensaje' => $jornada === null
                ? 'No hay jornada de gastronomía abierta para '.($cfg->empresa->nombre ?? 'esta empresa')
                    .'. Abra la jornada en Ventas → Gastronomía → Jornada para operar viandas.'
                : null,
        ];
    }

    public function autenticar(string $codigo, string $password, ?int $empresaId = null): ViandaUsuario
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Debe indicar el código de usuario de vianda.');
        }
        if (trim($password) === '') {
            throw new InvalidArgumentException('Debe indicar la clave.');
        }

        // El código Anita (usuv_usuario) se repite entre empresas: el login se restringe a la
        // empresa de la terminal para no autenticar un usuario de otra empresa.
        $usuario = ViandaUsuario::query()
            ->where('codigo_usuario', $codigo)
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('id')
            ->first();

        if ($usuario === null) {
            throw new InvalidArgumentException('Usuario de vianda no encontrado.');
        }
        if ($usuario->estado !== 'A') {
            throw new InvalidArgumentException('El usuario de vianda está inactivo.');
        }

        $claveAlmacenada = (string) ($usuario->password ?? '');
        if ($claveAlmacenada === '') {
            throw new InvalidArgumentException('El usuario no tiene clave configurada.');
        }

        $claveOk = str_starts_with($claveAlmacenada, '$2y$')
            ? Hash::check($password, $claveAlmacenada)
            : hash_equals($claveAlmacenada, trim($password));

        if (! $claveOk) {
            throw new InvalidArgumentException('Clave incorrecta.');
        }

        return $usuario;
    }

    /**
     * Menú del día del tipo de menú asignado al usuario.
     *
     * @return array{
     *   dia:array{numero:int,etiqueta:string},
     *   tipo_menu:?array{id:int,nombre:string},
     *   grupos:list<array{tipo:string,articulos:list<array<string,mixed>>}>,
     *   articulos_permitidos:list<int>
     * }
     */
    public function menuDelDia(ViandaUsuario $usuario, ?int $dia = null): array
    {
        $dia = $dia ?? Carbon::now()->isoWeekday();
        if (! ViandaDiaSemanaSupport::diaValido($dia)) {
            $dia = Carbon::now()->isoWeekday();
        }

        $base = [
            'dia' => ['numero' => $dia, 'etiqueta' => ViandaDiaSemanaSupport::etiqueta($dia)],
            'tipo_menu' => null,
            'grupos' => [],
            'articulos_permitidos' => [],
        ];

        if ($usuario->vianda_tipo_menu_id === null) {
            return $base;
        }

        $tipoMenu = ViandaTipoMenu::query()
            ->with(['articulos' => fn ($q) => $q->where('dia_semana', $dia)->orderBy('orden'),
                'articulos.articulo.tipoarticulos'])
            ->find($usuario->vianda_tipo_menu_id);

        if ($tipoMenu === null) {
            return $base;
        }

        $base['tipo_menu'] = ['id' => (int) $tipoMenu->id, 'nombre' => (string) $tipoMenu->nombre];

        $grupos = [];
        $permitidos = [];
        foreach ($tipoMenu->articulos as $linea) {
            $articulo = $linea->articulo;
            if ($articulo === null) {
                continue;
            }

            $tipoNombre = trim((string) ($articulo->tipoarticulos->nombre ?? '')) ?: 'Menú';
            $permitidos[(int) $articulo->id] = true;

            $grupos[$tipoNombre] ??= [];
            $grupos[$tipoNombre][] = [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'descripcion' => (string) $articulo->descripcion,
                'tipo' => $tipoNombre,
                'foto_url' => $this->fotoUrl($articulo),
            ];
        }

        $base['grupos'] = collect($grupos)
            ->map(fn ($articulos, $tipo) => ['tipo' => $tipo, 'articulos' => array_values($articulos)])
            ->values()
            ->all();
        $base['articulos_permitidos'] = array_map('intval', array_keys($permitidos));

        return $base;
    }

    /**
     * @return array{
     *   puede_pedir:bool,
     *   mensaje:?string,
     *   consumo_existente:?array{id:int,codigo_retiro:string,hora:?string}
     * }
     */
    public function estadoPedidoDiario(ViandaUsuario $usuario, ConfiguracionTerminalVianda $cfg): array
    {
        $empresaId = (int) $cfg->empresa_id;
        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        $fechaJornada = $jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        return ViandaConsumoLimiteDiarioSupport::estadoParaUsuario(
            (int) $usuario->id,
            $empresaId,
            $fechaJornada,
            (string) $usuario->tipo_usuario,
        );
    }

    /**
     *
     * @param  list<array{articulo_id:int,cantidad:float,comentario?:string}>  $lineasInput
     * @return array{consumo:ViandaConsumo,voucher:array<string,mixed>}
     */
    public function marchar(
        ConfiguracionTerminalVianda $cfg,
        ViandaUsuario $usuario,
        array $lineasInput,
        ?string $observacion,
        ?int $operadorId,
    ): array {
        $empresaId = (int) $cfg->empresa_id;
        $jornada = $this->jornadaService->exigirJornadaAbierta($empresaId);

        $menu = $this->menuDelDia($usuario);
        $permitidos = array_flip($menu['articulos_permitidos']);

        $lineasNormalizadas = $this->normalizarLineas($lineasInput, $permitidos);
        if ($lineasNormalizadas === []) {
            throw new InvalidArgumentException('Agregue al menos un artículo del menú del día antes de marchar la comanda.');
        }

        $fecha = Carbon::today()->format('Y-m-d');
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? $fecha;
        $listaprecioVentaId = $cfg->listaprecio_venta_id !== null ? (int) $cfg->listaprecio_venta_id : null;

        $consumo = DB::transaction(function () use (
            $cfg, $usuario, $lineasNormalizadas, $observacion, $operadorId,
            $empresaId, $jornada, $fecha, $fechaJornada, $listaprecioVentaId
        ) {
            ViandaConsumoLimiteDiarioSupport::exigirPuedePedir(
                (int) $usuario->id,
                $empresaId,
                $fechaJornada,
                (string) $usuario->tipo_usuario,
            );

            $consumo = ViandaConsumo::create([
                'empresa_id' => $empresaId,
                'configuracion_terminal_vianda_id' => (int) $cfg->id,
                'vianda_usuario_id' => (int) $usuario->id,
                'vianda_tipo_menu_id' => $usuario->vianda_tipo_menu_id,
                'centrocosto_id' => $usuario->centrocosto_id,
                'jornada_gastronomia_id' => (int) $jornada->id,
                'usuario_id' => $operadorId,
                'login_usuario' => (string) $usuario->codigo_usuario,
                'nombre_usuario' => (string) $usuario->nombre,
                'codigo_retiro' => 'PENDIENTE',
                'fecha' => $fecha,
                'fecha_jornada' => $fechaJornada,
                'hora' => now()->format('H:i'),
                'observacion' => $this->limpiarTexto($observacion, 2000),
                'cantidad_items' => 0,
                'total_costo' => 0,
                'total_venta' => 0,
                'estado' => 'A',
            ]);

            $consumo->codigo_retiro = ViandaConsumo::formatearCodigoRetiro((int) $consumo->id);

            $totalItems = 0;
            $totalCosto = 0.0;
            $totalVenta = 0.0;
            $orden = 0;
            foreach ($lineasNormalizadas as $ln) {
                $articulo = $ln['articulo'];
                $precioCosto = ViandaPrecioSupport::precioCostoUnitario((int) $articulo->id, $fechaJornada);
                $precioVenta = ViandaPrecioSupport::precioVentaUnitario((int) $articulo->id, $listaprecioVentaId, $fechaJornada);

                ViandaConsumoLinea::create([
                    'vianda_consumo_id' => (int) $consumo->id,
                    'articulo_id' => (int) $articulo->id,
                    'sku' => (string) $articulo->sku,
                    'descripcion' => (string) $articulo->descripcion,
                    'tipoarticulo_nombre' => trim((string) ($articulo->tipoarticulos->nombre ?? '')) ?: null,
                    'cantidad' => $ln['cantidad'],
                    'precio_costo_unitario' => $precioCosto,
                    'precio_venta_unitario' => $precioVenta,
                    'comentario' => $ln['comentario'],
                    'orden' => ++$orden,
                ]);

                $totalItems += (int) round($ln['cantidad']);
                $totalCosto += $precioCosto * $ln['cantidad'];
                $totalVenta += $precioVenta * $ln['cantidad'];
            }

            $consumo->cantidad_items = $totalItems;
            $consumo->total_costo = round($totalCosto, 4);
            $consumo->total_venta = round($totalVenta, 4);
            $consumo->save();

            $consumo->load('lineas.articulo');
            $this->stockService->registrarConsumo($consumo, $cfg);

            return $consumo;
        });

        $consumo->load(['lineas', 'centrocosto', 'viandaUsuario', 'empresa', 'tipoMenu']);
        $voucher = $this->voucherService->emitir($consumo, $cfg);

        return ['consumo' => $consumo, 'voucher' => $voucher];
    }

    /**
     * Reimprime el voucher de un consumo ya marchado (no vuelve a mover stock).
     *
     * @return array{ok:bool,omitida?:bool,mensaje?:string,texto_preview:string}
     */
    public function reimprimirVoucher(ConfiguracionTerminalVianda $cfg, int $consumoId): array
    {
        $consumo = ViandaConsumo::query()
            ->with(['lineas', 'centrocosto', 'viandaUsuario', 'empresa', 'tipoMenu'])
            ->where('empresa_id', (int) $cfg->empresa_id)
            ->find($consumoId);

        if ($consumo === null) {
            throw new InvalidArgumentException('No se encontró el voucher para reimprimir.');
        }

        return $this->voucherService->emitir($consumo, $cfg);
    }

    /**
     * @param  list<array<string,mixed>>  $lineasInput
     * @param  array<int,int>  $permitidos
     * @return list<array{articulo:Articulo,cantidad:float,comentario:?string}>
     */
    private function normalizarLineas(array $lineasInput, array $permitidos): array
    {
        $normalizadas = [];
        foreach ($lineasInput as $ln) {
            $articuloId = (int) ($ln['articulo_id'] ?? 0);
            $cantidad = (float) ($ln['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            if ($permitidos !== [] && ! isset($permitidos[$articuloId])) {
                throw new InvalidArgumentException('El artículo '.$articuloId.' no pertenece al menú del día del empleado.');
            }

            $articulo = Articulo::query()->with('tipoarticulos')->find($articuloId);
            if ($articulo === null) {
                throw new InvalidArgumentException('Artículo '.$articuloId.' inexistente.');
            }

            $normalizadas[] = [
                'articulo' => $articulo,
                'cantidad' => $cantidad,
                'comentario' => $this->limpiarTexto($ln['comentario'] ?? null, 255),
            ];
        }

        return $normalizadas;
    }

    private function fotoUrl(Articulo $articulo): ?string
    {
        $foto = trim((string) ($articulo->foto ?? ''));
        if ($foto === '') {
            return null;
        }

        return asset('storage/imagenes/fotos_articulos/'.$foto);
    }

    private function limpiarTexto(?string $texto, int $max): ?string
    {
        $txt = trim((string) $texto);

        return $txt === '' ? null : mb_substr($txt, 0, $max);
    }
}
