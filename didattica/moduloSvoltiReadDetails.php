<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function programmaSvoltoQuintaLegacyTextToRichHtml(string $text): string
{
    $lines = preg_split('/\R/u', str_replace("\t", '  ', $text));
    if ($lines === false) {
        return '';
    }

    $html = '';
    $listOpen = false;

    $closeList = function () use (&$html, &$listOpen): void {
        if ($listOpen) {
            $html .= '</ul>';
            $listOpen = false;
        }
    };

    foreach ($lines as $line) {
        $raw = (string)$line;
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^>>\s*(.+)$/u', $trimmed, $m)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (mb_strlen($trimmed, 'UTF-8') <= 90 && preg_match('/\p{L}/u', $trimmed) && !preg_match('/\p{Ll}/u', $trimmed)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (preg_match('/^(?:[•●▪◦\x{F0B7}\x{F0A7}]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u', ltrim($raw), $m)) {
            if (!$listOpen) {
                $html .= '<ul>';
                $listOpen = true;
            }
            $html .= '<li>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $closeList();
    return trim($html);
}

if(isset($_POST['modulo_id']) && isset($_POST['modulo_id']) != "") {
	$modulo_id = $_POST['modulo_id'];

					$query = "	SELECT
					programmi_svolti_moduli.id AS modulo_id,
					programmi_svolti_moduli.id_programma AS programma_id,
					programmi_svolti_moduli.ordine AS modulo_ordine,
					programmi_svolti_moduli.nome AS modulo_nome,
					programmi_svolti_moduli.contenuto AS modulo_contenuto,
					programmi_svolti_moduli.id_utente AS modulo_id_utente,
					programmi_svolti_moduli.updated AS modulo_updated,
					classi.anno AS programma_classe_anno
				FROM programmi_svolti_moduli
				INNER JOIN programmi_svolti
				ON programmi_svolti.id = programmi_svolti_moduli.id_programma
				INNER JOIN classi
				ON classi.id = programmi_svolti.id_classe
				WHERE programmi_svolti_moduli.id=$modulo_id ";
	
	$query .= "ORDER BY programmi_svolti_moduli.ordine ASC";

    $modulo = dbGetFirst($query);
    $modulo['modulo_is_quinta_structured'] = 0;
    $modulo['modulo_competenze_raggiunte'] = '';
    $modulo['modulo_contenuti_trattati'] = '';
    $modulo['modulo_abilita_quinta'] = '';
    $modulo['modulo_competenze_raggiunte_html'] = '';
    $modulo['modulo_contenuti_trattati_html'] = '';
    $modulo['modulo_abilita_quinta_html'] = '';

    $contenuto = (string)($modulo['modulo_contenuto'] ?? '');
    $decoded = json_decode($contenuto, true);
    if (is_array($decoded) && (($decoded['schema'] ?? '') === 'programma_svolto_quinta_v1' || ($decoded['schema'] ?? '') === 'programma_svolto_quinta_v2')) {
        $modulo['modulo_is_quinta_structured'] = 1;
        $modulo['modulo_competenze_raggiunte'] = (string)($decoded['competenze_raggiunte'] ?? '');
        $modulo['modulo_contenuti_trattati'] = (string)($decoded['contenuti_trattati'] ?? '');
        $modulo['modulo_abilita_quinta'] = (string)($decoded['abilita'] ?? '');
        $modulo['modulo_competenze_raggiunte_html'] = (string)($decoded['competenze_raggiunte_html'] ?? programmaSvoltoQuintaLegacyTextToRichHtml($modulo['modulo_competenze_raggiunte']));
        $modulo['modulo_contenuti_trattati_html'] = (string)($decoded['contenuti_trattati_html'] ?? programmaSvoltoQuintaLegacyTextToRichHtml($modulo['modulo_contenuti_trattati']));
        $modulo['modulo_abilita_quinta_html'] = (string)($decoded['abilita_html'] ?? programmaSvoltoQuintaLegacyTextToRichHtml($modulo['modulo_abilita_quinta']));
    }

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>
