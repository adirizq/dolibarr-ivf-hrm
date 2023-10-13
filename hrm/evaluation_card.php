<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021 Gauthier VERDOL <gauthier.verdol@atm-consulting.fr>
 * Copyright (C) 2021 Greg Rastklan <greg.rastklan@atm-consulting.fr>
 * Copyright (C) 2021 Jean-Pascal BOUDET <jean-pascal.boudet@atm-consulting.fr>
 * Copyright (C) 2021 Grégory BLEMAND <gregory.blemand@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *    \file       htdocs/hrm/evaluation_card.php
 *    \ingroup    hrm
 *    \brief      Page to create/edit/view evaluation
 */

// Load Dolibarr environment
require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/class/evaluation.class.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/class/job.class.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/class/skill.class.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/class/skillrank.class.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/lib/hrm_evaluation.lib.php';
require_once DOL_DOCUMENT_ROOT.'/hrm/lib/hrm_skillrank.lib.php';


// Load translation files required by the page
$langs->loadLangs(array('hrm', 'other', 'products'));  // why products?

// Get parameters
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'evaluationcard'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid   = GETPOST('lineid', 'int');

// Initialize technical objects
$object = new Evaluation($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->hrm->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('evaluationcard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

// Permissions
$permissiontoread = $user->rights->hrm->evaluation->read;
$permissiontoadd = $user->rights->hrm->evaluation->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontovalidate = (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->rights->hrm->evaluation_advance->validate) || (empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $permissiontoadd);
$permissiontoClose = $user->rights->hrm->evaluation->write;
$permissiontodelete = $user->rights->hrm->evaluation->delete/* || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT)*/;
$permissiondellink = $user->rights->hrm->evaluation->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->hrm->multidir_output[isset($object->entity) ? $object->entity : 1].'/evaluation';

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->element, $object->id, $object->table_element, '', 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled("hrm")) {
	accessforbidden();
}
if (!$permissiontoread || ($action === 'create' && !$permissiontoadd)) accessforbidden();


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$error = 0;

	$backurlforlist = dol_buildpath('/hrm/evaluation_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/hrm/evaluation_card.php', 1).'?id='.($id > 0 ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'hrm_EVALUATION_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOST('fk_soc', 'int'), '', '', 'date', '', $user, $triggermodname);
	}
	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOST('projectid', 'int'));
	}

	// Actions to send emails
	$triggersendname = 'hrm_EVALUATION_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_EVALUATION_TO';
	$trackid = 'evaluation'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';

	if ($action == 'saveSkill') {
		$TNote = GETPOST('TNote', 'array');
		if (!empty($TNote)) {
			foreach ($object->lines as $line) {
				$line->rankorder = $TNote[$line->fk_skill];
				$line->update($user);
			}
			setEventMessage($langs->trans("SaveLevelSkill"));
		}
	}

	if ($action == 'close') {
		// save evaldet lines to user;
		$sk = new SkillRank($db);
		$SkillrecordsForActiveUser = $sk->fetchAll('ASC', 'fk_skill', 0, 0, array("customsql"=>"fk_object = ".$object->fk_user ." AND objecttype ='".SkillRank::SKILLRANK_TYPE_USER."'"), 'AND');

		$errors = 0;
		// we go through the evaldets of the eval
		foreach ($object->lines as $key => $line) {
			// no reference .. we add the line to use it
			if (count($SkillrecordsForActiveUser) == 0) {
				$newSkill = new SkillRank($db);
				$resCreate = $newSkill->cloneFromCurrentSkill($line, $object->fk_user);

				if ($resCreate <= 0) {
					$errors++;
					setEventMessage($langs->trans('ErrorCreateUserSkill'), $line->fk_skill);
				}
			} else {
				//check if the skill is present to use it
				$find = false;
				$keyFind = 0;
				foreach ($SkillrecordsForActiveUser as $k => $sr) {
					if ($sr->fk_skill == $line->fk_skill) {
						$keyFind = $k;
						$find = true;
						break;
					}
				}
				//we update the skill user
				if ($find) {
					$updSkill = $SkillrecordsForActiveUser[$k];

					$updSkill->rankorder = $line->rankorder;
					$updSkill->update($user);
				} else { // sinon on ajoute la skill
					$newSkill = new SkillRank($db);
					$resCreate = $newSkill->cloneFromCurrentSkill($line, $object->fk_user);
				}
			}
		}
		if (empty($errors)) {
			$object->setStatut(Evaluation::STATUS_CLOSED);
			setEventMessage('EmployeeSkillsUpdated');
		}
	}

	if ($action == 'reopen' ) {
		// no update here we just change the evaluation status
		$object->setStatut(Evaluation::STATUS_VALIDATED);
	}
}




