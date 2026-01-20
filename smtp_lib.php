<?php
/**
 * Classe simples para enviar e-mail via SMTP sem dependências externas
 */
class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure;
    private $timeout = 30;
    private $socket;
    private $error;

    public function __construct($host, $port, $user, $pass, $secure = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = strtolower($secure);
    }

    public function getError() {
        return $this->error;
    }

    private function sendCommand($command) {
        fputs($this->socket, $command . "\r\n");
    }

    private function getResponse() {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }

    public function send($from_email, $from_name, $to, $subject, $message) {
        $host = ($this->secure == 'ssl') ? 'ssl://' . $this->host : $this->host;
        
        $this->socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            $this->error = "Não foi possível conectar ao host: $errstr ($errno)";
            return false;
        }

        $this->getResponse();
        $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST']);
        $this->getResponse();

        if ($this->secure == 'tls') {
            $this->sendCommand("STARTTLS");
            $this->getResponse();
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
                $this->error = "Falha ao iniciar criptografia TLS";
                return false;
            }
            $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST']);
            $this->getResponse();
        }

        if (!empty($this->user)) {
            $this->sendCommand("AUTH LOGIN");
            $this->getResponse();
            $this->sendCommand(base64_encode($this->user));
            $this->getResponse();
            $this->sendCommand(base64_encode($this->pass));
            $auth_response = $this->getResponse();
            if (substr($auth_response, 0, 3) != "235") {
                $this->error = "Falha na autenticação SMTP: " . $auth_response;
                return false;
            }
        }

        $this->sendCommand("MAIL FROM: <$from_email>");
        $this->getResponse();
        $this->sendCommand("RCPT TO: <$to>");
        $this->getResponse();
        $this->sendCommand("DATA");
        $this->getResponse();

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "From: $from_name <$from_email>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: SimpleSMTP-PHP\r\n";

        $this->sendCommand($headers . "\r\n" . $message . "\r\n.");
        $response = $this->getResponse();

        $this->sendCommand("QUIT");
        fclose($this->socket);

        if (substr($response, 0, 3) != "250") {
            $this->error = "Erro ao enviar dados: " . $response;
            return false;
        }

        return true;
    }
}
?>
