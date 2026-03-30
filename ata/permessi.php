<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

// tipi permesso validi
$tipi = dbGetAll("
  SELECT id, codice, descrizione
  FROM permesso_ata_tipo
  WHERE (valido IS NULL OR valido=1)
  ORDER BY codice;
");

// finestre ferie lato amministrativo (CARNEVALE/PASQUA/ESTIVE/NATALE)
$finestreFerie = dbGetAll("
  SELECT codice, data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE (valido IS NULL OR valido=1)
");
$finestreMap = [];
foreach ($finestreFerie as $f) {
  $cod = strtoupper(trim((string)$f['codice']));
  $finestreMap[$cod] = [
    'data_inizio' => $f['data_inizio'],
    'data_fine'   => $f['data_fine'],
  ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permessi ATA - Le mie richieste</title>
  <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
  ?>
  <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

  <style>
    .permessi-page {
      max-width: 760px;
      margin: 0 auto;
      padding-bottom: 30px;
    }

    .permessi-header-box {
      background: #fff8dc;
      border: 1px solid #f1d36b;
      border-radius: 18px;
      padding: 14px;
      margin-bottom: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }

    .permessi-title {
      font-size: 26px;
      font-weight: 700;
      color: #2d3340;
      margin-bottom: 6px;
      line-height: 1.2;
    }

    .permessi-subtitle {
      font-size: 15px;
      color: #6b7280;
      line-height: 1.4;
    }

    .btn-mobile-main {
      width: 100%;
      min-height: 56px;
      font-size: 20px;
      font-weight: 700;
      border-radius: 16px;
      margin-top: 12px;
    }

    #permesso_modal .modal-dialog {
      width: auto;
      margin: 10px;
    }

    #permesso_modal .modal-content {
      border-radius: 18px;
      overflow: hidden;
      border: none;
    }

    #permesso_modal .panel {
      margin-bottom: 0;
      border: none;
      box-shadow: none;
    }

    #permesso_modal .panel-heading {
      padding: 14px 16px;
    }

    #permesso_modal .panel-heading .modal-title,
    #permesso_modal .panel-heading h5 {
      font-size: 22px;
      font-weight: 700;
      margin: 0;
      color: #2d3340;
    }

    #permesso_modal .panel-body {
      padding: 16px;
    }

    #permesso_modal .panel-footer .btn {
      min-height: 52px;
      font-size: 18px;
      border-radius: 12px;
      margin-bottom: 8px;
    }

    #permesso_modal label {
      font-size: 16px;
      font-weight: 600;
      color: #2d3340;
    }

    #permesso_modal .form-group {
      margin-bottom: 16px;
    }

    #permesso_modal .form-control {
      min-height: 48px;
      font-size: 17px;
      border-radius: 12px;
      padding: 10px 12px;
    }

    #permesso_modal textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }

    #permesso_alert {
      border-radius: 12px;
      font-size: 15px;
    }

    .well.well-sm.ferie-riga,
    .well.well-sm.riga-104 {
      border-radius: 14px;
      padding: 12px;
      margin-bottom: 10px;
      background: #fafbfc;
    }

    #btn_add_ferie,
    #btn_add_104 {
      min-height: 40px;
      font-size: 14px;
      border-radius: 10px;
    }

    #singolo_hint,
    #ferie_periodo_box,
    #block_104_multi .alert {
      border-radius: 12px;
      font-size: 14px;
      line-height: 1.5;
    }

    @media (max-width: 767px) {
      .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
      }

      .permessi-page {
        max-width: 100%;
      }

      .permessi-title {
        font-size: 24px;
        text-align: center;
      }

      .permessi-subtitle {
        text-align: center;
        font-size: 16px;
      }

      #permesso_modal .modal-dialog {
        margin: 0;
        width: 100%;
        min-height: 100vh;
      }

      #permesso_modal .modal-content {
        min-height: 100vh;
        border-radius: 0;
      }

      #permesso_modal .modal-body {
        padding: 0;
      }

      #permesso_modal .panel-heading {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #fff4b8;
        border-bottom: 1px solid #ead98d;
      }

      #permesso_modal .panel-body {
        padding: 14px 14px 120px 14px;
      }

      #permesso_modal .panel-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 30;
        background: #ffffff;
        border-top: 1px solid #ddd;
        padding: 10px;
      }

      #permesso_modal .panel-footer.text-center {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      #permesso_modal .panel-footer.text-center .btn {
        width: 100%;
        margin: 0;
      }

      #permesso_modal .close {
        font-size: 34px;
        opacity: 1;
        line-height: 1;
      }

      #btn_add_ferie,
      #btn_add_104,
      .btn_del_ferie,
      .btn_del_104 {
        min-height: 44px;
      }

      .well.well-sm.ferie-riga .text-right,
      .well.well-sm.riga-104 .text-right {
        text-align: left !important;
      }
    }
  </style>
  <style>
  body {
    background: #f5f6f8;
  }

  .container-fluid {
    padding-left: 10px;
    padding-right: 10px;
  }

  .permessi-hero-card {
    background: #f6eed2;
    border: 1px solid #ead8a2;
    border-radius: 18px;
    padding: 16px 14px;
    margin-bottom: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
  }

  .permessi-hero-title {
    font-size: 20px;
    font-weight: 700;
    color: #24324a;
    text-align: center;
    margin-bottom: 8px;
  }

  .permessi-hero-text {
    font-size: 16px;
    line-height: 1.45;
    color: #516079;
    text-align: center;
    margin-bottom: 14px;
  }

  .btn-mobile-main {
    width: 100%;
    min-height: 56px;
    border-radius: 18px;
    font-size: 18px;
    font-weight: 700;
    padding: 12px 16px;
  }

  .records_content .panel {
    border-radius: 18px !important;
  }

  .records_content .btn-lg {
    min-height: 52px;
    border-radius: 14px;
    font-size: 20px;
    font-weight: 700;
  }

  .records_content .label {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 13px;
  }

  @media (max-width: 767px) {
    .modal-dialog {
      margin: 8px;
    }

    .modal-content {
      border-radius: 18px;
    }

    .panel-heading h5,
    .modal-title {
      font-size: 20px;
      font-weight: 700;
    }

    .form-control {
      font-size: 16px;
      min-height: 46px;
      border-radius: 12px;
    }

    textarea.form-control {
      min-height: 96px;
    }

    .btn {
      border-radius: 14px;
    }

    #btn_save_bozza,
    #btn_invia {
      width: 100%;
      min-height: 50px;
      font-size: 18px;
      font-weight: 700;
      margin-top: 8px;
    }

    .panel-footer .btn-default {
      width: 100%;
      min-height: 46px;
      margin-bottom: 8px;
    }

    .well {
      border-radius: 14px;
    }
  }
