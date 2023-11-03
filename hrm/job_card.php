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
 *    \file       htdocs/hrm/job_card.php
 *    \ingroup    hrm
 *    \brief      Page to create/edit/view job
 */


// Load Dolibarr environment
require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/class/job.class.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/lib/hrm_job.lib.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/class/skill.class.php'; // additional code
require_once DOL_DOCUMENT_ROOT . '/hrm/class/skillrank.class.php'; // additional code

// Load translation files required by the page
$langs->loadLangs(array('hrm', 'other', 'products'));   // why products?

// Get parameters
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'jobcard'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid   = GETPOST('lineid', 'int');

// Initialize technical objects
$object = new Job($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->hrm->dir_output . '/temp/massgeneration/' . $user->id;
$hookmanager->initHooks(array('jobcard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_' . $key, 'alpha')) {
		$search[$key] = GETPOST('search_' . $key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

// Permissions
$permissiontoread = $user->rights->hrm->all->read;
$permissiontoadd  = $user->rights->hrm->all->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->hrm->all->delete;
$upload_dir = $conf->hrm->multidir_output[isset($object->entity) ? $object->entity : 1] . '/job';

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->element, $object->id, $object->table_element, '', 'fk_soc', 'rowid', $isdraft);
if (empty($conf->hrm->enabled)) accessforbidden();
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

	$backurlforlist = dol_buildpath('/hrm/job_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/hrm/job_card.php', 1) . '?id=' . ($id > 0 ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'hrm_JOB_MODIFY'; // Name of trigger action code to execute when we modify record


	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT . '/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT . '/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';

	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOST('fk_soc', 'int'), '', '', 'date', '', $user, $triggermodname);
	}
	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOST('projectid', 'int'));
	}

	// custom code for hiding skill
	if ($action == 'toggle_hide_skill') {
		$skill_id = GETPOST('skill_id', 'int');

		$hidden_skills = $object->array_options['options_hiddenskill'];
		$hidden_skills_arr = ($hidden_skills != "") ? explode(',', $hidden_skills) : array();

		print_r($hidden_skills_arr);

		if(in_array($skill_id, $hidden_skills_arr)) {
			$hidden_skills_arr = array_diff($hidden_skills_arr, array($skill_id));
		} else {
			$hidden_skills_arr[] = $skill_id;
		}
		
		sort($hidden_skills_arr);
		$hidden_skills = implode(',', $hidden_skills_arr);

		$object->array_options['options_hiddenskill'] = $hidden_skills;
		$object->tms = $db->idate(dol_now()); // modification date
		$object->update($user);

		header('Location: ' . $_SERVER['HTTP_REFERER']);
	}

	// Actions to send emails
	$triggersendname = 'hrm_JOB_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_JOB_TO';
	$trackid = 'job' . $object->id;
	include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}


/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);

$title = $langs->trans("Job");
$help_url = '';
$css = array(); // custom code
$css[] = '/hrm/css/style.css'; // custom code
llxHeader('', $title, $help_url, '', 0, 0, '', $css); // custom code
// llxHeader('', $title, $help_url);

// Example : Adding jquery code
// print '<script type="text/javascript" language="javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';


