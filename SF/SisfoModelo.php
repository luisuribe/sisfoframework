<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoModelo.class.php
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
 * @version    CVS: $Id:SisfoModelo.class$
 * @see        include.php
 * @since      File available since Release 1.0
 */

include_once realpath(dirname(__FILE__) . '/../conf.main.php');
require_once realpath(dirname(__FILE__) . '/SisfoDB.php');
require_once realpath(dirname(__FILE__) . '/SisfoMail.php');
require_once realpath(dirname(__FILE__) . '/extension.php');


/**
 * SisfoModelo
 * Esta clase se extiende por herencia a cada modelo que la quiere usar
 *
 * (*) Si un modelo no es compatible la clase se puede utilizar como un simple manejador
 * de conexiones a la DB, no requiere conectarse ni desconectarse
 *
 * Version beta y ya puede usarse en ambientes en produccion
 *
 * @since 1.0
 * @author Mauricio Morales <mmorales@sisfo.com>
 * @see SisfoDB, SisfoDB_Base
 */
class SisfoModelo {

    /**
     * ID del registro actual
     *
     * Puede ser un entero, un varchar o un arreglo con las llaves si es compuesta:
     *
     * Ej:  $this->id = 23;
     *      $this->id = array('tipo_doc' => 'CC', 'doc' => 293983');
     *
     *
     * @var mixed
     */
    public $id           = null;

    /**
     * Arreglo tipo cache para los datos que se pueden escribir
     *
     * @var array
     */
    public $datos        = array();

    /**
     * Tabla del modelo
     * @var string
     */
    public $tabla        = '';

    /**
     * Campo de llave primaria en la tabla
     *
     * Puede ser un string (si es una sola llave) y un array con columnas si es
     * una llave compuesta.
     *
     * Ej:
     *
     * public $PK = 'id';  // llave normal
     * public $PK = array('tipo_doc', 'doc');
     *
     *
     * @var mixed
     */
    public $PK           = 'id';


    /**
     * Validacion que se debe aplicar sobre los campos del modelo
     * Los tipos de validaciones que se pueden aplicar son:
     *
     * 'fecha', 'fechaHora', 'noVacio', 'numerico', 'alfanumerico', 'longitud:N', 'email'
     *
     * En los datos del campo se puede especificar el mensaje de error que debe devolver,
     * sino devuelve por defecto: 'Dato invalido'
     *
     * Ejemplo:
     *
     * public $validacion = array (
     *          'nombre_campo' => array(
     *                                   'tipo' => array('noVacio', 'alfanumerico', 'longitud:32'),
     *                                   'error' => 'Por favor ingrese dato valido'
     *                                  )
     *                              ),
     *           .....
     *
     * @var array
     */
    public $validacion   = array();

    /**
     * Almacenamiento temporal de errores
     * de validacion con _validar()
     *
     * @var array
     */
    public $errores      = array();


    /**
     * Conexion a la base de datos que debe usar, hay que recordar
     * que en el conf.main.php las conexiones deben estar organizadas
     * en el arreglo $db
     *
     * Ejemplo:
     *
     * $db['name'] = 'baseDatosPorDefecto';
     * ...
     *
     * $db['ConexionOracle']['name'] = 'baseDatosOracle';
     * ...
     *
     * Entonces se puede dejar en blanco para tomar la base de datos por defecto
     * o especificar en el atributo usaConexion del modelo 'ConexionOracle'
     * para utilizar la otra conexion
     *
     * @var string
     */
    protected $usaConexion = null;

    /**
     * Aqui se almacena el metodo que debe ser llamado en caso
     * de encontrarse error en validacion pre-insercion
     *
     * @var string
     */
    public $metodoError  = '';

    /**
     * Nivel de recursividad para la devolucion datos
     * @var int
     */
    protected $recursivo    = 1;

    /**
     * Asociacion uno a muchos entre el modelo actual y otros
     * @var array
     */
    public $tieneMuchos  = array();

    /**
     * Asociacion pertenece a, el modelo actual pertenece a otro
     * @var array
     */
    public $perteneceA   = array();

    /**
     * Asociacion uno a uno entre el modelo actual y otro
     * @var array
     */
    public $tieneUno     = array();

