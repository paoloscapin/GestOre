<?php

require_once '../common/path.php';
require_once '../common/__Settings.php';
require_once '../common/iscrizioniPrimeLib.php';

iscrizioniPrimeEnsureSchema();

function h($value): string
{
    $escaped = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($escaped === '' && trim((string)$value) !== '') {
        if (function_exists('mb_convert_encoding')) {
            return htmlspecialchars(mb_convert_encoding((string)$value, 'UTF-8', 'ISO-8859-1'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (function_exists('iconv')) {
            $converted = iconv('ISO-8859-1', 'UTF-8//IGNORE', (string)$value);
            if ($converted !== false) {
                return htmlspecialchars($converted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
    }

    return $escaped;
}

function confirmedValue(array $pratica, array $confirmed, string $field): string
{
    if (array_key_exists($field, $confirmed)) {
        return (string)$confirmed[$field];
    }

    return (string)($pratica[$field] ?? '');
}

function hasSecondResponsible(array $pratica, array $confirmed): bool
{
    $values = [
        $pratica['responsabile_2_cognome'] ?? '',
        $pratica['responsabile_2_nome'] ?? '',
        confirmedValue($pratica, $confirmed, 'email_genitore_2'),
        confirmedValue($pratica, $confirmed, 'telefono_genitore_2'),
    ];

    foreach ($values as $value) {
        if (trim((string)$value) !== '') {
            return true;
        }
    }

    return false;
}

function iscrizioniTerzeMaterieSeconda(): array
{
    $rows = dbGetAll("
        SELECT DISTINCT m.nome
        FROM materia m
        INNER JOIN docente_insegna di ON di.id_materia = m.id
        INNER JOIN classi c ON c.id = di.id_classe
        WHERE c.anno = 2
        ORDER BY m.nome ASC
    ") ?: [];

    $materie = [];
    foreach ($rows as $row) {
        $nome = trim((string)($row['nome'] ?? ''));
        if ($nome !== '') {
            $materie[] = $nome;
        }
    }

    if ($materie) {
        return $materie;
    }

    $rows = dbGetAll("SELECT nome FROM materia ORDER BY nome ASC") ?: [];
    foreach ($rows as $row) {
        $nome = trim((string)($row['nome'] ?? ''));
        if ($nome !== '') {
            $materie[] = $nome;
        }
    }

    return $materie;
}

function iscrizioniPrimeParentLanguages(): array
{
    return [
        'it' => ['label' => '🇮🇹 Italiano', 'dir' => 'ltr'],
        'en' => ['label' => '🇬🇧 English - Inglese', 'dir' => 'ltr'],
        'fr' => ['label' => '🇫🇷 Français - Francese', 'dir' => 'ltr'],
        'de' => ['label' => '🇩🇪 Deutsch - Tedesco', 'dir' => 'ltr'],
        'es' => ['label' => '🇪🇸 Español - Spagnolo', 'dir' => 'ltr'],
        'pt' => ['label' => '🇧🇷 Português - Portoghese', 'dir' => 'ltr'],
        'ru' => ['label' => '🇷🇺 Русский - Russo', 'dir' => 'ltr'],
        'sq' => ['label' => '🇦🇱 Shqip - Albanese', 'dir' => 'ltr'],
        'ro' => ['label' => '🇷🇴 Română - Rumeno', 'dir' => 'ltr'],
        'hi' => ['label' => '🇮🇳 हिन्दी - Hindi', 'dir' => 'ltr'],
        'pa' => ['label' => '🇮🇳 ਪੰਜਾਬੀ - Punjabi', 'dir' => 'ltr'],
        'bn' => ['label' => '🇧🇩 বাংলা - Bengali', 'dir' => 'ltr'],
        'sr' => ['label' => '🇷🇸 Српски - Serbo', 'dir' => 'ltr'],
        'hr' => ['label' => '🇭🇷 Hrvatski - Croato', 'dir' => 'ltr'],
        'tr' => ['label' => '🇹🇷 Türkçe - Turco', 'dir' => 'ltr'],
        'fa' => ['label' => '🇮🇷 فارسی - Persiano', 'dir' => 'ltr'],
        'ps' => ['label' => '🇦🇫 پښتو - Pashto', 'dir' => 'ltr'],
        'tl' => ['label' => '🇵🇭 Filipino - Tagalog', 'dir' => 'ltr'],
        'pl' => ['label' => '🇵🇱 Polski - Polacco', 'dir' => 'ltr'],
        'uk' => ['label' => '🇺🇦 Українська - Ucraino', 'dir' => 'ltr'],
        'zh' => ['label' => '🇨🇳 中文 - Cinese', 'dir' => 'ltr'],
        'ar' => ['label' => '🇲🇦 العربية - Arabo', 'dir' => 'ltr'],
        'ur' => ['label' => '🇵🇰 اردو - Urdu', 'dir' => 'ltr'],
    ];
}

function iscrizioniPrimeParentLanguagesClean(): array
{
    return [
        'it' => ['label' => '🇮🇹 Italiano', 'dir' => 'ltr'],
        'en' => ['label' => '🇬🇧 English - Inglese', 'dir' => 'ltr'],
        'fr' => ['label' => '🇫🇷 Français - Francese', 'dir' => 'ltr'],
        'de' => ['label' => '🇩🇪 Deutsch - Tedesco', 'dir' => 'ltr'],
        'es' => ['label' => '🇪🇸 Español - Spagnolo', 'dir' => 'ltr'],
        'pt' => ['label' => '🇧🇷 Português - Portoghese', 'dir' => 'ltr'],
        'ru' => ['label' => '🇷🇺 Русский - Russo', 'dir' => 'ltr'],
        'sq' => ['label' => '🇦🇱 Shqip - Albanese', 'dir' => 'ltr'],
        'ro' => ['label' => '🇷🇴 Română - Rumeno', 'dir' => 'ltr'],
        'hi' => ['label' => '🇮🇳 हिन्दी - Hindi', 'dir' => 'ltr'],
        'pa' => ['label' => '🇮🇳 ਪੰਜਾਬੀ - Punjabi', 'dir' => 'ltr'],
        'bn' => ['label' => '🇧🇩 বাংলা - Bengali', 'dir' => 'ltr'],
        'sr' => ['label' => '🇷🇸 Српски - Serbo', 'dir' => 'ltr'],
        'hr' => ['label' => '🇭🇷 Hrvatski - Croato', 'dir' => 'ltr'],
        'tr' => ['label' => '🇹🇷 Türkçe - Turco', 'dir' => 'ltr'],
        'fa' => ['label' => '🇮🇷 فارسی - Persiano', 'dir' => 'rtl'],
        'ps' => ['label' => '🇦🇫 پښتو - Pashto', 'dir' => 'rtl'],
        'tl' => ['label' => '🇵🇭 Filipino - Tagalog', 'dir' => 'ltr'],
        'pl' => ['label' => '🇵🇱 Polski - Polacco', 'dir' => 'ltr'],
        'uk' => ['label' => '🇺🇦 Українська - Ucraino', 'dir' => 'ltr'],
        'zh' => ['label' => '🇨🇳 中文 - Cinese', 'dir' => 'ltr'],
        'ar' => ['label' => '🇲🇦 العربية - Arabo', 'dir' => 'rtl'],
        'ur' => ['label' => '🇵🇰 اردو - Urdu', 'dir' => 'rtl'],
    ];
}

function iscrizioniPrimeLanguageFlagHtml(string $lang): string
{
    $countryByLang = [
        'it' => 'IT', 'en' => 'GB', 'fr' => 'FR', 'de' => 'DE', 'es' => 'ES', 'pt' => 'BR',
        'ru' => 'RU', 'sq' => 'AL', 'ro' => 'RO', 'hi' => 'IN', 'pa' => 'IN', 'bn' => 'BD',
        'sr' => 'RS', 'hr' => 'HR', 'tr' => 'TR', 'fa' => 'IR', 'ps' => 'AF', 'tl' => 'PH',
        'pl' => 'PL', 'uk' => 'UA', 'zh' => 'CN', 'ar' => 'MA', 'ur' => 'PK',
    ];
    $country = $countryByLang[strtolower(trim($lang))] ?? '';
    if (strlen($country) !== 2) {
        return '';
    }
    $html = '';
    foreach (str_split($country) as $letter) {
        $html .= '&#x' . strtoupper(dechex(0x1F1E6 + ord($letter) - ord('A'))) . ';';
    }
    return $html;
}

function iscrizioniPrimeParentTranslations(): array
{
    $translations = [
        'it' => [
            'page_title' => 'Conferma iscrizione',
            'school_kicker' => 'Istituto scolastico',
            'school_year' => 'Anno scolastico',
            'language' => 'Lingua',
            'main_title' => 'Conferma dati iscrizione',
            'subtitle' => 'Iscrizione alle classi prime',
            'invalid_link' => 'Link non valido, scaduto o pratica non disponibile.',
            'locked_notice' => 'La pratica e stata verificata dalla segreteria. Da questo link puoi consultare il riepilogo e i documenti caricati, ma non puoi piu modificare la pratica.',
            'intro_notice' => 'Verifica i dati anagrafici e aggiorna email e telefoni. Puoi salvare una bozza e rientrare da questo stesso link prima dell\'invio definitivo.',
            'student' => 'Studente',
            'confirm_data' => 'Dati da confermare',
            'documents' => 'Documenti',
            'surname' => 'Cognome',
            'name' => 'Nome',
            'tax_code' => 'Codice fiscale',
            'birth_date' => 'Data nascita',
            'course' => 'Corso',
            'practice_status' => 'Stato pratica',
            'student_email' => 'Email studente',
            'student_email_hint' => 'Indicare solo una email personale dello studente. Non usare la mail della scuola media; se non disponibile lasciare vuoto.',
            'student_phone' => 'Telefono studente',
            'resp1_email' => 'Email responsabile 1',
            'resp1_phone' => 'Telefono responsabile 1',
            'resp2_email' => 'Email responsabile 2',
            'resp2_phone' => 'Telefono responsabile 2',
            'responsible_1' => 'Responsabile 1',
            'responsible_2' => 'Responsabile 2',
            'data_confirm_checkbox' => 'Confermo che i dati indicati sono corretti o aggiornati.',
            'terze_specific_title' => 'Informazioni richieste per iscrizione in terza',
            'nulla_osta_title' => 'Nulla osta',
            'nulla_osta_help' => 'Il nulla osta deve essere richiesto dal genitore alla segreteria della scuola attualmente frequentata. La scuola di provenienza inviera poi il nulla osta alla nostra segreteria.',
            'nulla_osta_checkbox' => 'Confermo di aver richiesto il nulla osta alla scuola di provenienza.',
            'nulla_osta_date' => 'Data della richiesta di nulla osta',
            'carenze_title' => 'Carenze formative',
            'carenze_question' => 'Sono presenti carenze formative comunicate dalla scuola di provenienza?',
            'carenze_no' => 'No, non sono presenti carenze formative',
            'carenze_yes' => 'Si, sono presenti carenze formative',
            'carenze_subjects' => 'Materie con carenza',
            'carenze_subjects_help' => 'Seleziona una o piu materie. Se la materia non e presente, scegli Altro e scrivila nel campo sotto.',
            'carenze_other' => 'Altra materia',
            'save_draft' => 'Salva bozza',
            'save_documents' => 'Salva e vai ai documenti',
            'docs_intro' => 'Puoi caricare uno o piu PDF gia pronti oppure, da telefono, acquisire il documento come foto. Se servono piu pagine, seleziona o scatta piu foto: GestOre unisce tutto in un unico PDF multipagina.',
            'docs_photo_help' => 'Le foto devono essere chiare, dritte e leggibili. Prima di caricare puoi eliminare una foto venuta male dalla selezione del telefono; dopo il caricamento puoi cancellare il documento e rifarlo.',
            'privacy_notice' => 'I documenti vengono raccolti solo per la gestione della pratica di iscrizione e per gli adempimenti scolastici collegati.',
            'privacy_link' => 'Leggi l\'informativa privacy sui documenti caricati',
            'optional_doc_hint' => 'Facoltativo: da caricare solo se disponibile o se non gia consegnato/versato al momento dell\'iscrizione.',
            'not_uploaded' => 'Non ancora caricato',
            'paper_delivery' => 'Consegna cartacea in segreteria didattica',
            'paper_badge' => 'Cartaceo',
            'uploaded_badge' => 'Caricato',
            'missing_badge' => 'Mancante',
            'view_pdf' => 'Visualizza PDF caricato',
            'choose_one' => 'Scegli una sola possibilita:',
            'choose_123' => '1, 2 oppure 3.',
            'choice_recorded' => 'Scelta gia registrata',
            'cancel_paper' => 'Annulla scelta cartacea',
            'delete_pdf' => 'Cancella PDF caricato',
            'change_choice_hint' => 'Se devi cambiare scelta, annulla prima quella registrata e poi scegli 1, 2 oppure 3.',
            'already_uploaded' => 'PDF gia caricato',
            'append_pdf' => 'Aggiungi i nuovi file al PDF gia caricato.',
            'replace_pdf' => 'Sostituisci il PDF gia caricato con i nuovi file.',
            'choice_pdf' => 'Carico un PDF gia pronto',
            'add_pdf' => 'Aggiungi PDF',
            'pdf_help' => 'Usa questa scelta se hai gia il documento in PDF. Puoi aggiungere anche piu PDF: GestOre li unira in un unico file finale.',
            'choice_photo' => 'Scatto una foto del documento',
            'take_photo' => 'Scatta foto',
            'photo_help' => 'Usa questa scelta se hai il documento su carta. Puoi fare una o piu foto con il telefono; GestOre le trasformera in PDF.',
            'choice_paper' => 'Porto una fotocopia a scuola',
            'paper_button' => 'Consegno fotocopia in segreteria',
            'paper_help' => 'Usa questa scelta come alternativa al caricamento online. La segreteria sapra che consegnerai una copia cartacea.',
            'confirm_upload' => 'Conferma caricamento online',
            'upload_document' => 'Carica documento',
            'upload_help' => 'Premi qui per salvare online i file che hai appena aggiunto.',
            'final_section' => 'Invio domanda',
            'final_help' => 'Quando hai controllato i dati e caricato i documenti, oppure indicato quelli che consegnerai in segreteria didattica, puoi inviare definitivamente la domanda.',
            'final_button' => 'SALVA ED INVIA CONFERMA DATI ISCRIZIONE',
            'sent_title' => 'Conferma dati iscrizione gia inviata.',
            'sent_text' => 'Non serve fare altro da questa pagina. La segreteria didattica ha ricevuto la pratica e potra verificare i documenti caricati o indicati come consegna cartacea.',
            'photo_editor_title' => 'Sistema foto',
            'photo_editor_help' => 'Sposta i quattro punti blu sui vertici del documento. Con due dita puoi ruotare la foto; poi premi Conferma foto.',
            'rotate_left' => 'Ruota -90',
            'rotate_right' => 'Ruota +90',
            'fine_rotation' => 'Rotazione fine',
            'apply_rotation' => 'Applica rotazione',
            'reset' => 'Ripristina',
            'confirm_photo' => 'Conferma foto',
            'cancel' => 'Annulla',
            'busy' => 'Elaborazione in corso...',
            'success_title' => 'Conferma inviata',
            'success_main' => 'I dati dell\'iscrizione sono stati salvati e inviati correttamente.',
            'success_text_1' => 'La segreteria didattica ricevera la conferma e potra verificare i documenti caricati o indicati come consegna cartacea.',
            'success_text_2' => 'Una mail di conferma viene inviata agli indirizzi indicati, se l\'invio mail e configurato correttamente.',
            'ok_understood' => 'Ho capito',
            'error_title' => 'Manca un passaggio',
            'error_main' => 'Prima di inviare devi completare i dati richiesti.',
            'error_text' => 'Controlla la pagina e correggi il punto indicato. Poi potrai inviare di nuovo la conferma.',
            'back_to_fix' => 'Torno a correggere',
        ],
        'en' => [
            'language' => 'Language', 'school_kicker' => 'School', 'school_year' => 'School year', 'main_title' => 'Enrollment data confirmation', 'subtitle' => 'Enrollment in first-year classes',
            'intro_notice' => 'Check personal data and update email addresses and phone numbers. You can save a draft and come back using this same link before the final submission.',
            'locked_notice' => 'The application has been checked by the school office. From this link you can view the summary and uploaded documents, but you can no longer edit the form.',
            'invalid_link' => 'Invalid or expired link, or application not available.', 'student' => 'Student', 'confirm_data' => 'Data to confirm', 'documents' => 'Documents',
            'surname' => 'Surname', 'name' => 'Name', 'tax_code' => 'Tax code', 'birth_date' => 'Date of birth', 'course' => 'Course', 'practice_status' => 'Application status',
            'student_email' => 'Student email', 'student_email_hint' => 'Enter only a personal email address for the student. Do not use the lower-secondary school email; leave it blank if not available.',
            'student_phone' => 'Student phone', 'resp1_email' => 'Parent/guardian 1 email', 'resp1_phone' => 'Parent/guardian 1 phone', 'resp2_email' => 'Parent/guardian 2 email', 'resp2_phone' => 'Parent/guardian 2 phone',
            'responsible_1' => 'Parent/guardian 1', 'responsible_2' => 'Parent/guardian 2', 'data_confirm_checkbox' => 'I confirm that the information shown is correct or updated.',
            'save_draft' => 'Save draft', 'save_documents' => 'Save and go to documents',
            'docs_intro' => 'You can upload one or more ready PDF files or, from a phone, take photos of the document. If there are several pages, select or take several photos: GestOre will merge everything into one multi-page PDF.',
            'docs_photo_help' => 'Photos must be clear, straight and readable. Before uploading you can remove a bad photo from the phone selection; after upload you can delete the document and redo it.',
            'privacy_notice' => 'Documents are collected only to manage the enrollment application and the related school procedures.', 'privacy_link' => 'Read the privacy notice about uploaded documents',
            'optional_doc_hint' => 'Optional: upload only if available or if it was not already delivered/paid during enrollment.', 'not_uploaded' => 'Not uploaded yet', 'paper_delivery' => 'Paper copy to be delivered to the school office',
            'paper_badge' => 'Paper copy', 'uploaded_badge' => 'Uploaded', 'missing_badge' => 'Missing', 'view_pdf' => 'View uploaded PDF',
            'choose_one' => 'Choose only one option:', 'choose_123' => '1, 2 or 3.', 'choice_recorded' => 'Choice already saved', 'cancel_paper' => 'Cancel paper-copy choice', 'delete_pdf' => 'Delete uploaded PDF',
            'change_choice_hint' => 'To change your choice, first cancel the saved one, then choose 1, 2 or 3.', 'already_uploaded' => 'PDF already uploaded',
            'append_pdf' => 'Add the new files to the already uploaded PDF.', 'replace_pdf' => 'Replace the already uploaded PDF with the new files.',
            'choice_pdf' => 'I upload a ready PDF', 'add_pdf' => 'Add PDF', 'pdf_help' => 'Use this option if you already have the document as a PDF. You can add more than one PDF: GestOre will merge them into one final file.',
            'choice_photo' => 'I take a photo of the document', 'take_photo' => 'Take photo', 'photo_help' => 'Use this option if the document is on paper. You can take one or more photos with your phone; GestOre will turn them into a PDF.',
            'choice_paper' => 'I will bring a paper copy to school', 'paper_button' => 'I will deliver a paper copy to the office', 'paper_help' => 'Use this option as an alternative to online upload. The office will know that you will bring a paper copy.',
            'confirm_upload' => 'Confirm online upload', 'upload_document' => 'Upload document', 'upload_help' => 'Press here to save online the files you have just added.',
            'final_section' => 'Submit application', 'final_help' => 'When you have checked the data and uploaded the documents, or marked the ones you will deliver to the school office, you can submit the application permanently.',
            'final_button' => 'SAVE AND SEND ENROLLMENT DATA CONFIRMATION', 'sent_title' => 'Enrollment data confirmation already submitted.', 'sent_text' => 'No further action is needed on this page. The school office has received the application and can check the uploaded documents or paper-copy choices.',
        ],
        'fr' => [
            'language' => 'Langue', 'school_kicker' => 'Etablissement scolaire', 'school_year' => 'Annee scolaire', 'main_title' => 'Confirmation des donnees d\'inscription', 'subtitle' => 'Inscription aux classes de premiere annee',
            'intro_notice' => 'Verifiez les donnees personnelles et mettez a jour les emails et telephones. Vous pouvez enregistrer un brouillon et revenir avec ce meme lien avant l\'envoi definitif.',
            'student' => 'Eleve', 'confirm_data' => 'Donnees a confirmer', 'documents' => 'Documents', 'surname' => 'Nom', 'name' => 'Prenom', 'tax_code' => 'Code fiscal', 'birth_date' => 'Date de naissance',
            'course' => 'Filiere', 'practice_status' => 'Etat du dossier', 'student_email' => 'Email de l\'eleve', 'student_email_hint' => 'Indiquez uniquement une adresse personnelle de l\'eleve. N\'utilisez pas l\'email du college; laissez vide si non disponible.',
            'data_confirm_checkbox' => 'Je confirme que les donnees indiquees sont correctes ou mises a jour.', 'save_draft' => 'Enregistrer le brouillon', 'save_documents' => 'Enregistrer et aller aux documents',
            'choose_one' => 'Choisissez une seule possibilite:', 'choose_123' => '1, 2 ou 3.', 'choice_pdf' => 'Je charge un PDF deja pret', 'add_pdf' => 'Ajouter PDF',
            'choice_photo' => 'Je prends une photo du document', 'take_photo' => 'Prendre une photo', 'choice_paper' => 'J\'apporte une photocopie a l\'ecole', 'paper_button' => 'Je remets une photocopie au secretariat',
            'final_button' => 'ENREGISTRER ET ENVOYER LA CONFIRMATION DES DONNEES D\'INSCRIPTION',
        ],
        'de' => [
            'language' => 'Sprache', 'school_kicker' => 'Schule', 'school_year' => 'Schuljahr', 'main_title' => 'Bestaetigung der Einschreibedaten', 'subtitle' => 'Einschreibung in das erste Schuljahr',
            'intro_notice' => 'Pruefen Sie die Daten und aktualisieren Sie E-Mail-Adressen und Telefonnummern. Sie koennen einen Entwurf speichern und vor dem endgueltigen Senden ueber denselben Link zurueckkehren.',
            'student' => 'Schueler/in', 'confirm_data' => 'Zu bestaetigende Daten', 'documents' => 'Dokumente', 'surname' => 'Nachname', 'name' => 'Vorname', 'tax_code' => 'Steuernummer', 'birth_date' => 'Geburtsdatum',
            'course' => 'Bildungsgang', 'practice_status' => 'Status', 'student_email' => 'E-Mail des Schuelers', 'student_email_hint' => 'Bitte nur eine persoenliche E-Mail-Adresse des Schuelers angeben. Keine E-Mail der Mittelschule verwenden; falls nicht vorhanden leer lassen.',
            'data_confirm_checkbox' => 'Ich bestaetige, dass die angegebenen Daten richtig oder aktualisiert sind.', 'save_draft' => 'Entwurf speichern', 'save_documents' => 'Speichern und zu den Dokumenten',
            'choose_one' => 'Waehlen Sie nur eine Moeglichkeit:', 'choose_123' => '1, 2 oder 3.', 'choice_pdf' => 'Ich lade ein fertiges PDF hoch', 'add_pdf' => 'PDF hinzufuegen',
            'choice_photo' => 'Ich fotografiere das Dokument', 'take_photo' => 'Foto aufnehmen', 'choice_paper' => 'Ich bringe eine Papierkopie in die Schule', 'paper_button' => 'Papierkopie im Sekretariat abgeben',
            'final_button' => 'EINSCHREIBEDATEN SPEICHERN UND SENDEN',
        ],
        'ru' => [
            'language' => 'Язык', 'school_kicker' => 'Учебное заведение', 'school_year' => 'Учебный год', 'main_title' => 'Подтверждение данных для зачисления', 'subtitle' => 'Зачисление на первый год обучения',
            'intro_notice' => 'Проверьте личные данные и обновите адреса электронной почты и телефоны. Можно сохранить черновик и вернуться по этой же ссылке до окончательной отправки.',
            'student' => 'Ученик', 'confirm_data' => 'Данные для подтверждения', 'documents' => 'Документы', 'surname' => 'Фамилия', 'name' => 'Имя', 'tax_code' => 'Налоговый код', 'birth_date' => 'Дата рождения',
            'course' => 'Курс', 'practice_status' => 'Статус заявления', 'student_email' => 'Email ученика', 'student_email_hint' => 'Укажите только личный email ученика. Не используйте школьный email средней школы; если email нет, оставьте поле пустым.',
            'data_confirm_checkbox' => 'Подтверждаю, что указанные данные верны или обновлены.', 'save_draft' => 'Сохранить черновик', 'save_documents' => 'Сохранить и перейти к документам',
            'choose_one' => 'Выберите только один вариант:', 'choose_123' => '1, 2 или 3.', 'choice_pdf' => 'Загрузить готовый PDF', 'add_pdf' => 'Добавить PDF',
            'choice_photo' => 'Сфотографировать документ', 'take_photo' => 'Сделать фото', 'choice_paper' => 'Принесу копию в школу', 'paper_button' => 'Передам копию в секретариат',
            'final_button' => 'СОХРАНИТЬ И ОТПРАВИТЬ ПОДТВЕРЖДЕНИЕ ДАННЫХ',
        ],
        'uk' => [
            'language' => 'Мова', 'school_kicker' => 'Навчальний заклад', 'school_year' => 'Навчальний рік', 'main_title' => 'Підтвердження даних для зарахування', 'subtitle' => 'Зарахування на перший рік навчання',
            'intro_notice' => 'Перевірте особисті дані та оновіть електронні адреси і телефони. Можна зберегти чернетку і повернутися за цим самим посиланням до остаточного надсилання.',
            'student' => 'Учень/учениця', 'confirm_data' => 'Дані для підтвердження', 'documents' => 'Документи', 'surname' => 'Прізвище', 'name' => 'Імʼя', 'tax_code' => 'Податковий код', 'birth_date' => 'Дата народження',
            'course' => 'Курс', 'practice_status' => 'Статус заяви', 'student_email' => 'Email учня', 'student_email_hint' => 'Вкажіть лише особистий email учня. Не використовуйте email середньої школи; якщо його немає, залиште поле порожнім.',
            'data_confirm_checkbox' => 'Підтверджую, що зазначені дані правильні або оновлені.', 'save_draft' => 'Зберегти чернетку', 'save_documents' => 'Зберегти і перейти до документів',
            'choose_one' => 'Оберіть лише один варіант:', 'choose_123' => '1, 2 або 3.', 'choice_pdf' => 'Завантажити готовий PDF', 'add_pdf' => 'Додати PDF',
            'choice_photo' => 'Сфотографувати документ', 'take_photo' => 'Зробити фото', 'choice_paper' => 'Принесу копію до школи', 'paper_button' => 'Передам копію до секретаріату',
            'final_button' => 'ЗБЕРЕГТИ І НАДІСЛАТИ ПІДТВЕРДЖЕННЯ ДАНИХ',
        ],
        'zh' => [
            'language' => '语言', 'school_kicker' => '学校', 'school_year' => '学年', 'main_title' => '入学资料确认', 'subtitle' => '高中一年级入学',
            'intro_notice' => '请检查个人资料并更新邮箱和电话号码。最终提交前，您可以保存草稿，并使用同一链接再次进入。',
            'student' => '学生', 'confirm_data' => '需要确认的资料', 'documents' => '文件', 'surname' => '姓', 'name' => '名', 'tax_code' => '税号', 'birth_date' => '出生日期',
            'course' => '课程', 'practice_status' => '申请状态', 'student_email' => '学生邮箱', 'student_email_hint' => '请只填写学生个人邮箱。不要使用初中学校邮箱；没有可留空。',
            'data_confirm_checkbox' => '我确认所显示的信息正确或已更新。', 'save_draft' => '保存草稿', 'save_documents' => '保存并进入文件上传',
            'choose_one' => '只能选择一种方式：', 'choose_123' => '1、2 或 3。', 'choice_pdf' => '上传已有 PDF', 'add_pdf' => '添加 PDF',
            'choice_photo' => '拍摄文件照片', 'take_photo' => '拍照', 'choice_paper' => '我会把复印件带到学校', 'paper_button' => '把复印件交到秘书处',
            'final_button' => '保存并发送入学资料确认',
        ],
        'ar' => [
            'language' => 'اللغة', 'school_kicker' => 'المؤسسة التعليمية', 'school_year' => 'السنة الدراسية', 'main_title' => 'تأكيد بيانات التسجيل', 'subtitle' => 'التسجيل في السنة الدراسية الأولى',
            'intro_notice' => 'يرجى التحقق من البيانات الشخصية وتحديث البريد الإلكتروني وأرقام الهاتف. يمكن حفظ مسودة والعودة من نفس الرابط قبل الإرسال النهائي.',
            'student' => 'الطالب', 'confirm_data' => 'البيانات المطلوب تأكيدها', 'documents' => 'الوثائق', 'surname' => 'اللقب', 'name' => 'الاسم', 'tax_code' => 'الرقم الضريبي', 'birth_date' => 'تاريخ الميلاد',
            'course' => 'المسار', 'practice_status' => 'حالة الطلب', 'student_email' => 'بريد الطالب', 'student_email_hint' => 'يرجى إدخال بريد شخصي للطالب فقط. لا تستخدم بريد المدرسة المتوسطة؛ اتركه فارغا إذا لم يكن متوفرا.',
            'data_confirm_checkbox' => 'أؤكد أن البيانات المذكورة صحيحة أو محدثة.', 'save_draft' => 'حفظ مسودة', 'save_documents' => 'حفظ والانتقال إلى الوثائق',
            'choose_one' => 'اختر إمكانية واحدة فقط:', 'choose_123' => '1 أو 2 أو 3.', 'choice_pdf' => 'أرفع ملف PDF جاهزا', 'add_pdf' => 'إضافة PDF',
            'choice_photo' => 'ألتقط صورة للوثيقة', 'take_photo' => 'التقاط صورة', 'choice_paper' => 'سأحضر نسخة ورقية إلى المدرسة', 'paper_button' => 'تسليم نسخة ورقية إلى السكرتارية',
            'final_button' => 'حفظ وإرسال تأكيد بيانات التسجيل',
        ],
        'ur' => [
            'language' => 'زبان', 'school_kicker' => 'تعلیمی ادارہ', 'school_year' => 'تعلیمی سال', 'main_title' => 'داخلہ کے ڈیٹا کی تصدیق', 'subtitle' => 'پہلے تعلیمی سال میں داخلہ',
            'intro_notice' => 'ذاتی معلومات چیک کریں اور ای میل اور فون نمبر اپ ڈیٹ کریں۔ حتمی ارسال سے پہلے آپ مسودہ محفوظ کر کے اسی لنک سے دوبارہ آ سکتے ہیں۔',
            'student' => 'طالب علم', 'confirm_data' => 'تصدیق کے لیے معلومات', 'documents' => 'دستاویزات', 'surname' => 'خاندانی نام', 'name' => 'نام', 'tax_code' => 'ٹیکس کوڈ', 'birth_date' => 'تاریخ پیدائش',
            'course' => 'کورس', 'practice_status' => 'درخواست کی حالت', 'student_email' => 'طالب علم کی ای میل', 'student_email_hint' => 'صرف طالب علم کی ذاتی ای میل درج کریں۔ مڈل اسکول کی ای میل استعمال نہ کریں؛ موجود نہ ہو تو خالی چھوڑ دیں۔',
            'data_confirm_checkbox' => 'میں تصدیق کرتا/کرتی ہوں کہ درج معلومات درست یا اپ ڈیٹ ہیں۔', 'save_draft' => 'مسودہ محفوظ کریں', 'save_documents' => 'محفوظ کریں اور دستاویزات پر جائیں',
            'choose_one' => 'صرف ایک امکان منتخب کریں:', 'choose_123' => '1، 2 یا 3۔', 'choice_pdf' => 'تیار PDF اپ لوڈ کرتا/کرتی ہوں', 'add_pdf' => 'PDF شامل کریں',
            'choice_photo' => 'دستاویز کی تصویر لیتا/لیتی ہوں', 'take_photo' => 'تصویر لیں', 'choice_paper' => 'کاغذی کاپی اسکول لاؤں گا/گی', 'paper_button' => 'کاپی سیکرٹریٹ میں جمع کراؤں گا/گی',
            'final_button' => 'داخلہ ڈیٹا کی تصدیق محفوظ اور ارسال کریں',
        ],
    ];

    $missing = [
        'fr' => [
            'student_phone' => 'Telephone de l\'eleve',
            'resp1_email' => 'Email responsable 1',
            'resp1_phone' => 'Telephone responsable 1',
            'resp2_email' => 'Email responsable 2',
            'resp2_phone' => 'Telephone responsable 2',
            'responsible_1' => 'Responsable 1',
            'responsible_2' => 'Responsable 2',
            'docs_intro' => 'Vous pouvez charger un ou plusieurs PDF deja prets ou, depuis un telephone, prendre le document en photo. S\'il y a plusieurs pages, selectionnez ou prenez plusieurs photos: GestOre les reunira dans un seul PDF multipage.',
            'docs_photo_help' => 'Les photos doivent etre claires, droites et lisibles. Avant le chargement vous pouvez supprimer une photo mal prise; apres le chargement vous pouvez supprimer le document et le refaire.',
            'privacy_notice' => 'Les documents sont collectes uniquement pour gerer le dossier d\'inscription et les formalites scolaires liees.',
            'privacy_link' => 'Lire la note de confidentialite sur les documents charges',
            'optional_doc_hint' => 'Facultatif: a charger seulement si disponible ou si ce document n\'a pas deja ete remis/paye au moment de l\'inscription.',
            'not_uploaded' => 'Pas encore charge',
            'paper_delivery' => 'Remise papier au secretariat didactique',
            'paper_badge' => 'Papier',
            'uploaded_badge' => 'Charge',
            'missing_badge' => 'Manquant',
            'view_pdf' => 'Voir le PDF charge',
            'choice_recorded' => 'Choix deja enregistre',
            'cancel_paper' => 'Annuler le choix papier',
            'delete_pdf' => 'Supprimer le PDF charge',
            'change_choice_hint' => 'Pour changer de choix, annulez d\'abord celui qui est enregistre, puis choisissez 1, 2 ou 3.',
            'already_uploaded' => 'PDF deja charge',
            'append_pdf' => 'Ajouter les nouveaux fichiers au PDF deja charge.',
            'replace_pdf' => 'Remplacer le PDF deja charge par les nouveaux fichiers.',
            'pdf_help' => 'Utilisez ce choix si vous avez deja le document en PDF. Vous pouvez ajouter plusieurs PDF: GestOre les reunira dans un seul fichier final.',
            'photo_help' => 'Utilisez ce choix si le document est sur papier. Vous pouvez prendre une ou plusieurs photos avec le telephone; GestOre les transformera en PDF.',
            'paper_help' => 'Utilisez ce choix comme alternative au chargement en ligne. Le secretariat saura que vous remettrez une copie papier.',
            'confirm_upload' => 'Confirmer le chargement en ligne',
            'upload_document' => 'Charger le document',
            'upload_help' => 'Appuyez ici pour enregistrer en ligne les fichiers que vous venez d\'ajouter.',
            'final_section' => 'Envoi de la demande',
            'final_help' => 'Lorsque vous avez controle les donnees et charge les documents, ou indique ceux que vous remettrez au secretariat, vous pouvez envoyer definitivement la demande.',
            'sent_title' => 'Confirmation des donnees d\'inscription deja envoyee.',
            'sent_text' => 'Il n\'y a rien d\'autre a faire sur cette page. Le secretariat didactique a recu le dossier et pourra verifier les documents charges ou indiques comme remise papier.',
        ],
        'de' => [
            'student_phone' => 'Telefon des Schuelers',
            'resp1_email' => 'E-Mail Verantwortliche/r 1',
            'resp1_phone' => 'Telefon Verantwortliche/r 1',
            'resp2_email' => 'E-Mail Verantwortliche/r 2',
            'resp2_phone' => 'Telefon Verantwortliche/r 2',
            'responsible_1' => 'Verantwortliche/r 1',
            'responsible_2' => 'Verantwortliche/r 2',
            'docs_intro' => 'Sie koennen ein oder mehrere fertige PDF-Dateien hochladen oder mit dem Telefon Fotos des Dokuments aufnehmen. Bei mehreren Seiten nehmen oder waehlen Sie mehrere Fotos: GestOre fuehrt alles zu einer mehrseitigen PDF-Datei zusammen.',
            'docs_photo_help' => 'Fotos muessen klar, gerade und lesbar sein. Vor dem Hochladen koennen Sie ein schlechtes Foto entfernen; nach dem Hochladen koennen Sie das Dokument loeschen und neu erstellen.',
            'privacy_notice' => 'Die Dokumente werden nur fuer die Bearbeitung der Einschreibung und die damit verbundenen schulischen Verfahren gesammelt.',
            'privacy_link' => 'Datenschutzhinweis zu den hochgeladenen Dokumenten lesen',
            'optional_doc_hint' => 'Optional: nur hochladen, wenn vorhanden oder wenn es bei der Einschreibung noch nicht abgegeben/bezahlt wurde.',
            'not_uploaded' => 'Noch nicht hochgeladen',
            'paper_delivery' => 'Papierabgabe im Sekretariat',
            'paper_badge' => 'Papier',
            'uploaded_badge' => 'Hochgeladen',
            'missing_badge' => 'Fehlt',
            'view_pdf' => 'Hochgeladenes PDF anzeigen',
            'choice_recorded' => 'Auswahl bereits gespeichert',
            'cancel_paper' => 'Papierauswahl annullieren',
            'delete_pdf' => 'Hochgeladenes PDF loeschen',
            'change_choice_hint' => 'Wenn Sie die Auswahl aendern muessen, annullieren Sie zuerst die gespeicherte Auswahl und waehlen dann 1, 2 oder 3.',
            'already_uploaded' => 'PDF bereits hochgeladen',
            'append_pdf' => 'Neue Dateien zum bereits hochgeladenen PDF hinzufuegen.',
            'replace_pdf' => 'Bereits hochgeladenes PDF durch die neuen Dateien ersetzen.',
            'pdf_help' => 'Nutzen Sie diese Auswahl, wenn Sie das Dokument bereits als PDF haben. Sie koennen mehrere PDFs hinzufuegen: GestOre fuehrt sie zu einer Enddatei zusammen.',
            'photo_help' => 'Nutzen Sie diese Auswahl, wenn das Dokument auf Papier vorliegt. Sie koennen ein oder mehrere Fotos mit dem Telefon machen; GestOre wandelt sie in PDF um.',
            'paper_help' => 'Nutzen Sie diese Auswahl als Alternative zum Online-Upload. Das Sekretariat weiss dann, dass Sie eine Papierkopie abgeben.',
            'confirm_upload' => 'Online-Upload bestaetigen',
            'upload_document' => 'Dokument hochladen',
            'upload_help' => 'Hier druecken, um die gerade hinzugefuegten Dateien online zu speichern.',
            'final_section' => 'Antrag senden',
            'final_help' => 'Wenn Sie die Daten kontrolliert und die Dokumente hochgeladen oder die Papierabgabe angegeben haben, koennen Sie den Antrag endgueltig senden.',
            'sent_title' => 'Bestaetigung der Einschreibedaten bereits gesendet.',
            'sent_text' => 'Auf dieser Seite ist nichts weiter zu tun. Das Sekretariat hat den Antrag erhalten und kann die hochgeladenen Dokumente oder Papierabgaben pruefen.',
        ],
        'ru' => [
            'student_phone' => 'Телефон ученика',
            'resp1_email' => 'Email ответственного 1',
            'resp1_phone' => 'Телефон ответственного 1',
            'resp2_email' => 'Email ответственного 2',
            'resp2_phone' => 'Телефон ответственного 2',
            'responsible_1' => 'Ответственный 1',
            'responsible_2' => 'Ответственный 2',
            'docs_intro' => 'Можно загрузить один или несколько готовых PDF-файлов или сфотографировать документ с телефона. Если страниц несколько, выберите или сделайте несколько фотографий: GestOre объединит их в один многостраничный PDF.',
            'docs_photo_help' => 'Фотографии должны быть четкими, ровными и читаемыми. Перед загрузкой можно удалить неудачную фотографию; после загрузки можно удалить документ и сделать его заново.',
            'privacy_notice' => 'Документы собираются только для обработки заявления о зачислении и связанных школьных процедур.',
            'privacy_link' => 'Прочитать уведомление о конфиденциальности загруженных документов',
            'optional_doc_hint' => 'Необязательно: загрузите только если документ доступен или если он еще не был передан/оплачен при записи.',
            'not_uploaded' => 'Еще не загружено',
            'paper_delivery' => 'Бумажная копия будет передана в учебный секретариат',
            'paper_badge' => 'Бумага',
            'uploaded_badge' => 'Загружено',
            'missing_badge' => 'Не хватает',
            'view_pdf' => 'Открыть загруженный PDF',
            'choice_recorded' => 'Выбор уже сохранен',
            'cancel_paper' => 'Отменить бумажную копию',
            'delete_pdf' => 'Удалить загруженный PDF',
            'change_choice_hint' => 'Чтобы изменить выбор, сначала отмените сохраненный вариант, затем выберите 1, 2 или 3.',
            'already_uploaded' => 'PDF уже загружен',
            'append_pdf' => 'Добавить новые файлы к уже загруженному PDF.',
            'replace_pdf' => 'Заменить уже загруженный PDF новыми файлами.',
            'pdf_help' => 'Используйте этот вариант, если документ уже есть в PDF. Можно добавить несколько PDF: GestOre объединит их в один итоговый файл.',
            'photo_help' => 'Используйте этот вариант, если документ на бумаге. Можно сделать одну или несколько фотографий телефоном; GestOre преобразует их в PDF.',
            'paper_help' => 'Используйте этот вариант вместо онлайн-загрузки. Секретариат будет знать, что вы принесете бумажную копию.',
            'confirm_upload' => 'Подтвердить онлайн-загрузку',
            'upload_document' => 'Загрузить документ',
            'upload_help' => 'Нажмите здесь, чтобы сохранить онлайн только что добавленные файлы.',
            'final_section' => 'Отправка заявления',
            'final_help' => 'После проверки данных и загрузки документов, либо отметки документов для передачи в секретариат, можно окончательно отправить заявление.',
            'sent_title' => 'Подтверждение данных уже отправлено.',
            'sent_text' => 'На этой странице больше ничего делать не нужно. Учебный секретариат получил заявление и сможет проверить загруженные документы или бумажные копии.',
        ],
        'uk' => [
            'student_phone' => 'Телефон учня',
            'resp1_email' => 'Email відповідального 1',
            'resp1_phone' => 'Телефон відповідального 1',
            'resp2_email' => 'Email відповідального 2',
            'resp2_phone' => 'Телефон відповідального 2',
            'responsible_1' => 'Відповідальний 1',
            'responsible_2' => 'Відповідальний 2',
            'docs_intro' => 'Можна завантажити один або кілька готових PDF-файлів або сфотографувати документ телефоном. Якщо сторінок кілька, виберіть або зробіть кілька фото: GestOre обʼєднає їх в один багатосторінковий PDF.',
            'docs_photo_help' => 'Фото мають бути чіткими, рівними і читабельними. Перед завантаженням можна видалити невдале фото; після завантаження можна видалити документ і зробити його знову.',
            'privacy_notice' => 'Документи збираються лише для обробки заяви про зарахування і повʼязаних шкільних процедур.',
            'privacy_link' => 'Прочитати повідомлення про конфіденційність завантажених документів',
            'optional_doc_hint' => 'Необовʼязково: завантажуйте лише якщо документ доступний або якщо він ще не був переданий/оплачений під час запису.',
            'not_uploaded' => 'Ще не завантажено',
            'paper_delivery' => 'Паперова копія буде передана до навчального секретаріату',
            'paper_badge' => 'Папір',
            'uploaded_badge' => 'Завантажено',
            'missing_badge' => 'Відсутній',
            'view_pdf' => 'Відкрити завантажений PDF',
            'choice_recorded' => 'Вибір уже збережено',
            'cancel_paper' => 'Скасувати паперову копію',
            'delete_pdf' => 'Видалити завантажений PDF',
            'change_choice_hint' => 'Щоб змінити вибір, спочатку скасуйте збережений варіант, потім виберіть 1, 2 або 3.',
            'already_uploaded' => 'PDF уже завантажено',
            'append_pdf' => 'Додати нові файли до вже завантаженого PDF.',
            'replace_pdf' => 'Замінити вже завантажений PDF новими файлами.',
            'pdf_help' => 'Використайте цей варіант, якщо документ уже є у PDF. Можна додати кілька PDF: GestOre обʼєднає їх в один фінальний файл.',
            'photo_help' => 'Використайте цей варіант, якщо документ на папері. Можна зробити одне або кілька фото телефоном; GestOre перетворить їх у PDF.',
            'paper_help' => 'Використайте цей варіант замість онлайн-завантаження. Секретаріат знатиме, що ви принесете паперову копію.',
            'confirm_upload' => 'Підтвердити онлайн-завантаження',
            'upload_document' => 'Завантажити документ',
            'upload_help' => 'Натисніть тут, щоб зберегти онлайн щойно додані файли.',
            'final_section' => 'Надсилання заяви',
            'final_help' => 'Після перевірки даних і завантаження документів, або позначення документів для передачі до секретаріату, можна остаточно надіслати заяву.',
            'sent_title' => 'Підтвердження даних уже надіслано.',
            'sent_text' => 'На цій сторінці більше нічого робити не потрібно. Навчальний секретаріат отримав заяву і зможе перевірити завантажені документи або паперові копії.',
        ],
        'zh' => [
            'student_phone' => '学生电话',
            'resp1_email' => '监护人 1 邮箱',
            'resp1_phone' => '监护人 1 电话',
            'resp2_email' => '监护人 2 邮箱',
            'resp2_phone' => '监护人 2 电话',
            'responsible_1' => '监护人 1',
            'responsible_2' => '监护人 2',
            'docs_intro' => '您可以上传一个或多个已准备好的 PDF，也可以用手机拍摄文件。如果有多页，请选择或拍摄多张照片：GestOre 会合并成一个多页 PDF。',
            'docs_photo_help' => '照片必须清楚、端正、可阅读。上传前可以删除拍得不好的照片；上传后也可以删除文件并重新制作。',
            'privacy_notice' => '这些文件只用于处理入学申请和相关学校手续。',
            'privacy_link' => '阅读上传文件的隐私说明',
            'optional_doc_hint' => '可选：只有在有该文件，且报名时尚未提交或支付时才上传。',
            'not_uploaded' => '尚未上传',
            'paper_delivery' => '将纸质复印件交给教学秘书处',
            'paper_badge' => '纸质',
            'uploaded_badge' => '已上传',
            'missing_badge' => '缺少',
            'view_pdf' => '查看已上传的 PDF',
            'choice_recorded' => '选择已保存',
            'cancel_paper' => '取消纸质提交选择',
            'delete_pdf' => '删除已上传的 PDF',
            'change_choice_hint' => '如需更改选择，请先取消已保存的选择，然后选择 1、2 或 3。',
            'already_uploaded' => 'PDF 已上传',
            'append_pdf' => '把新文件添加到已上传的 PDF 中。',
            'replace_pdf' => '用新文件替换已上传的 PDF。',
            'pdf_help' => '如果文件已经是 PDF，请使用此选择。可以添加多个 PDF：GestOre 会合并成一个最终文件。',
            'photo_help' => '如果文件是纸质，请使用此选择。可以用手机拍一张或多张照片；GestOre 会转换成 PDF。',
            'paper_help' => '如果不在线上传，可以选择此项。秘书处会知道您将提交纸质复印件。',
            'confirm_upload' => '确认在线上传',
            'upload_document' => '上传文件',
            'upload_help' => '点击这里保存刚添加的文件。',
            'final_section' => '提交申请',
            'final_help' => '检查资料并上传文件，或标记将交到秘书处的文件后，可以最终提交申请。',
            'sent_title' => '入学资料确认已提交。',
            'sent_text' => '此页面无需再操作。教学秘书处已收到申请，并可检查上传文件或纸质提交选择。',
        ],
        'ar' => [
            'student_phone' => 'هاتف الطالب',
            'resp1_email' => 'بريد المسؤول 1',
            'resp1_phone' => 'هاتف المسؤول 1',
            'resp2_email' => 'بريد المسؤول 2',
            'resp2_phone' => 'هاتف المسؤول 2',
            'responsible_1' => 'المسؤول 1',
            'responsible_2' => 'المسؤول 2',
            'docs_intro' => 'يمكنك رفع ملف PDF واحد أو أكثر جاهزا، أو استخدام الهاتف لتصوير الوثيقة. إذا كانت هناك عدة صفحات، اختر أو التقط عدة صور: سيجمعها GestOre في ملف PDF واحد متعدد الصفحات.',
            'docs_photo_help' => 'يجب أن تكون الصور واضحة ومستقيمة ومقروءة. قبل الرفع يمكنك حذف الصورة غير الواضحة؛ وبعد الرفع يمكنك حذف الوثيقة وإعادتها.',
            'privacy_notice' => 'تجمع الوثائق فقط لإدارة طلب التسجيل والإجراءات المدرسية المرتبطة به.',
            'privacy_link' => 'اقرأ بيان الخصوصية حول الوثائق المرفوعة',
            'optional_doc_hint' => 'اختياري: ارفعه فقط إذا كان متوفرا أو إذا لم يتم تسليمه/دفعه عند التسجيل.',
            'not_uploaded' => 'لم يتم الرفع بعد',
            'paper_delivery' => 'تسليم نسخة ورقية إلى السكرتارية التعليمية',
            'paper_badge' => 'ورقي',
            'uploaded_badge' => 'مرفوع',
            'missing_badge' => 'ناقص',
            'view_pdf' => 'عرض ملف PDF المرفوع',
            'choice_recorded' => 'تم حفظ الاختيار',
            'cancel_paper' => 'إلغاء اختيار النسخة الورقية',
            'delete_pdf' => 'حذف ملف PDF المرفوع',
            'change_choice_hint' => 'إذا أردت تغيير الاختيار، ألغ الاختيار المحفوظ أولا ثم اختر 1 أو 2 أو 3.',
            'already_uploaded' => 'تم رفع PDF سابقا',
            'append_pdf' => 'أضف الملفات الجديدة إلى ملف PDF المرفوع سابقا.',
            'replace_pdf' => 'استبدل ملف PDF المرفوع سابقا بالملفات الجديدة.',
            'pdf_help' => 'استخدم هذا الخيار إذا كانت الوثيقة لديك بصيغة PDF. يمكنك إضافة عدة ملفات PDF: سيجمعها GestOre في ملف نهائي واحد.',
            'photo_help' => 'استخدم هذا الخيار إذا كانت الوثيقة على الورق. يمكنك التقاط صورة أو أكثر بالهاتف؛ وسيحولها GestOre إلى PDF.',
            'paper_help' => 'استخدم هذا الخيار كبديل للرفع عبر الإنترنت. ستعرف السكرتارية أنك ستسلم نسخة ورقية.',
            'confirm_upload' => 'تأكيد الرفع عبر الإنترنت',
            'upload_document' => 'رفع الوثيقة',
            'upload_help' => 'اضغط هنا لحفظ الملفات التي أضفتها للتو عبر الإنترنت.',
            'final_section' => 'إرسال الطلب',
            'final_help' => 'عندما تتحقق من البيانات وترفع الوثائق، أو تحدد الوثائق التي ستسلمها إلى السكرتارية، يمكنك إرسال الطلب نهائيا.',
            'sent_title' => 'تم إرسال تأكيد بيانات التسجيل سابقا.',
            'sent_text' => 'لا يلزم القيام بأي شيء آخر من هذه الصفحة. استلمت السكرتارية التعليمية الطلب ويمكنها التحقق من الوثائق المرفوعة أو المحددة كتسليم ورقي.',
        ],
        'ur' => [
            'student_phone' => 'طالب علم کا فون',
            'resp1_email' => 'ذمہ دار 1 کی ای میل',
            'resp1_phone' => 'ذمہ دار 1 کا فون',
            'resp2_email' => 'ذمہ دار 2 کی ای میل',
            'resp2_phone' => 'ذمہ دار 2 کا فون',
            'responsible_1' => 'ذمہ دار 1',
            'responsible_2' => 'ذمہ دار 2',
            'docs_intro' => 'آپ ایک یا زیادہ تیار PDF فائلیں اپ لوڈ کر سکتے ہیں، یا فون سے دستاویز کی تصویر لے سکتے ہیں۔ اگر کئی صفحات ہوں تو کئی تصاویر منتخب کریں یا لیں: GestOre انہیں ایک ہی کئی صفحات والے PDF میں جوڑ دے گا۔',
            'docs_photo_help' => 'تصاویر صاف، سیدھی اور پڑھنے کے قابل ہونی چاہئیں۔ اپ لوڈ سے پہلے خراب تصویر حذف کر سکتے ہیں؛ اپ لوڈ کے بعد دستاویز حذف کر کے دوبارہ بنا سکتے ہیں۔',
            'privacy_notice' => 'دستاویزات صرف داخلہ درخواست اور اس سے متعلقہ اسکولی کارروائیوں کے لیے جمع کی جاتی ہیں۔',
            'privacy_link' => 'اپ لوڈ دستاویزات کے بارے میں رازداری نوٹس پڑھیں',
            'optional_doc_hint' => 'اختیاری: صرف اس صورت میں اپ لوڈ کریں اگر دستیاب ہو یا جنوری کے داخلہ وقت پہلے جمع/ادا نہ کیا گیا ہو۔',
            'not_uploaded' => 'ابھی اپ لوڈ نہیں ہوا',
            'paper_delivery' => 'تعلیمی سیکرٹریٹ میں کاغذی کاپی جمع کرانی ہے',
            'paper_badge' => 'کاغذی',
            'uploaded_badge' => 'اپ لوڈ شدہ',
            'missing_badge' => 'غائب',
            'view_pdf' => 'اپ لوڈ شدہ PDF دیکھیں',
            'choice_recorded' => 'انتخاب محفوظ ہو چکا ہے',
            'cancel_paper' => 'کاغذی انتخاب منسوخ کریں',
            'delete_pdf' => 'اپ لوڈ شدہ PDF حذف کریں',
            'change_choice_hint' => 'اگر انتخاب بدلنا ہو تو پہلے محفوظ انتخاب منسوخ کریں، پھر 1، 2 یا 3 منتخب کریں۔',
            'already_uploaded' => 'PDF پہلے ہی اپ لوڈ ہے',
            'append_pdf' => 'نئی فائلیں پہلے سے اپ لوڈ PDF میں شامل کریں۔',
            'replace_pdf' => 'پہلے سے اپ لوڈ PDF کو نئی فائلوں سے بدل دیں۔',
            'pdf_help' => 'اگر دستاویز پہلے ہی PDF میں ہے تو یہ انتخاب استعمال کریں۔ آپ ایک سے زیادہ PDF شامل کر سکتے ہیں: GestOre انہیں ایک حتمی فائل میں جوڑ دے گا۔',
            'photo_help' => 'اگر دستاویز کاغذ پر ہے تو یہ انتخاب استعمال کریں۔ فون سے ایک یا زیادہ تصاویر لے سکتے ہیں؛ GestOre انہیں PDF میں بدل دے گا۔',
            'paper_help' => 'آن لائن اپ لوڈ کے بدلے یہ انتخاب استعمال کریں۔ سیکرٹریٹ کو معلوم ہو گا کہ آپ کاغذی کاپی جمع کرائیں گے۔',
            'confirm_upload' => 'آن لائن اپ لوڈ کی تصدیق',
            'upload_document' => 'دستاویز اپ لوڈ کریں',
            'upload_help' => 'ابھی شامل کی گئی فائلیں آن لائن محفوظ کرنے کے لیے یہاں دبائیں۔',
            'final_section' => 'درخواست ارسال کریں',
            'final_help' => 'جب آپ ڈیٹا چیک کر لیں اور دستاویزات اپ لوڈ کر دیں، یا جنہیں سیکرٹریٹ میں جمع کرانا ہے انہیں نشان زد کر دیں، تو درخواست حتمی طور پر بھیج سکتے ہیں۔',
            'sent_title' => 'داخلہ ڈیٹا کی تصدیق پہلے ہی بھیجی جا چکی ہے۔',
            'sent_text' => 'اس صفحے پر مزید کچھ کرنے کی ضرورت نہیں۔ تعلیمی سیکرٹریٹ نے درخواست وصول کر لی ہے اور اپ لوڈ دستاویزات یا کاغذی انتخاب کی جانچ کر سکے گا۔',
        ],
    ];

    foreach ($missing as $lang => $values) {
        $translations[$lang] = array_replace($translations[$lang] ?? [], $values);
    }

    return $translations;
}

function trp(string $key): string
{
    global $parentLang, $parentTranslations;
    $vocabulary = iscrizioniPrimeParentTranslationVocabulary();
    return $vocabulary[$parentLang][$key]
        ?? $vocabulary['it'][$key]
        ?? $parentTranslations[$parentLang][$key]
        ?? $parentTranslations['it'][$key]
        ?? $key;
}

function trpWithVars(string $key, array $vars): string
{
    $text = trp($key);
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }

    return $text;
}

function iscrizioniPrimeParentTranslationVocabulary(): array
{
    static $vocabulary = null;
    if ($vocabulary !== null) {
        return $vocabulary;
    }

    $vocabulary = [];
    $path = __DIR__ . '/traduzioni_vocabolario_it.tsv';
    if (!is_readable($path)) {
        return $vocabulary;
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return $vocabulary;
    }

    $headers = fgetcsv($handle, 0, "\t");
    if (!is_array($headers)) {
        fclose($handle);
        return $vocabulary;
    }

    $indexes = array_flip($headers);
    $reservedColumns = ['chiave' => true, 'testo_it' => true, 'nota_contesto' => true];
    $languageColumns = [];
    foreach ($headers as $index => $header) {
        $lang = trim((string)$header);
        if ($lang !== '' && !isset($reservedColumns[$lang])) {
            $languageColumns[$lang] = $index;
        }
    }

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        $key = trim((string)($row[$indexes['chiave'] ?? -1] ?? ''));
        if ($key === '') {
            continue;
        }

        $italian = trim((string)($row[$indexes['testo_it'] ?? -1] ?? ''));
        if ($italian !== '') {
            $vocabulary['it'][$key] = $italian;
        }

        foreach ($languageColumns as $lang => $index) {
            $value = trim((string)($row[$index] ?? ''));
            if ($value !== '') {
                $vocabulary[$lang][$key] = $value;
            }
        }
    }
    fclose($handle);

    return $vocabulary;
}

function trpResponsibleRole(string $value, string $fallbackKey): string
{
    $value = trim($value);
    if ($value === '') {
        return trp($fallbackKey);
    }

    $normalized = strtolower($value);
    $normalized = str_replace(['à', 'è', 'é', 'ì', 'ò', 'ù'], ['a', 'e', 'e', 'i', 'o', 'u'], $normalized);
    $roleKeys = [
        'padre' => 'role_father',
        'madre' => 'role_mother',
        'genitore' => 'role_parent',
        'tutore' => 'role_guardian',
    ];

    return isset($roleKeys[$normalized]) ? trp($roleKeys[$normalized]) : $value;
}

function trpResponsibleContactLabel(int $index, string $kind): string
{
    global $parentLang, $parentTranslations;
    $key = 'resp' . $index . '_' . $kind;
    $genericKey = $kind === 'email' ? 'resp_email' : 'resp_phone';
    $generic = trpWithVars($genericKey, ['n' => $index]);
    if ($generic !== $genericKey) {
        return $generic;
    }

    $labels = [
        'fr' => [
            'email' => ['Email responsable 1', 'Email responsable 2'],
            'phone' => ['Telephone responsable 1', 'Telephone responsable 2'],
        ],
        'de' => [
            'email' => ['E-Mail Verantwortliche/r 1', 'E-Mail Verantwortliche/r 2'],
            'phone' => ['Telefon Verantwortliche/r 1', 'Telefon Verantwortliche/r 2'],
        ],
        'ru' => [
            'email' => ['Email ответственного 1', 'Email ответственного 2'],
            'phone' => ['Телефон ответственного 1', 'Телефон ответственного 2'],
        ],
        'uk' => [
            'email' => ['Email відповідального 1', 'Email відповідального 2'],
            'phone' => ['Телефон відповідального 1', 'Телефон відповідального 2'],
        ],
        'zh' => [
            'email' => ['监护人 1 邮箱', '监护人 2 邮箱'],
            'phone' => ['监护人 1 电话', '监护人 2 电话'],
        ],
        'ar' => [
            'email' => ['بريد المسؤول 1', 'بريد المسؤول 2'],
            'phone' => ['هاتف المسؤول 1', 'هاتف المسؤول 2'],
        ],
        'ur' => [
            'email' => ['ذمہ دار 1 کی ای میل', 'ذمہ دار 2 کی ای میل'],
            'phone' => ['ذمہ دار 1 کا فون', 'ذمہ دار 2 کا فون'],
        ],
    ];

    if (isset($parentTranslations[$parentLang][$key])) {
        return $parentTranslations[$parentLang][$key];
    }

    return $labels[$parentLang][$kind][$index - 1] ?? trp($key);
}

function iscrizioniPrimeParentDocumentLabel(string $tipo): string
{
    global $parentLang;
    $labels = [
        'en' => [
            'pagella' => 'School report',
            'diploma' => 'Final lower-secondary diploma',
            'certificazione_competenze' => 'Skills certification',
            'invalsi' => 'INVALSI',
            'documento_identita_studente' => 'Student identity document',
            'codice_fiscale_studente' => 'Student tax code card',
            'documento_identita_genitore_1' => 'Parent/guardian 1 identity document',
            'codice_fiscale_genitore_1' => 'Parent/guardian 1 tax code card',
            'documento_identita_genitore_2' => 'Parent/guardian 2 identity document',
            'codice_fiscale_genitore_2' => 'Parent/guardian 2 tax code card',
            'attestazione_erogazione_liberale' => 'PagoPA voluntary contribution receipt - 50 euro',
            'altro' => 'Other document',
        ],
        'fr' => [
            'pagella' => 'Bulletin scolaire',
            'diploma' => 'Diplome / certificat final',
            'certificazione_competenze' => 'Certification des competences',
            'documento_identita_studente' => 'Piece d\'identite de l\'eleve',
            'codice_fiscale_studente' => 'Code fiscal de l\'eleve',
            'documento_identita_genitore_1' => 'Piece d\'identite du responsable 1',
            'codice_fiscale_genitore_1' => 'Code fiscal du responsable 1',
            'documento_identita_genitore_2' => 'Piece d\'identite du responsable 2',
            'codice_fiscale_genitore_2' => 'Code fiscal du responsable 2',
            'attestazione_erogazione_liberale' => 'Recu contribution volontaire PagoPA 50 euros',
            'altro' => 'Autre document',
        ],
        'de' => [
            'pagella' => 'Schulzeugnis',
            'diploma' => 'Abschlusszeugnis',
            'certificazione_competenze' => 'Kompetenzbescheinigung',
            'documento_identita_studente' => 'Ausweis des Schuelers',
            'codice_fiscale_studente' => 'Steuernummer des Schuelers',
            'documento_identita_genitore_1' => 'Ausweis des Verantwortlichen 1',
            'codice_fiscale_genitore_1' => 'Steuernummer des Verantwortlichen 1',
            'documento_identita_genitore_2' => 'Ausweis des Verantwortlichen 2',
            'codice_fiscale_genitore_2' => 'Steuernummer des Verantwortlichen 2',
            'attestazione_erogazione_liberale' => 'PagoPA-Spendenbescheinigung 50 Euro',
            'altro' => 'Anderes Dokument',
        ],
        'ru' => [
            'pagella' => 'Табель успеваемости',
            'diploma' => 'Диплом / итоговое свидетельство',
            'certificazione_competenze' => 'Сертификат компетенций',
            'documento_identita_studente' => 'Документ, удостоверяющий личность ученика',
            'codice_fiscale_studente' => 'Налоговый код ученика',
            'documento_identita_genitore_1' => 'Документ ответственного 1',
            'codice_fiscale_genitore_1' => 'Налоговый код ответственного 1',
            'documento_identita_genitore_2' => 'Документ ответственного 2',
            'codice_fiscale_genitore_2' => 'Налоговый код ответственного 2',
            'attestazione_erogazione_liberale' => 'Квитанция добровольного взноса PagoPA 50 евро',
            'altro' => 'Другой документ',
        ],
        'uk' => [
            'pagella' => 'Табель успішності',
            'diploma' => 'Диплом / підсумкове свідоцтво',
            'certificazione_competenze' => 'Сертифікат компетентностей',
            'documento_identita_studente' => 'Документ, що посвідчує особу учня',
            'codice_fiscale_studente' => 'Податковий код учня',
            'documento_identita_genitore_1' => 'Документ відповідального 1',
            'codice_fiscale_genitore_1' => 'Податковий код відповідального 1',
            'documento_identita_genitore_2' => 'Документ відповідального 2',
            'codice_fiscale_genitore_2' => 'Податковий код відповідального 2',
            'attestazione_erogazione_liberale' => 'Квитанція добровільного внеску PagoPA 50 євро',
            'altro' => 'Інший документ',
        ],
        'zh' => [
            'pagella' => '成绩单',
            'diploma' => '毕业证书',
            'certificazione_competenze' => '能力认证',
            'documento_identita_studente' => '学生身份证件',
            'codice_fiscale_studente' => '学生税号',
            'documento_identita_genitore_1' => '监护人 1 身份证件',
            'codice_fiscale_genitore_1' => '监护人 1 税号',
            'documento_identita_genitore_2' => '监护人 2 身份证件',
            'codice_fiscale_genitore_2' => '监护人 2 税号',
            'attestazione_erogazione_liberale' => 'PagoPA 自愿捐款 50 欧元凭证',
            'altro' => '其他文件',
        ],
        'ar' => [
            'pagella' => 'كشف الدرجات',
            'diploma' => 'الشهادة النهائية',
            'certificazione_competenze' => 'شهادة الكفاءات',
            'documento_identita_studente' => 'وثيقة هوية الطالب',
            'codice_fiscale_studente' => 'الرقم الضريبي للطالب',
            'documento_identita_genitore_1' => 'وثيقة هوية المسؤول 1',
            'codice_fiscale_genitore_1' => 'الرقم الضريبي للمسؤول 1',
            'documento_identita_genitore_2' => 'وثيقة هوية المسؤول 2',
            'codice_fiscale_genitore_2' => 'الرقم الضريبي للمسؤول 2',
            'attestazione_erogazione_liberale' => 'إيصال مساهمة PagoPA الاختيارية 50 يورو',
            'altro' => 'وثيقة أخرى',
        ],
        'ur' => [
            'pagella' => 'رپورٹ کارڈ',
            'diploma' => 'آخری سند',
            'certificazione_competenze' => 'مہارتوں کی تصدیق',
            'documento_identita_studente' => 'طالب علم کی شناختی دستاویز',
            'codice_fiscale_studente' => 'طالب علم کا ٹیکس کوڈ',
            'documento_identita_genitore_1' => 'ذمہ دار 1 کی شناختی دستاویز',
            'codice_fiscale_genitore_1' => 'ذمہ دار 1 کا ٹیکس کوڈ',
            'documento_identita_genitore_2' => 'ذمہ دار 2 کی شناختی دستاویز',
            'codice_fiscale_genitore_2' => 'ذمہ دار 2 کا ٹیکس کوڈ',
            'attestazione_erogazione_liberale' => 'PagoPA رضاکارانہ ادائیگی 50 یورو کی رسید',
            'altro' => 'دوسری دستاویز',
        ],
    ];

    $fromVocabulary = trp('doc.' . $tipo);
    if ($fromVocabulary !== 'doc.' . $tipo) {
        return $fromVocabulary;
    }

    return $labels[$parentLang][$tipo] ?? (iscrizioniPrimeDocumentTypes($GLOBALS['pratica'] ?? [])[$tipo] ?? $tipo);
}

$token = trim((string)($_GET['t'] ?? ''));
$adminPreview = false;
$previewId = intval($_GET['preview_id'] ?? 0);
if ($previewId > 0) {
    require_once '../common/checkSession.php';
    ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
    $adminPreview = true;
    $token = 'admin_preview:' . $previewId;
}
$parentLanguages = iscrizioniPrimeParentLanguagesClean();
$parentTranslations = iscrizioniPrimeParentTranslations();
$parentLang = strtolower(trim((string)($_GET['lang'] ?? 'it')));
if (!isset($parentLanguages[$parentLang])) {
    $parentLang = 'it';
}
$parentDir = $parentLanguages[$parentLang]['dir'];
$pratica = iscrizioniPrimeGetByToken($token);
$confirmed = [];
$documents = [];
$isTerze = $pratica && iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze';
$allowedDocumentTypes = $pratica ? array_keys(iscrizioniPrimeDocumentTypes($pratica)) : [];
$materieSeconda = $isTerze ? iscrizioniTerzeMaterieSeconda() : [];
$annoScolastico = $pratica ? trim((string)($pratica['anno_scolastico'] ?? '')) : '';
if ($annoScolastico === '') {
    $annoScolastico = '2026-27';
}
$nomeIstituto = trim((string)($__settings->local->nomeIstituto ?? 'ITT Buonarroti - Trento'));
$classeTargetLabel = $isTerze
    ? 'Iscrizione alle classi terze'
    : trp('subtitle');
$praticaInviata = $pratica && (
    in_array((string)($pratica['stato'] ?? ''), ['inviata', 'verifica_iniziale_ok', 'da_integrare', 'verificata'], true)
    || trim((string)($pratica['dati_confermati_json'] ?? '')) !== ''
);
$praticaBloccata = $pratica && in_array((string)($pratica['stato'] ?? ''), ['verificata', 'annullata'], true);

if (!$pratica) {
    http_response_code(404);
} elseif (!empty($pratica['dati_confermati_json'])) {
    $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
    if (is_array($decoded)) {
        $confirmed = $decoded;
    }
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
} elseif ($pratica) {
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
}

$dataInvioPratica = '';
if ($praticaInviata) {
    $sentValue = trim((string)($confirmed['saved_at'] ?? ''));
    if ($sentValue === '') {
        $sentValue = trim((string)($pratica['updated_at'] ?? ''));
    }
    if ($sentValue !== '') {
        $timestampInvio = strtotime($sentValue);
        if ($timestampInvio) {
            $dataInvioPratica = date('d/m/Y H:i', $timestampInvio);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo h($parentLang); ?>" dir="<?php echo h($parentDir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h(trp('page_title')); ?></title>
    <link rel="icon" href="<?php echo h($__application_base_path); ?>/ore-32.png" type="image/png">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #172033; }
        .page { max-width: 920px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border: 1px solid #d9e0ea; border-radius: 8px; box-shadow: 0 8px 28px rgba(23,32,51,.08); padding: 18px; margin: 14px 0; }
        .school-title-card { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 380px); gap: 18px; align-items: center; }
        .school-header { display: flex; gap: 14px; align-items: center; min-width: 0; }
        .school-logo { width: 76px; height: 58px; object-fit: contain; flex: 0 0 auto; }
        .school-kicker { color: #475569; font-size: 14px; font-weight: 750; text-transform: uppercase; letter-spacing: .02em; }
        .school-name { font-size: 18px; font-weight: 800; margin-top: 2px; }
        .school-year { display: inline-block; margin-top: 8px; border-radius: 999px; background: #e0f2fe; color: #075985; padding: 5px 10px; font-size: 13px; font-weight: 800; }
        .language-switch { display: flex; justify-content: stretch; }
        .language-switch label { width: 100%; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px 12px; align-items: center; font-size: 16px; color: #172033; background: #fff7ed; border: 3px solid #fb923c; border-radius: 10px; padding: 12px 14px; box-shadow: 0 10px 28px rgba(194,65,12,.22); }
        .language-title { font-size: 18px; line-height: 1.12; font-weight: 900; color: #7c2d12; }
        .language-subtitle { display: block; margin-top: 3px; font-size: 13px; color: #9a3412; font-weight: 800; }
        .language-flag { grid-row: span 2; font-size: 54px; line-height: 1; min-width: 64px; text-align: center; filter: drop-shadow(0 4px 8px rgba(15,23,42,.18)); }
        .language-switch select { grid-column: 2; border: 2px solid #c2410c; border-radius: 8px; padding: 11px 12px; font: inherit; font-weight: 800; background: #fff; color: #172033; min-width: 0; width: 100%; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        h3 { font-size: 15px; margin: 18px 0 10px; }
        .muted { color: #64748b; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .field { border-bottom: 1px solid #edf1f6; padding: 8px 0; }
        .label { color: #64748b; font-size: 13px; margin-bottom: 4px; }
        .value { font-weight: 650; overflow-wrap: anywhere; }
        .notice { border-left: 4px solid #0ea5e9; background: #eaf6fc; padding: 12px; border-radius: 6px; }
        .resubmit-notice { border: 2px solid #f59e0b; border-left-width: 7px; background: #fffbeb; color: #78350f; padding: 14px; border-radius: 8px; font-weight: 750; box-shadow: 0 8px 26px rgba(146,64,14,.16); }
        .resubmit-notice strong { display: block; font-size: 18px; margin-bottom: 6px; color: #7c2d12; }
        .error { border-left-color: #dc2626; background: #fee2e2; }
        .success { border-left-color: #16a34a; background: #e9f8ef; }
        .privacy-link { display: inline-block; margin-top: 8px; color: #0369a1; font-weight: 750; }
        .form-row { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        label { font-weight: 650; }
        .hint { color: #64748b; font-size: 13px; line-height: 1.35; }
        input[type="email"], input[type="tel"], input[type="text"], input[type="date"], select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 11px 12px; font: inherit; background: #fff; color: #172033; }
        input:focus { border-color: #0ea5e9; outline: 3px solid rgba(14,165,233,.18); }
        .terze-extra { border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 8px; padding: 14px; margin-top: 18px; }
        .terze-extra h3 { margin-top: 0; }
        .terze-panel { border: 1px solid #dbeafe; background: #fff; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .radio-stack { display: grid; gap: 9px; margin: 8px 0 12px; }
        .radio-stack label { display: flex; gap: 9px; align-items: flex-start; color: #334155; }
        .radio-stack input { margin-top: 4px; flex: 0 0 auto; }
        select[multiple] { min-height: 150px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        button { border: 0; border-radius: 6px; padding: 11px 16px; font: inherit; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #334155; color: #fff; }
        .btn-primary:disabled, .btn-secondary:disabled { opacity: .65; cursor: wait; }
        .check { display: flex; gap: 9px; align-items: flex-start; margin-top: 10px; color: #334155; }
        .check input { margin-top: 4px; }
        .status-line { min-height: 24px; margin-top: 12px; font-weight: 650; }
        .doc-list { display: grid; gap: 12px; }
        .doc-item { border: 1px solid var(--doc-border, #d9e0ea); border-left: 7px solid var(--doc-accent, var(--doc-border, #d9e0ea)); border-radius: 8px; padding: 12px; background: var(--doc-bg, #fbfdff); box-shadow: inset 0 0 0 9999px rgba(255,255,255,.18); }
        .doc-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 8px; }
        .doc-meta { min-width: 0; }
        .doc-title { font-weight: 750; }
        .doc-current { overflow-wrap: anywhere; }
        .badge { border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 750; background: #e2e8f0; color: #334155; white-space: nowrap; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-paper { background: #fef3c7; color: #92400e; }
        .doc-upload { display: grid; gap: 12px; margin-top: 10px; }
        .doc-action-group { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; }
        .doc-choice-intro { border: 2px solid var(--doc-accent, #0ea5e9); background: rgba(255,255,255,.72); border-radius: 8px; padding: 10px; font-weight: 800; color: #172033; }
        .doc-form.is-complete { padding-top: 10px; padding-bottom: 10px; }
        .doc-form.is-complete .doc-head { margin-bottom: 0; }
        .doc-form.is-complete .doc-upload { margin-top: 8px; }
        .doc-form.is-complete .doc-final-group,
        .doc-form.is-complete .photo-preview,
        .doc-form.is-complete .doc-pending,
        .doc-form.is-complete .doc-status { display: none !important; }
        .doc-form.is-paper .doc-choice-intro,
        .doc-form.is-paper .doc-choice-group,
        .doc-form.is-paper .doc-existing-options { display: none !important; }
        .doc-form.is-complete .doc-current { font-size: 13px; }
        .doc-form.is-complete .doc-clear-group { padding: 8px; }
        .doc-form.is-pending .doc-choice-intro,
        .doc-form.is-pending .doc-choice-group,
        .doc-form.is-pending .doc-existing-options,
        .doc-form.is-pending .doc-clear-group { display: none !important; }
        .doc-form.is-missing .doc-clear-group,
        .doc-form.is-missing .doc-existing-options { display: none !important; }
        .readonly-documents .doc-upload,
        .readonly-documents .photo-preview,
        .readonly-documents .doc-pending,
        .readonly-documents .doc-status { display: none !important; }
        .doc-action-title { color: #334155; font-size: 13px; font-weight: 750; margin-bottom: 8px; }
        .doc-action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .doc-mode-options { display: grid; gap: 8px; }
        .doc-mode-options label { display: flex; gap: 8px; align-items: flex-start; font-weight: 650; color: #334155; }
        .doc-mode-options input { margin-top: 3px; }
        .doc-upload input[type="file"] { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
        .doc-upload button { background: #0ea5e9; color: #fff; }
        .doc-choice-title { font-size: 16px; font-weight: 800; margin-bottom: 4px; color: #172033; }
        .doc-choice-step { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 999px; background: #172033; color: #fff; font-weight: 800; margin-right: 6px; }
        .doc-final-group { border-color: #bbf7d0; background: #f0fdf4; }
        .btn-file { background: #0ea5e9 !important; color: #fff; }
        .btn-native-camera { background: #2563eb !important; color: #fff; }
        .btn-add-photo { background: #1d4ed8 !important; color: #fff; }
        .btn-paper { background: #a16207 !important; color: #fff; }
        .btn-delete { background: #b91c1c !important; color: #fff; }
        .btn-final { background: #0f766e !important; color: #fff; }
        .btn-submit-final { background: #7c2d12; color: #fff; font-size: 17px; }
        .btn-final:disabled { opacity: .55; cursor: not-allowed; }
        .doc-help { margin: 8px 0 0; color: #475569; font-size: 13px; line-height: 1.4; }
        .doc-pending { margin-top: 10px; padding: 9px 10px; border-radius: 6px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-weight: 700; display: none; }
        .doc-view { display: inline-block; margin-top: 8px; color: #0369a1; font-weight: 700; }
        .photo-preview { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .photo-chip { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px; background: #fff; display: flex; align-items: center; gap: 6px; max-width: 100%; }
        .photo-chip img { width: 52px; height: 52px; object-fit: cover; border-radius: 4px; flex: 0 0 auto; }
        .photo-chip button { background: #b91c1c; color: #fff; padding: 6px 9px; }
        .camera-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,.72); z-index: 2000; padding: 14px; }
        .camera-modal.open { display: flex; }
        .camera-panel { width: min(760px, 100%); max-height: calc(100vh - 28px); overflow: auto; background: #fff; border-radius: 8px; padding: 14px; box-shadow: 0 16px 42px rgba(0,0,0,.28); display: flex; flex-direction: column; }
        .camera-video { width: 100%; max-height: 70vh; background: #0f172a; border-radius: 6px; }
        .camera-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; position: sticky; bottom: -14px; background: #fff; padding: 10px 0 calc(10px + env(safe-area-inset-bottom)); z-index: 2; }
        .camera-actions button { background: #334155; color: #fff; }
        .camera-actions .btn-primary { background: #0f766e; }
        .edit-image { max-width: 100%; max-height: 68vh; display: block; }
        .edit-canvas { width: 100%; max-height: 58vh; display: block; background: #111827; border-radius: 6px; touch-action: none; transform-origin: center center; transition: transform .08s linear; }
        .rotate-control { display: flex; align-items: center; gap: 10px; flex: 1 1 100%; color: #334155; font-weight: 700; }
        .rotate-control input { flex: 1; min-width: 140px; }
        .busy-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 3000; background: rgba(15,23,42,.72); padding: 16px; }
        .busy-overlay.open { display: flex; }
        .busy-box { background: #fff; border-radius: 8px; padding: 18px; width: min(360px, 100%); text-align: center; font-weight: 750; box-shadow: 0 18px 46px rgba(0,0,0,.3); }
        .busy-spinner { width: 42px; height: 42px; margin: 0 auto 12px; border: 5px solid #dbeafe; border-top-color: #2563eb; border-radius: 999px; animation: spin 1s linear infinite; }
        .success-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 3200; background: rgba(15,23,42,.76); padding: 16px; }
        .success-overlay.open { display: flex; }
        .success-card { width: min(520px, 100%); background: #fff; border-radius: 8px; padding: 24px; text-align: center; box-shadow: 0 22px 56px rgba(0,0,0,.34); border-top: 8px solid #15803d; }
        .success-card.warning { border-top-color: #b45309; }
        .success-icon { width: 74px; height: 74px; margin: 0 auto 14px; border-radius: 999px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 46px; font-weight: 900; line-height: 1; }
        .success-card.warning .success-icon { background: #fef3c7; color: #b45309; }
        .success-card h2 { margin: 0 0 8px; font-size: 26px; color: #172033; }
        .success-card p { margin: 8px 0; color: #334155; font-size: 16px; line-height: 1.45; }
        .success-card .success-main { font-weight: 750; color: #14532d; }
        .success-card.warning .success-main { color: #7c2d12; }
        .success-card button { margin-top: 16px; background: #15803d; color: #fff; width: 100%; }
        .success-card.warning button { background: #b45309; }
        .error-list { text-align: left; margin: 12px 0 4px; padding-left: 20px; color: #7c2d12; font-weight: 750; line-height: 1.45; }
        html[dir="rtl"] body { direction: rtl; }
        html[dir="rtl"] .school-header { flex-direction: row-reverse; text-align: right; }
        html[dir="rtl"] .notice { border-left: 0; border-right: 4px solid #0ea5e9; }
        html[dir="rtl"] .resubmit-notice { border-left-width: 2px; border-right-width: 7px; }
        html[dir="rtl"] .error { border-right-color: #dc2626; }
        html[dir="rtl"] .success { border-right-color: #16a34a; }
        html[dir="rtl"] .doc-item { border-left-width: 1px; border-right: 7px solid var(--doc-accent, var(--doc-border, #d9e0ea)); }
        html[dir="rtl"] .doc-choice-step { margin-right: 0; margin-left: 6px; }
        html[dir="rtl"] .error-list { text-align: right; padding-left: 0; padding-right: 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 680px) {
            .grid { grid-template-columns: 1fr; }
            .page { padding: 10px; }
            .school-title-card { grid-template-columns: 1fr; }
            .school-header { align-items: flex-start; }
            .school-logo { width: 68px; height: 52px; }
            .language-switch label { grid-template-columns: auto minmax(0, 1fr); }
            .language-flag { font-size: 48px; min-width: 58px; }
            .actions button { width: 100%; }
            .doc-head { align-items: flex-start; }
            .doc-action-buttons { display: grid; grid-template-columns: 1fr 1fr; }
            .doc-action-buttons button { width: 100%; padding-left: 8px; padding-right: 8px; }
            .doc-action-buttons.single { grid-template-columns: 1fr; }
            .doc-help { font-size: 12px; }
            .photo-chip { width: 100%; align-items: flex-start; }
            .photo-chip span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
            .camera-modal { padding: 6px; align-items: stretch; }
            .camera-panel { max-height: calc(100vh - 12px); border-radius: 6px; }
            .camera-actions button { flex: 1 1 42%; }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="card">
        <div class="school-title-card">
            <div>
                <div class="school-header">
                    <img class="school-logo" src="<?php echo h($__application_base_path); ?>/img/logoB_google.png" alt="Logo <?php echo h($nomeIstituto); ?>">
                    <div>
                        <div class="school-kicker"><?php echo h(trp('school_kicker')); ?></div>
                        <div class="school-name"><?php echo h($nomeIstituto); ?></div>
                        <div class="school-year"><?php echo h(trp('school_year')); ?> <?php echo h($annoScolastico); ?></div>
                    </div>
                </div>
                <h1 style="margin-top: 18px;"><?php echo h(trp('main_title')); ?></h1>
                <div class="muted"><?php echo h($classeTargetLabel); ?></div>
            </div>
            <form class="language-switch" method="get">
                <?php if ($adminPreview && $previewId > 0) : ?>
                    <input type="hidden" name="preview_id" value="<?php echo intval($previewId); ?>">
                <?php else : ?>
                    <input type="hidden" name="t" value="<?php echo h($token); ?>">
                <?php endif; ?>
                <label>
                    <span class="language-flag" aria-hidden="true"><?php echo iscrizioniPrimeLanguageFlagHtml($parentLang); ?></span>
                    <span class="language-title">
                        Cambia lingua
                        <span class="language-subtitle"><?php echo h(trp('language')); ?> / Language</span>
                    </span>
                    <select name="lang" onchange="this.form.submit()" aria-label="Cambia lingua">
                        <?php foreach ($parentLanguages as $code => $info) : ?>
                            <option value="<?php echo h($code); ?>" <?php echo $parentLang === $code ? 'selected' : ''; ?>><?php echo h($info['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>
    </div>

    <?php if (!$pratica) : ?>
        <div class="card notice error">
            <?php echo h(trp('invalid_link')); ?>
        </div>
    <?php else : ?>
        <?php if ($adminPreview) : ?>
            <div class="card notice">
                Accesso riservato alla segreteria: questa apertura non rigenera il token e non modifica il link inviato alla famiglia.
            </div>
        <?php elseif ($praticaBloccata) : ?>
            <div class="card notice success">
                <?php echo h(trp('locked_notice')); ?>
            </div>
        <?php else : ?>
            <div class="card notice">
                <?php echo h(trp('intro_notice')); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?php echo h(trp('student')); ?></h2>
            <div class="grid">
                <div class="field"><div class="label"><?php echo h(trp('surname')); ?></div><div class="value"><?php echo h($pratica['cognome']); ?></div></div>
                <div class="field"><div class="label"><?php echo h(trp('name')); ?></div><div class="value"><?php echo h($pratica['nome']); ?></div></div>
                <div class="field"><div class="label"><?php echo h(trp('tax_code')); ?></div><div class="value"><?php echo h($pratica['codice_fiscale']); ?></div></div>
                <div class="field"><div class="label"><?php echo h(trp('birth_date')); ?></div><div class="value"><?php echo h(iscrizioniPrimeFormatDateIt($pratica['data_nascita'] ?? '')); ?></div></div>
                <div class="field"><div class="label"><?php echo h(trp('course')); ?></div><div class="value"><?php echo h($pratica['corso_studi']); ?></div></div>
                <div class="field"><div class="label"><?php echo h(trp('practice_status')); ?></div><div class="value"><?php echo h($pratica['stato']); ?></div></div>
            </div>
        </div>

        <form id="iscrizioneForm" class="card" autocomplete="on">
            <h2><?php echo h(trp('confirm_data')); ?></h2>
            <input type="hidden" name="token" value="<?php echo h($token); ?>">

            <h3><?php echo h(trp('student')); ?></h3>
            <div class="grid">
                <div class="form-row">
                    <label for="email_studente"><?php echo h(trp('student_email')); ?></label>
                    <input type="email" id="email_studente" name="email_studente" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_studente')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                    <div class="hint"><?php echo h(trp('student_email_hint')); ?></div>
                </div>
                <div class="form-row">
                    <label for="telefono_studente"><?php echo h(trp('student_phone')); ?></label>
                    <input type="tel" id="telefono_studente" name="telefono_studente" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_studente')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <h3><?php echo h(trpResponsibleRole((string)($pratica['responsabile_1_tipo'] ?? ''), 'responsible_1')); ?></h3>
            <div class="field">
                <div class="label"><?php echo h(trp('name')); ?></div>
                <div class="value"><?php echo h(trim(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? ''))); ?></div>
            </div>
            <div class="grid">
                <div class="form-row">
                    <label for="email_genitore_1"><?php echo h(trpResponsibleContactLabel(1, 'email')); ?></label>
                    <input type="email" id="email_genitore_1" name="email_genitore_1" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_genitore_1')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
                <div class="form-row">
                    <label for="telefono_genitore_1"><?php echo h(trpResponsibleContactLabel(1, 'phone')); ?></label>
                    <input type="tel" id="telefono_genitore_1" name="telefono_genitore_1" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_genitore_1')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <h3><?php echo h(trpResponsibleRole((string)($pratica['responsabile_2_tipo'] ?? ''), 'responsible_2')); ?></h3>
            <div class="field">
                <div class="label"><?php echo h(trp('name')); ?></div>
                <div class="value"><?php echo h(trim(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? ''))); ?></div>
            </div>
            <div class="grid">
                <div class="form-row">
                    <label for="email_genitore_2"><?php echo h(trpResponsibleContactLabel(2, 'email')); ?></label>
                    <input type="email" id="email_genitore_2" name="email_genitore_2" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_genitore_2')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
                <div class="form-row">
                    <label for="telefono_genitore_2"><?php echo h(trpResponsibleContactLabel(2, 'phone')); ?></label>
                    <input type="tel" id="telefono_genitore_2" name="telefono_genitore_2" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_genitore_2')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <?php if ($isTerze) :
                $nullaOstaChecked = !empty($confirmed['nulla_osta_richiesto']) || !empty($pratica['nulla_osta_richiesto']);
                $nullaOstaData = (string)($confirmed['nulla_osta_data'] ?? ($pratica['nulla_osta_data'] ?? ''));
                $carenzeDichiarate = (string)($confirmed['carenze_formative_dichiarate'] ?? ($pratica['carenze_formative_dichiarate'] ?? ''));
                $materieSelezionate = $confirmed['carenze_formative_materie'] ?? null;
                if (!is_array($materieSelezionate)) {
                    $decodedMaterie = json_decode((string)($pratica['carenze_formative_materie'] ?? '[]'), true);
                    $materieSelezionate = is_array($decodedMaterie) ? $decodedMaterie : [];
                }
                $carenzeAltro = (string)($confirmed['carenze_formative_altro'] ?? ($pratica['carenze_formative_altro'] ?? ''));
            ?>
                <div class="terze-extra">
                    <h3><?php echo h(trp('terze_specific_title')); ?></h3>
                    <div class="terze-panel">
                        <h3><?php echo h(trp('nulla_osta_title')); ?></h3>
                        <div class="hint"><?php echo h(trp('nulla_osta_help')); ?></div>
                        <label class="check">
                            <input type="checkbox" name="nulla_osta_richiesto" value="1" <?php echo $nullaOstaChecked ? 'checked' : ''; ?> <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                            <span><?php echo h(trp('nulla_osta_checkbox')); ?></span>
                        </label>
                        <div class="form-row">
                            <label for="nulla_osta_data"><?php echo h(trp('nulla_osta_date')); ?></label>
                            <input type="date" id="nulla_osta_data" name="nulla_osta_data" value="<?php echo h($nullaOstaData); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="terze-panel">
                        <h3><?php echo h(trp('carenze_title')); ?></h3>
                        <div class="form-row">
                            <label><?php echo h(trp('carenze_question')); ?></label>
                            <div class="radio-stack">
                                <label>
                                    <input type="radio" name="carenze_formative_dichiarate" value="no" <?php echo $carenzeDichiarate === 'no' ? 'checked' : ''; ?> <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                                    <span><?php echo h(trp('carenze_no')); ?></span>
                                </label>
                                <label>
                                    <input type="radio" name="carenze_formative_dichiarate" value="si" <?php echo $carenzeDichiarate === 'si' ? 'checked' : ''; ?> <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                                    <span><?php echo h(trp('carenze_yes')); ?></span>
                                </label>
                            </div>
                        </div>
                        <div id="carenzeMaterieBox" <?php echo $carenzeDichiarate === 'si' ? '' : 'hidden'; ?>>
                            <div class="form-row">
                                <label for="carenzeMaterieSelect"><?php echo h(trp('carenze_subjects')); ?></label>
                                <div class="hint"><?php echo h(trp('carenze_subjects_help')); ?></div>
                                <select id="carenzeMaterieSelect" name="carenze_formative_materie[]" multiple <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                                    <?php foreach ($materieSeconda as $materiaSeconda) : ?>
                                        <option value="<?php echo h($materiaSeconda); ?>" <?php echo in_array($materiaSeconda, $materieSelezionate, true) ? 'selected' : ''; ?>><?php echo h($materiaSeconda); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__ALTRO__" <?php echo $carenzeAltro !== '' ? 'selected' : ''; ?>>ALTRO</option>
                                </select>
                            </div>
                            <div id="carenzeAltroRow" class="form-row" <?php echo $carenzeAltro !== '' ? '' : 'hidden'; ?>>
                                <label for="carenze_formative_altro"><?php echo h(trp('carenze_other')); ?></label>
                                <input type="text" id="carenze_formative_altro" name="carenze_formative_altro" value="<?php echo h($carenzeAltro); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <label class="check">
                <input type="checkbox" name="privacy_confermata" value="1" <?php echo !empty($confirmed['privacy_confermata']) ? 'checked' : ''; ?> <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                <span><?php echo h(trp('data_confirm_checkbox')); ?></span>
            </label>

            <?php if (!$praticaBloccata) : ?>
                <div class="actions">
                    <button type="submit" class="btn-primary" data-action="draft"><?php echo h(trp('save_draft')); ?></button>
                    <button type="submit" class="btn-secondary" data-action="documents"><?php echo h(trp('save_documents')); ?></button>
                </div>
            <?php endif; ?>
            <div id="saveStatus" class="status-line" aria-live="polite"></div>
        </form>

        <div class="card <?php echo $praticaBloccata ? 'readonly-documents' : ''; ?>">
            <h2><?php echo h(trp('documents')); ?></h2>
            <div class="muted"><?php echo h(trp('docs_intro')); ?></div>
            <div class="doc-help"><?php echo h(trp('docs_photo_help')); ?></div>
            <div class="notice" style="margin-top: 12px;">
                <?php echo h(trp('privacy_notice')); ?>
                <br>
                <a class="privacy-link" href="privacy_documenti.php?t=<?php echo rawurlencode($token); ?>&lang=<?php echo rawurlencode($parentLang); ?>" target="_blank" rel="noopener"><?php echo h(trp('privacy_link')); ?></a>
            </div>
            <div class="doc-list" style="margin-top: 14px;">
                <?php
                $documentColors = [
                    ['#bfdbfe', '#60a5fa', '#1d4ed8'],
                    ['#bbf7d0', '#4ade80', '#15803d'],
                    ['#fed7aa', '#fb923c', '#c2410c'],
                    ['#fde68a', '#facc15', '#a16207'],
                    ['#fbcfe8', '#f472b6', '#be185d'],
                    ['#ddd6fe', '#a78bfa', '#6d28d9'],
                    ['#99f6e4', '#2dd4bf', '#0f766e'],
                    ['#fecaca', '#f87171', '#b91c1c'],
                ];
                $documentIndex = 0;
                foreach ($documents as $document) :
                    $tipo = (string)$document['tipo_documento'];
                    if (!in_array($tipo, $allowedDocumentTypes, true)) {
                        continue;
                    }
                    if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsible($pratica, $confirmed)) {
                        continue;
                    }
                    $documentColor = $documentColors[$documentIndex % count($documentColors)];
                    $documentIndex++;
                    $label = iscrizioniPrimeParentDocumentLabel($tipo);
                    $isOptional = in_array($tipo, ['attestazione_erogazione_liberale', 'altro'], true);
                    $isPaper = (string)$document['stato'] === 'consegna_cartacea';
                    $isUploaded = !$isPaper && (string)$document['stato'] !== 'mancante' && !empty($document['original_name']);
                    $documentStatusText = $isPaper ? trp('paper_delivery') : ($isUploaded ? (string)$document['original_name'] : trp('not_uploaded'));
                    $badgeText = $isPaper ? trp('paper_badge') : ($isUploaded ? trp('uploaded_badge') : trp('missing_badge'));
                    $badgeClass = $isPaper ? 'badge-paper' : ($isUploaded ? 'badge-ok' : '');
                    $viewUrl = 'visualizza_documento.php?t=' . rawurlencode($token) . '&tipo=' . rawurlencode($tipo);
                ?>
                    <form class="doc-item doc-form" enctype="multipart/form-data" data-doc-state="<?php echo $isPaper ? 'paper' : ($isUploaded ? 'uploaded' : 'missing'); ?>" style="--doc-bg: <?php echo h($documentColor[0]); ?>; --doc-border: <?php echo h($documentColor[1]); ?>; --doc-accent: <?php echo h($documentColor[2]); ?>;">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">
                        <input type="hidden" name="tipo_documento" value="<?php echo h($tipo); ?>">
                        <div class="doc-head">
                            <div class="doc-meta">
                                <div class="doc-title"><?php echo h($label); ?></div>
                                <?php if ($isOptional) : ?>
                                    <div class="hint"><?php echo h(trp('optional_doc_hint')); ?></div>
                                <?php endif; ?>
                                <div class="muted doc-current"><?php echo h($documentStatusText); ?></div>
                                <?php if ($isUploaded) : ?>
                                    <a class="doc-view" href="<?php echo h($viewUrl); ?>" target="_blank" rel="noopener"><?php echo h(trp('view_pdf')); ?></a>
                                <?php else : ?>
                                    <a class="doc-view" href="<?php echo h($viewUrl); ?>" target="_blank" rel="noopener" hidden><?php echo h(trp('view_pdf')); ?></a>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?php echo h($badgeClass); ?>"><?php echo h($badgeText); ?></span>
                        </div>
                        <div class="doc-upload">
                            <input type="file" class="doc-file-input" name="documento[]" accept="application/pdf,.pdf" multiple>
                            <input type="file" class="doc-native-camera-input" accept="image/jpeg,image/png" capture="environment" multiple>
                            <input type="hidden" name="upload_mode" value="<?php echo $isUploaded ? 'append' : 'replace'; ?>" class="doc-upload-mode">
                            <div class="doc-choice-intro">
                                <div><?php echo h(trp('choose_one')); ?></div>
                                <div><?php echo h(trp('choose_123')); ?></div>
                            </div>
                            <div class="doc-action-group doc-clear-group" <?php echo ($isUploaded || $isPaper) ? '' : 'hidden'; ?>>
                                <div class="doc-action-title"><?php echo h(trp('choice_recorded')); ?></div>
                                <div class="doc-action-buttons single">
                                    <button type="button" class="btn-delete doc-delete"><?php echo $isPaper ? h(trp('cancel_paper')) : h(trp('delete_pdf')); ?></button>
                                </div>
                                <div class="doc-help"><?php echo h(trp('change_choice_hint')); ?></div>
                            </div>
                            <div class="doc-action-group doc-existing-options" <?php echo $isUploaded ? '' : 'hidden'; ?>>
                                <div class="doc-action-title"><?php echo h(trp('already_uploaded')); ?></div>
                                <div class="doc-mode-options">
                                    <label>
                                        <input type="radio" name="upload_mode_choice_<?php echo h($tipo); ?>" value="append" checked>
                                        <span><?php echo h(trp('append_pdf')); ?></span>
                                    </label>
                                    <label>
                                        <input type="radio" name="upload_mode_choice_<?php echo h($tipo); ?>" value="replace">
                                        <span><?php echo h(trp('replace_pdf')); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">1</span><?php echo h(trp('choice_pdf')); ?></div>
                                <div class="doc-action-buttons">
                                    <button type="button" class="btn-file doc-file-button"><?php echo h(trp('add_pdf')); ?></button>
                                </div>
                                <div class="doc-help"><?php echo h(trp('pdf_help')); ?></div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">2</span><?php echo h(trp('choice_photo')); ?></div>
                                <div class="doc-action-buttons">
                                    <button type="button" class="btn-native-camera doc-native-camera"><?php echo h(trp('take_photo')); ?></button>
                                </div>
                                <div class="doc-help"><?php echo h(trp('photo_help')); ?></div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">3</span><?php echo h(trp('choice_paper')); ?></div>
                                <div class="doc-action-buttons single">
                                    <button type="button" class="btn-paper doc-paper" <?php echo $isPaper ? 'hidden' : ''; ?>><?php echo h(trp('paper_button')); ?></button>
                                </div>
                                <div class="doc-help"><?php echo h(trp('paper_help')); ?></div>
                            </div>
                            <div class="doc-action-group doc-final-group" hidden>
                                <div class="doc-action-title doc-final-title"><?php echo h(trp('confirm_upload')); ?></div>
                                <div class="doc-action-buttons doc-final-buttons single">
                                    <button type="button" class="btn-add-photo doc-add-photo" hidden>Aggiungi altra foto</button>
                                    <button type="submit" class="btn-final doc-upload-button" disabled><?php echo h(trp('upload_document')); ?></button>
                                </div>
                                <div class="doc-help doc-final-help"><?php echo h(trp('upload_help')); ?></div>
                            </div>
                        </div>
                        <div class="photo-preview"></div>
                        <div class="doc-pending"></div>
                        <div class="status-line muted doc-status" aria-live="polite"></div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($praticaInviata && !$praticaBloccata) : ?>
            <div class="card resubmit-notice" id="resubmitNotice">
                <strong>Attenzione: dopo ogni modifica devi reinviare la conferma.</strong>
                Se cancelli, sostituisci, aggiungi allegati o cambi una scelta di consegna, la segreteria vede la modifica solo dopo che premi nuovamente
                <em>SALVA ED INVIA CONFERMA DATI ISCRIZIONE</em> in fondo alla pagina.
            </div>
        <?php endif; ?>

        <?php if ($praticaBloccata || $praticaInviata) : ?>
            <div class="card notice success">
                <strong><?php echo h(trp('sent_title')); ?></strong><br>
                <?php if ($dataInvioPratica !== '') : ?>
                    La conferma dati iscrizione e' stata inviata il <?php echo h($dataInvioPratica); ?>.<br>
                <?php endif; ?>
                <?php if ($praticaInviata && !$praticaBloccata) : ?>
                    La segreteria didattica ha ricevuto la pratica. Puoi ancora aggiornare o integrare gli allegati caricati finche' la pratica non viene verificata dalla segreteria.
                <?php else : ?>
                    <?php echo h(trp('sent_text')); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$praticaBloccata) : ?>
            <div class="card">
                <h2><?php echo h(trp('final_section')); ?></h2>
                <?php if ($praticaInviata) : ?>
                    <div class="resubmit-notice">
                        <strong>Hai modificato o integrato la pratica?</strong>
                        Premi il pulsante qui sotto per reinviare la conferma aggiornata alla segreteria didattica.
                    </div>
                <?php else : ?>
                    <div class="muted"><?php echo h(trp('final_help')); ?></div>
                <?php endif; ?>
                <div class="actions">
                    <button type="button" id="submitApplication" class="btn-submit-final">
                        <?php echo $praticaInviata ? 'SALVA E REINVIA CONFERMA DATI ISCRIZIONE' : h(trp('final_button')); ?>
                    </button>
                </div>
                <div id="submitStatus" class="status-line" aria-live="polite"></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<div id="editPhotoModal" class="camera-modal" aria-hidden="true">
    <div class="camera-panel">
        <h2><?php echo h(trp('photo_editor_title')); ?></h2>
        <canvas id="editPhotoCanvas" class="edit-canvas"></canvas>
        <canvas id="editOutputCanvas" hidden></canvas>
        <img id="editPhotoImage" class="edit-image" alt="Foto da ritagliare" hidden>
        <div class="doc-help"><?php echo h(trp('photo_editor_help')); ?></div>
        <div class="camera-actions">
            <button type="button" id="editRotateLeft"><?php echo h(trp('rotate_left')); ?></button>
            <button type="button" id="editRotateRight"><?php echo h(trp('rotate_right')); ?></button>
            <label class="rotate-control" for="editRotateFine">
                <?php echo h(trp('fine_rotation')); ?>
                <input type="range" id="editRotateFine" min="-12" max="12" step="0.5" value="0">
                <span id="editRotateFineValue">0</span>
            </label>
            <button type="button" id="editApplyFineRotate"><?php echo h(trp('apply_rotation')); ?></button>
            <button type="button" id="editSkewLeft" hidden>Raddrizza -1</button>
            <button type="button" id="editSkewRight" hidden>Raddrizza +1</button>
            <button type="button" id="editReset"><?php echo h(trp('reset')); ?></button>
            <button type="button" id="editConfirm" class="btn-primary"><?php echo h(trp('confirm_photo')); ?></button>
            <button type="button" id="editCancel"><?php echo h(trp('cancel')); ?></button>
        </div>
        <div id="editPhotoStatus" class="status-line muted" aria-live="polite"></div>
    </div>
</div>
<div id="busyOverlay" class="busy-overlay" aria-hidden="true">
    <div class="busy-box">
        <div class="busy-spinner"></div>
        <div id="busyOverlayText"><?php echo h(trp('busy')); ?></div>
    </div>
</div>
<div id="submitSuccessOverlay" class="success-overlay" aria-hidden="true">
    <div class="success-card" role="dialog" aria-modal="true" aria-labelledby="submitSuccessTitle">
        <div class="success-icon" aria-hidden="true">✓</div>
        <h2 id="submitSuccessTitle"><?php echo h(trp('success_title')); ?></h2>
        <p class="success-main"><?php echo h(trp('success_main')); ?></p>
        <p><?php echo h(trp('success_text_1')); ?></p>
        <p><?php echo h(trp('success_text_2')); ?></p>
        <button type="button" id="submitSuccessClose"><?php echo h(trp('ok_understood')); ?></button>
    </div>
</div>
<div id="submitErrorOverlay" class="success-overlay" aria-hidden="true">
    <div class="success-card warning" role="dialog" aria-modal="true" aria-labelledby="submitErrorTitle">
        <div class="success-icon" aria-hidden="true">!</div>
        <h2 id="submitErrorTitle"><?php echo h(trp('error_title')); ?></h2>
        <p id="submitErrorMessage" class="success-main"><?php echo h(trp('error_main')); ?></p>
        <p><?php echo h(trp('error_text')); ?></p>
        <button type="button" id="submitErrorClose"><?php echo h(trp('back_to_fix')); ?></button>
    </div>
</div>
<?php if ($pratica) : ?>
<script src="<?php echo h($__application_base_path); ?>/common/opencvjs/opencv.js"></script>
<script>
const pageToken = <?php echo json_encode($token, JSON_UNESCAPED_SLASHES); ?>;
let activeCropperObjectUrl = '';
let activeCropperForm = null;
let activeCropperIndex = null;
let perspectiveImage = null;
let perspectiveSourceCanvas = document.createElement('canvas');
let perspectivePoints = [];
let perspectiveDraggingPoint = null;
let perspectiveGesture = null;
let perspectiveViewZoom = 1;
const pendingNativeImages = new WeakMap();

function updateCarenzeMaterieVisibility() {
    const box = document.getElementById('carenzeMaterieBox');
    if (!box) {
        return;
    }
    const selected = document.querySelector('input[name="carenze_formative_dichiarate"]:checked');
    box.hidden = !selected || selected.value !== 'si';
    updateCarenzeAltroVisibility();
}

function updateCarenzeAltroVisibility() {
    const row = document.getElementById('carenzeAltroRow');
    const select = document.getElementById('carenzeMaterieSelect');
    if (!row || !select) {
        return;
    }
    const hasOther = Array.from(select.selectedOptions).some((option) => option.value === '__ALTRO__');
    row.hidden = hasOther === false;
}

document.querySelectorAll('input[name="carenze_formative_dichiarate"]').forEach(function (input) {
    input.addEventListener('change', updateCarenzeMaterieVisibility);
});
const carenzeMaterieSelect = document.getElementById('carenzeMaterieSelect');
if (carenzeMaterieSelect) {
    carenzeMaterieSelect.addEventListener('change', updateCarenzeAltroVisibility);
}
updateCarenzeMaterieVisibility();

function readyFilesInfo(files) {
    const count = files.length;
    const imageCount = files.filter((file) => file.type.startsWith('image/')).length;
    const pdfCount = files.filter((file) => file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')).length;
    const allPdf = count > 0 && pdfCount === count;
    const allImages = count > 0 && imageCount === count;

    if (allPdf) {
        return {
            button: count > 1 ? 'Unisci PDF e carica' : 'Carica PDF',
            pending: count > 1
                ? count + ' PDF pronti. Premi Unisci PDF e carica per salvarli in un unico documento online.'
                : 'PDF pronto. Premi Carica PDF per salvarlo online.',
            help: count > 1
                ? 'GestOre unira i PDF selezionati in un unico documento finale.'
                : 'GestOre salvera online il PDF selezionato.',
            status: count > 1
                ? count + ' PDF pronti da caricare.'
                : 'PDF pronto da caricare.'
        };
    }

    if (allImages) {
        return {
            button: 'Crea PDF dalle foto e carica',
            pending: count > 1
                ? count + ' foto pronte. Premi Crea PDF dalle foto e carica per salvarle in un unico documento online.'
                : 'Foto pronta. Premi Crea PDF dalle foto e carica per salvare il documento online.',
            help: 'GestOre trasformera le foto in un PDF finale.',
            status: count > 1
                ? count + ' foto pronte da trasformare in PDF.'
                : 'Foto pronta da trasformare in PDF.'
        };
    }

    return {
        button: 'Crea PDF finale e carica',
        pending: count + ' file pronti. Premi Crea PDF finale e carica per salvare il documento online.',
        help: 'GestOre preparera un unico PDF finale con i file selezionati.',
        status: count + ' file pronti da caricare.'
    };
}

function setDocumentUiState(form, state) {
    form.dataset.docState = state;
    form.classList.toggle('is-missing', state === 'missing');
    form.classList.toggle('is-pending', state === 'pending');
    form.classList.toggle('is-complete', state === 'uploaded' || state === 'paper');
    form.classList.toggle('is-uploaded', state === 'uploaded');
    form.classList.toggle('is-paper', state === 'paper');

    const intro = form.querySelector('.doc-choice-intro');
    const choices = form.querySelectorAll('.doc-choice-group');
    const clearGroup = form.querySelector('.doc-clear-group');
    const existingOptions = form.querySelector('.doc-existing-options');
    const finalGroup = form.querySelector('.doc-final-group');
    const hasFinalFiles = form.querySelector('.doc-file-input').files.length > 0;
    const uploadMode = form.querySelector('.doc-upload-mode');
    const appendChoice = form.querySelector('.doc-mode-options input[value="append"]');
    const replaceChoice = form.querySelector('.doc-mode-options input[value="replace"]');
    const canAddToExisting = state === 'uploaded';

    if (intro) intro.hidden = !(state === 'missing' || canAddToExisting);
    choices.forEach((group) => group.hidden = !(state === 'missing' || canAddToExisting));
    if (clearGroup) clearGroup.hidden = !(state === 'uploaded' || state === 'paper');
    if (existingOptions) existingOptions.hidden = !canAddToExisting;
    if (finalGroup) finalGroup.hidden = !(state === 'pending' && hasFinalFiles);
    if (canAddToExisting && uploadMode && uploadMode.value !== 'append' && uploadMode.value !== 'replace') {
        uploadMode.value = 'append';
    }
    if (canAddToExisting && uploadMode && uploadMode.value === 'append' && appendChoice) {
        appendChoice.checked = true;
    }
    if (canAddToExisting && uploadMode && uploadMode.value === 'replace' && replaceChoice) {
        replaceChoice.checked = true;
    }
}

function refreshPhotoPreview(form) {
    const input = form.querySelector('.doc-file-input');
    const preview = form.querySelector('.photo-preview');
    const pending = form.querySelector('.doc-pending');
    const uploadButton = form.querySelector('.doc-upload-button');
    const addPhotoButton = form.querySelector('.doc-add-photo');
    const finalButtons = form.querySelector('.doc-final-buttons');
    const finalGroup = form.querySelector('.doc-final-group');
    const finalHelp = form.querySelector('.doc-final-help');
    preview.innerHTML = '';

    const previousState = form.dataset.docState || 'missing';
    const files = Array.from(input.files);
    const info = files.length ? readyFilesInfo(files) : null;
    const uploadMode = form.querySelector('.doc-upload-mode');
    const isAppending = uploadMode && uploadMode.value === 'append' && previousState === 'uploaded';
    const hasImages = files.some((file) => file.type.startsWith('image/'));
    uploadButton.disabled = files.length === 0;
    if (info) {
        uploadButton.textContent = isAppending ? 'Aggiungi al PDF caricato' : info.button;
        if (finalHelp) {
            finalHelp.textContent = isAppending
                ? 'GestOre aggiungera questi file al PDF gia caricato e salvera un unico PDF finale.'
                : info.help;
        }
    }
    if (addPhotoButton) {
        addPhotoButton.hidden = !hasImages;
        addPhotoButton.textContent = files.length > 1 ? 'Aggiungi altra foto / pagina' : 'Aggiungi foto del retro o altra pagina';
    }
    if (finalButtons) {
        finalButtons.classList.toggle('single', !hasImages);
    }
    if (finalGroup) {
        finalGroup.hidden = files.length === 0;
    }
    if (files.length) {
        if (previousState !== 'pending') {
            form.dataset.previousDocState = previousState;
        }
        setDocumentUiState(form, 'pending');
        pending.style.display = 'block';
        pending.textContent = isAppending
            ? info.status + ' Premi Aggiungi al PDF caricato per unirli al documento gia presente.'
            : info.pending;
    } else {
        const emptyState = form.dataset.docState === 'pending'
            ? (form.dataset.previousDocState || 'missing')
            : (form.dataset.docState || 'missing');
        delete form.dataset.previousDocState;
        setDocumentUiState(form, emptyState);
        pending.style.display = 'none';
        pending.textContent = '';
    }

    files.forEach(function (file, index) {
        const chip = document.createElement('div');
        chip.className = 'photo-chip';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(img.src); };
            chip.appendChild(img);
        }

        const label = document.createElement('span');
        label.textContent = file.name;
        chip.appendChild(label);

        if (file.type.startsWith('image/')) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.textContent = 'Sistema';
            edit.style.background = '#475569';
            edit.addEventListener('click', function () {
                openPhotoEditor(form, file, index);
            });
            chip.appendChild(edit);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Togli';
        remove.addEventListener('click', function () {
            const transfer = new DataTransfer();
            Array.from(input.files).forEach(function (currentFile, currentIndex) {
                if (currentIndex !== index) {
                    transfer.items.add(currentFile);
                }
            });
            input.files = transfer.files;
            refreshPhotoPreview(form);
        });
        chip.appendChild(remove);
        preview.appendChild(chip);
    });
}

function replaceFileInInput(form, file, index) {
    const input = form.querySelector('.doc-file-input');
    const transfer = new DataTransfer();
    Array.from(input.files).forEach(function (currentFile, currentIndex) {
        if (index === null || currentIndex !== index) {
            transfer.items.add(currentFile);
        }
        if (currentIndex === index) {
            transfer.items.add(file);
        }
    });
    if (index === null) {
        transfer.items.add(file);
    }
    input.files = transfer.files;
    refreshPhotoPreview(form);
}

function appendReadyFile(form, file) {
    const input = form.querySelector('.doc-file-input');
    const transfer = new DataTransfer();
    Array.from(input.files).forEach((currentFile) => transfer.items.add(currentFile));
    transfer.items.add(file);
    input.files = transfer.files;
    refreshPhotoPreview(form);
}

function processNextNativeImage(form) {
    const queue = pendingNativeImages.get(form) || [];
    const status = form.querySelector('.doc-status');

    if (!queue.length) {
        pendingNativeImages.delete(form);
        const files = Array.from(form.querySelector('.doc-file-input').files);
        status.textContent = files.length ? readyFilesInfo(files).status : '';
        return;
    }

    const next = queue.shift();
    pendingNativeImages.set(form, queue);
    status.textContent = 'Sistema la foto ' + next.position + ' di ' + next.total + ', poi confermala.';
    openPhotoEditor(form, next.file, null);
}

function makePhotoFile(blob, prefix) {
    return new File([blob], prefix + '_' + Date.now() + '.jpg', { type: 'image/jpeg' });
}

function showBusy(message) {
    const overlay = document.getElementById('busyOverlay');
    document.getElementById('busyOverlayText').textContent = message || 'Elaborazione in corso...';
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function hideBusy() {
    const overlay = document.getElementById('busyOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
}

async function readJsonResponse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (error) {
        const looksHtml = /^\s*</.test(text);
        if (looksHtml && (response.status === 401 || response.status === 403 || response.redirected)) {
            return {
                ok: false,
                message: 'Sessione scaduta o accesso non autorizzato. Riapri la pagina dal pannello segreteria e riprova.'
            };
        }
        return {
            ok: false,
            message: looksHtml
                ? 'Il server ha restituito una pagina di errore invece di una risposta JSON. Riprova dopo aver ricaricato la pagina.'
                : (text || 'Risposta del server non valida.')
        };
    }
}

function showSubmitSuccess() {
    const overlay = document.getElementById('submitSuccessOverlay');
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function showSubmitError(message) {
    const overlay = document.getElementById('submitErrorOverlay');
    const title = document.getElementById('submitErrorTitle');
    const messageBox = document.getElementById('submitErrorMessage');
    const text = message || 'Prima di inviare devi completare i dati richiesti.';

    title.textContent = 'Manca un passaggio';
    messageBox.textContent = text;

    const marker = 'questi documenti:';
    if (text.indexOf(marker) !== -1) {
        const before = text.slice(0, text.indexOf(marker) + marker.length);
        const after = text.slice(text.indexOf(marker) + marker.length).replace(/\.$/, '');
        const items = after.split(',').map((item) => item.trim()).filter(Boolean);
        title.textContent = 'Documenti mancanti';
        messageBox.innerHTML = escapeHtml(before) + '<ul class="error-list">' + items.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>';
    }

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function showResubmitReminder() {
    const notice = document.getElementById('resubmitNotice');
    const submitButton = document.getElementById('submitApplication');
    if (!notice || !submitButton) {
        return;
    }

    notice.innerHTML = '<strong>Modifica registrata: ora devi reinviare la conferma.</strong>'
        + ' Hai cambiato uno o piu documenti. Scorri in fondo alla pagina e premi '
        + '<em>SALVA E REINVIA CONFERMA DATI ISCRIZIONE</em>, altrimenti la segreteria non riceve la conferma aggiornata.';
    notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
    submitButton.style.boxShadow = '0 0 0 4px rgba(245, 158, 11, .35)';
}

function cvReady() {
    return typeof cv !== 'undefined' && cv.Mat && cv.getPerspectiveTransform;
}

function getPointEventPosition(event, canvas) {
    const touch = event.touches && event.touches.length ? event.touches[0] : event;
    const rect = canvas.getBoundingClientRect();
    return {
        x: (touch.clientX - rect.left) * (canvas.width / rect.width),
        y: (touch.clientY - rect.top) * (canvas.height / rect.height)
    };
}

function getTouchMetrics(event, canvas) {
    if (!event.touches || event.touches.length < 2) {
        return null;
    }
    const p1 = getPointEventPosition({ touches: [event.touches[0]] }, canvas);
    const p2 = getPointEventPosition({ touches: [event.touches[1]] }, canvas);
    return {
        angle: Math.atan2(p2.y - p1.y, p2.x - p1.x) * 180 / Math.PI,
        distance: Math.hypot(p2.x - p1.x, p2.y - p1.y)
    };
}

function clampNumber(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function normalizeGestureAngle(degrees) {
    let normalized = degrees;
    while (normalized > 180) {
        normalized -= 360;
    }
    while (normalized < -180) {
        normalized += 360;
    }
    return normalized;
}

function applyPerspectiveViewZoom() {
    const canvas = document.getElementById('editPhotoCanvas');
    if (!canvas) {
        return;
    }
    canvas.style.transform = '';
    canvas.style.width = perspectiveViewZoom > 1.01 ? Math.round(perspectiveViewZoom * 100) + '%' : '100%';
    canvas.style.maxHeight = perspectiveViewZoom > 1.01 ? 'none' : '58vh';
}

function initPerspectivePoints(canvas) {
    const mx = canvas.width * 0.09;
    const my = canvas.height * 0.09;
    perspectivePoints = [
        { x: mx, y: my },
        { x: canvas.width - mx, y: my },
        { x: canvas.width - mx, y: canvas.height - my },
        { x: mx, y: canvas.height - my }
    ];
}

function orderDetectedPoints(points) {
    const ordered = [null, null, null, null];
    points.forEach(function (point) {
        point.sum = point.x + point.y;
        point.diff = point.x - point.y;
    });
    ordered[0] = points.reduce((best, point) => point.sum < best.sum ? point : best, points[0]);
    ordered[2] = points.reduce((best, point) => point.sum > best.sum ? point : best, points[0]);
    ordered[1] = points.reduce((best, point) => point.diff > best.diff ? point : best, points[0]);
    ordered[3] = points.reduce((best, point) => point.diff < best.diff ? point : best, points[0]);
    return ordered.map(function (point) {
        return { x: point.x, y: point.y };
    });
}

function contourExtremePoints(contour) {
    const points = [];
    for (let i = 0; i < contour.rows; i++) {
        points.push({
            x: contour.intPtr(i, 0)[0],
            y: contour.intPtr(i, 0)[1]
        });
    }
    return points.length ? orderDetectedPoints(points) : [];
}

function approxContourPoints(contour) {
    const peri = cv.arcLength(contour, true);
    const hull = new cv.Mat();
    const epsilons = [0.01, 0.015, 0.025, 0.04, 0.06, 0.085];

    try {
        cv.convexHull(contour, hull, false, true);
        for (let epsilonIndex = 0; epsilonIndex < epsilons.length; epsilonIndex++) {
            const approx = new cv.Mat();
            cv.approxPolyDP(hull, approx, epsilons[epsilonIndex] * peri, true);

            if (approx.rows === 4) {
                const points = [];
                for (let i = 0; i < 4; i++) {
                    points.push({
                        x: approx.intPtr(i, 0)[0],
                        y: approx.intPtr(i, 0)[1]
                    });
                }
                approx.delete();
                return orderDetectedPoints(points);
            }
            approx.delete();
        }
    } finally {
        hull.delete();
    }

    return [];
}

function createDetectionMasks(gray, blurred) {
    const masks = [];
    const edges = new cv.Mat();
    const edgeDilated = new cv.Mat();
    const thresholdLow = new cv.Mat();
    const thresholdMid = new cv.Mat();
    const thresholdOtsu = new cv.Mat();
    const kernel = cv.Mat.ones(7, 7, cv.CV_8U);

    cv.Canny(blurred, edges, 35, 130);
    cv.dilate(edges, edgeDilated, kernel);
    cv.morphologyEx(edgeDilated, edgeDilated, cv.MORPH_CLOSE, kernel);
    masks.push(edgeDilated);
    edges.delete();

    cv.threshold(gray, thresholdLow, 28, 255, cv.THRESH_BINARY);
    cv.morphologyEx(thresholdLow, thresholdLow, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdLow);

    cv.threshold(gray, thresholdMid, 45, 255, cv.THRESH_BINARY);
    cv.morphologyEx(thresholdMid, thresholdMid, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdMid);

    cv.threshold(blurred, thresholdOtsu, 0, 255, cv.THRESH_BINARY + cv.THRESH_OTSU);
    cv.morphologyEx(thresholdOtsu, thresholdOtsu, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdOtsu);

    kernel.delete();
    return masks;
}

function detectDocumentCorners(canvas) {
    if (!cvReady()) {
        return false;
    }

    let src = null;
    let gray = null;
    let blurred = null;
    let contours = null;
    let hierarchy = null;
    let best = null;
    let fallback = null;
    let masks = [];

    try {
        src = cv.imread(canvas);
        gray = new cv.Mat();
        blurred = new cv.Mat();

        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0);
        masks = createDetectionMasks(gray, blurred);

        const minArea = canvas.width * canvas.height * 0.055;
        for (let maskIndex = 0; maskIndex < masks.length; maskIndex++) {
            contours = new cv.MatVector();
            hierarchy = new cv.Mat();
            cv.findContours(masks[maskIndex], contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);

            for (let i = 0; i < contours.size(); i++) {
                const contour = contours.get(i);
                const area = cv.contourArea(contour);
                if (area < minArea) {
                    contour.delete();
                    continue;
                }

                const rect = cv.boundingRect(contour);
                const rectArea = rect.width * rect.height;
                if (rect.width < canvas.width * 0.18 || rect.height < canvas.height * 0.18 || rectArea < minArea) {
                    contour.delete();
                    continue;
                }

                const approxPoints = approxContourPoints(contour);
                if (approxPoints.length === 4 && area > (best ? best.area : 0)) {
                    best = { area: area, points: approxPoints };
                }

                const extremePoints = contourExtremePoints(contour);
                if (extremePoints.length === 4 && area > (fallback ? fallback.area : 0)) {
                    fallback = { area: area, points: extremePoints };
                }
                contour.delete();
            }

            contours.delete();
            contours = null;
            hierarchy.delete();
            hierarchy = null;
        }

        const points = best ? best.points : (fallback ? fallback.points : []);
        if (points.length !== 4) {
            return false;
        }
        perspectivePoints = points;
        return true;
    } catch (error) {
        return false;
    } finally {
        if (src) src.delete();
        if (gray) gray.delete();
        if (blurred) blurred.delete();
        if (contours) contours.delete();
        if (hierarchy) hierarchy.delete();
        masks.forEach(function (mask) {
            mask.delete();
        });
    }
}

function drawPerspectiveEditor() {
    const canvas = document.getElementById('editPhotoCanvas');
    const ctx = canvas.getContext('2d');

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!perspectiveImage) {
        return;
    }

    ctx.drawImage(perspectiveImage, 0, 0, canvas.width, canvas.height);
    ctx.save();
    ctx.lineWidth = Math.max(3, canvas.width / 220);
    ctx.strokeStyle = '#38bdf8';
    ctx.fillStyle = 'rgba(14, 165, 233, .16)';
    ctx.beginPath();
    perspectivePoints.forEach(function (point, index) {
        if (index === 0) {
            ctx.moveTo(point.x, point.y);
        } else {
            ctx.lineTo(point.x, point.y);
        }
    });
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    perspectivePoints.forEach(function (point, index) {
        ctx.beginPath();
        ctx.fillStyle = '#0284c7';
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.arc(point.x, point.y, Math.max(10, canvas.width / 70), 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 13px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(index + 1), point.x, point.y);
    });
    ctx.restore();
}

function nearestPerspectivePoint(pos) {
    let nearest = null;
    let distance = Infinity;
    perspectivePoints.forEach(function (point, index) {
        const current = Math.hypot(point.x - pos.x, point.y - pos.y);
        if (current < distance) {
            nearest = index;
            distance = current;
        }
    });
    return distance < 42 ? nearest : null;
}

function setupPerspectiveCanvasEvents() {
    const canvas = document.getElementById('editPhotoCanvas');
    if (canvas.dataset.ready === '1') {
        return;
    }
    canvas.dataset.ready = '1';

    function start(event) {
        if (event.touches && event.touches.length >= 2) {
            const metrics = getTouchMetrics(event, canvas);
            if (metrics) {
                perspectiveGesture = {
                    startAngle: metrics.angle,
                    startDistance: Math.max(1, metrics.distance),
                    startZoom: perspectiveViewZoom,
                    deltaAngle: 0,
                    scale: 1,
                    mode: null
                };
                perspectiveDraggingPoint = null;
                event.preventDefault();
            }
            return;
        }

        const pos = getPointEventPosition(event, canvas);
        perspectiveDraggingPoint = nearestPerspectivePoint(pos);
        if (perspectiveDraggingPoint !== null) {
            event.preventDefault();
        }
    }

    function move(event) {
        if (perspectiveGesture && event.touches && event.touches.length >= 2) {
            const metrics = getTouchMetrics(event, canvas);
            if (metrics) {
                const deltaAngle = normalizeGestureAngle(metrics.angle - perspectiveGesture.startAngle);
                const scale = metrics.distance / perspectiveGesture.startDistance;
                const angleMovement = Math.abs(deltaAngle);
                const scaleMovement = Math.abs(scale - 1);

                perspectiveGesture.deltaAngle = deltaAngle;
                perspectiveGesture.scale = scale;

                if (!perspectiveGesture.mode) {
                    if (scaleMovement >= 0.08 && angleMovement < 10) {
                        perspectiveGesture.mode = 'zoom';
                    } else if (angleMovement >= 8 && scaleMovement < 0.12) {
                        perspectiveGesture.mode = 'rotate';
                    } else if (scaleMovement >= 0.12 || angleMovement >= 12) {
                        perspectiveGesture.mode = (scaleMovement * 90) > angleMovement ? 'zoom' : 'rotate';
                    }
                }

                if (perspectiveGesture.mode === 'zoom') {
                    perspectiveViewZoom = clampNumber(perspectiveGesture.startZoom * scale, 1, 3);
                    applyPerspectiveViewZoom();
                    document.getElementById('editPhotoStatus').textContent =
                        'Zoom ' + Math.round(perspectiveViewZoom * 100) + '%. Usa un dito per sistemare i punti.';
                } else if (perspectiveGesture.mode === 'rotate') {
                    document.getElementById('editPhotoStatus').textContent =
                        'Rotazione: ' + deltaAngle.toFixed(1) + ' gradi. Rilascia per applicare.';
                }
                event.preventDefault();
            }
            return;
        }

        if (perspectiveDraggingPoint === null) {
            return;
        }
        event.preventDefault();
        const pos = getPointEventPosition(event, canvas);
        perspectivePoints[perspectiveDraggingPoint] = {
            x: Math.max(0, Math.min(canvas.width, pos.x)),
            y: Math.max(0, Math.min(canvas.height, pos.y))
        };
        drawPerspectiveEditor();
    }

    function end() {
        if (perspectiveGesture) {
            const delta = perspectiveGesture.deltaAngle || 0;
            const mode = perspectiveGesture.mode;
            perspectiveGesture = null;
            if (mode === 'rotate' && Math.abs(delta) >= 0.8) {
                rotatePerspectiveImage(delta);
            } else if (mode === 'zoom') {
                document.getElementById('editPhotoStatus').textContent =
                    'Zoom ' + Math.round(perspectiveViewZoom * 100) + '%. Trascina i punti o allarga/stringi ancora con due dita.';
            } else {
                document.getElementById('editPhotoStatus').textContent = 'Gesto troppo piccolo: nessuna modifica applicata.';
            }
        }
        perspectiveDraggingPoint = null;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);
}

function loadPerspectiveImage(blobOrFile) {
    const canvas = document.getElementById('editPhotoCanvas');
    const image = new Image();
    image.onload = function () {
        const maxWidth = Math.min(900, window.innerWidth - 48);
        const maxHeight = Math.min(680, window.innerHeight - 220);
        const ratio = Math.min(maxWidth / image.naturalWidth, maxHeight / image.naturalHeight, 1);
        canvas.width = Math.max(320, Math.round(image.naturalWidth * ratio));
        canvas.height = Math.max(240, Math.round(image.naturalHeight * ratio));

        perspectiveSourceCanvas.width = image.naturalWidth;
        perspectiveSourceCanvas.height = image.naturalHeight;
        perspectiveSourceCanvas.getContext('2d').drawImage(image, 0, 0);
        perspectiveImage = image;
        initPerspectivePoints(canvas);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        const detected = detectDocumentCorners(canvas);
        setupPerspectiveCanvasEvents();
        drawPerspectiveEditor();
        document.getElementById('editPhotoStatus').textContent = detected
            ? 'Ho provato a riconoscere gli angoli: controlla i punti blu e premi Conferma foto.'
            : 'Non ho trovato automaticamente gli angoli: sposta i quattro punti blu sui vertici del documento.';
    };
    image.src = activeCropperObjectUrl;
}

function rotatePerspectiveImage(degrees) {
    if (!perspectiveImage) {
        return;
    }

    const src = perspectiveSourceCanvas;
    const rotated = document.createElement('canvas');
    if (Math.abs(degrees) === 90) {
        rotated.width = src.height;
        rotated.height = src.width;
    } else {
        rotated.width = src.width;
        rotated.height = src.height;
    }
    const ctx = rotated.getContext('2d');
    ctx.translate(rotated.width / 2, rotated.height / 2);
    ctx.rotate(degrees * Math.PI / 180);
    ctx.drawImage(src, -src.width / 2, -src.height / 2);
    rotated.toBlob(function (blob) {
        if (blob) {
            openPhotoEditor(activeCropperForm, blob, activeCropperIndex);
            document.getElementById('editRotateFine').value = '0';
            document.getElementById('editRotateFineValue').textContent = '0';
        }
    }, 'image/jpeg', 0.92);
}

function distanceBetween(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

function confirmPerspectivePhoto(callback) {
    if (!cvReady()) {
        callback(null, 'OpenCV.js non e ancora pronto. Attendere qualche secondo e riprovare.');
        return;
    }

    const displayCanvas = document.getElementById('editPhotoCanvas');
    const scaleX = perspectiveSourceCanvas.width / displayCanvas.width;
    const scaleY = perspectiveSourceCanvas.height / displayCanvas.height;
    const p = perspectivePoints.map(function (point) {
        return { x: point.x * scaleX, y: point.y * scaleY };
    });

    const targetWidth = Math.max(distanceBetween(p[0], p[1]), distanceBetween(p[3], p[2]));
    const targetHeight = Math.max(distanceBetween(p[0], p[3]), distanceBetween(p[1], p[2]));
    const maxSide = 2200;
    const ratio = Math.min(maxSide / Math.max(targetWidth, targetHeight), 1);
    const outWidth = Math.max(300, Math.round(targetWidth * ratio));
    const outHeight = Math.max(300, Math.round(targetHeight * ratio));

    let src = null;
    let dst = null;
    let srcTri = null;
    let dstTri = null;
    let matrix = null;
    try {
        src = cv.imread(perspectiveSourceCanvas);
        dst = new cv.Mat();
        srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            p[0].x, p[0].y,
            p[1].x, p[1].y,
            p[2].x, p[2].y,
            p[3].x, p[3].y
        ]);
        dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            0, 0,
            outWidth, 0,
            outWidth, outHeight,
            0, outHeight
        ]);
        matrix = cv.getPerspectiveTransform(srcTri, dstTri);
        cv.warpPerspective(src, dst, matrix, new cv.Size(outWidth, outHeight), cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar());
        const output = document.getElementById('editOutputCanvas');
        output.width = outWidth;
        output.height = outHeight;
        cv.imshow(output, dst);
        output.toBlob(function (blob) {
            callback(blob, blob ? '' : 'Impossibile salvare la foto raddrizzata.');
        }, 'image/jpeg', 0.9);
    } catch (error) {
        callback(null, error.message || 'Errore durante il raddrizzamento della foto.');
    } finally {
        if (src) src.delete();
        if (dst) dst.delete();
        if (srcTri) srcTri.delete();
        if (dstTri) dstTri.delete();
        if (matrix) matrix.delete();
    }
}

function closePhotoEditor() {
    const modal = document.getElementById('editPhotoModal');
    const image = document.getElementById('editPhotoImage');

    activeCropperForm = null;
    activeCropperIndex = null;
    perspectiveImage = null;
    perspectivePoints = [];
    perspectiveGesture = null;
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    image.removeAttribute('src');
    if (activeCropperObjectUrl) {
        URL.revokeObjectURL(activeCropperObjectUrl);
    }
    activeCropperObjectUrl = '';
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function openPhotoEditor(form, blobOrFile, index) {
    const modal = document.getElementById('editPhotoModal');
    const status = document.getElementById('editPhotoStatus');

    if (typeof cv === 'undefined') {
        status.textContent = 'Editor prospettiva non disponibile: manca OpenCV.js.';
        replaceFileInInput(form, blobOrFile instanceof File ? blobOrFile : makePhotoFile(blobOrFile, 'foto_documento'), index);
        return;
    }

    if (activeCropperObjectUrl) {
        URL.revokeObjectURL(activeCropperObjectUrl);
    }

    activeCropperForm = form;
    activeCropperIndex = index;
    activeCropperObjectUrl = URL.createObjectURL(blobOrFile);
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    document.getElementById('editRotateFine').value = '0';
    document.getElementById('editRotateFineValue').textContent = '0';
    status.textContent = cvReady()
        ? 'Sposta i quattro punti sui vertici del documento e premi Conferma foto.'
        : 'OpenCV.js si sta caricando. Puoi gia sistemare i punti; se Conferma non parte, attendi qualche secondo.';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    loadPerspectiveImage(blobOrFile);
}

const iscrizioneForm = document.getElementById('iscrizioneForm');
if (iscrizioneForm) {
    iscrizioneForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const form = event.currentTarget;
        const status = document.getElementById('saveStatus');
        const buttons = form.querySelectorAll('button');
        const submitter = event.submitter;
        const goDocuments = submitter && submitter.dataset.action === 'documents';

        buttons.forEach((button) => button.disabled = true);
        status.textContent = 'Salvataggio in corso...';
        status.className = 'status-line muted';

        try {
            const response = await fetch('salva.php', {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const result = await readJsonResponse(response);

            if (!result.ok) {
                throw new Error(result.message || 'Salvataggio non riuscito.');
            }

            status.textContent = goDocuments
                ? 'Dati salvati. Ora possiamo procedere al caricamento documenti.'
                : result.message;
            status.className = 'status-line';
        } catch (error) {
            status.textContent = error.message;
            status.className = 'status-line';
        } finally {
            buttons.forEach((button) => button.disabled = false);
        }
    });
}

const submitApplication = document.getElementById('submitApplication');
if (submitApplication) {
    submitApplication.addEventListener('click', async function () {
        const button = this;
        const form = document.getElementById('iscrizioneForm');
        const status = document.getElementById('submitStatus');

        if (!window.confirm('Inviare definitivamente la domanda? Dopo l\'invio non sara piu possibile modificarla da questo link.')) {
            return;
        }

        button.disabled = true;
        status.textContent = 'Salvataggio e invio domanda in corso...';
        status.className = 'status-line muted';

        try {
            const response = await fetch('invia.php', {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const result = await readJsonResponse(response);

            if (!result.ok) {
                throw new Error(result.message || 'Invio non riuscito.');
            }

            status.textContent = result.message;
            status.className = 'status-line';
            showSubmitSuccess();
            document.querySelectorAll('button, input').forEach(function (control) {
                if (control.id !== 'submitSuccessClose') {
                    control.disabled = true;
                }
            });
        } catch (error) {
            status.textContent = error.message;
            status.className = 'status-line';
            showSubmitError(error.message);
            button.disabled = false;
        }
    });
}

document.getElementById('submitSuccessClose').addEventListener('click', function () {
    const overlay = document.getElementById('submitSuccessOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
});

document.getElementById('submitErrorClose').addEventListener('click', function () {
    const overlay = document.getElementById('submitErrorOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
});

document.querySelectorAll('.doc-form').forEach(function (form) {
    const input = form.querySelector('.doc-file-input');
    const nativeCameraInput = form.querySelector('.doc-native-camera-input');
    setDocumentUiState(form, form.dataset.docState || 'missing');

    const handleSelectedFiles = function (fileInput, mode) {
        const selected = Array.from(fileInput.files);
        const imageFiles = mode === 'camera' ? selected.filter((file) => file.type.startsWith('image/')) : [];
        const otherFiles = selected.filter((file) => !file.type.startsWith('image/'));

        fileInput.value = '';
        if (mode === 'file' && selected.some((file) => file.type.startsWith('image/'))) {
            form.querySelector('.doc-status').textContent = 'Per le immagini usare Scatta foto, cosi vengono ritagliate e raddrizzate.';
        }
        otherFiles.forEach((file) => appendReadyFile(form, file));

        if (imageFiles.length) {
            pendingNativeImages.set(form, imageFiles.map(function (file, index) {
                return {
                    file: file,
                    position: index + 1,
                    total: imageFiles.length
                };
            }));
            processNextNativeImage(form);
        } else {
            refreshPhotoPreview(form);
        }
    };

    input.addEventListener('change', function () {
        handleSelectedFiles(input, 'file');
    });

    nativeCameraInput.addEventListener('change', function () {
        handleSelectedFiles(nativeCameraInput, 'camera');
    });

    form.querySelector('.doc-file-button').addEventListener('click', function () {
        input.click();
    });

    form.querySelector('.doc-native-camera').addEventListener('click', function () {
        nativeCameraInput.click();
    });

    const addPhotoButton = form.querySelector('.doc-add-photo');
    if (addPhotoButton) {
        addPhotoButton.addEventListener('click', function () {
            nativeCameraInput.click();
        });
    }

    form.querySelectorAll('.doc-mode-options input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const uploadMode = form.querySelector('.doc-upload-mode');
            if (uploadMode && radio.checked) {
                uploadMode.value = radio.value;
                refreshPhotoPreview(form);
            }
        });
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = form.querySelector('.doc-upload-button');
        const status = form.querySelector('.doc-status');
        const current = form.querySelector('.doc-current');
        const badge = form.querySelector('.badge');
        const input = form.querySelector('.doc-file-input');
        const deleteButton = form.querySelector('.doc-delete');
        const viewLink = form.querySelector('.doc-view');
        const uploadMode = form.querySelector('.doc-upload-mode');

        if (!input.files.length) {
            status.textContent = 'Selezionare un file.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Caricamento in corso...';

        try {
            const payload = new FormData(form);
            payload.set('token', pageToken || (form.querySelector('input[name="token"]') ? form.querySelector('input[name="token"]').value : ''));
            const response = await fetch('upload_documento.php?t=' + encodeURIComponent(pageToken || ''), {
                method: 'POST',
                body: payload,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const result = await readJsonResponse(response);

            if (!result.ok) {
                throw new Error(result.message || 'Caricamento non riuscito.');
            }

            status.textContent = result.message;
            if (result.document && result.document.original_name) {
                current.textContent = result.document.original_name;
            }
            badge.textContent = 'Caricato';
            badge.classList.add('badge-ok');
            badge.classList.remove('badge-paper');
            deleteButton.hidden = false;
            deleteButton.textContent = 'Cancella PDF caricato';
            viewLink.hidden = false;
            const paperButton = form.querySelector('.doc-paper');
            if (paperButton) {
                paperButton.hidden = false;
            }
            if (uploadMode) {
                uploadMode.value = 'append';
            }
            input.value = '';
            setDocumentUiState(form, 'uploaded');
            refreshPhotoPreview(form);
            showResubmitReminder();
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    const deleteButton = form.querySelector('.doc-delete');
    const paperButton = form.querySelector('.doc-paper');
    if (paperButton) {
        paperButton.addEventListener('click', async function () {
            const status = form.querySelector('.doc-status');
            const current = form.querySelector('.doc-current');
            const badge = form.querySelector('.badge');
            const viewLink = form.querySelector('.doc-view');
            const input = form.querySelector('.doc-file-input');
            const uploadMode = form.querySelector('.doc-upload-mode');
            const existingOptions = form.querySelector('.doc-existing-options');
            const clearGroup = form.querySelector('.doc-clear-group');

            if (!window.confirm('Confermi che questo documento verra consegnato come fotocopia in segreteria didattica?')) {
                return;
            }

            paperButton.disabled = true;
            status.textContent = 'Registrazione consegna cartacea...';

            try {
                const response = await fetch('consegna_cartacea_documento.php', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const result = await readJsonResponse(response);

                if (!result.ok) {
                    throw new Error(result.message || 'Registrazione non riuscita.');
                }

                status.textContent = result.message;
                current.textContent = result.document && result.document.original_name
                    ? result.document.original_name
                    : 'Consegna cartacea in segreteria didattica';
                badge.textContent = 'Cartaceo';
                badge.classList.remove('badge-ok');
                badge.classList.add('badge-paper');
                viewLink.hidden = true;
                paperButton.hidden = true;
                deleteButton.hidden = false;
                deleteButton.textContent = 'Annulla scelta cartacea';
                if (clearGroup) {
                    clearGroup.hidden = false;
                }
                if (existingOptions) {
                    existingOptions.hidden = true;
                }
                if (uploadMode) {
                    uploadMode.value = 'replace';
                }
                input.value = '';
                setDocumentUiState(form, 'paper');
                refreshPhotoPreview(form);
                showResubmitReminder();
            } catch (error) {
                status.textContent = error.message;
            } finally {
                paperButton.disabled = false;
            }
        });
    }

    deleteButton.addEventListener('click', async function () {
        const status = form.querySelector('.doc-status');
        const current = form.querySelector('.doc-current');
        const badge = form.querySelector('.badge');
        const viewLink = form.querySelector('.doc-view');
        const uploadMode = form.querySelector('.doc-upload-mode');
        const existingOptions = form.querySelector('.doc-existing-options');
        const clearGroup = form.querySelector('.doc-clear-group');

        const isPaperChoice = badge.textContent.trim().toLowerCase() === 'cartaceo';
        const confirmMessage = isPaperChoice
            ? 'Annullare la scelta di consegna cartacea in segreteria?'
            : 'Cancellare il PDF caricato?';

        if (!window.confirm(confirmMessage)) {
            return;
        }

        deleteButton.disabled = true;
        status.textContent = isPaperChoice ? 'Annullamento scelta cartacea...' : 'Cancellazione PDF in corso...';

        try {
            const response = await fetch('cancella_documento.php', {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const result = await readJsonResponse(response);

            if (!result.ok) {
                throw new Error(result.message || 'Cancellazione non riuscita.');
            }

            status.textContent = result.message;
            current.textContent = 'Non ancora caricato';
            badge.textContent = 'Mancante';
            badge.classList.remove('badge-ok');
            badge.classList.remove('badge-paper');
            deleteButton.hidden = true;
            deleteButton.textContent = 'Cancella PDF caricato';
            if (clearGroup) {
                clearGroup.hidden = true;
            }
            viewLink.hidden = true;
            if (paperButton) {
                paperButton.hidden = false;
            }
            if (existingOptions) {
                existingOptions.hidden = true;
            }
            if (uploadMode) {
                uploadMode.value = 'replace';
            }
            input.value = '';
            setDocumentUiState(form, 'missing');
            refreshPhotoPreview(form);
            showResubmitReminder();
        } catch (error) {
            status.textContent = error.message;
        } finally {
            deleteButton.disabled = false;
        }
    });
});

document.getElementById('editCancel').addEventListener('click', function () {
    const form = activeCropperForm;
    closePhotoEditor();
    if (form) {
        processNextNativeImage(form);
    }
});
document.getElementById('editRotateLeft').addEventListener('click', function () {
    rotatePerspectiveImage(-90);
});
document.getElementById('editRotateRight').addEventListener('click', function () {
    rotatePerspectiveImage(90);
});
document.getElementById('editRotateFine').addEventListener('input', function () {
    document.getElementById('editRotateFineValue').textContent = this.value;
});
document.getElementById('editApplyFineRotate').addEventListener('click', function () {
    const degrees = parseFloat(document.getElementById('editRotateFine').value || '0');
    if (Math.abs(degrees) >= 0.1) {
        rotatePerspectiveImage(degrees);
    }
});
document.getElementById('editSkewLeft').addEventListener('click', function () {
    rotatePerspectiveImage(-1);
});
document.getElementById('editSkewRight').addEventListener('click', function () {
    rotatePerspectiveImage(1);
});
document.getElementById('editReset').addEventListener('click', function () {
    const canvas = document.getElementById('editPhotoCanvas');
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    initPerspectivePoints(canvas);
    drawPerspectiveEditor();
});
document.getElementById('editConfirm').addEventListener('click', function () {
    const status = document.getElementById('editPhotoStatus');

    if (!activeCropperForm || !perspectiveImage) {
        status.textContent = 'Nessuna foto da confermare.';
        return;
    }

    status.textContent = 'Raddrizzamento prospettiva in corso...';
    showBusy('Raddrizzamento della foto in corso...');
    window.setTimeout(function () {
        confirmPerspectivePhoto(function (blob, error) {
            hideBusy();
            if (!blob) {
                status.textContent = error || 'Impossibile salvare la foto sistemata.';
                return;
            }

            const file = makePhotoFile(blob, 'foto_raddrizzata');
            const form = activeCropperForm;
            const index = activeCropperIndex;
            replaceFileInInput(form, file, index);
            closePhotoEditor();

            const files = Array.from(form.querySelector('.doc-file-input').files);
            form.querySelector('.doc-status').textContent = files.length ? readyFilesInfo(files).status : '';
            processNextNativeImage(form);
        });
    }, 30);
});
</script>
<?php endif; ?>
</body>
</html>
