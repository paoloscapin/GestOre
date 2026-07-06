<?php

require_once __DIR__ . '/connect.php';

const CARENZE_PARENT_ACCESS_CONTEXT = 'carenze';

function carenzeParentAccessEnsureTable(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_accessi_temporanei (
            id int(11) NOT NULL AUTO_INCREMENT,
            id_genitore int(11) NOT NULL,
            id_studente int(11) NOT NULL,
            context varchar(40) NOT NULL DEFAULT 'carenze',
            token_hash char(64) NOT NULL,
            token_last4 char(4) NOT NULL,
            expires_at datetime NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            last_used_at datetime NULL,
            last_ip varchar(80) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_genitori_accessi_token (token_hash),
            KEY idx_genitori_accessi_parent_student (id_genitore, id_studente, context, active),
            KEY idx_genitori_accessi_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function carenzeParentAccessExpiry(): string
{
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $expiry = new DateTime($now->format('Y') . '-09-30 23:59:59', new DateTimeZone('Europe/Rome'));
    if ($now > $expiry) {
        $expiry = new DateTime(((int)$now->format('Y') + 1) . '-09-30 23:59:59', new DateTimeZone('Europe/Rome'));
    }
    return $expiry->format('Y-m-d H:i:s');
}

function carenzeParentAccessGenerateToken(): array
{
    $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    return [
        'plain' => $plain,
        'hash' => hash('sha256', $plain),
        'last4' => substr($plain, -4),
    ];
}

function carenzeParentAccessEnsureToken(int $genitoreId, int $studenteId): string
{
    carenzeParentAccessEnsureTable();
    $genitoreId = intval($genitoreId);
    $studenteId = intval($studenteId);
    if ($genitoreId <= 0 || $studenteId <= 0) {
        return '';
    }

    $token = carenzeParentAccessGenerateToken();
    $expiresAt = carenzeParentAccessExpiry();
    dbExec("
        INSERT INTO genitori_accessi_temporanei
            (id_genitore, id_studente, context, token_hash, token_last4, expires_at, active, created_at, updated_at)
        VALUES
            (" . dbI($genitoreId) . ",
             " . dbI($studenteId) . ",
             " . dbQ(CARENZE_PARENT_ACCESS_CONTEXT) . ",
             " . dbQ($token['hash']) . ",
             " . dbQ($token['last4']) . ",
             " . dbQ($expiresAt) . ",
             1,
             NOW(),
             NOW())
    ");

    return $token['plain'];
}

function carenzeParentAccessRowsForStudent(int $studenteId): array
{
    if ($studenteId <= 0) {
        return [];
    }

    return dbGetAll("
        SELECT DISTINCT
            g.id,
            g.nome,
            g.cognome,
            g.email
        FROM genitori_studenti gs
        INNER JOIN genitori g ON g.id = gs.id_genitore
        WHERE gs.id_studente = " . dbI($studenteId) . "
          AND COALESCE(g.attivo, 1) = 1
          AND TRIM(COALESCE(g.email, '')) <> ''
        ORDER BY g.cognome ASC, g.nome ASC, g.id ASC
    ") ?: [];
}

function carenzeParentAccessFooterHtml(int $studenteId, string $baseLink): string
{
    $parents = carenzeParentAccessRowsForStudent($studenteId);
    if (!$parents) {
        return '';
    }

    $baseLink = rtrim($baseLink, '/');
    $links = [];
    foreach ($parents as $parent) {
        $token = carenzeParentAccessEnsureToken((int)($parent['id'] ?? 0), $studenteId);
        if ($token === '') {
            continue;
        }
        $name = trim((string)($parent['cognome'] ?? '') . ' ' . (string)($parent['nome'] ?? ''));
        if ($name === '') {
            $name = trim((string)($parent['email'] ?? 'Genitore'));
        }
        $url = $baseLink . '/genitore/accessoTemporaneo.php?t=' . rawurlencode($token);
        $links[] = '<li style="margin:6px 0;"><a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#0f766e;font-weight:800;">Accesso GestOre per ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>';
    }

    if (!$links) {
        return '';
    }

    return '
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 14px;margin-top:14px;color:#14532d;">
        <div style="font-weight:900;margin-bottom:6px;">Accesso temporaneo genitori a GestOre</div>
        <div style="font-size:13px;line-height:1.45;">
          Fino al 30/09, in attesa delle credenziali ordinarie del registro elettronico, puoi usare il link personale qui sotto per consultare carenze e corsi in GestOre.
        </div>
        <ul style="padding-left:18px;margin:8px 0 0 0;">' . implode('', $links) . '</ul>
      </div>';
}

function carenzeParentAccessFindByToken(string $token): ?array
{
    carenzeParentAccessEnsureTable();
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    return dbGetFirst("
        SELECT a.*, g.nome, g.cognome, g.email, g.codice_fiscale
        FROM genitori_accessi_temporanei a
        INNER JOIN genitori g ON g.id = a.id_genitore
        INNER JOIN genitori_studenti gs
                ON gs.id_genitore = a.id_genitore
               AND gs.id_studente = a.id_studente
        WHERE a.token_hash = " . dbQ(hash('sha256', $token)) . "
          AND a.context = " . dbQ(CARENZE_PARENT_ACCESS_CONTEXT) . "
          AND a.active = 1
          AND a.expires_at >= NOW()
          AND COALESCE(g.attivo, 1) = 1
        LIMIT 1
    ") ?: null;
}

function carenzeParentAccessMarkUsed(int $accessId, string $ip): void
{
    if ($accessId <= 0) {
        return;
    }
    dbExec("
        UPDATE genitori_accessi_temporanei
        SET last_used_at = NOW(),
            last_ip = " . dbQ(substr($ip, 0, 80)) . "
        WHERE id = " . dbI($accessId) . "
        LIMIT 1
    ");
}

?>
