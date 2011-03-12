<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoDB_Base.php
 *
 * SF 1.0 BETA
 * Framework Sisfo para soporte de aplicaciones desarrolladas de forma
 * tradicional
 * 
 * PHP 5
 *
 * LICENSE:    GNU GENERAL PUBLIC LICENSE 2.0
 * http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package    SF
 * @author     Mauricio Morales <mmorales@sisfo.com>
 * @copyright  2008 Sisfo Ltda.
 * @version    CVS: $Id:SisfoDB_Base.class$
 * @see        SisfoDB, SisfoDB_oci8, SisfoDB_pgsql
 * @since      File available since Release 1.0
 */

require_once realpath(dirname(__FILE__) . '/../SisfoDB.php');

class SisfoDB_Base {

    /*
     * Los tipos de dato por convencion seran:
     * CHAR
     * INT
     * FLOAT
     * TEXT
     * DATE
     */
    
    /**
     * Nombre de tabla actual
     * @var string
     */
    public  $tabla;
    
    public  $PK;
    
    public $tipos = array('char' => array('character', 'character varying'),
                                       'int'  => array('integer'),
                                       'float' => array('double precision'),
                                       'date' => array('date'),
                                       'timestamp' => array('timestamp'),
                                       'text' => array('text'),
    );
    
    /**
     * Cache para tipos de datos
     *
     * @var 
     */
    private $tmpTipos;
    
    /**
     * ID del registro que se manipula
     * @var int
     */
    public  $id = null;
    
    /**
     * Control de transaccionabilidad
     * @var boolean
     */
    public static $transIni = false;
    
    /**
     * Propiedad para configuracion de autorenderizacion de errores
     *
     * @var boolean
     */
    public static $autoError = true;
    
    /**
     * Pila de errores
     */
    public  $error   = array();
    
    /**
     * Error de ultima operacion
     *
     * @var unknown_type
     */
    public  $opError = false;
    
    /**
     * Modo debug
     *
     * @var boolean
     */
    public static $debug   = false;
    
    /**
     * Objeto de conexion DB
     * @var resource
     */
    public static $dbObject = null;
    
    
    /**
     * Conexion que se esta usando en el proceso
     * 
     * @var string
     */
    public $conexion = 'default';
    
    /**
     * Realiza la conexion con la base de datos
     *
     * @param string $conexion
     */
    public function __construct($conexion = null) {
        global $db;
        
        $dsn  = $db['dsn'];
        $opts = $db['opts'];
        
        if (!is_null($conexion)) {
            if (isset($db[$conexion]) && is_array($db[$conexion]) && isset($db[$conexion]['dsn'])) {
                $dsn  = $db[$conexion]['dsn'];
                $opts = $db[$conexion]['opts'];
                $this->conexion = $conexion;
            }
        }
        
        if (!isset(self::$dbObject[$this->conexion])) {
            self::$dbObject[$this->conexion] = MDB2::connect($dsn, $opts);
        }
        
        if (!self::$dbObject[$this->conexion] || PEAR::isError(self::$dbObject[$this->conexion])) {
            
            if (defined('DEBUG') && DEBUG) {
                sf_MostrarError('', self::$dbObject[$this->conexion]->getUserinfo());
            }
            
            $this->_error('Error de conexion a DB', true);
        }
    }
    
    
    function _error($msj, $fatal = false) 
    {
        
        $this->error = true;
        
        if (self::$debug) {
            echo $msj;
        }
        
        if ($fatal) {
            if (self::$transIni) {
                $this->rollback();
            }
            exit;
        }
        
    }
    
    
    /**
     * Cambia la tabla que se esta usando
     *
     * @param string $tabla
     * @return void
     */
    function setTabla($tabla) {
        $this->tabla = $tabla;
    }
    
