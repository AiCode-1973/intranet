<?php
/**
 * Email-to-Ticket Engine
 * Lê e-mails IMAP da caixa suporte@hsesantos.com.br e cria chamados automaticamente.
 *
 * CLI (Task Scheduler Windows):
 *   php C:\xampp1\htdocs\intranet\email_para_chamado.php
 *
 * Este arquivo só executa quando chamado via CLI.
 * O admin/email_chamados.php inclui este arquivo para usar as funções.
 */

if (!defined('EMAIL_ENGINE_LOADED')) {
    define('EMAIL_ENGINE_LOADED', true);
}

// ── Criação das tabelas necessárias ──────────────────────────────────────────
function email_setup_tables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS email_imap_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        imap_host      VARCHAR(255) NOT NULL DEFAULT '',
        imap_port      INT NOT NULL DEFAULT 993,
        imap_user      VARCHAR(255) NOT NULL DEFAULT '',
        imap_pass      VARCHAR(255) NOT NULL DEFAULT '',
        imap_ssl       TINYINT DEFAULT 1,
        novalidate_cert TINYINT DEFAULT 0,
        pasta_inbox    VARCHAR(100) DEFAULT 'INBOX',
        pasta_processado VARCHAR(100) DEFAULT '',
        ativo          TINYINT DEFAULT 1,
        updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS email_chamados_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        message_id   VARCHAR(500),
        de_email     VARCHAR(255),
        de_nome      VARCHAR(255),
        assunto      VARCHAR(500),
        chamado_id   INT DEFAULT NULL,
        status       ENUM('criado','duplicado','erro') DEFAULT 'criado',
        erro_msg     TEXT,
        processado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_message_id (message_id(100)),
        FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ── Processamento principal ───────────────────────────────────────────────────