</style>
</head>

<body>
<?php require_once '../common/header-ata.php'; ?>

<div class="container-fluid">
  <div class="permessi-page">
    <div class="permessi-header-box">
      <div class="permessi-title">
        <span class="glyphicon glyphicon-folder-open"></span>
        Permessi ATA
      </div>
      <div class="permessi-subtitle">
        Consulta le tue richieste oppure inseriscine una nuova.
      </div>

      <button class="btn btn-warning btn-mobile-main" id="btn_new">
        <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova richiesta
      </button>
    </div>

    <div class="records_content"></div>
  </div>
</div>

<!-- MODAL: add/update richiesta -->
<div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="permessoModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-body">

        <div class="panel panel-yellow4">
          <div class="panel-heading">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title" id="permessoModalLabel">Richiesta permesso</h5>
          </div>

          <div class="panel-body">
            <div id="permesso_alert" class="alert alert-danger" style="display:none; margin-bottom:10px;"></div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="permesso_tipo_id">Tipo permesso</label>
                  <select class="form-control" id="permesso_tipo_id">
                    <option value="">Seleziona...</option>
                    <?php foreach($tipi as $t): ?>
                      <option value="<?php echo (int)$t['id']; ?>"
                              data-codice="<?php echo htmlspecialchars($t['codice']); ?>">
                        <?php echo htmlspecialchars($t['codice'].' - '.$t['descrizione']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group">
                  <label>Stato</label>
                  <input type="text" class="form-control" id="permesso_stato" readonly value="BOZZA">
                </div>
              </div>
            </div>

            <!-- FERIE sottotipo -->
            <div class="row" id="block_ferie_sottotipo" style="display:none;">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="ferie_sottotipo">Tipologia ferie</label>
                  <select class="form-control" id="ferie_sottotipo">
                    <option value="">Seleziona...</option>
                    <option value="GENERICHE">GENERICHE</option>
                    <option value="CARNEVALE">CARNEVALE</option>
                    <option value="PASQUA">PASQUA</option>
                    <option value="ESTIVE">ESTIVE</option>
                    <option value="NATALE">NATALE</option>
                  </select>

                  <div id="ferie_periodo_box" class="text-muted" style="margin-top:6px; display:none;">
                    <span class="glyphicon glyphicon-calendar"></span>&ensp;
                    <span id="ferie_periodo_testo"></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="permesso_note">Note</label>
              <textarea class="form-control" rows="3" id="permesso_note" placeholder="Note (facoltative)"></textarea>
            </div>

            <hr style="margin:10px 0;">

            <!-- BLOCCO SINGOLO -->
            <div id="block_singolo" style="display:none;">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="singolo_data">Data</label>
                    <input type="date" class="form-control" id="singolo_data">
                  </div>
                </div>

                <div class="col-md-4" id="block_singolo_ora_da" style="display:none;">
                  <div class="form-group">
                    <label for="singolo_ora_da">Ore da</label>
                    <input type="time" class="form-control" id="singolo_ora_da">
                  </div>
                </div>

                <div class="col-md-4" id="block_singolo_ora_a" style="display:none;">
                  <div class="form-group">
                    <label for="singolo_ora_a">Ore a</label>
                    <input type="time" class="form-control" id="singolo_ora_a">
                  </div>
                </div>
              </div>
              <div class="alert alert-info" id="singolo_hint" style="display:none; padding:8px; margin-bottom:0;"></div>
            </div>

            <!-- FERIE -->
            <div id="block_ferie_multi" style="display:none;">
              <div class="row">
                <div class="col-md-6">
                  <label>Intervalli ferie (puoi aggiungerne più di uno)</label>
                </div>
                <div class="col-md-6 text-right">
                  <button type="button" class="btn btn-default" id="btn_add_ferie">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi intervallo
                  </button>
                </div>
              </div>
              <div id="righe_ferie_container" style="margin-top:10px;"></div>
            </div>

            <!-- LEGGE 104 -->
            <div id="block_104_multi" style="display:none;">
              <div class="row">
                <div class="col-md-6">
                  <label>Intervalli LEGGE 104</label>
                </div>
                <div class="col-md-6 text-right">
                  <button type="button" class="btn btn-default" id="btn_add_104">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi riga
                  </button>
                </div>
              </div>

              <div class="alert alert-info" style="padding:8px; margin-top:8px; margin-bottom:8px;">
                Puoi inserire:
                <ul style="margin:6px 0 0 18px;">
                  <li><b>GIORNI</b>: dal/al (senza ore)</li>
                  <li><b>ORE</b>: un solo giorno + fascia oraria (ore da/ore a)</li>
                </ul>
              </div>

              <div id="righe_104_container" style="margin-top:10px;"></div>
            </div>

          </div>

          <div class="panel-footer text-center">
            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
            <button type="button" class="btn btn-primary" id="btn_save_bozza">Salva bozza</button>
            <button type="button" class="btn btn-success" id="btn_invia">Invia richiesta</button>

            <input type="hidden" id="permesso_id" value="">
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
  window.__FERIE_FINESTRE = <?php echo json_encode($finestreMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>

<script type="text/javascript" src="js/scriptPermessiAta.js"></script>
</body>
</html>