    /**
     * Retorna arreglo asociativo de campos, tipos de dato y longitud
     * cuando aplique
     * 
     * Devuelve arreglo de la forma
     * array('campo1' => array('tipo' => 'CHAR', 'long' => '32'), 'campo2'...)
     * 
     * @return array
     */
    public function getTiposDato($tabla = null) {
        
        if (!is_null($tabla)) {
            $antTabla = $this->tabla;
            $this->setTabla($tabla);
            $mitabla = $tabla;
        } else {
            $mitabla = $this->tabla;
        }
        
        if ($this->tmpTipos['__tabla'] == $mitabla) {
            
            $datatypes = $this->tmpTipos;
            unset($datatypes['__tabla']);
            
            if (!is_null($tabla)) {
                $this->setTabla($antTabla);
            }
            
            return $datatypes;
        }
        
        $bdTipos = $this->describir();
        
        $datatypes = array();
        
        foreach ($bdTipos as $reg) {
            $name = $reg['nombre'];
            unset($reg['nombre']);
            $datatypes[strtolower($name)] = $reg;
            
            foreach($this->tipos as $tipo => $tp) {
                foreach($tp as $defTipo) {
                    
                    if (strpos($datatypes[strtolower($name)]['tipo'], $defTipo) !== false) {
                        $datatypes[strtolower($name)]['tipo'] = $tipo;
                    }
                }
            }
        }
        
        $cache = $datatypes;
        $cache['__tabla'] = $this->tabla;
        $this->tmpTipos = $cache;
        
        if (!is_null($tabla)) {
            $this->setTabla($antTabla);
        }
        
        return $datatypes;
    }
    
    /**
     * Cambia el arreglo de datos para prepararlo para query seguro.
     * 
     * @param unknown_type $campos
     */
    function soloValidos(&$campos) {
        
        $datos = $this->getTiposDato();
        $tmp = array();
        
        foreach ($campos as $nombre => $valor) {
            if ($this->exiteCampo($nombre)) {
                $tmp[$nombre] = $valor;
            }
        }
        
        $campos = $tmp;
    }
    