function processarEmailsChamados($conn) {
    $result = ['criados' => 0, 'duplicados' => 0, 'erros' => 0, 'erro' => null];

    $r = $conn->query("SELECT * FROM email_imap_config WHERE ativo = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        $result['erro'] = 'Nenhuma configuração IMAP ativa encontrada.';
        return $result;
    }
    $cfg = $r->fetch_assoc();

    if (empty($cfg['imap_host']) || empty($cfg['imap_user']) || empty($cfg['imap_pass'])) {
        $result['erro'] = 'Configuração IMAP incompleta (host, usuário ou senha em branco).';
        return $result;
    }

    // Monta string de conexão
    $flags = '/imap';
    if ($cfg['imap_ssl'])          $flags .= '/ssl';
    if ($cfg['novalidate_cert'])   $flags .= '/novalidate-cert';
    $mailbox = '{' . $cfg['imap_host'] . ':' . $cfg['imap_port'] . $flags . '}' . $cfg['pasta_inbox'];

    $imap = @imap_open($mailbox, $cfg['imap_user'], $cfg['imap_pass'], 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
    if (!$imap) {
        $result['erro'] = imap_last_error() ?: 'Falha ao conectar ao servidor IMAP.';
        return $result;
    }

    $emails = imap_search($imap, 'UNSEEN');

    if ($emails) {
        foreach ($emails as $num) {
            $header = imap_headerinfo($imap, $num);
            $msgId  = isset($header->message_id) ? trim($header->message_id) : ('gen-' . $num . '-' . time());

            // Verifica duplicata
            $check = $conn->prepare("SELECT id FROM email_chamados_log WHERE message_id = ?");
            $check->bind_param("s", $msgId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $result['duplicados']++;
                imap_setflag_full($imap, (string)$num, '\\Seen');
                continue;
            }
            $check->close();

            // Remetente
            $fromObj = isset($header->from[0]) ? $header->from[0] : null;
            $deEmail = $fromObj ? $fromObj->mailbox . '@' . $fromObj->host : 'desconhecido@externo.com';
            $deNome  = ($fromObj && !empty($fromObj->personal))
                ? imap_utf8($fromObj->personal)
                : $deEmail;

            // Assunto
            $assunto = (!empty($header->subject))
                ? imap_utf8($header->subject)
                : '(sem assunto)';

            // Corpo
            $body = email_get_body($imap, $num);

            // Campos do chamado
            $titulo     = mb_substr($assunto, 0, 255);
            $descricao  = "**E-mail recebido de:** {$deNome} <{$deEmail}>\n\n---\n\n" . $body;
            $prioridade = 'Média';
            $categoria  = 'E-mail';

            $stmt = $conn->prepare("INSERT INTO chamados (titulo, descricao, prioridade, categoria, usuario_id) VALUES (?, ?, ?, ?, NULL)");
            $stmt->bind_param("ssss", $titulo, $descricao, $prioridade, $categoria);

            if ($stmt->execute()) {
                $chamadoId = $stmt->insert_id;

                $log = $conn->prepare("INSERT INTO email_chamados_log (message_id, de_email, de_nome, assunto, chamado_id, status) VALUES (?, ?, ?, ?, ?, 'criado')");
                $log->bind_param("ssssi", $msgId, $deEmail, $deNome, $assunto, $chamadoId);
                $log->execute();
                $log->close();

                $result['criados']++;
                imap_setflag_full($imap, (string)$num, '\\Seen');

                if (!empty($cfg['pasta_processado'])) {
                    @imap_mail_move($imap, (string)$num, $cfg['pasta_processado']);
                }
            } else {
                $erroMsg = $conn->error;
                $log = $conn->prepare("INSERT INTO email_chamados_log (message_id, de_email, de_nome, assunto, status, erro_msg) VALUES (?, ?, ?, ?, 'erro', ?)");
                $log->bind_param("sssss", $msgId, $deEmail, $deNome, $assunto, $erroMsg);
                $log->execute();
                $log->close();
                $result['erros']++;
            }
            $stmt->close();
        }
        imap_expunge($imap);
    }

    imap_close($imap);
    return $result;
}

// ── Extração do corpo do e-mail ──────────────────────────────────────────────
function email_get_body($imap, $num) {
    $structure = imap_fetchstructure($imap, $num);

    if (!isset($structure->parts)) {
        $body = imap_body($imap, $num);
        return email_decode_part($body, $structure->encoding ?? 0, $structure->subtype ?? 'PLAIN');
    }

    $plain = '';
    $html  = '';
    email_walk_parts($imap, $num, $structure->parts, '', $plain, $html);

    if ($plain) return trim($plain);
    if ($html)  return trim(strip_tags($html));
    return '(sem conteúdo)';
}

function email_walk_parts($imap, $num, $parts, $prefix, &$plain, &$html) {
    foreach ($parts as $i => $part) {
        $partNum = ($prefix !== '' ? $prefix . '.' : '') . ($i + 1);
        $subtype = strtoupper($part->subtype ?? '');
        $type    = $part->type ?? 0;

        if ($type === 0) { // TEXT
            $body    = imap_fetchbody($imap, $num, $partNum);
            $decoded = email_decode_part($body, $part->encoding ?? 0, $part->subtype ?? 'PLAIN');
            if ($subtype === 'PLAIN' && !$plain) $plain = $decoded;
            if ($subtype === 'HTML'  && !$html)  $html  = $decoded;
        } elseif (isset($part->parts)) {
            email_walk_parts($imap, $num, $part->parts, $partNum, $plain, $html);
        }
    }
}

function email_decode_part($body, $encoding, $subtype) {
    switch ((int)$encoding) {
        case 1: $body = imap_8bit($body); break;
        case 2: $body = imap_binary($body); break;
        case 3: $body = base64_decode($body); break;
        case 4: $body = quoted_printable_decode($body); break;
    }
    if (strtoupper($subtype) === 'HTML') {
        $body = strip_tags($body);
    }
    if (!mb_check_encoding($body, 'UTF-8')) {
        $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
    }
    return $body;
}

// ── Entrada CLI (Task Scheduler / Cron) ─────────────────────────────────────
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';

    email_setup_tables($conn);
    $result = processarEmailsChamados($conn);

    echo "[" . date('Y-m-d H:i:s') . "] Criados: {$result['criados']}, Duplicados: {$result['duplicados']}, Erros: {$result['erros']}\n";
    if ($result['erro']) {
        echo "Erro: {$result['erro']}\n";
    }
    exit(0);
}
