<?php

/**
 * sportelloStudentiAssenti.php
 *
 * CRON (es. ogni giorno) - invia mail agli studenti iscritti che risultano assenti (presente NULL o 0)
 * per sportelli di oggi.
 *
 * UI mail: usa mail-ui.php (stile coerente con altre comunicazioni GestOre)
 */

require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';   // ✅ nuovo

// -------------------------------
// Helper: format data IT dd/mm/yyyy
// -------------------------------
function ymd_to_it(string $ymd): string {
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $ymd)) {
        $p = explode('-', substr($ymd, 0, 10));
        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }
    return $ymd;
}

// -------------------------------
// 1) Sportelli di OGGI (attivi, non cancellati)
// -------------------------------
// ⚠️ nel tuo codice c'era: date_format(...)=CURDATE() -> sbagliato (CURDATE() è date, non stringa yyyymmdd)
// Qui uso DATE(s.data)=CURDATE() e aggiungo anche s.attivo=1 (se vuoi includere anche bozze, toglilo)
$querySportelli = "
    SELECT
        s.id         AS sportello_id,
        s.ora        AS sportello_ora,
        s.data       AS sportello_data,
        s.cancellato AS sportello_cancellato,
        s.attivo     AS sportello_attivo,
        s.docente_id AS sportello_docente_id,
        s.materia_id AS sportello_materia_id,
        s.categoria  AS sportello_categoria,
        s.luogo      AS sportello_luogo,

        d.cognome AS docente_cognome,
        d.nome    AS docente_nome,
        d.email   AS docente_email,

        m.nome AS sportello_materia
    FROM sportello s
    INNER JOIN docente d ON d.id = s.docente_id
    INNER JOIN materia m ON m.id = s.materia_id
    WHERE DATE(s.data) = CURDATE()
      AND s.attivo = 1
      AND s.cancellato = 0
";

$sportelli = dbGetAll($querySportelli);
if (!$sportelli) {
    echo "Nessuno sportello oggi.\n";
    info("[CRON assenti] Nessuno sportello oggi");
    exit;
}

$totSportelli = count($sportelli);
$totSportelliConIscritti = 0;
$totMailInviate = 0;

foreach ($sportelli as $sp) {

    $sportello_id  = (int)$sp['sportello_id'];
    $ora           = (string)$sp['sportello_ora'];
    $dataYmd       = (string)$sp['sportello_data'];
    $dataIt        = ymd_to_it($dataYmd);

    $categoriaRaw  = (string)$sp['sportello_categoria'];
    $categoriaUp   = strtoupper(trim($categoriaRaw));
    $luogo         = (string)$sp['sportello_luogo'];
    $materia       = (string)$sp['sportello_materia'];

    $docCognome    = (string)$sp['docente_cognome'];
    $docNome       = (string)$sp['docente_nome'];
    $docenteNome   = trim($docCognome . ' ' . $docNome);

    // Solo per sportello didattico (come nel tuo codice)
    if ($categoriaUp !== "SPORTELLO DIDATTICO") {
        continue;
    }

    // studenti iscritti (solo iscritto=1)
    $cntIscritti = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = $sportello_id AND iscritto = 1");
    info("[CRON assenti] sportello_id=$sportello_id data=$dataIt ora=$ora iscritti=$cntIscritti categoria=$categoriaRaw");
    echo "Sportello $sportello_id $dataIt $ora - iscritti=$cntIscritti\n";

    if ($cntIscritti <= 0) {
        continue;
    }
    $totSportelliConIscritti++;

    // -------------------------------
    // 2) Studenti assenti: presente NULL o 0
    // -------------------------------
    $qAssenti = "
        SELECT
            ss.studente_id AS studente_id,
            ss.argomento   AS sportello_argomento,
            st.cognome     AS studente_cognome,
            st.nome        AS studente_nome,
            st.email       AS studente_email
        FROM sportello_studente ss
        INNER JOIN studente st ON st.id = ss.studente_id
        WHERE ss.sportello_id = $sportello_id
          AND ss.iscritto = 1
          AND (ss.presente IS NULL OR ss.presente = 0)
        ORDER BY st.cognome, st.nome
    ";
    $assenti = dbGetAll($qAssenti);
    if (!$assenti) {
        info("[CRON assenti] sportello_id=$sportello_id: nessuno studente assente (tutti presenti)");
        echo "  -> nessun assente\n";
        continue;
    }

    info("[CRON assenti] sportello_id=$sportello_id: assenti=" . count($assenti));
    echo "  -> assenti=" . count($assenti) . "\n";

    // -------------------------------
    // 3) invio mail a ciascuno studente assente
    // -------------------------------
    foreach ($assenti as $st) {

        $studCognome = (string)$st['studente_cognome'];
        $studNome    = (string)$st['studente_nome'];
        $studEmail   = trim((string)$st['studente_email']);
        $argomento   = (string)$st['sportello_argomento'];

        if ($studEmail === '') {
            warning("[CRON assenti] sportello_id=$sportello_id: email mancante per $studCognome $studNome -> skip");
            continue;
        }

        $to = $studEmail;
        $toName = trim($studNome . ' ' . $studCognome);

        // ✅ Mail UI coerente con GestOre
        $title = "NOTIFICA ASSENZA – SPORTELLO DIDATTICO";
        $intro = "Risulti <b>assente</b> ad un’attività prenotata.";

        $content = '
            <div style="margin:0 0 12px 0;">
                ' . badge('ASSENZA RILEVATA', '#fee2e2', '#7f1d1d') . '
            </div>

            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    ' . kvRow('Attività', 'Sportello didattico') . '
                    ' . kvRow('Materia', $materia) . '
                    ' . kvRow('Data', $dataIt) . '
                    ' . kvRow('Ora', $ora) . '
                    ' . kvRow('Docente', $docenteNome) . '
                    ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
                    ' . ($argomento !== '' ? kvRow('Argomento', $argomento) : '') . '
                    ' . kvRow('ID Sportello', (string)$sportello_id) . '
                </table>
            </div>

            <div style="font-size:13.5px;line-height:1.55;color:#374151;">
                Se eri presente, segnala subito al docente la mancanza.
                <br><br>
                <b>Ricorda:</b> l’assenza ad un’attività prenotata e non giustificata in anticipo può essere considerata ai fini disciplinari.
                <br>
                Puoi cancellarti da un’attività fino alla sera precedente.
            </div>
        ';

        $footer = "Per informazioni accedi a <b>GestOre</b>.";
        $body = mailWrap($title, strtoupper($toName), $intro, $content, $footer, 'warning');

        $subject = "GestOre - Notifica assenza sportello - $materia - $dataIt $ora";

        info("[CRON assenti] invio mail assenza a $to ($toName) sportello_id=$sportello_id");
        echo "    -> mail a $to\n";

        sendMail($to, $toName, $subject, $body);
        $totMailInviate++;
    }
}

info("[CRON assenti] Fine: sportelli_oggi=$totSportelli sportelli_con_iscritti=$totSportelliConIscritti mail_inviate=$totMailInviate");
echo "OK - sportelli_oggi=$totSportelli sportelli_con_iscritti=$totSportelliConIscritti mail_inviate=$totMailInviate\n";