    /**
     * Retorna verdadero si el campo existe en la tabla actual
     *
     * @param string $campo
     * @return boolean
     */
    function exiteCampo($campo) {
        $datos = $this->getTiposDato();
        
        foreach ($datos as $nombre => $prefs) {
            if (strtolower($campo) == $nombre) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Retorna lista para formar select
     *
     * @param array $campos
     * @param string $condiciones
     * @return array
     */
    function lista($campos, $condiciones = '', $opciones = array()) {
        $this->opError = false;
        
        $separador = (isset($opciones['separador'])) ? $opciones['separador'] : ' - ';
        
        $query  = "SELECT {$this->PK}, ";
        $query .= $this->concatenar($campos, $separador);
        $query .= " FROM {$this->tabla}";
        $query .= " WHERE 1 = 1 ";
        $query .= ($condiciones) ? (" AND ( " . $condiciones . " ) ") : '';
        $query .= " ORDER BY {$campos[0]} ";
        
        $this->debug($query);
        $result = self::$dbObject[$this->conexion]->queryAll($query, null, MDB2_FETCHMODE_DEFAULT);
        
        if (PEAR::isError($result)) {
            $this->error[] = $result->getUserinfo();
            $this->opError = true;
            $this->fatalError();
            return false;
        }
        
        return $result;
    }
    
    /**
     * Ejecuta una insercion en la BD
     * Recibe los datos en un arreglo de la forma:
     * array ('campo1' => 'valor', 'campo2'....)
     * 
     * @param array $datos
     * @return boolean
     */
    public function insertar($datos, &$errores = null) {
        $this->soloValidos($datos);
        $this->opError = false;
        $tmpErrores    = array();
        
        $query = "INSERT INTO {$this->tabla} (";
        foreach($datos as $nombre => $value) {
            $query .= "{$nombre},";
        }
        $query = substr($query, 0, -1);
        $query .= ") VALUES (";
        
        foreach($datos as $nombre => $value) {
            $error  = $this->validar($nombre, $value);
            
            // Validar devuelve true si esta bien y arreglo en caso contrario
            if ($error !== true) {
                $tmpErrores[$error['campo']] = $error['error'];
            }
            
            $query .= $this->getEstandar($nombre, $value) . ",";
        }
        
        $query = substr($query, 0, -1);
        $query .= ")"; 
        
        $this->debug($query);
        
        if (empty($tmpErrores)) {
            
            $result = self::$dbObject[$this->conexion]->query($query);
            
            if (PEAR::isError($result)) {
                $this->error[] = $result->getUserinfo();
                $this->opError = true;
                $this->fatalError();
                
                if (self::$autoError) {
                    sf_MostrarError('', $result->getUserinfo());
                }
                
                return false;
            }
        } else {
            $errores = $tmpErrores;
            return false;
        }
        
        $tPK = $this->PK;

        // Si es llave compuesta
        if (is_array($this->PK) && count($this->PK) > 1) {
            return true;
//            $this->PK = $this->PK[0];
        } elseif (is_array($this->PK)) {
            $tPK = $this->PK[0];
        }

        // En otro caso retorna el ID que ha sido guardado
        return $this->queryOne("SELECT MAX({$tPK}) FROM {$this->tabla}");
//        return self::$dbObject[$this->conexion]->lastInsertID($this->tabla, $tPK);
    }
    
    /**
     * Actualiza el registro en la base de datos
     * Recibe arreglo de la forma:
     * array('id' => 'valor'
     *
     */
    public function actualizar($datos, &$errores = null) {
        $this->soloValidos($datos);
        $this->opError = false;
        $tmpErrores    = array();
        
        // Si es una llave compuesta
        if (is_array($this->PK)) {
            
            $id = array();
            
            foreach($this->PK as $pk) {
                $id[$pk] = $datos[$pk];
                unset ($datos[$pk]);
            }
            
        } else {
            $id = (int) isset($datos[$this->PK]) ? $datos[$this->PK] : $this->id;
            unset($datos[$this->PK]);
        }
        
        $query = "UPDATE {$this->tabla} SET ";
        foreach($datos as $nombre => $value) {
            
            // Validar devuelve true si esta bien y arreglo en caso contrario
            $error  = $this->validar($nombre, $value);
            
            if ($error !== true) {
                $tmpErrores[$error['campo']] = $error['error'];
            }
            
            $query .= "{$nombre} = " . $this->getEstandar($nombre, $value) . ",";
            
        }
        $query  = substr($query, 0, -1);
        
        
        // Una vez mas, verificacion de llave compuesta
        if (is_array($this->PK)) {
            $query .= " WHERE ";
            $tmpQ   = "";
            
            foreach ($id as $idcampo => $idval) {
                $query .= empty($tmpQ) ? '' : ' AND '; $tmpQ = '-';
                $query .= " {$idcampo} = " . $this->getEstandar($idcampo, $idval);
            }
            
        } else {
            $query .= " WHERE {$this->PK} = " . $this->getEstandar($this->PK, $id);
        }
        
        $this->debug($query);
        
        if (empty($tmpErrores)) {
            $result = self::$dbObject[$this->conexion]->query($query);
            
            if (PEAR::isError($result)) {
                $this->error[] = $result->getUserinfo();
                $this->opError = true;
                $this->fatalError();
                
                if (self::$autoError) {
                    sf_MostrarError('', $result->getUserinfo());
                }
                
                return false;
            }
        } else {
            $errores = $tmpErrores;
            return false;
        }
        
        if (is_array($id)) {
            return true;
        }
        
        return $id;
    }
    
    /**
     * Borra registro de la DB
     * 
     * @param $id opcional si ya esta la propiedad ID en el objeto
     * @return boolean
     */
    public function borrar($id = null) {
        $this->opError = false;
        $this->id = $id;
        
        $query = "DELETE FROM {$this->tabla} ";
        
        // Una vez mas, verificacion de llave compuesta
        if (is_array($this->PK)) {
            $query .= " WHERE ";
            $tmpQ   = "";
            
            foreach ($id as $idcampo => $idval) {
                $query .= empty($tmpQ) ? '' : ' AND '; $tmpQ = '-';
                $query .= " {$idcampo} = " . $this->getEstandar($idcampo, $idval);
            }
            
        } else {
            $query .= " WHERE {$this->PK} = " . $this->getEstandar($this->PK, $this->$id);
        }
        
        $this->debug($query);
                   
        $result = self::$dbObject[$this->conexion]->query($query);
        
        if (PEAR::isError($result)) {
            $this->error[] = $result->getUserinfo();
            $this->opError = true;
            $this->fatalError();
            
            if (self::$autoError) {
                sf_MostrarError('', $result->getUserinfo());
            }
            
            return false;
        }
        
        $this->id = null;
        
        if ($result) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Retorna los datos de una tabla
     * Recibe arreglo de opciones
     * 
     */
    public function getDatos($id, $condiciones = '', $opciones = array()) {
        $defOpciones = array('tabla' => '', 'PK' => '', 'campos' => array());
        $opciones    = array_merge($defOpciones, $opciones);
        $tabla       = ($opciones['tabla']) ? $opciones['tabla'] : $this->tabla;
        $PK          = ($opciones['PK']) ? $opciones['PK'] : $this->PK;
        $id          = ($id) ? $id : $this->id;
        $condiciones = ($condiciones) ? " AND ( " . $condiciones . " ) " : "";
        $llave       = "";
        
        // Si es un allave compuesta
        if (is_array($PK) && is_array($id)) {
            $tmpQ = "";
            foreach($id as $campoId => $valId) {
                $llave .= empty($tmpQ) ? '' : ' AND '; $tmpQ = '-';
                $llave .= " {$campoId} = " . $this->getEstandar($campoId, $valId);
            }
            $llave = " AND ({$llave}) ";
            
        } else {
            $llave       = ($id) ? " AND ({$PK} = ". $this->getEstandar($PK, $id) .") " : "";
        }
        
        $query = "SELECT * FROM {$tabla} 
                  WHERE 1 = 1
                  {$llave} 
                  {$condiciones}";
        
        $this->debug($query);
        
        $result = self::$dbObject[$this->conexion]->queryRow($query, null, MDB2_FETCHMODE_ASSOC);
        if (PEAR::isError($result)) {
            $this->error[] = $result->getUserinfo();
            $this->opError = true;
            $this->fatalError();
            
            if (self::$autoError) {
                sf_MostrarError('', $result->getUserinfo());
            }
            
            return false;
        }
        
        return $result;
    }
    
    /**
     * Retorna todos los registros que cumplen con las condiciones
     * Intenta devolver modelos asociados si hay mas...
     *
     * @param string $condiciones
     */
    public function getTodos($condiciones = "", &$Modelo = null, $recursividad = 1, $orden = '') {
        
        $condiciones = ($condiciones) ? " AND ( " . $condiciones ." ) " : "";
        
        $query = "SELECT {$this->tabla}.* ";
        
        if ($recursividad == 1) {
            if (!empty($Modelo->perteneceA)) {
                foreach ($Modelo->perteneceA as $nombre => $miembro) {
                    $query .= ", {$miembro['tabla']}.* ";
                }
            }
            if (!empty($Modelo->tieneUno)) {
                foreach ($Modelo->tieneUno as $nombre => $miembro) {
                    $query .= ", {$miembro['tabla']}.* ";
                }
            }
        }
        
        $query .=" FROM {$this->tabla} ";
        
        if ($recursividad == 1) {
            if (!empty($Modelo->perteneceA)) {
                foreach ($Modelo->perteneceA as $nombre => $miembro) {
                    $query .= ", {$miembro['tabla']} ";
                }
            }
            if (!empty($Modelo->tieneUno)) {
                foreach ($Modelo->tieneUno as $nombre => $miembro) {
                    $query .= ", {$miembro['tabla']} ";
                }
            }
        }
        
        $query .= " WHERE 1 = 1 ";
        
        if ($recursividad == 1) {
            if (!empty($Modelo->perteneceA)) {
                foreach ($Modelo->perteneceA as $nombre => $miembro) {
                    
                    if (is_array($miembro['PK'])) {
                        $query .= " AND ( ";
                        $tmpQ  = "";
                        
                        foreach($miembro['foranea'] as $campoOrig => $campoRel) {
                            $query .= empty($tmpQ) ? '' : ' AND '; $tmpQ = '-';
                            $query .= " {$this->tabla}.{$campoOrig} = {$miembro['tabla']}.{$campoRel} ";
                        }
                        
                        $query .= " ) ";
                    } else {
                        $query .= " AND ({$this->tabla}.{$miembro['foranea']} = {$miembro['tabla']}.{$miembro['PK']}) ";
                    }
                }
            }
            if (!empty($Modelo->tieneUno)) {
                foreach ($Modelo->tieneUno as $nombre => $miembro) {
                    
                    if (is_array($miembro['PK']) || is_array($miembro['foranea'])) {
                        $query .= " AND ( ";
                        $tmpQ  = "";
                        
                        foreach($miembro['foranea'] as $campoOrig => $campoRel) {
                            $query .= empty($tmpQ) ? '' : ' AND '; $tmpQ = '-';
                            $query .= " {$this->tabla}.{$campoOrig} = {$miembro['tabla']}.{$campoRel} ";
                        }
                        
                        $query .= " ) ";
                    } else {
                        $query .= " AND ({$this->tabla}.{$this->PK} = {$miembro['tabla']}.{$miembro['foranea']}) ";
                    }
                }
            }
        }
        
        $query .= " {$condiciones} ";

        if ($orden) {
            $query .= " ORDER BY {$orden} ";
        }
        
        $this->debug($query);
        
        $result = self::$dbObject[$this->conexion]->queryAll($query, null, MDB2_FETCHMODE_ASSOC);
        if (PEAR::isError($result)) {
            $this->error[] = $result->getUserinfo();
            $this->opError = true;
            $this->fatalError();
            
            if (self::$autoError) {
                sf_MostrarError('', $result->getUserinfo());
            }
            
            return false;
        }
        
        return $result;
    }
    
    /**
     * Retorna el valor con enclosure y cast al especificado en la DB
     *
     * @param array $campo  array('campo1' => valor1)
     */
    public function getEstandar($campo, $valor) {
        $datos = $this->getTiposDato();
        
        if ($this->hacerHtml) {
            $valor = htmlentities($valor, ENT_QUOTES);
        }
        
        foreach($datos as $nombre => $prefs) {
            if ($nombre == $campo) {
                if ($prefs['tipo'] == 'char' || $prefs['tipo'] == 'text') {
                    return "'" . $valor . "'";
                } elseif ($prefs['tipo'] == 'float') {
                    return (float) $valor;
                } elseif ($prefs['tipo'] == 'int') {
                    return (int) $valor;
                } elseif ($prefs['tipo'] == 'date') {
                    if (val_Fecha($valor)) {
                        return "'" . $valor . "'";
                    }
                } elseif ($prefs['tipo'] == 'timestamp') {
                    if (val_FechaHora($valor)) {
                        return "'" . $valor . "'";
                    }
                }
            }
        }
        
        return 'NULL';
    }
    
    
    /**
     * Valida que el dato especificado en un campo sea valido
     *
     * @param string $campo
     * @param string $valor
     */
    public function validar($campo, $valor) {
        $datos = $this->getTiposDato();
        
        foreach($datos as $nombre => $prefs) {
            if ($nombre == $campo) {
                if ($prefs['tipo'] == 'char') {
                    if (isset($prefs['longitud']) && !empty($prefs['longitud'])) {
                    if (!val_Longitud($valor, $prefs['longitud'])) {
                        return array('campo' => $campo, 'error' => "La longitud " . strlen($valor) . " excede la permitida: {$prefs['longitud']}");
                        }
                    }
                } elseif ($prefs['tipo'] == 'float') {
                    if (!val_Numerico($valor)) {
                        return array('campo' => $campo, 'error' => 'Tipo de dato numerico no valido');
                    }
                } elseif ($prefs['tipo'] == 'int') {
                    if (!val_Numerico($valor)) {
                        return array('campo' => $campo, 'error' => 'Tipo de dato numerico no valido');
                    }
                } elseif ($prefs['tipo'] == 'date') {
                    if (!val_Fecha($valor)) {
                        return array('campo' => $campo, 'error' => 'La fecha especificada no es valida');
                    }
                } elseif ($prefs['tipo'] == 'timestamp') {
                    if (!val_FechaHora($valor)) {
                        return array('campo' => $campo, 'error' => 'La fecha-hora especificada no es valida');
                    }
                }
            }
        }
        
        return true;
    }
    
    public function commit() {
        self::$dbObject[$this->conexion]->commit();
        self::$transIni = false;
        $this->debug('DB COMMIT');
    }
    
    public function rollback() {
        self::$dbObject[$this->conexion]->rollback();
        self::$transIni = false;
        $this->debug('DB ROLLBACK');
    }
    
    public function begin() {
        if (!self::$transIni) {
            self::$dbObject[$this->conexion]->beginTransaction();
        } else {
            $this->error[] = 'Actualmente hay una transaccion iniciada';
        }
        self::$transIni = true;
        $this->debug('DB BEGIN');
    }
    
    public function disconnect() {
        if (self::$transIni) {
            $this->rollback();
        }
        
        try {
            if (!PEAR::isError(self::$dbObject)) {
                self::$dbObject[$this->conexion]->disconnect();
            }
        } catch (Exception $e) {
            
        }
        
    }
    
    /**
     * Checks for SQL errors
     *
     * @param object $result
     * @return boolean
     * @TODO Send email to admin
     */
    public function checkError($result) {
        if (PEAR::isError($result)) {
            $this->error[] = $result->getUserinfo();
            $this->opError = true;
            $this->fatalError();

            if (self::$autoError) {
                sf_MostrarError('', $result->getUserinfo());
            }

            return false;
        }

        return true;

    }

    public function query($sql, $modo = 'asoc') {
        $origModo = $modo;
        $modo     = ($modo == 'asoc') ? MDB2_FETCHMODE_ASSOC : MDB2_FETCHMODE_DEFAULT;

        $this->debug($sql);
        
        if ($origModo == '') {
            $result = self::$dbObject[$this->conexion]->query($sql);
        } else {
            $result = self::$dbObject[$this->conexion]->query($sql, null, $modo);
        }

        if ( !$this->checkError($result) ) {
            return false;
        }

        return $result;

    }
    
    public function queryAll($sql, $modo = 'asoc') {

        $this->debug($sql);

        $modo = ($modo == 'asoc') ? MDB2_FETCHMODE_ASSOC : MDB2_FETCHMODE_DEFAULT;
        $result = self::$dbObject[$this->conexion]->queryAll($sql, null, $modo);

        if ( !$this->checkError($result) ) {
            return false;
        }

        return $result;
    }
    
    public function queryOne($sql) {

        $this->debug($sql);

        $result = self::$dbObject[$this->conexion]->queryOne($sql);

        if ( !$this->checkError($result) ) {
            return false;
        }

        return $result;

    }
    
    public function queryRow($sql, $modo = 'asoc') {

        $this->debug($sql);

        $modo = ($modo == 'asoc') ? MDB2_FETCHMODE_ASSOC : MDB2_FETCHMODE_DEFAULT;
        $result = self::$dbObject[$this->conexion]->queryRow($sql, null, $modo);

        if ( !$this->checkError($result) ) {
            return false;
        }

        return $result;

    }
    
    function getUltimoError() {
        return array_pop($this->error);
    }
    
    function huboError() {
        return $this->opError;
    }
    
    function fatalError() {
        if (self::$transIni) {
            $this->rollback();
        }
    }
    
    function __destruct() {
        $this->disconnect();
    }
    
    /**
     * Devuelve datos en formato estandar del resultado del DESCRIBE
     * en la db, este metodo se debe implementar en cada driver
     * 
     * Devuelve: array('nombre' => array('tipo' => dato, 'null' => nullable),...)
     * 
     * @return 
     */
    public function describir() {
        return array();
    }
    
    /**
     * Se implementa en cada driver, y debe devolver SQL de concatenacion
     * dependiendo del DBMS
     * @param array $campos
     * @return string
     */
    public function concatenar($campos, $separador = ' - ') {
        return array();
    }
    
    function debug($msj) {
        if (self::$debug) {
            echo "(*) $msj </br>\r\n";
        }
    }
    
}
?>
