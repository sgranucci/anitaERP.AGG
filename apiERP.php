<?php
//header('Access-Control-Allow-Origin: *');
//header('Content-Type: application/json; charset=utf8');

ini_set('memory_limit', '512M');

include_once 'include/IFXConnection.php';
$data = json_decode(file_get_contents('php://input'), true);

#$path_sistema = '/usr2/bierzo';
$path_sistema = (array_key_exists('path_sistema', $data) ? $data['path_sistema'] : '/usr2/bierzo');
$sistema = (array_key_exists('sistema', $data) ? $data['sistema'] : "ventas");
$proceso = getmypid();

$_nombre_file = $path_sistema."/".$sistema."/cmd_sql." . substr (microtime (true), 0, 8) . ".sql";
$_nombre_ret = $path_sistema."/".$sistema."/cmd_sql." . substr (microtime (true), 0, 8) . "-".$data['acc']."-".$proceso.".csv";

if (array_key_exists('DB_NAME', $data)) {
	putenv ("DBPATH="        . $data["IFX_DB_PATH"]);
	putenv ("INFORMIXSERVER=" 	. $data["IFX_SERVER"]);
	$conn2 = new IFXConnection ($data["DB_NAME"]."@".$data["IFX_SERVER"], $config["IFX_DB_USER"], $config["IFX_DB_PASSWORD"]);
}

$data['where'] 		 = (array_key_exists('where', $data) ? $data['where'] : "");
$data['campos'] 	 = (array_key_exists('campos', $data) ? $data['campos'] : "");
$data['tabla'] 		 = (array_key_exists('tabla', $data) ? $data['tabla'] : "");
$data['whereArmado']     = (array_key_exists('whereArmado', $data) ? $data['whereArmado'] : "");
$data['orderBy'] 	 = (array_key_exists('orderBy', $data) ? " ORDER BY ".$data['orderBy'] : "");
$data['groupBy'] 	 = (array_key_exists('groupBy', $data) ? " GROUP BY ".$data['groupBy'] : "");
$data['valores'] 	 = (array_key_exists('valores', $data) ? $data['valores'] : "");

switch ($data['acc']){
    case 'list':
	    $arrWhere = $data['where'];
		$sql = "UNLOAD TO ".$_nombre_ret." SELECT ".$data['campos']." FROM ".$data['tabla']." ".$data['whereArmado']." ".$data['groupBy']." ".$data['orderBy'];
		break;
	case 'insert':
		$sql = "INSERT INTO ".$data['tabla']." (".$data['campos'].") VALUES (".$data['valores'].")";
	break;
	case 'update':
		$sql = "UPDATE ".$data['tabla']." SET ".$data['valores']." ".$data['whereArmado'];
	break;
	case 'delete':
		$sql = "DELETE FROM ".$data['tabla']." ".$data['whereArmado'];
	break;
    case 'customSql':
	    $sql = $data['customSql'];
		break;
}

$fp1 = fopen($_nombre_file, "w");
fprintf($fp1, "%s", $sql);
fclose($fp1);

$_cmdd = "export LD_ASSUME_KERNEL=2.4.19;export INFORMIXDIR=/home/informix;export LD_LIBRARY_PATH=:/home/informix/lib:/home/informix/lib/esql:/home/informix_esql/lib;export INFORMIXSERVER=bincadmin;cd ".$path_sistema."/".$sistema.";";

$_cmd = $_cmdd."sql ".$sistema." ".$_nombre_file." 2>&1";

$arr = shell_exec($_cmd);

if ($data['acc'] == "list") {
    $dataArr = array();
	$archivo = fopen($_nombre_ret,'r');
	$camposArr = explode(",", $data['campos']);
	while ($linea = fgets($archivo)) {
		$linea = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '#', $linea);
		$registroAssoc = array();
		$lineaArr = (explode("|", $linea));
		foreach ($camposArr as $key => $value) {
			$nombreAux = explode(" as ", $value);
			if (count($nombreAux) == 2) {
				$value = $nombreAux[1];
			}else{
				$nombreAux = explode(" AS ", $value);
				if (count($nombreAux) == 2) {
					$value = $nombreAux[1];
				}
			}
			$registroAssoc[trim($value)] = $lineaArr[$key];
		}
		$dataArr[] = $registroAssoc;
	}
	echo json_encode($dataArr);
}else{
	$_nombre_retorno = $_nombre_file.".ret";
	if (filesize($_nombre_retorno) > 0)
		echo "Error";
	else
		echo $arr;
}
?>

