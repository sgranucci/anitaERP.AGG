<?php

namespace App\Support\Seguridad;

use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Ventas\Cliente;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\ProveedorListadoFiltros;
use App\Support\Stock\ArticuloConsultaDesdeModal;
use App\Support\Stock\ArticuloEstadoCanalSupport;
use App\Support\Stock\ArticuloListadoFiltros;
use App\Support\Stock\UsuarioDepositoAutorizado;
use App\Support\Sueldos\EmpleadoEstados;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registros del buscador. Como máximo cuatro por tipo.
 * Las sugerencias traen el nombre o el código, con tope.
 */
class BuscadorGlobalRegistrosSupport
{
    public const LIMITE_POR_TIPO = 4;

    public function __construct(
        private EmpresaRepository $empresaRepository
    ) {}

    /**
     * @return list<array{titulo: string, detalle: string, url: string, icono: string, nueva_pestana: bool}>
     */
    public function buscar(string $consulta): array
    {
        $q = trim($consulta);
        if (mb_strlen($q) < BuscadorGlobalSupport::MIN_CARACTERES) {
            return [];
        }

        $filas = [];
        if ($this->permitido('listar-clientes', 'editar-clientes', 'actualizar-clientes')) {
            $filas = array_merge($filas, $this->clientes($q));
        }
        if ($this->permitido('listar-articulos', 'editar-articulos', 'actualizar-articulos')) {
            $filas = array_merge($filas, $this->articulos($q));
        }
        if ($this->permitido('listar-proveedor', 'editar-proveedor', 'actualizar-proveedor')) {
            $filas = array_merge($filas, $this->proveedores($q));
        }
        if ($this->puedeAbrirUsuarios()) {
            $filas = array_merge($filas, $this->usuarios($q));
        }
        if ($this->permitido('listar-empleado-sueldos', 'editar-empleado-sueldos', 'actualizar-empleado-sueldos')) {
            $filas = array_merge($filas, $this->empleados($q));
        }
        if ($this->permitido('listar-depositos', 'editar-depositos', 'actualizar-depositos')) {
            $filas = array_merge($filas, $this->depositos($q));
        }
        if ($this->permitido('listar-centro-costo', 'editar-centro-costo', 'actualizar-centro-costo')) {
            $filas = array_merge($filas, $this->centrosCosto($q));
        }
        if ($this->permitido('listar-cuentas-contables', 'editar-cuentas-contables', 'actualizar-cuentas-contables')) {
            $filas = array_merge($filas, $this->cuentasContables($q));
        }
        if ($this->permitido('listar-cuentas-de-caja', 'editar-cuentas-de-caja', 'actualizar-cuentas-de-caja')) {
            $filas = array_merge($filas, $this->cuentasCaja($q));
        }

        return $filas;
    }

    /**
     * @return list<array{titulo: string, detalle: string, url: string, icono: string, nueva_pestana: bool}>
     */
    private function clientes(string $q): array
    {
        $query = Cliente::query()->select(['cliente.id', 'cliente.codigo', 'cliente.nombre', 'cliente.fantasia']);
        $query->whereIn('cliente.estado', [Cliente::ESTADO_ACTIVO, Cliente::ESTADO_REGULARIZADO]);

        return $this->rankear(
            $query,
            'cliente.codigo',
            'cliente.nombre',
            ['cliente.fantasia'],
            ['cliente.numerodocumento'],
            $q,
            'Cliente',
            'fa fa-user',
            'cliente',
            fn (int $id): string => route('editar_cliente', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * @return list<array{titulo: string, detalle: string, url: string, icono: string, nueva_pestana: bool}>
     */
    private function articulos(string $q): array
    {
        $query = Articulo::query()->select(['articulo.id', 'articulo.sku', 'articulo.descripcion']);
        ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
            $query,
            ArticuloEstadoCanalSupport::ESTADO_ACTIVO,
            ''
        );
        if (ArticuloListadoFiltros::filtroEmpresaActivo()) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'articulo.empresa_id', true);
        }

        return $this->rankear(
            $query,
            'articulo.sku',
            'articulo.descripcion',
            ['articulo.skualternativo'],
            [],
            $q,
            'Artículo',
            'fa fa-cube',
            'articulo',
            fn (int $id): string => ArticuloConsultaDesdeModal::urlEditar($id)
        );
    }

