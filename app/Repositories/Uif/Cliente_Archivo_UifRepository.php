<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Archivo_Uif;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Cliente_Archivo_UifRepository implements Cliente_Archivo_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Archivo_Uif $cliente_archivo_uif)
    {
        $this->model = $cliente_archivo_uif;
    }

    public function create($request, $id)
    {
		return self::guardaCliente_Archivo_Uif($request, 'create', $id);
    }

    public function createUnique($id, $file)
    {
		return $this->model->create([
									'cliente_uif_id' => $id,
									'nombrearchivo' => $file,
									]);
    }

    public function update($request, $id)
    {
		return self::guardaCliente_Archivo_Uif($request, 'update', $id);
    }

    public function delete($cliente_uif_id)
    {
        return $this->model->where('cliente_uif_id', $cliente_uif_id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_archivo_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_archivo_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_archivo_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_archivo_uif;
    }

	private function guardaCliente_Archivo_Uif($request, $funcion, $id = null)
	{
		$origen = ClienteUifOrigenPcSupport::origenDeClienteId((int) $id)
			?? ClienteUifOrigenPcSupport::resolverObligatorio()['origen'];

		return ClienteUifArchivoStorage::withOrigen($origen, function () use ($request, $funcion, $id) {
			return $this->guardaCliente_Archivo_UifEnOrigen($request, $funcion, $id);
		});
	}

	private function guardaCliente_Archivo_UifEnOrigen($request, $funcion, $id = null)
	{
		$nombrearchivos = $request->file('nombrearchivos');
		$tieneNuevos = false;
		if (is_array($nombrearchivos)) {
			foreach ($nombrearchivos as $archivo) {
				if ($archivo && $archivo->isValid()) {
					$tieneNuevos = true;
					break;
				}
			}
		}
		$nombresAnteriores = $request->input('nombresanteriores');
		$tieneConservar = is_array($nombresAnteriores);
		// Flag del form editable: permite quitar todos los adjuntos sin borrar por accidente
		// cuando la solapa no envió inputs (p.ej. perfil solo visualización que igual puede guardar).
		$syncExplicit = $request->has('archivos_cliente_uif_sync');

		if ($funcion === 'update' && ! $syncExplicit && ! $tieneConservar && ! $tieneNuevos) {
			return '1';
		}

		// Prefijo Anita (inroclienteid); fallback al id local si aún no tiene nro Anita.
		$clienteUif = \App\Models\Uif\Cliente_Uif::query()->find($id);
		$prefijoArchivo = (int) ($clienteUif->inroclienteid ?? 0);
		if ($prefijoArchivo <= 0) {
			$prefijoArchivo = (int) $id;
		}

		$fechasPrevias = [];
		if ($funcion == 'update')
		{
			$fechasPrevias = $this->model->where('cliente_uif_id', $id)
				->pluck('created_at', 'nombrearchivo')
				->all();
			// Borra los registros antes de grabar nuevamente
       		$this->delete($id);
		}

		// Recorre todos los files nuevos
		if ($nombrearchivos ?? '')
		{
			foreach ($nombrearchivos as $archivo)
			{
		  		if ($archivo && $archivo->isValid())
				{
					$destDir = ClienteUifArchivoStorage::dirClientes();
					if (! ClienteUifArchivoStorage::ensureDir($destDir)) {
						throw new \RuntimeException('No se pudo preparar el directorio de archivos del cliente UIF.');
					}
    				$file = $archivo->getClientOriginalName();
    				$destName = $prefijoArchivo.'-'.$file;

    				if (! $archivo->move($destDir, $destName)) {
						throw new \RuntimeException('No se pudo guardar el archivo: '.$file);
					}

					$cliente_archivo_uif = $this->model->create([
									'cliente_uif_id' => $id,
									'nombrearchivo' => $destName,
									]);
				} elseif ($archivo && ! $archivo->isValid()) {
					throw new \RuntimeException(
						'Error al subir archivo: '.$archivo->getErrorMessage()
					);
				}
			}
		}

		// Recorre los files originales para agregarlos
		if ($tieneConservar)
		{
			for ($i_archivo = 0; $i_archivo < count($nombresAnteriores); $i_archivo++)
			{
				// Busca en los files agregados si el archivo es uno nuevo
				$fl_encontro = false;
				if ($nombrearchivos)
				{
					foreach($nombrearchivos as $archivo)
					{
						if ($archivo && $archivo->isValid())
						{
							// Guarda fisicamente el archivo
							$file = $archivo->getClientOriginalName();
		
							if ($file == $nombresAnteriores[$i_archivo])
								$fl_encontro = true;
						}
					}
				}
				// Agrega el archivo anterior no tocado
				if (!$fl_encontro && $nombresAnteriores[$i_archivo] != '')
				{
					$nombreAnterior = $nombresAnteriores[$i_archivo];
					$cliente_archivo_uif = $this->model->create([
									'cliente_uif_id' => $id,
									'nombrearchivo' => $nombreAnterior,
									]);
					if (! empty($fechasPrevias[$nombreAnterior])) {
						$cliente_archivo_uif->created_at = $fechasPrevias[$nombreAnterior];
						$cliente_archivo_uif->save();
					}
				}
			}
		}
		$retorno = $cliente_archivo_uif ?? '1';
		return $retorno;
	}

	public function traerArchivosDeAnita(int $clienteUifId, $inroclienteid): void
	{
		$cid = filter_var($inroclienteid, FILTER_VALIDATE_INT);
		if ($clienteUifId <= 0 || $cid === false || $cid <= 0) {
			return;
		}

		$cfg = config('uif.anita_uif_archivos', []);
		$mount = (string) ($cfg['mount'] ?? '');
		$tabla = (string) ($cfg['tabla_cliente'] ?? '');
		$campos = (string) ($cfg['campos_cliente'] ?? 'inroclienteid, inrolinea, carchivo');
		$sistema = (string) ($cfg['sistema'] ?? 'base_admin');

		$filasApi = $tabla !== ''
			? AnitaUifArchivosSync::listarDesdeAnita(
				$tabla,
				$campos,
				$sistema,
				" WHERE inroclienteid = '".$cid."' "
			)
			: [];

		$dirs = AnitaUifArchivosSync::directoriosCandidatosCliente($mount, (int) $cid);
		$desdeFs = AnitaUifArchivosSync::listarBasenamesEnDirectorios($dirs);
		$desdeFsPlano = AnitaUifArchivosSync::listarBasenamesClientePorPrefijo($mount, (int) $cid);

		$nombres = AnitaUifArchivosSync::mergeNombresArchivo($filasApi, array_merge($desdeFs, $desdeFsPlano));
		foreach ($nombres as $nombre) {
			$this->importarArchivoClienteSiExiste($clienteUifId, (int) $cid, $nombre, $mount);
		}
	}

	/**
	 * Nombre a registrar en BD: basename en /scan (Anita) o "{cliente_uif_id}-{basename}"
	 * si se copia al layout legacy del ERP.
	 */
	private function nombreDestinoImportClienteUif(int $clienteUifId, string $nombreArchivo, bool $usarBasenameOrigen): string
	{
		if ($usarBasenameOrigen) {
			return basename($nombreArchivo);
		}
		if (preg_match('/^(\d+)-/', $nombreArchivo, $m)) {
			if ((int) $m[1] === $clienteUifId) {
				return $nombreArchivo;
			}
		}

		return $clienteUifId.'-'.$nombreArchivo;
	}

	private function importarArchivoClienteSiExiste(int $clienteUifId, int $inroclienteid, string $nombreArchivo, string $mount): void
	{
		$nombreArchivo = basename($nombreArchivo);
		if ($nombreArchivo === '') {
			return;
		}

		$directo = ClienteUifArchivoStorage::dirClientes().DIRECTORY_SEPARATOR.$nombreArchivo;
		$origen = (is_file($directo) && is_readable($directo))
			? $directo
			: AnitaUifArchivosSync::primeraRutaExistente(
				AnitaUifArchivosSync::rutasOrigenCandidatas($mount, $inroclienteid, $nombreArchivo)
			);
		if ($origen === null) {
			return;
		}

		$copiar = ClienteUifArchivoStorage::syncDebeCopiar();
		$destNombre = $this->nombreDestinoImportClienteUif($clienteUifId, basename($origen), ! $copiar);

		$yaExiste = $this->model->newQuery()
			->where('cliente_uif_id', $clienteUifId)
			->where(function ($q) use ($destNombre, $nombreArchivo) {
				$q->where('nombrearchivo', $destNombre)
					->orWhere('nombrearchivo', $nombreArchivo)
					->orWhere('nombrearchivo', basename($nombreArchivo));
			})
			->exists();
		if ($yaExiste) {
			return;
		}

		if ($copiar) {
			$destDir = public_path('storage/archivos/clientes_uif/'.$clienteUifId);
			if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
				return;
			}
			$destFile = $destDir.'/'.$destNombre;
			if (! @copy($origen, $destFile)) {
				return;
			}
		}

		$archivo = $this->model->create([
			'cliente_uif_id' => $clienteUifId,
			'nombrearchivo' => $destNombre,
		]);

		// Preferir mtime del origen para no marcar reimports Anita como "subidos hoy".
		$mtime = @filemtime($origen);
		if ($mtime !== false && $mtime > 0) {
			$archivo->created_at = date('Y-m-d H:i:s', $mtime);
			$archivo->updated_at = $archivo->created_at;
			$archivo->save();
		}
	}

}