    /**
     * Debug de SQL, inactivo.  True: activo
     * @var boolean
     */
    public static $debug            = false;

    /**
     * Automaticamente muestra ventana de error en vez de Fatal Error;
     * @var boolean
     */
    public static $autoError        = true;

    /**
     * Indica si se usara transacciones
     * @var boolean
     */
    protected static $transaccional = false;

    /**
     * Interaccion con SisfoDB
     * para conexiones a la base de datos
     *
     * @var resource
     */
    private $SisfoDB = null;

    private $hacerHtml = true;

    /**
     * Deterima si hay una transaccion en progreso
     * @var boolean
     */
    private static $transIni = false;


    /**
     * Constructor, carga conexion a DB con Singleton, y relaciones
     * con otros modelos especificados en tieneUno, tieneMuchos y perteneceA
     *
     * En $tabla recibe en nombre de la tabla y la llave primaria con la que se asume
     * el objeto principal del ActiveRecord.  Esto es usual cuando se usa SisfoModelo
     * en modo pasivo.
     *
     * Ejemplo:
     *
     * $objUsuario = new SisfoModelo(array('tabla' => 'usuario', 'PK' => 'id', 'usaConexion' => 'unaConexionaDB'));
     *
     * Para usar SisfoModelo en modo activo solo basta con instanciar una clase
     * que herede todos los metodos y atributos
     *
     * Ejemplo:
     *
     * class Usuario extends SisfoModelo {
     *      public $tabla = 'usuario';
     *      public $PK    = 'id';
     *      public $usaConexion = 'ConexionPgql';  // Es opcional, solo si se usa conxion diferente a la por defecto
     * }
     *
     * @param array $tabla
     * @return object SisfoModelo
     */
    function __construct($tabla = null)
    {
        
        if (!is_null($tabla)) {
            $this->tabla       = $tabla['tabla'];
            $this->PK          = isset($tabla['PK']) ? $tabla['PK'] : 'id';
            $this->usaConexion = isset($tabla['usaConexion']) ? $tabla['usaConexion'] : null;
        }
        
        $this->SisfoDB  = SisfoDB::getManejador($this->usaConexion);
        $this->SisfoDB->hacerHtml = $this->hacerHtml;
        $this->SisfoDB->setTabla($this->tabla);
        $this->SisfoDB->PK = $this->PK;
        SisfoDB_Base::$autoError = self::$autoError;
        $this->_asociar();
    }

    /**
     * Hace o quita asociaciones que se hayan hecho
     * manipulando los atributos perteneceA, tieneMuchos, tieneUno
     * directamente del objeto
     *
     * @return void
     */
    function _asociar() {
        $debugActual = self::$debug;

        if (!empty($this->perteneceA)) {
            foreach($this->perteneceA as $name => $obj) {
                if (!is_null($this->usaConexion)) {
                    $obj['usaConexion'] = $this->usaConexion;
                }
                $this->{$name} = new SisfoModelo($obj);
            }
        }
        if (!empty($this->tieneUno)) {
            foreach($this->tieneUno as $name => $obj) {
                if (!is_null($this->usaConexion)) {
                    $obj['usaConexion'] = $this->usaConexion;
                }
                $this->{$name} = new SisfoModelo($obj);
            }
        }
        if (!empty($this->tieneMuchos)) {
            foreach($this->tieneMuchos as $name => $obj) {
                if (!is_null($this->usaConexion)) {
                    $obj['usaConexion'] = $this->usaConexion;
                }
                $this->{$name} = new SisfoModelo($obj);
            }
        }

        self::$debug = $debugActual;
    }

    /**
     * Destructor encargado se cerrar conexiones y hacer commits pendientes si no hay errores
     *
     * @return void
     */
    function __destruct()
    {

        if (self::$transaccional && self::$transIni && !$this->error) {
            if (is_object($this->SisfoDB)) {
                $this->SisfoDB->commit();
            }
        }

        if ($this->SisfoDB) {
            if (is_object($this->SisfoDB)) {
                $this->SisfoDB->disconnect();
            }
        }

    }

    /**
     * Guarda un error en el modelo y hace rollback si hay transaccion
     * Este metodo es para uso exclusivo de SisfoModelo y no de sus hijos
     *
     * @param string $msj
     * @param boolean $fatal
     */
    function _error($msj, $fatal = false)
    {

        $this->error = true;

        if ($this->debug) {
            echo $msj;
        }

        if ($fatal) {
            if (self::$transaccional && self::$transIni) {
                $this->SisfoDB->rollback();
            }
            exit;
        }

    }