/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);

$title = $langs->trans("Evaluation");
$help_url = '';
$css = array();
$css[] = '/hrm/css/style.css';
llxHeader('', $title, $help_url, '', 0, 0, '', $css);

print '<script type="text/javascript" language="javascript">
	$(document).ready(function() {
	  $("#btn_valid").click(function(){
		 var form = $("#form_save_rank");

		 $.ajax({

			 type: "POST",
			 url: form.attr("action"),
			 data: form.serialize(),
			 dataType: "json"
		 }).always(function() {
             window.location.href = "'.dol_buildpath('/hrm/evaluation_card.php', 1).'?id='.$id.'&action=validate&token='.newToken().'";
             return false;
		 });

	   });
	});
</script>';

// Part to create
if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewEval"), '', 'object_' . $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create", "Cancel");

	print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("Evaluation"), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = evaluationPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("Workstation"), -1, $object->picto);

	$formconfirm = '';

	if ($action == 'validate' && $permissiontovalidate) {
		// Confirm validate proposal
		$error = 0;

		// We verify whether the object is provisionally numbering
		$ref = substr($object->ref, 1, 4);
		if ($ref == 'PROV') {
			$numref = $object->getNextNumRef();
			if (empty($numref)) {
				$error++;
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} else {
			$numref = $object->ref;
		}

		$text = $langs->trans('ConfirmValidateEvaluation', $numref);
		if (isModEnabled('notification')) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
			$notify = new Notify($db);
			$text .= '<br>';
			$text .= $notify->confirmMessage('HRM_EVALUATION_VALIDATE', $object->socid, $object);
		}

		if (!$error) {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateEvaluation'), $text, 'confirm_validate', '', 0, 1);
		}
	}

	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteEvaluation'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}
	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Confirmation of action xxxx (You can use it for xxx = 'close', xxx = 'reopen', ...)
	if ($action == 'xxx') {
		$text = $langs->trans('ConfirmActionMyObject', $object->ref);

		$formquestion = array();

		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
	}

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;


	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/hrm/evaluation_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= $langs->trans('Label').' : '.$object->label;
	$u_position = new User(($db));
	$u_position->fetch($object->fk_user);
	$morehtmlref .= '<br>'.$u_position->getNomUrl(1);
	$job = new Job($db);
	$job->fetch($object->fk_job);
	$morehtmlref .= '<br>'.$langs->trans('JobProfile').' : '.$job->getNomUrl(1);
	$morehtmlref .= '</div>';



	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	// Custom code for showing competencies achievement precentage
	print '<div class="arearef heightref valignmiddle centpercent">';
	if ($object->status == Evaluation::STATUS_CLOSED) {
		$sql = 'select';

		$sql .= ' AVG (CASE';
		$sql .= ' WHEN ed.rankorder >= ed.required_rank THEN 1';
		$sql .= ' WHEN ed.rankorder < ed.required_rank THEN 0';
		$sql .= ' END) AS "competenciesAchievement",';

		$sql .= ' AVG (CASE';
		$sql .= ' WHEN ed.rankorder >= ed.required_rank AND s.skill_type = 0 THEN 1';
		$sql .= ' WHEN ed.rankorder < ed.required_rank AND s.skill_type = 0 THEN 0';
		$sql .= ' END) AS "ccCompetenciesAchievement",';

		$sql .= ' AVG (CASE';
		$sql .= ' WHEN ed.rankorder >= ed.required_rank AND s.skill_type = 1 THEN 1';
		$sql .= ' WHEN ed.rankorder < ed.required_rank AND s.skill_type = 1 THEN 0';
		$sql .= ' END) AS "hsCompetenciesAchievement",';

		$sql .= ' AVG (CASE';
		$sql .= ' WHEN ed.rankorder >= ed.required_rank AND s.skill_type = 9 THEN 1';
		$sql .= ' WHEN ed.rankorder < ed.required_rank AND s.skill_type = 9 THEN 0';
		$sql .= ' END) AS "ssCompetenciesAchievement"';

		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'hrm_evaluation as e';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_evaluationdet as ed ON  e.rowid = ed.fk_evaluation';
		$sql .= ' JOIN llx_hrm_skill AS s ON ed.fk_skill = s.rowid';

		$sql .= " WHERE e.rowid =" . ((int) $object->id);

		$resql = $db->query($sql);

		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				print '<div class="photoref" style="margin-bottom: 2rem; padding-left: 1rem; padding-right: 1rem;"><div class="titlefield fieldname_description tdtop" style="text-align:start;">Competencies Achievement</div>';
				print '<h1 class="valuefield" style="text-align:start; margin-top: 0.3rem !important; margin-bottom:0 !important; color: var(--colortexttitlenotab);">' . round(($obj->competenciesAchievement * 100), 2) . '%</h1></div>';

				print '<div class="photoref" style="margin-bottom: 2rem; margin-left: 1rem; padding-left: 1rem; padding-right: 1rem;"><div class="titlefield fieldname_description tdtop" style="text-align:start;">Company Cultures Achievement</div>';
				print '<h1 class="valuefield" style="text-align:start; margin-top: 0.3rem !important; margin-bottom:0 !important; color: var(--colortexttitlenotab);">' . round(($obj->ccCompetenciesAchievement * 100), 2) . '%</h1></div>';

				print '<div class="photoref" style="margin-bottom: 2rem; margin-left: 1rem; padding-left: 1rem; padding-right: 1rem;"><div class="titlefield fieldname_description tdtop" style="text-align:start;">Hard Skills Achievement</div>';
				print '<h1 class="valuefield" style="text-align:start; margin-top: 0.3rem !important; margin-bottom:0 !important; color: var(--colortexttitlenotab);">' . round(($obj->hsCompetenciesAchievement * 100), 2) . '%</h1></div>';

				print '<div class="photoref" style="margin-bottom: 2rem; margin-left: 1rem; padding-left: 1rem; padding-right: 1rem;"><div class="titlefield fieldname_description tdtop" style="text-align:start;">Soft Skills Achievement</div>';
				print '<h1 class="valuefield" style="text-align:start; margin-top: 0.3rem !important; margin-bottom:0 !important; color: var(--colortexttitlenotab);">' . round(($obj->ssCompetenciesAchievement * 100), 2) . '%</h1></div>';
			}
		}
	} else {
		print '<div class="photoref" style="margin-bottom: 2rem; padding-left: 1rem; padding-right: 1rem;"><div class="titlefield fieldname_description tdtop" style="text-align:start;">Competencies Achievement</div>';
		print '<div class="" style="text-align:start; margin-top: 0.3rem !important; margin-bottom:0 !important; color: var(--colortexttitlenotab);">Evaluation has not been closed</div></div>';
	}
	print '</div>';


	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	$object->fields['label']['visible']=0; // Already in banner
	$object->fields['fk_user']['visible']=0; // Already in banner
	$object->fields['fk_job']['visible']=0; // Already in banner
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();


	/*
	 * Lines
	 */

	if (!empty($object->table_element_line) && $object->status == Evaluation::STATUS_DRAFT) {
		// Show object lines
		$result = $object->getLinesArray();
		if ($result < 0) {
			dol_print_error($db, $object->error, $object->errors);
		}

		print '<br>';

		print '	<form name="form_save_rank" id="form_save_rank" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOST('lineid', 'int')).'" method="POST">
		<input type="hidden" name="token" value="' . newToken().'">
		<input type="hidden" name="action" value="saveSkill">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		$conf->modules_parts['tpl']['hrm']='/hrm/core/tpl/'; // Pour utilisation du tpl hrm sur cet écran

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}


		$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1);

		if (empty($object->lines)) {
			print '<tr><td colspan="4"><span class="opacitymedium">'.img_warning().' '.$langs->trans("TheJobProfileHasNoSkillsDefinedFixBefore").'</td></tr>';
		}

		// Form to add new line
		/*
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add products/services form

				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				if (empty($reshook))
					$object->formAddObjectLine(1, $mysoc, $soc);
			}
		}
		*/

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";

		print "<br>";
	}

	// list of comparison
	if ($object->status != Evaluation::STATUS_DRAFT) {
		// Recovery of skills related to this evaluation

		$sql = 'select';
		$sql .= '  e.ref,';
		$sql .= '  e.date_creation,';
		$sql .= '  e.fk_job,';
		$sql .= '  j.label as "refjob",';
		$sql .= '  ed.fk_skill,';

		$sql .= '  sk.label as "skilllabel",';
		$sql .= '  sk.skill_type,';
		$sql .= '  sk.description,';
		$sql .= '  ed.rankorder,';
		$sql .= '  ed.required_rank,';
		$sql .= '  ed.rankorder as "userRankForSkill",';
		$sql .= '  skdet_user.description as "userRankForSkillDesc",';
		$sql .= '  skdet_required.description as "required_rank_desc"';

		$sql .= '  FROM ' . MAIN_DB_PREFIX . 'hrm_evaluation as e';
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_evaluationdet as ed ON  e.rowid = ed.fk_evaluation';
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_job as j ON e.fk_job = j.rowid';
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_skill as sk ON ed.fk_skill = sk.rowid';
		$sql .= '  INNER JOIN ' . MAIN_DB_PREFIX . 'hrm_skilldet as skdet_user ON (skdet_user.fk_skill = sk.rowid AND skdet_user.rankorder = ed.rankorder)';
		//$sql .= "  LEFT JOIN " . MAIN_DB_PREFIX . "hrm_skillrank as skr ON (j.rowid = skr.fk_object AND skr.fk_skill = ed.fk_skill AND skr.objecttype = 'job')";
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_skilldet as skdet_required ON (skdet_required.fk_skill = sk.rowid AND skdet_required.rankorder = ed.required_rank)';
		$sql .= " WHERE e.rowid =" . ((int) $object->id);

		//      echo $sql;

		$resql = $db->query($sql);
		$Tab = array();

		if ($resql) {
			$num = 0;
			while ($obj = $db->fetch_object($resql)) {
				$Tab[$num] = new stdClass();
				$class = '';
				$Tab[$num]->skill_type = $obj->skill_type;
				$Tab[$num]->skill_id = $obj->fk_skill;
				$Tab[$num]->skilllabel = $obj->skilllabel;
				$Tab[$num]->description = $obj->description;
				$Tab[$num]->userRankForSkill = '<span title="'.$obj->userRankForSkillDesc.'" class="radio_js_bloc_number TNote_1">' . $obj->userRankForSkill . '</span>';
				$Tab[$num]->required_rank = '<span title="'.$obj->required_rank_desc.'" class="radio_js_bloc_number TNote_1">' . $obj->required_rank . '</span>';

				// Original code
				// if ($obj->userRankForSkill > $obj->required_rank) {
				// 	$title=$langs->trans('MaxlevelGreaterThanShort');
				// 	$class .= 'veryhappy diffnote';
				// } elseif ($obj->userRankForSkill == $obj->required_rank) {
				// 	$title=$langs->trans('MaxLevelEqualToShort');
				// 	$class .= 'happy diffnote';
				// } elseif ($obj->userRankForSkill < $obj->required_rank) {
				// 	$title=$langs->trans('MaxLevelLowerThanShort');
				// 	$class .= 'sad';
				// }

				# Custom code (Changing icon and style)
				if ($obj->userRankForSkill > $obj->required_rank) {
					$title = $langs->trans('MaxlevelGreaterThanShort');
					$class .= 'greater diffnote-custom';
					$content = '<svg class="scaled-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12.3657 0.888071C12.6127 0.352732 13.1484 0 13.75 0C14.9922 0 15.9723 0.358596 16.4904 1.29245C16.7159 1.69889 16.8037 2.13526 16.8438 2.51718C16.8826 2.88736 16.8826 3.28115 16.8826 3.62846L16.8825 7H20.0164C21.854 7 23.2408 8.64775 22.9651 10.4549L21.5921 19.4549C21.3697 20.9128 20.1225 22 18.6434 22H8L8 9H8.37734L12.3657 0.888071Z" fill="#3DC6A6"></path> <path d="M6 9H3.98322C2.32771 9 1 10.3511 1 12V19C1 20.6489 2.32771 22 3.98322 22H6L6 9Z" fill="#3DC6A6"></path> </g></svg>';
				} elseif ($obj->userRankForSkill == $obj->required_rank) {
					$title = $langs->trans('MaxLevelEqualToShort');
					$class .= 'pass diffnote-custom';
					$content = '<svg class="scaled-svg" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" fill="none"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path stroke="#3DC6A6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 5L8 15l-5-4"></path> </g></svg>';
				} elseif ($obj->userRankForSkill < $obj->required_rank) {
					$title = $langs->trans('MaxLevelLowerThanShort');
					$class .= 'fail diffnote-custom';
					$content = $obj->userRankForSkill - $obj->required_rank;
				}

				$Tab[$num]->result = '<span title="'.$title.'" class="classfortooltip ' . $class . ' note">' . $content . '</span>';

				$num++;
			}

			print '<div class="underbanner clearboth"></div>';
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("TypeSkill") . ' </th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Label") . '</th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Description") . '</th>';
			print '<th style="width:auto;text-align:center" class="liste_titre">' . $langs->trans("EmployeeRank") . '</th>';
			print '<th style="width:auto;text-align:center" class="liste_titre">' . $langs->trans("RequiredRank") . '</th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Result") . ' ' .$form->textwithpicto('', GetLegendSkills(), 1) .'</th>';
			print '</tr>';

			$sk = new Skill($db);
			foreach ($Tab as $t) {
				$sk->fetch($t->skill_id);
				print '<tr>';
				print ' <td>' . Skill::typeCodeToLabel($t->skill_type) . '</td>';
				print ' <td>' . $sk->getNomUrl(1) . '</td>';
				print ' <td>' . $t->description . '</td>';
				print ' <td align="center">' . $t->userRankForSkill . '</td>';
				print ' <td align="center">' . $t->required_rank . '</td>';
				print ' <td>' . $t->result . '</td>';
				print '</tr>';
			}

			print '</table>';

			?>

			<script>

				$(document).ready(function() {
					$(".radio_js_bloc_number").tooltip();
				});

			</script>

			<?php
		}
	}

	// Buttons for actions
	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			// Send
			if (empty($user->socid)) {
				print dolGetButtonAction('', $langs->trans('SendMail'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&token='.newToken().'#formmailbeforetitle');
			}

			// Back to draft
			if ($object->status == $object::STATUS_VALIDATED) {
				print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
				print dolGetButtonAction('', $langs->trans('Close'), 'close', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close&token='.newToken(), '', $permissiontodelete || ($object->status == $object::STATUS_CLOSED && $permissiontoclose));
			} elseif ($object->status != $object::STATUS_CLOSED) {
				print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
			}

			if ($object->status == $object::STATUS_CLOSED) {
				print dolGetButtonAction($langs->trans('ReOpen'), '', 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopen&token='.newToken(), '', $permissiontoadd);
			}


			// Validate
			if ($object->status == $object::STATUS_DRAFT) {
				if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
					print dolGetButtonAction($langs->trans('Save').'&nbsp;'.$langs->trans('and').'&nbsp;'.$langs->trans('Valid'), '', 'default', '#', 'btn_valid', $permissiontovalidate);
				} else {
					$langs->load("errors");
					print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
				}
			}


			// Delete (need delete permission, or if draft, just need create/modify permission)
			print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete);
		}


		print '</div>'."\n";
	}

	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 0;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->hrm->dir_output.'/'.$object->element.'/'.$objref;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $user->rights->hrm->evaluation->read; // If you can read, you can build the PDF to read content
			$delallowed = $user->rights->hrm->evaluation->write; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('hrm:Evaluation', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		// Show links to link elements
		$linktoelem = $form->showLinkToObjectBlock($object, null, array('evaluation'));
		$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);


		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', DOL_URL_ROOT.'/hrm/evaluation_agenda.php?id='.$object->id);

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'evaluation';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->hrm->dir_output;
	$trackid = 'evaluation'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
