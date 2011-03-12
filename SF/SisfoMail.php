<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SisfoDB_Base.php
 *
 * SF 1.0 BETA
 * Sistema para envio de mail html y plano.  Tambien adjuntos
 * Esta clase se integra facilmente con SisfoModelo
 * 
 * Una gran porcion de codigo ha sido retomado del componente SwiftMailer de CakePHP
 * 
 * PHP 5
 *
 * LICENSE: Este archivo y los relacionados son propiedad intelectual
 * de Sisfo. Prohibida la reproduccion parcial o total del contenido, asi
 * como el uso en otras aplicaciones sin autorizacion escrita de Sisfo
 *
 * @package    SF
 * @author     Mauricio Morales <mmorales@sisfo.com>
 * @copyright  2008 Sisfo Ltda.
 * @version    CVS: $Id:SisfoMail.class$
 * @since      File available since Release 1.0
 * @see        Swift
 */

require_once realpath(dirname(__FILE__) . '/SwiftMailer/Swift.php');
require_once realpath(dirname(__FILE__) . '/../conf.main.php');

class SisfoMail {

    public $smtpType      = 'open';       // open, ssl, tls
    public $smtpUsername  = '';
    public $smtpPassword  = '';
    public $smtpHost      = '';           // Especificar host o dejar en blanco para autodetectar
    public $smtpPort      = null;         // Nulo para autodetectar o  (25 open, 465 ssl, etc.)
    public $smtpTimeout   = 10;           // Segundos antes de que ocurra timeout
    
    public $sendmailCmd   = null;         // Nulo para auto detectar, de otra forma definirlo (Por ejemplo.: '/usr/sbin/sendmail -bs')
    
    public $from          = null;
    public $fromName      = null;
    public $to            = null;         // Cada $to, $cc, y $bcc deben estar formateado de la forma:
    public $cc            = null;         // key => value que representa correo/nombre. e.g.:
    public $bcc           = null;         //   array('bob@google.com'=>'Bob Smith', 'joe@yahoo.com'=>'Joe Shmoe')
    
    public $bodyHtml      = "";           // Cuerpo del mensaje en HTML
    public $bodyTxt       = "";           // Cuerpo del mensaje en TXT
    
    public $viewHtml      = "";
    public $viewTxt       = "";
    
    public $attach        = array();
    
    /**
     * Constructor
     * Si existen parametros de servidor de correo en el conf.main los usa
     * 
     */
    function __construct() {
        global $mail;

        if (isset($mail)) {
            $this->smtpHost     = $mail['smtpHost'];
            $this->smtpType     = $mail['smtpType']; 
            $this->smtpPort     = $mail['smtpPort'];
            $this->smtpUsername = $mail['username']; 
            $this->smtpPassword = $mail['password'];
            $this->from         = $mail['from_address'];
            $this->fromName     = $mail['from_name'];
        }
        
    }
    
    /**
     * Crea lista de recipientes en formato valido
     * Recibe cadena de correos separados por coma (,)
     *
     * @param string $toStr
     * @return void
     */
    function mailList($toStr, $type = 'to') {
        
        $mailList = explode(',', $toStr);
        
        if (!empty($mailList)) {
            foreach($mailList as $mail) {
                if (!empty($mail)) {
                    $to[trim($mail)] = trim($mail);
                }
            }
        }
        
        if (!empty($to)) {
            $this->$type = $to;
            return;
        }
        
        if (!is_array($toStr) && !empty($toStr)) {
            $this->$type[trim($toStr)] = trim($toStr);
        }
        
        return;
    }
    
    /**
     * Crea el tipo de Swiftmailer apropiado basado en el tipo de conexion
     *
     * @param string $method
     * @return Swift
     */
    function _connect($method) { // smtp, sendmail, native
        // Create the appropriate Swift mailer object based upon the connection type.
        switch ($method) {
            case 'smtp':
                return $this->_connectSMTP();
            case 'sendmail':
                return $this->_connectSendmail();
            case 'native': default:
                return $this->_connectNative();
        }
    }
    
    /**
     * Conexion nativa
     *
     * @return Swift
     */
    function _connectNative() {
        require_once realpath(dirname(__FILE__) . '/SwiftMailer/Swift/Connection/NativeMail.php');
        // Return the swift mailer object.
        return new Swift(new Swift_Connection_NativeMail());
    }
    
    /**
     * Conexion sendmail
     *
     * @return Swift
     */
    function _connectSendmail() {
        require_once realpath(dirname(__FILE__) . '/SwiftMailer/Swift/Connection/Sendmail.php');
        
        // Auto-detect the sendmail command to use if not specified.
        if (empty($this->sendmailCmd)) {
            $this->sendmailCmd = Swift_Connection_Sendmail::AUTO_DETECT;
        }
        
        // Return the swift mailer object.
        return new Swift(new Swift_Connection_Sendmail($this->sendmailCmd));
    }
    