    /**
     * Inicia transaccion en la base de datos
     * Si hay transaccion activa no hace nada
     *
     * @return void
     */
    function begin()
    {
        $this->SisfoDB->begin();
        self::$transIni = true;
    }

    /**
     * Hace rollback de transaccion en la base de datos
     *
     * @return void
     */
    function rollback()
    {
        $this->SisfoDB->rollback();
        self::$transIni = false;
    }


    /**
     * Hace un commit a la base de datos de las operaciones
     * realizadas desde el ultimo begin
     *
     * @return void
     */
    function commit()
    {
        $this->SisfoDB->commit();
        self::$transIni = false;
    }

    /**
     * Envoltura del metodo queryAll de mdb2
     *
     * @param string $sql
     * @param string $modo asoc | normal
     * @return array
     */
    function queryAll($sql, $modo = 'asoc') {
        return $this->SisfoDB->queryAll($sql, $modo);
    }

    /**
     * Envoltura del metodo queryRow de mdb2
     *
     * @param string $sql
     * @param string $modo asoc | normal
     * @return array
     */
    function queryRow($sql, $modo = 'asoc') {
        return $this->SisfoDB->queryRow($sql, $modo);
    }

    /**
     * Envoltura del metodo queryOne de mdb2
     *
     * @param string $sql
     * @return mixed
     */
    function queryOne($sql) {
        return $this->SisfoDB->queryOne($sql);
    }

    /**
     * Retorna arreglo con datos del registro
     *
     * @param int $id optional
     * @param string $condiciones
     * @return array;
     */
    function _getDatos($id = null, $condiciones = '')
    {
        $this->_asociar(); // Carga asociaciones hechas en el aire

        //FIXME : Relaciones tieneUno, tieneMuchos, perteneceA
        $id = ($id) ? $id : $this->id;
        $principal = $this->SisfoDB->getDatos($id, $condiciones);

        if (!empty($principal)) {
            if (!empty($this->perteneceA)) {
                foreach($this->perteneceA as $name => $obj) {

                	// Parche para evitar consultas cuando la llave esta vacia
                	// TODO Revisar
                	if ($principal[$obj['foranea']] == NULL) {
                		continue;
                	}

                    // Parche para llaves compuestas
                    if (is_array($obj['foranea'])) {
                        $id = array();

                        foreach($obj['foranea'] as $nOriginal => $nTabla) {
                            $id[$nTabla] = $principal[$nOriginal];
                        }

                    } else {
                        $id = $principal[$obj['foranea']];
                    }

                    $principal['_' . $name] = $this->{$name}->_getDatos($id);
                }
            }
            if (!empty($this->tieneUno)) {
                foreach($this->tieneUno as $name => $obj) {

                    // Parche para llaves compuestas
                    if (is_array($obj['foranea'])) {
                        $condiciones = "";

                        foreach($obj['foranea'] as $nOriginal => $nTabla) {
                            $condiciones .= empty($condiciones) ? '' : ' AND ';
                            $condiciones .= " $nTabla = " . $this->SisfoDB->getEstandar($nOriginal, $principal[$nOriginal]);
                        }

                    } else {
                        $condiciones = $obj['foranea'] . ' = ' . $principal[$this->PK];
                    }

                    $principal['_' . $name] = $this->{$name}->_getDatos(null, $condiciones);
                }
            }
            if (!empty($this->tieneMuchos)) {
                foreach($this->tieneMuchos as $name => $obj) {

                    // Parche para llaves compuestas
                    if (is_array($obj['foranea'])) {
                        $condiciones = "";

                        foreach($obj['foranea'] as $nOriginal => $nTabla) {
                            $condiciones .= empty($condiciones) ? '' : ' AND ';
                            $condiciones .= " $nTabla = " . $this->SisfoDB->getEstandar($nOriginal, $principal[$nOriginal]);
                        }

                    } else {
                        $condiciones = $obj['foranea'] . ' = ' . $principal[$this->PK];
                    }

                    // Parche para filtrado de registros
                    if (is_array($obj['condiciones'])) {

                        $id = array();

                        foreach($obj['condiciones'] as $campo => $key ) {
                            $condiciones .= empty($condiciones) ? '' : ' AND ';
                            $condiciones .= " $campo = " . $obj['condiciones'][$campo];
                        }

                    }

                    // Parche para orden de registros
                    if ($obj['orden']) {
                        $orden = $obj['orden'];
                    }

                    $principal['_' . $name] = $this->{$name}->_buscar($condiciones, 1, $orden);

                }
            }
        }

        return $principal;

    }


