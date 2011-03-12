<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoDB_oci8.php
 * Driver para Oracle
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
 * @version    CVS: $Id:SisfoDB_oci8.class$
 * @see        SisfoDB
 * @since      File available since Release 1.0
 */

require_once realpath(dirname(__FILE__) . '/SisfoDB_Base.php');

class SisfoDB_oci8 extends SisfoDB_Base {
    
    /**
     * Tipos de datos como los describe oracle en su comando
     * asociacion con tipos de dato estandards
     * 
     * 
     */
    public $tipos = array('char' => array('CHAR', 'VARCHAR', 'VARCHAR2'),
                                       'int'  => array('NUMBER'),
                                       'float' => array('NUMBER'),
                                       'date' => array('DATE'),
                                       'timestamp' => array('TIMESTAMP'),
                                       'text' => array('BLOB'),
    );
    
    
    /**
     * Hace un Describe de la tabla del modelo
     * retorna arreglo
     * 
     * @return array
     */
    function describir() {

        
        $query = "  SELECT
                        column_name AS nombre,
                        data_type AS tipo,
                        nullable AS \"null\",
                        data_default AS \"default\",
                        column_id AS posicion,
                        data_length AS longitud
                    FROM 
                        all_tab_columns
                    WHERE 
                        table_name = '" . strtoupper($this->tabla) . "'";
        
        $this->debug($query);
        $data = self::$dbObject[$this->conexion]->queryAll($query, null, MDB2_FETCHMODE_ASSOC);
        
        if (PEAR::isError($data)) {
            $this->debug('No se puede establecer conexion o error interno del servidor');
            $this->fatalError();
            
            if (self::$autoError || (defined('DEBUG') && DEBUG)) {
                sf_MostrarError('', $data->getUserinfo());
            }
            
            return array();
        }
        
        return $data;
    }
    
    /**
     * Concatenacion
     *
     * @param array $campos
     * @return string
     */
    function concatenar($campos, $separador = ' - ') {
        $output = "";
        foreach ($campos as $campo) {
            $output .= "{$campo} ||'{$separador}'|| ";
        }
        $output = substr($output, 0, (-7 - strlen($separador)));
        return $output;
    }   
    
}

?>