    /**
     * Conexion SMTP
     *
     * @return Swift
     */
    function _connectSMTP() {
        require_once realpath(dirname(__FILE__) . '/SwiftMailer/Swift/Connection/SMTP.php');
        
        // Detecta automatica SMTP host si no se provee
        if (empty($this->smtpHost)) {
            $this->smtpHost = Swift_Connection_SMTP::AUTO_DETECT;
        }
        
        // Detecta puerto Smtp
        if (empty($this->smtpPort)) {
            $this->smtpPort = Swift_Connection_SMTP::AUTO_DETECT;
        }
        
        // Determina tipo de conexion
        switch ($this->smtpType) {
            case 'ssl':
                $smtpType = Swift_Connection_SMTP::ENC_SSL; break;
            case 'tls':
                $smtpType = Swift_Connection_SMTP::ENC_TLS; break;
            case 'open': default:
                $smtpType = Swift_Connection_SMTP::ENC_OFF;
                
        }
        
        // Prepara el objeto Swift y autenticacion si es requerida
        $smtp =& new Swift_Connection_SMTP($this->smtpHost, $this->smtpPort, $smtpType);
        $smtp->setTimeout($this->smtpTimeout);
        
        if (!empty($this->smtpUsername)) {
            $smtp->setUsername($this->smtpUsername);
            $smtp->setPassword($this->smtpPassword);
        }
        
        // Retorna el objeto Swift
        return new Swift($smtp);
    }
    
    
    /**
     * Retorna cuerp en texto plano
     *
     * @return string
     */
    function _getBodyText() {
        return $this->bodyTxt;
    }
    
    /**
     * Retorna cuerpo en HTML
     *
     * @return string
     */
    function _getBodyHTML() {
        return $this->bodyHtml;
    }
    
    /**
     * Adjunta archivos al mensaje
     *
     * @param object $message
     */
    function doAttachments(&$message) {
        
        foreach($this->attach as $atch) {
            
            if ($atch['src'] == 'inline') {
                $message->attach(new Swift_Message_Attachment(
                $atch['file'], $atch['name'], $atch['type']));
            } elseif ($atch['src'] == 'external') {
                $data = file_get_contents($atch['file']);
                $message->attach(new Swift_Message_Attachment(
                $data, $atch['name'], $atch['type']));
            } else {
                $message->attach(new Swift_Message_Attachment(
                new Swift_File($atch['file']), $atch['name'], $atch['type']));
            }
            
        }
        
    }
    
    /**
     * Realiza el envio de correos
     *
     * @param string $body
     * @param string $subject
     * @param string $method
     * @return mixed
     */
    function send($subject = '', $method = 'smtp') {
        
        // Crea el mensaje y le cambia su asunto
        $message =& new Swift_Message($subject);
        
        // Agrega los cuerpos html y text
        $bodyHTML = $this->_getBodyHTML();
        $bodyText = $this->_getBodyText();
        
        $message->attach(new Swift_Message_Part($bodyHTML, "text/html"));
        $message->attach(new Swift_Message_Part($bodyText, "text/plain"));
        
        $this->doAttachments($message);
        
        // Cambia datos del remitente
        $from =& new Swift_Address($this->from, $this->fromName);
        
        // Crea lista de destinatarios
        $recipients =& new Swift_RecipientList();
        
        // Agrega todos los destinatarios PARA
        if (!empty($this->to)) {
            if (is_array($this->to)) {
                foreach($this->to as $address => $name) {
                    $recipients->addTo($address, $name);
                }
            } else {
                $recipients->addTo($this->to, $this->to);
            }
        }
        
        // Agrega todos los destinatarios con copia CC
        if (!empty($this->cc)) {
            if (is_array($this->cc)) {
                foreach($this->cc as $address => $name) {
                    $recipients->addCc($address, $name);
                }
            } else {
                $recipients->addCc($this->cc, $this->cc);
            }
        }
        
        // Destiandarios BCC
        if (!empty($this->bcc)) {
            if (is_array($this->bcc)) {
                foreach($this->bcc as $address => $name) {
                    $recipients->addBcc($address, $name);
                }
            } else {
                $recipients->addBcc($this->bcc, $this->bcc);
            }
        }
        
        // Intenta hacer el envio
        $mailer =& $this->_connect($method);
        $result = $mailer->send($message, $recipients, $from);
        $mailer->disconnect();
        
        return $result;
    }
    
}
?>