    /**
     * Retorna un campo de la tabla asociada
     *
     * @param string $campo
     * @param int $id opcional si ya esta seteado this->id
     * @param string $condiciones opcionales
     * @return mixed valor del campo o falso si no existe
     */
    function _getCampo($campo, $id = null, $condiciones = "") {
        $id = ($id) ? $id : $this->id;
        $principal = $this->SisfoDB->getDatos($id, $condiciones);
        return isset($principal[$campo]) ? $principal[$campo] : false;
    }


    /**
     * Retorna verdadero si en el arreglo de datos del modelo
     * existen los campos de las llaves (una o varias si es compuesta)
     *
     * @param array $datos
     * @return boolean
     */
    function _existenLlaves($datos = null) {

        // Si es una sola llave
        if (is_string($this->PK) && isset($datos[$this->PK])) {

            return true;

        } elseif (is_array($this->PK)) {

            $cantIx  = count($this->PK);
            $i       = 0;

            foreach($this->PK as $pk) {
                if (isset($datos[$pk])) {
                    $i++;
                }
            }

            if ($i == $cantIx) {
                return true;
            }

        }

        return false;
    }

    /**
     * Guarda los datos del objeto en la DB
     * Retorna el ID del registro o False
     *
     * @param array $datos
     * @return mixed
     */
    function _guardar($datos = null, $opciones = array())
    {

        $datos = ife(empty($this->datos), $datos, $this->datos);

        if (empty($datos)) {
            return false;
        }

        // Si se debe aplicar validacion, lo hace !
        if (!empty($this->validacion)) {
            if ($this->_validar($datos) !== true) {

                // Redireccion en caso de errores
                if (!empty($this->metodoError)) {
                    $this->redirigirError();
                }

                return false;
            }
        }

        if (!$this->_existenLlaves($datos)) {
            $datos['created'] = date('Y-m-d H:i');
        }

        $datos['modified'] = date('Y-m-d H:i');

        if ($this->_antesDeGuardar($datos)) {

            $insertar = false;

            // Aqui viene lo mas importante del parche para llaves compuestas
            // Si es una llave compuesta
            if (is_array($this->PK)) {

                // Arreglo de condiciones
                $condiciones = "";
                foreach ($this->PK as $pk) {
                    $condiciones .= empty($condiciones) ? '' : ' AND ';
                    $condiciones .= " {$this->tabla}.{$pk} = " . $this->SisfoDB->getEstandar($pk, $datos[$pk]);
                }

                // Verifica si existe o no
                $obj = $this->_buscar($condiciones, 0);
                $condiciones = "";
                if (!empty($obj) && !PEAR::isError($obj)) {
                    $insertar = false;
                } else {
                    $insertar = true;
                }

            } else {

                // Si lla llave primaria es dada entonces actualiza
                if (isset($datos[$this->PK])) {
                    $insertar = false;
                } else {
                    $insertar = true;
                }

            }

            if ($insertar) {
                $result = $this->SisfoDB->insertar($datos, $this->errores);
            } else {
                $result = $this->SisfoDB->actualizar($datos, $this->errores);
            }
        }

        $this->_despuesDeGuardar();
        $this->id = $result;

        // Redireccion en caso de errores
        if (!$result && !empty($this->metodoError)) {
            $this->redirigirError();
        }

        // Si es una llave compuesta se devuelve true o false
        // Y ademas el ID del objeto queda con la llave compuesta insertada
        if (is_array($this->PK) && $result) {

            $this->id      = array();

            foreach($this->PK as $pk) {
                $this->id[$pk] = $datos[$pk];
            }
        }

        return $result;
    }

