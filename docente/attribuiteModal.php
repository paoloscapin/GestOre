<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
	// prepara l'elenco dei tipi di attivita attribuite
	$categoria = '';
	$tipoAttivitaAttribuiteOptionList = '				<option value="0"></option>';
	$query = "SELECT * FROM `ore_previste_tipo_attivita` WHERE valido = true AND inserito_da_docente = false AND previsto_da_docente = false ORDER BY ore_previste_tipo_attivita.categoria DESC, ore_previste_tipo_attivita.nome ASC;";
	foreach(dbGetAll($query) as $row) {
		if ($categoria !== $row['categoria']) {
			if ($categoria !== '') {
				$tipoAttivitaAttribuiteOptionList .= '</optgroup>';
			}
			$categoria = $row['categoria'];
			$tipoAttivitaAttribuiteOptionList .= '<optgroup label="'.$categoria.'">';
		}
		// se ha un numero fisso di ore o un max, lo segnala
		$subtext = '';
		if ($row['ore'] != 0) {
			$subtext = ' data-subtext="'.$row['ore'].' ore"';
		} else if ($row['ore_max'] != 0) {
			$subtext = ' data-subtext="max '.$row['ore_max'].' ore"';
		}
		$tipoAttivitaAttribuiteOptionList .= '<option value="'.$row['id'].'"'.$subtext.' >'.$row['nome'].'</option>';
	}
	$tipoAttivitaAttribuiteOptionList .= '</optgroup>';
?>

<!-- Modal - attribuite details -->
<div class="modal fade" id="attribuite_modal" tabindex="-1" role="dialog" aria-labelledby="attribuiteModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
		<div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Ore Attribuite</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

			<div class="form-group attribuite_tipo_attivita_selector">
                    <label class="col-sm-3 control-label" for="attribuite_tipo_attivita">Tipo attività</label>
					<div class="col-sm-6">
						<select id="attribuite_tipo_attivita" name="attribuite_tipo_attivita" class="attribuite_tipo_attivita selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $tipoAttivitaAttribuiteOptionList ?>
						</select>
					</div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="attribuite_ore">ore</label>
                    <div class="col-sm-2"><input type="text" id="attribuite_ore" placeholder="" class="form-control" /></div>
                </div>

				<div class="form-group">
                    <label class="col-sm-3 control-label" for="attribuite_dettaglio">dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="attribuite_dettaglio" placeholder="" class="form-control" /></div>
                </div>

                <div class="form-group" id="attribuite_commento-part">
                    <hr>
                    <label class="col-sm-3 control-label" for="attribuite_commento">commento</label>
                    <div class="col-sm-9"><input type="text" id="attribuite_commento" placeholder="commento" class="form-control"/></div>
                </div>

                <div class="form-group" id="_error-attribuite-part">
				<strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error-attribuite"></div>
				</strong>
				</div>

            </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attribuiteSave()" >Salva</button>
				<input type="hidden" id="hidden_attribuite_id">
				</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - corsi di recupero details -->