    /**
     * @return list<array{titulo: string, detalle: string, url: string, icono: string, nueva_pestana: bool}>
     */
    private function proveedores(string $q): array
    {
        $query = Proveedor::query()->select(['proveedor.id', 'proveedor.codigo', 'proveedor.nombre', 'proveedor.fantasia']);
        $query->whereIn('proveedor.estado', Proveedor::$estadosHabilitadosOperacion);
        if (ProveedorListadoFiltros::filtroEmpresaActivo()) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor.empresa_id', true);
        }

        return $this->rankear(
            $query,
            'proveedor.codigo',
            'proveedor.nombre',
            ['proveedor.fantasia'],
            ['proveedor.nroinscripcion'],
            $q,
            'Proveedor',
            'fa fa-truck',
            'proveedor',
            fn (int $id): string => route('editar_proveedor', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usuarios(string $q): array
    {
        return $this->rankear(
            $this->consultaUsuario(),
            'usuario.usuario',
            'usuario.nombre',
            ['usuario.email'],
            [],
            $q,
            'Usuario',
            'fa fa-user-circle',
            'usuario',
            fn (int $id): string => route('editar_usuario', ['id' => $id])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function empleados(string $q): array
    {
        return $this->rankear(
            $this->consultaEmpleado(),
            'empleado_sueldos.legajo',
            'empleado_sueldos.nombre',
            [],
            ['empleado_sueldos.documento', 'empleado_sueldos.cuil'],
            $q,
            'Empleado',
            'fa fa-address-card',
            'empleado',
            fn (int $id): string => route('editar_empleado_sueldos', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            false
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function depositos(string $q): array
    {
        return $this->rankear(
            $this->consultaDeposito(),
            'depmae.codigo',
            'depmae.nombre',
            [],
            [],
            $q,
            'Depósito',
            'fa fa-archive',
            'deposito',
            fn (int $id): string => route('editar_depmae', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function centrosCosto(string $q): array
    {
        return $this->rankear(
            Centrocosto::query()->select(['centrocosto.id', 'centrocosto.codigo', 'centrocosto.nombre', 'centrocosto.abreviatura as fantasia']),
            'centrocosto.codigo',
            'centrocosto.nombre',
            ['centrocosto.abreviatura'],
            [],
            $q,
            'Centro de costo',
            'fa fa-sitemap',
            'centrocosto',
            fn (int $id): string => route('editar_centrocosto', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cuentasContables(string $q): array
    {
        $query = Cuentacontable::query()->select(['cuentacontable.id', 'cuentacontable.codigo', 'cuentacontable.nombre']);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cuentacontable.empresa_id', true);

        return $this->rankear(
            $query,
            'cuentacontable.codigo',
            'cuentacontable.nombre',
            [],
            [],
            $q,
            'Cuenta contable',
            'fa fa-book',
            'cuentacontable',
            fn (int $id): string => route('editar_cuentacontable', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cuentasCaja(string $q): array
    {
        $query = Cuentacaja::query()->select(['cuentacaja.id', 'cuentacaja.codigo', 'cuentacaja.nombre']);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cuentacaja.empresa_id', true);

        return $this->rankear(
            $query,
            'cuentacaja.codigo',
            'cuentacaja.nombre',
            [],
            [],
            $q,
            'Cuenta de caja',
            'fa fa-money-bill',
            'cuentacaja',
            fn (int $id): string => route('editar_cuentacaja', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
        );
    }

    /**
     * Código exacto primero, después el que empieza igual, después el nombre.
     *
     * @param  list<string>  $columnasTexto
     * @param  list<string>  $columnasDocumento
     * @param  callable(int): string  $url
     * @return list<array<string, mixed>>
     */
    private function rankear(
        Builder $query,
        string $columnaCodigo,
        string $columnaNombre,
        array $columnasTexto,
        array $columnasDocumento,
        string $q,
        string $tipo,
        string $icono,
        string $clavePrefijo,
        callable $url,
        bool $codigoTexto = true
    ): array {
        $like = '%'.$this->escapeLike($q).'%';
        $prefijo = $this->escapeLike($q).'%';
        $digitos = preg_replace('/\D+/', '', $q) ?? '';
        $buscaDocumento = strlen($digitos) >= 7;
        $esNumero = preg_match('/^\d+$/', $q) === 1;
        $compararCodigo = $codigoTexto || $esNumero;
        $variantes = $esNumero ? [] : BuscadorParecidoSupport::variantes($q);
        $patronesFaltante = $esNumero ? [] : BuscadorParecidoSupport::patronesLetraFaltante($q);

        $query->where(function ($w) use ($columnaCodigo, $columnaNombre, $columnasTexto, $columnasDocumento, $q, $like, $prefijo, $buscaDocumento, $variantes, $patronesFaltante, $compararCodigo) {
            $abrio = false;
            if ($compararCodigo) {
                $w->where($columnaCodigo, $q)
                    ->orWhere($columnaCodigo, 'like', $prefijo);
                $abrio = true;
            }
            $abrio ? $w->orWhere($columnaNombre, 'like', $like) : $w->where($columnaNombre, 'like', $like);
            foreach ($columnasTexto as $columna) {
                $w->orWhere($columna, 'like', $like);
            }
            foreach ($variantes as $variante) {
                $likeVariante = '%'.$this->escapeLike($variante).'%';
                $w->orWhere($columnaNombre, 'like', $likeVariante);
                $w->orWhere($columnaCodigo, 'like', $likeVariante);
                foreach ($columnasTexto as $columna) {
                    $w->orWhere($columna, 'like', $likeVariante);
                }
            }
            foreach ($patronesFaltante as $patron) {
                $w->orWhere($columnaNombre, 'like', '%'.$patron.'%');
            }
            if ($buscaDocumento) {
                foreach ($columnasDocumento as $columna) {
                    $w->orWhere($columna, 'like', $like);
                }
            }
        });

        if ($compararCodigo) {
            $query->orderByRaw(
                'CASE WHEN '.$columnaCodigo.' = ? THEN 0 WHEN '.$columnaCodigo.' LIKE ? THEN 1 WHEN '.$columnaNombre.' LIKE ? THEN 2 WHEN '.$columnaNombre.' LIKE ? THEN 3 ELSE 4 END',
                [$q, $prefijo, $prefijo, $like]
            );
        } else {
            $query->orderByRaw(
                'CASE WHEN '.$columnaNombre.' LIKE ? THEN 0 WHEN '.$columnaNombre.' LIKE ? THEN 1 ELSE 2 END',
                [$prefijo, $like]
            );
        }
        $query->orderBy($columnaNombre);
        $query->limit(self::LIMITE_POR_TIPO);

        $filas = [];
        foreach ($query->get() as $fila) {
            $codigo = trim((string) ($fila->codigo ?? $fila->sku ?? ''));
            $nombre = trim((string) ($fila->nombre ?? $fila->descripcion ?? ''));
            $fantasia = trim((string) ($fila->fantasia ?? ''));
            $titulo = $nombre !== '' ? $nombre : $codigo;
            $detalle = $tipo;
            if ($codigo !== '') {
                $detalle .= ' · '.$codigo;
            }
            if ($fantasia !== '' && strcasecmp($fantasia, $nombre) !== 0) {
                $detalle .= ' · '.$fantasia;
            }

            $filas[] = [
                'titulo' => $titulo,
                'detalle' => $detalle,
                'url' => $url((int) $fila->id),
                'icono' => $icono,
                'nueva_pestana' => true,
                'tipo' => 'registro',
                'clave' => $clavePrefijo.':'.(int) $fila->id,
            ];
        }

        return $filas;
    }

    /**
     * Nombres o códigos que empiezan como lo escrito. Tope chico: no recorre el padrón.
     *
     * @return list<array{texto: string, tipo: string}>
     */
    public function sugerir(string $consulta): array
    {
        $q = trim($consulta);
        if (mb_strlen($q) < 3 || preg_match('/\s/u', $q) === 1) {
            return [];
        }

        if (preg_match('/^\d+$/', $q) === 1) {
            return $this->tomarSugerencias([
                $this->sugerirColumna('listar-clientes', 'editar-clientes', 'actualizar-clientes', function () use ($q) {
                    return $this->consultaCliente()->where('cliente.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('cliente.codigo')->limit(3)->pluck('cliente.codigo');
                }),
                $this->sugerirColumna('listar-articulos', 'editar-articulos', 'actualizar-articulos', function () use ($q) {
                    return $this->consultaArticulo()->where('articulo.sku', 'like', $this->escapeLike($q).'%')
                        ->orderBy('articulo.sku')->limit(3)->pluck('articulo.sku');
                }),
                $this->sugerirColumna('listar-proveedor', 'editar-proveedor', 'actualizar-proveedor', function () use ($q) {
                    return $this->consultaProveedor()->where('proveedor.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('proveedor.codigo')->limit(3)->pluck('proveedor.codigo');
                }),
                $this->sugerirSi($this->puedeAbrirUsuarios(), function () use ($q) {
                    return $this->consultaUsuario()->where('usuario.usuario', 'like', $this->escapeLike($q).'%')
                        ->orderBy('usuario.usuario')->limit(3)->pluck('usuario.usuario');
                }),
                $this->sugerirColumna('listar-empleado-sueldos', 'editar-empleado-sueldos', 'actualizar-empleado-sueldos', function () use ($q) {
                    return $this->consultaEmpleado()->where('empleado_sueldos.legajo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('empleado_sueldos.legajo')->limit(3)->pluck('empleado_sueldos.legajo');
                }),
                $this->sugerirColumna('listar-depositos', 'editar-depositos', 'actualizar-depositos', function () use ($q) {
                    return $this->consultaDeposito()->where('depmae.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('depmae.codigo')->limit(3)->pluck('depmae.codigo');
                }),
                $this->sugerirColumna('listar-centro-costo', 'editar-centro-costo', 'actualizar-centro-costo', function () use ($q) {
                    return Centrocosto::query()->where('centrocosto.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('centrocosto.codigo')->limit(3)->pluck('centrocosto.codigo');
                }),
                $this->sugerirColumna('listar-cuentas-contables', 'editar-cuentas-contables', 'actualizar-cuentas-contables', function () use ($q) {
                    return $this->consultaCuentaContable()->where('cuentacontable.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('cuentacontable.codigo')->limit(3)->pluck('cuentacontable.codigo');
                }),
                $this->sugerirColumna('listar-cuentas-de-caja', 'editar-cuentas-de-caja', 'actualizar-cuentas-de-caja', function () use ($q) {
                    return $this->consultaCuentaCaja()->where('cuentacaja.codigo', 'like', $this->escapeLike($q).'%')
                        ->orderBy('cuentacaja.codigo')->limit(3)->pluck('cuentacaja.codigo');
                }),
            ], $q);
        }

        return $this->tomarSugerencias([
            $this->sugerirColumna('listar-clientes', 'editar-clientes', 'actualizar-clientes', function () use ($q) {
                return $this->consultaCliente()->where('cliente.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('cliente.nombre')->limit(3)->pluck('cliente.nombre');
            }),
            $this->sugerirColumna('listar-articulos', 'editar-articulos', 'actualizar-articulos', function () use ($q) {
                return $this->consultaArticulo()->where('articulo.descripcion', 'like', $this->escapeLike($q).'%')
                    ->orderBy('articulo.descripcion')->limit(3)->pluck('articulo.descripcion');
            }),
            $this->sugerirColumna('listar-proveedor', 'editar-proveedor', 'actualizar-proveedor', function () use ($q) {
                return $this->consultaProveedor()->where('proveedor.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('proveedor.nombre')->limit(3)->pluck('proveedor.nombre');
            }),
            $this->sugerirSi($this->puedeAbrirUsuarios(), function () use ($q) {
                return $this->consultaUsuario()->where('usuario.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('usuario.nombre')->limit(3)->pluck('usuario.nombre');
            }),
            $this->sugerirColumna('listar-empleado-sueldos', 'editar-empleado-sueldos', 'actualizar-empleado-sueldos', function () use ($q) {
                return $this->consultaEmpleado()->where('empleado_sueldos.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('empleado_sueldos.nombre')->limit(3)->pluck('empleado_sueldos.nombre');
            }),
            $this->sugerirColumna('listar-depositos', 'editar-depositos', 'actualizar-depositos', function () use ($q) {
                return $this->consultaDeposito()->where('depmae.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('depmae.nombre')->limit(3)->pluck('depmae.nombre');
            }),
            $this->sugerirColumna('listar-centro-costo', 'editar-centro-costo', 'actualizar-centro-costo', function () use ($q) {
                return Centrocosto::query()->where('centrocosto.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('centrocosto.nombre')->limit(3)->pluck('centrocosto.nombre');
            }),
            $this->sugerirColumna('listar-cuentas-contables', 'editar-cuentas-contables', 'actualizar-cuentas-contables', function () use ($q) {
                return $this->consultaCuentaContable()->where('cuentacontable.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('cuentacontable.nombre')->limit(3)->pluck('cuentacontable.nombre');
            }),
            $this->sugerirColumna('listar-cuentas-de-caja', 'editar-cuentas-de-caja', 'actualizar-cuentas-de-caja', function () use ($q) {
                return $this->consultaCuentaCaja()->where('cuentacaja.nombre', 'like', $this->escapeLike($q).'%')
                    ->orderBy('cuentacaja.nombre')->limit(3)->pluck('cuentacaja.nombre');
            }),
        ], $q);
    }

    private function consultaCliente(): Builder
    {
        return Cliente::query()
            ->select(['cliente.id', 'cliente.codigo', 'cliente.nombre'])
            ->whereIn('cliente.estado', [Cliente::ESTADO_ACTIVO, Cliente::ESTADO_REGULARIZADO]);
    }

    private function consultaArticulo(): Builder
    {
        $query = Articulo::query()->select(['articulo.id', 'articulo.sku', 'articulo.descripcion']);
        ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
            $query,
            ArticuloEstadoCanalSupport::ESTADO_ACTIVO,
            ''
        );
        if (ArticuloListadoFiltros::filtroEmpresaActivo()) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'articulo.empresa_id', true);
        }

        return $query;
    }

    private function consultaProveedor(): Builder
    {
        $query = Proveedor::query()->select(['proveedor.id', 'proveedor.codigo', 'proveedor.nombre']);
        $query->whereIn('proveedor.estado', Proveedor::$estadosHabilitadosOperacion);
        if (ProveedorListadoFiltros::filtroEmpresaActivo()) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'proveedor.empresa_id', true);
        }

        return $query;
    }

    private function consultaUsuario(): Builder
    {
        $query = UsuarioOperativoSupport::query()
            ->select(['usuario.id', 'usuario.usuario as codigo', 'usuario.nombre', 'usuario.email as fantasia']);
        $asignadas = array_values(array_filter(array_map(
            'intval',
            $this->empresaRepository->traeEmpresasAsignadas()
        )));
        if ($asignadas !== []) {
            $query->where(function ($w) use ($asignadas) {
                $w->whereDoesntHave('usuario_empresas')
                    ->orWhereHas('usuario_empresas', function ($empresa) use ($asignadas) {
                        $empresa->whereIn('empresa.id', $asignadas);
                    });
            });
        }

        return $query;
    }

    private function consultaEmpleado(): Builder
    {
        $query = Empleado_Sueldos::query()
            ->select([
                'empleado_sueldos.id',
                'empleado_sueldos.legajo as codigo',
                'empleado_sueldos.nombre',
                'empleado_sueldos.documento as fantasia',
            ])
            ->where(function ($w) {
                $w->whereNull('empleado_sueldos.estado')
                    ->orWhere('empleado_sueldos.estado', '!=', EmpleadoEstados::BAJA);
            });
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        return $query;
    }

    private function consultaDeposito(): Builder
    {
        $query = Depmae::query()->select(['depmae.id', 'depmae.codigo', 'depmae.nombre']);
        UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'depmae.empresa_id', true);

        return $query;
    }

    private function consultaCuentaContable(): Builder
    {
        $query = Cuentacontable::query()->select(['cuentacontable.id', 'cuentacontable.codigo', 'cuentacontable.nombre']);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cuentacontable.empresa_id', true);

        return $query;
    }

    private function consultaCuentaCaja(): Builder
    {
        $query = Cuentacaja::query()->select(['cuentacaja.id', 'cuentacaja.codigo', 'cuentacaja.nombre']);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cuentacaja.empresa_id', true);

        return $query;
    }

    /**
     * @param  callable(): mixed  $consulta
     * @return list<string>
     */
    private function sugerirSi(bool $permitido, callable $consulta): array
    {
        if (! $permitido) {
            return [];
        }

        return $this->valoresSugeridos($consulta());
    }

    /**
     * @param  callable(): mixed  $consulta
     * @return list<string>
     */
    private function sugerirColumna(string $listar, string $editar, string $actualizar, callable $consulta): array
    {
        if (! $this->permitido($listar, $editar, $actualizar)) {
            return [];
        }

        return $this->valoresSugeridos($consulta());
    }

    /**
     * @param  iterable<mixed>  $filas
     * @return list<string>
     */
    private function valoresSugeridos(iterable $filas): array
    {
        $valores = [];
        foreach ($filas as $valor) {
            $texto = trim((string) $valor);
            if ($texto !== '') {
                $valores[] = $texto;
            }
        }

        return $valores;
    }

    private function puedeAbrirUsuarios(): bool
    {
        $rol = (string) session('rol_nombre');

        return $rol === 'administrador' || $rol === 'Enc-sistemas';
    }

    /**
     * @param  list<list<string>>  $grupos
     * @return list<array{texto: string, tipo: string}>
     */
    private function tomarSugerencias(array $grupos, string $q): array
    {
        $salida = [];
        $usados = [];
        $normalizada = BuscadorDiccionarioSupport::normalizar($q);
        $indice = 0;
        while (count($salida) < 4) {
            $avanzo = false;
            foreach ($grupos as $grupo) {
                if (! isset($grupo[$indice])) {
                    continue;
                }
                $avanzo = true;
                $texto = $grupo[$indice];
                if (BuscadorDiccionarioSupport::normalizar($texto) === $normalizada) {
                    continue;
                }
                $clave = mb_strtolower($texto);
                if (isset($usados[$clave])) {
                    continue;
                }
                $usados[$clave] = true;
                $salida[] = ['texto' => $texto, 'tipo' => 'completar'];
                if (count($salida) >= 4) {
                    return $salida;
                }
            }
            if (! $avanzo) {
                break;
            }
            $indice++;
        }

        return $salida;
    }

    private function permitido(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if (can($slug, false)) {
                return true;
            }
        }

        return false;
    }

    private function escapeLike(string $valor): string
    {
        return addcslashes($valor, '%_\\');
    }
}