    /**
     * Elimina el registro de la DB
     *
     * @param int $id
     * @return boolean
     */
    function _borrar($id = null)
    {
        $id = (is_null($id)) ? $this->id : $id;
        $result = $this->SisfoDB->borrar($id);
        $this->id = null;
        return $result;
    }

    /**
     * Busqueda en la tabla
     *
     * @param string $condiciones
     */
    function _buscar($condiciones = "", $recursividad = 1, $orden = '')
    {
        //TODO: Pendiente implementar relacion tiene muchos
        //TODO: Pendiente devolver datos en formato amigable
        // ya se encuentra devolviendo informacion de tieneUno y perteneceA

        $datos = $this->SisfoDB->getTodos($condiciones, $this, $recursividad, $orden);

        return $datos;
    }

    /**
     * Devuelve lista para imprimir en select del helper sisfo
     *
     * @param array $campo array(campo1, campo2,...)
     */
    function _lista($campos, $condiciones = '', $opciones = array())
    {
        if (!is_array($campos) || empty($campos)) {
            return array();
        }

        return $this->SisfoDB->lista($campos, $condiciones, $opciones);
    }

    /**
     * Pagina resultados de un query
     *
     * @param mixed $condiciones
     * @param array $opciones
     */
    function _paginar($condiciones = "", $opciones = array())
    {

        require_once realpath(dirname(__FILE__) . '/../Pager_Wrapper.php');

        $pager_options = array(
            'mode'       => 'Sliding',
            'perPage'    => 30,
            'delta'      => 2,
        );

        $pager_options = array_merge($pager_options, $opciones);

        if (is_array($condiciones)) {
            $query = $condiciones['query'];
        } else {
            $condiciones = ($condiciones) ? " AND ({$condiciones}) " : "";
            $query = "SELECT * FROM {$this->tabla} WHERE 1 = 1 {$condiciones} ";
        }

        $conexion = (is_null(($this->usaConexion))) ? 'default' : $this->usaConexion;
        return Pager_Wrapper_MDB2(SisfoDB_Base::$dbObject[$conexion], $query, $pager_options);
    }

    /**
     * Ejecuta una consulta dada en la base de datos
     *
     * @param string $sql
     * @return array
     */
    function query($sql, $modo = '')
    {
        return $this->SisfoDB->query($sql, $modo);
    }

    /**
     * Este metodo se puede sobrescribir en los modelos hijos
     *
     * @return boolean
     */
    function _antesDeGuardar(&$datos)
    {
        return true;
    }

    /**
     * Metodo ejecutado justo despues de guardar un registro
     *
     * @return void
     */
    function _despuesDeGuardar()
    {
        return true;
    }

    function usarEntidadesHtml($bool) {
        $this->hacerHtml = $bool;
        $this->SisfoDB->hacerHtml = $bool;
    }

    function _getError() {
        return $this->SisfoDB->getUltimoError();
    }

    /**
     * Cambia el debug a ON o OFF
     *
     * @param boolean $opt
     * @return void
     */
    function setDebug($opt) {
        self::$debug = $opt;
        SisfoDB_Base::$debug = $opt;
    }


    /**
     * Cambia la tabla del entorno de trabaja
     *
     * @param string $tabla
     * @param string $pk
     */
    function setTabla($tabla, $pk = '') {
        $this->tabla       = $tabla;
        $this->PK          = ($pk) ? $pk : $this->PK;
        $this->SisfoDB->PK = $this->PK;

        $this->SisfoDB->setTabla($this->tabla);
    }

    /**
     * Cambia la conexion a la base de datos
     *
     * @param string $conexion
     */
    function setConexion($conexion) {

        $this->usaConexion = $conexion;

        $this->SisfoDB  = SisfoDB::getManejador($this->usaConexion);
        $this->SisfoDB->hacerHtml = $this->hacerHtml;
        $this->SisfoDB->setTabla($this->tabla);
        $this->SisfoDB->PK = $this->PK;
        SisfoDB_Base::$autoError = self::$autoError;
    }

    /**
     * Cambia la renderizacion automatica de errores fatales
     * true: activa
     * false: inactiva
     *
     * Por defecto esta activa
     *
     * @param boolean $opt
     * @return void
     */
    function setAutoError($opt) {
        self::$autoError = $opt;
        SisfoDB_Base::$autoError = self::$autoError;
    }