// Part to create
if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewJobProfile", $langs->transnoentities('Job')), '', 'object_' . $object->picto);

	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
	}

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">' . "\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

	print '</table>' . "\n";

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" name="add" value="' . dol_escape_htmltag($langs->trans("Create")) . '">';
	print '&nbsp; ';
	print '<input type="' . ($backtopage ? "submit" : "button") . '" class="button button-cancel" name="cancel" value="' . dol_escape_htmltag($langs->trans("Cancel")) . '"' . ($backtopage ? '' : ' onclick="history.go(-1)"') . '>'; // Cancel for create does not post form if we don't know the backtopage
	print '</div>';

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("JobProfile"), '', 'object_' . $object->picto);

	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="' . $object->id . '">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">' . "\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center"><input type="submit" class="button button-save" name="save" value="' . $langs->trans("Save") . '">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="' . $langs->trans("Cancel") . '">';
	print '</div>';

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = jobPrepareHead($object);
	$picto = 'company.png';
	print dol_get_fiche_head($head, 'job_card', $langs->trans("Workstation"), -1, $object->picto);

	$formconfirm = '';

	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteJob'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}
	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Confirmation of action xxxx
	if ($action == 'xxx') {
		$formquestion = array();
		/*
		$forcecombo=0;
		if ($conf->browser->name == 'ie') $forcecombo = 1;	// There is a bug in IE10 that make combo inside popup crazy
		$formquestion = array(
			// 'text' => $langs->trans("ConfirmClone"),
			// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
			// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
			// array('type' => 'other',    'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse')?GETPOST('idwarehouse'):'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
		);
		*/
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
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
	$linkback = '<a href="' . dol_buildpath('/hrm/job_list.php', 1) . '?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref = '<div class="refid">';
	$morehtmlref.= $object->label;
	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'rowid', $morehtmlref);


	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field
	//unset($object->fields['fk_project']);				// Hide field already shown in banner
	//unset($object->fields['fk_soc']);					// Hide field already shown in banner
	$object->fields['label']['visible']=0; // Already in banner
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();


	/*
	 * Lines
	 */

	if (!empty($object->table_element_line)) {
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . (($action != 'editline') ? '' : '#line_' . GETPOST('lineid', 'int')) . '" method="POST">
		<input type="hidden" name="token" value="' . newToken() . '">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline') . '">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id . '">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines)) {
			$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1);
		}

		// Form to add new line
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

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}


	// Buttons for actions

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">' . "\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			// Back to draft
			if ($object->status == $object::STATUS_VALIDATED) {
				print dolGetButtonAction($langs->trans('SetToDraft'), '', 'default', $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=confirm_setdraft&confirm=yes&token=' . newToken(), '', $permissiontoadd);
			}

			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=edit&token=' . newToken(), '', $permissiontoadd);

			// Delete (need delete permission, or if draft, just need create/modify permission)
			print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete);
		}
		print '</div>' . "\n";
	}

	// Custom code

	function GetLegendSkills()
	{
		global $langs;

		$legendSkills = '<div style="font-style:italic;">' . $langs->trans('legend') . '
			<table class="border" width="100%">
				<tr>
					<td><span style="vertical-align:middle" class="greater diffnote-custom little"><svg class="scaled-svg-small" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12.3657 0.888071C12.6127 0.352732 13.1484 0 13.75 0C14.9922 0 15.9723 0.358596 16.4904 1.29245C16.7159 1.69889 16.8037 2.13526 16.8438 2.51718C16.8826 2.88736 16.8826 3.28115 16.8826 3.62846L16.8825 7H20.0164C21.854 7 23.2408 8.64775 22.9651 10.4549L21.5921 19.4549C21.3697 20.9128 20.1225 22 18.6434 22H8L8 9H8.37734L12.3657 0.888071Z" fill="#3DC6A6"></path> <path d="M6 9H3.98322C2.32771 9 1 10.3511 1 12V19C1 20.6489 2.32771 22 3.98322 22H6L6 9Z" fill="#3DC6A6"></path></g></svg></span>&nbsp;&nbsp;&nbsp;' . $langs->trans('MaxlevelGreaterThan') . '</td>
				</tr>
				<tr>
					<td><span style="vertical-align:middle" class="pass diffnote-custom little"><svg class="scaled-svg-small" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" fill="none"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path stroke="#3DC6A6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 5L8 15l-5-4"></path></g></svg></span>&nbsp;&nbsp;&nbsp;' . $langs->trans('MaxLevelEqualTo') . '</td>
				</tr>
				<tr>
					<td><span style="vertical-align:middle" class="fail diffnote-custom little"></span>&nbsp;&nbsp;&nbsp;' . $langs->trans('MaxLevelLowerThan') . '</td>
				</tr>
			</table>
			</div>';
		return $legendSkills;
	}


	// Hide/show skill icon

	$eye_slash = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-eye-slash-fill" viewBox="0 0 16 16"> <path d="m10.79 12.912-1.614-1.615a3.5 3.5 0 0 1-4.474-4.474l-2.06-2.06C.938 6.278 0 8 0 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588zM5.21 3.088A7.028 7.028 0 0 1 8 2.5c5 0 8 5.5 8 5.5s-.939 1.721-2.641 3.238l-2.062-2.062a3.5 3.5 0 0 0-4.474-4.474L5.21 3.089z"/> <path d="M5.525 7.646a2.5 2.5 0 0 0 2.829 2.829l-2.83-2.829zm4.95.708-2.829-2.83a2.5 2.5 0 0 1 2.829 2.829zm3.171 6-12-12 .708-.708 12 12-.708.708z"/> </svg>';
	$eye_open = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16"> <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/> <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/> </svg>';

	
	// Hide/show skill feature

	print '<h2 style="margin-top: 2rem; color: var(--colortexttitlenotab);">Skills Achievement</h2>';

	$sql = 'SELECT';
	$sql .= '  s.rowid AS "skill_id",';
	$sql .= '  j.rowid AS "job_id",';
	$sql .= '  j.label AS "job_position",';
	$sql .= '  s.label AS "skill_code",';
	$sql .= '  s.skill_type,';
	$sql .= '  s.description AS "skill_description",';
	$sql .= '  es.required_rank,';
	$sql .= '  ROUND(AVG(es.rankorder),2) AS "average_skill_score",';
	$sql .= '  CONCAT(SUM(CASE WHEN es.rankorder >= es.required_rank THEN 1 ELSE 0 END), "/", COUNT(u.rowid)) AS "skill_achievement_ratio",';
	$sql .= '  FIND_IN_SET(s.rowid, je.hiddenskill) != 0 AS "hidden"';

	$sql .= '  FROM ' . MAIN_DB_PREFIX . 'hrm_evaluationdet as es';
	$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_evaluation as e ON es.fk_evaluation = e.rowid';
	$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'user u ON e.fk_user = u.rowid';
	$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_skill s ON es.fk_skill = s.rowid';
	$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_job j ON e.fk_job = j.rowid';
	$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_job_extrafields je ON j.rowid = je.fk_object';
	$sql .= "  WHERE j.rowid =" . ((int) $object->id);
	$sql .= "  GROUP BY j.rowid, s.rowid";

	//      echo $sql;

	$resql = $db->query($sql);
	$Tab = array();

	if ($resql) {
		$num = 0;
		while ($obj = $db->fetch_object($resql)) {
			$Tab[$num] = new stdClass();
			$class = '';
			$Tab[$num]->id = $obj->id; 
			$Tab[$num]->skill_type = $obj->skill_type;
			$Tab[$num]->skill_id = $obj->skill_id;
			$Tab[$num]->skilllabel = $obj->skill_code;
			$Tab[$num]->description = $obj->skill_description;
			$Tab[$num]->hidden = $obj->hidden;
			$Tab[$num]->userRankForSkill = '<span class="radio_js_bloc_number TNote_1">' . $obj->average_skill_score . '</span>';
			$Tab[$num]->required_rank = '<span class="radio_js_bloc_number TNote_1">' . $obj->required_rank . '</span>';
			$Tab[$num]->skill_achievement_ratio = '<span class="radio_js_bloc_number TNote_1">' . $obj->skill_achievement_ratio . '</span>';

			if ($obj->average_skill_score > $obj->required_rank) {
				$title = $langs->trans('MaxlevelGreaterThanShort');
				$class .= 'greater diffnote-custom';
				$content = '<svg class="scaled-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12.3657 0.888071C12.6127 0.352732 13.1484 0 13.75 0C14.9922 0 15.9723 0.358596 16.4904 1.29245C16.7159 1.69889 16.8037 2.13526 16.8438 2.51718C16.8826 2.88736 16.8826 3.28115 16.8826 3.62846L16.8825 7H20.0164C21.854 7 23.2408 8.64775 22.9651 10.4549L21.5921 19.4549C21.3697 20.9128 20.1225 22 18.6434 22H8L8 9H8.37734L12.3657 0.888071Z" fill="#3DC6A6"></path> <path d="M6 9H3.98322C2.32771 9 1 10.3511 1 12V19C1 20.6489 2.32771 22 3.98322 22H6L6 9Z" fill="#3DC6A6"></path> </g></svg>';
			} elseif ($obj->average_skill_score == $obj->required_rank) {
				$title = $langs->trans('MaxLevelEqualToShort');
				$class .= 'pass diffnote-custom';
				$content = '<svg class="scaled-svg" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" fill="none"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path stroke="#3DC6A6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 5L8 15l-5-4"></path> </g></svg>';
			} elseif ($obj->average_skill_score < $obj->required_rank) {
				$title = $langs->trans('MaxLevelLowerThanShort');
				$class .= 'fail diffnote-custom';
				$content = $obj->average_skill_score - $obj->required_rank;
			}

			if ($obj->hidden == 1){
				$Tab[$num]->hideskill = '<a class="reposition" style="color:black; font-size:2rem;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&skill_id=' . $obj->skill_id . '&amp;action=toggle_hide_skill' . '">' . $eye_open .'</a>';
				$Tab[$num]->hideskillbg = '<tr style="background:lightgray;">';
			} else {
				$Tab[$num]->hideskill = '<a class="reposition" style="color:black; font-size:2rem;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&skill_id=' . $obj->skill_id . '&amp;action=toggle_hide_skill' . '">' . $eye_slash .'</a>';
				$Tab[$num]->hideskillbg = '<tr>';
			}

			$Tab[$num]->result = '<span title="' . $title . '" class="classfortooltip ' . $class . ' note">' . $content . '</span>';

			$num++;
		}

		print '<div class="underbanner clearboth"></div>';
		print '<table class="noborder centpercent">';

		print '<tr class="liste_titre">';
		print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("TypeSkill") . ' </th>';
		print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Label") . '</th>';
		print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Description") . '</th>';
		print '<th style="width:auto;text-align:center" class="liste_titre">' . $langs->trans("RequiredRank") . '</th>';
		print '<th style="width:auto;text-align:center" class="liste_titre">' . 'Average employees skill score' . '</th>';
		print '<th style="width:auto;text-align:center" class="liste_titre">' . 'Total employees pass standard' . '</th>';
		print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Result") . ' ' . $form->textwithpicto('', GetLegendSkills(), 1) . '</th>';
		print '<th style="width:auto;text-align:center" class="liste_titre">Hide/Show Skill</th>';

		print '</tr>';

		$visible_skills = array_filter($Tab, function($obj) {
			return $obj->hidden != 1;
		});

		$hidden_skills = array_filter($Tab, function($obj) {
			return $obj->hidden == 1;
		});

		$Tab = array_merge($visible_skills, $hidden_skills);

		$sk = new Skill($db);
		foreach ($Tab as $t) {
			$sk->fetch($t->skill_id);
			print $t->hideskillbg;
			print ' <td>' . Skill::typeCodeToLabel($t->skill_type) . '</td>';
			print ' <td>' . $sk->getNomUrl(1) . '</td>';
			print ' <td>' . $t->description . '</td>';
			print ' <td align="center">' . $t->required_rank . '</td>';
			print ' <td align="center">' . $t->userRankForSkill . '</td>';
			print ' <td align="center">' . $t->skill_achievement_ratio . '</td>';
			print ' <td>' . $t->result . '</td>';
			print '<td align="center" class="liste_titre">';
			print $t->hideskill;
			print '</td>';
			
			print '</tr>';
		}

		print '</table>';
	}

	// End custom code


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
			$relativepath = $objref . '/' . $objref . '.pdf';
			$filedir = $conf->hrm->dir_output . '/' . $object->element . '/' . $objref;
			$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
			$genallowed = $user->rights->hrm->job->read; // If you can read, you can build the PDF to read content
			$delallowed = $user->rights->hrm->job->write; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('hrm:Job', $object->element . '/' . $objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		// Show links to link elements
		$linktoelem = $form->showLinkToObjectBlock($object, null, array('job'));
		$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);


		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', DOL_URL_ROOT.'/hrm/job_agenda.php?id='.$object->id);

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element . '@' . $object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

		print '</div></div>';
	}

	// Presend form
	$modelmail = 'job';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->hrm->dir_output;
	$trackid = 'job' . $object->id;

	include DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