    /**
     * Valida los datos que se van a guardar en el modelo,
     * Revise coherencia con base de datos y tipos de validaciones especificadas
     * en el modelo (si fue asi)
     *
     * Se especifica en el modelo el atributo $validacion :
     *
     * public $validacion = array (
     *          'nombre_campo' => array(
     *                                   'tipo' => array('noVacio', 'alfanumerico', 'longitud:32'),
     *                                   'error' => 'Por favor ingrese dato valido'
     *                                  )
     *                              ),
     *           .....
     *
     * TODO: mmorales, hacer esto que se necesita !!
     *
     * @param array $datos
     * @return mixed
     */
    function _validar($datos = null) {

        $datos = ife(is_array($datos), $datos, $this->datos);

        $tiposVal = array(
        'fecha'         => 'return val_Fecha($tval);',
        'fechaHora'     => 'return val_FechaHora($tval);',
        'noVacio'       => 'return val_noVacio($tval);',
        'numerico'      => 'return val_Numerico($tval);',
        'alfanumerico'  => 'return val_Alfanumerico($tval);',
        'longitud'      => 'return val_Longitud($tval, $adc);',
        'email'         => 'return val_Email($tval);',
        );

        $error = array();

        if (!empty($this->validacion)) {
            foreach($this->validacion as $campo => $formaValida) {

                $tval = $adc = '';

                if (is_array($formaValida['tipo'])) {

                    // Verifica para cada tipo de dato
                    foreach($formaValida['tipo'] as $tipo) {

                        // Si es validacion tipo longitud
                        if (stripos($tipo, 'longitud') !== false) {

                            $adc  = (int) substr($tipo, strpos($tipo, ':') + 1);
                            $func = $tiposVal['longitud'];

                        } else {
                            $func = $tiposVal[$tipo];
                        }

                        $tval = isset($datos[$campo]) ? $datos[$campo] : false;

                        if (!empty($func)) {

                            $r = eval($func);

                            if ($r) {
                                // No hay problema
                            } else {
                                $error[$campo] = isset($formaValida['error']) ? $formaValida['error'] : 'Dato invalido';
                            }
                        }
                    }

                } else {

                    // Si es validacion tipo longitud
                    if (stripos($formaValida['tipo'], 'longitud')) {

                        $adc  = (int) substr($formaValida['tipo'], strpos($formaValida['tipo'], ':') + 1);
                        $func = $tiposVal['longitud'];

                    } else {
                        $func = $tiposVal[$formaValida['tipo']];
                    }

                    $tval = isset($datos[$campo]) ? $datos[$campo] : false;

                    if (!empty($func)) {
                        if (eval($func)) {
                            // No hay problema
                        } else {
                            $error[$campo] = isset($formaValida['error']) ? $formaValida['error'] : 'Dato invalido';
                        }
                    }
                }
            }
        }

        $error  = array_merge($error, $this->errores);

        $this->errores = $error;
        return ife(empty($error), true, $error);
    }


    /**
     * Cambia el metodo que debe llamar en caso de errores de validacion en la
     * preinsercion a la base de datos.
     *
     * En la forma antigua este metodo reemplaza el conocido:
     * if (!$_POST['nombre']) {
     *      $this->insertForm(1);
     * }
     *
     * Y quedaria:
     * $this->lanzarEnError('insertForm');
     *
     * @param string $nombreFuncion
     * @param array $parametros
     */
    public function lanzarEnError($nombreFuncion) {

        // Prepara funcion para llamar via eval
        $func = '$this->' . $nombreFuncion . '($errores);';

        $this->metodoError = $func;
    }


    /**
     * Redirige error y envia errores en una cadena amigable
     * para estilos CSS
     *
     * @return void
     */
    public function redirigirError() {

        if (self::$transIni) {
            $this->rollback();
        }

        // Prepara datos de error
        $errores = '';

        $errores = '<ul>';
        foreach($this->errores as $campo => $error) {
            $campo = isset($this->campos[$campo]) ? $this->campos[$campo] : $campo;
            $errores .= "<li>El campo {$campo} ha presentado un error: {$error}</li>";
        }

        $errores .= '</ul>';

        eval ($this->metodoError); // Redirige error y finaliza script

        exit;
    }

}
?>
