<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *    \file       htdocs/hrm/evaluation_note.php
 *    \ingroup    hrm
 *    \brief      Tab for notes on Evaluation
 */

// Load Dolibarr environment
require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/hrm/class/evaluation.class.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/class/job.class.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/lib/hrm_evaluation.lib.php';
require_once DOL_DOCUMENT_ROOT . '/hrm/class/skill.class.php';


// Load translation files required by the page
$langs->loadLangs(array('hrm', 'companies'));

// Get parameters
$id   = GETPOST('id', 'int');
$ref  = GETPOST('ref', 'alpha');

$action     = GETPOST('action', 'aZ09');
$cancel     = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object = new Evaluation($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->hrm->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('evaluationnote', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id > 0 || !empty($ref)) {
	$upload_dir = $conf->hrm->multidir_output[!empty($object->entity) ? $object->entity : $conf->entity]."/".$object->id;
}

// Permissions
$permissionnote   = $user->rights->hrm->evaluation->write; // Used by the include of actions_setnotes.inc.php
$permissiontoread = $user->rights->hrm->evaluation->read;  // Used by the include of actions_addupdatedelete.inc.php

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->element, $object->id, $object->table_element, '', 'fk_soc', 'rowid', $isdraft);
//if (empty($conf->hrm->enabled)) accessforbidden();
//if (!$permissiontoread) accessforbidden();


/*
 * Actions
 */

$reshook = $hookmanager->executeHooks('doActions', array(), $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php'; // Must be include, not include_once
}


/*
 * View
 */

$form = new Form($db);

//$help_url='EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes';

// Original
// $help_url = '';
// llxHeader('', $langs->trans('Evaluation'), $help_url);

// Custom
$help_url = '';
$css = array();
$css[] = '/hrm/css/style.css';
llxHeader('', $langs->trans('Evaluation'), $help_url, '', 0, 0, '', $css);

if ($id > 0 || !empty($ref)) {
	$object->fetch_thirdparty();

	$head = evaluationPrepareHead($object);

	print dol_get_fiche_head($head, 'note', $langs->trans('Notes'), -1, $object->picto);

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


	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';


	$cssclass = "titlefield";
	include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

	// Custom code for showing skill gap
	print '<h2 style="margin-top: 2rem; color: var(--colortexttitlenotab);">Skills gap</h2>';
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
		// Original code
		// $sql .= '  INNER JOIN ' . MAIN_DB_PREFIX . 'hrm_skilldet as skdet_user ON (skdet_user.fk_skill = sk.rowid AND skdet_user.rankorder = ed.rankorder)';
		// Modified code
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_skilldet as skdet_user ON (skdet_user.fk_skill = sk.rowid AND skdet_user.rankorder = ed.rankorder)';
		//$sql .= "  LEFT JOIN " . MAIN_DB_PREFIX . "hrm_skillrank as skr ON (j.rowid = skr.fk_object AND skr.fk_skill = ed.fk_skill AND skr.objecttype = 'job')";
		$sql .= '  LEFT JOIN ' . MAIN_DB_PREFIX . 'hrm_skilldet as skdet_required ON (skdet_required.fk_skill = sk.rowid AND skdet_required.rankorder = ed.required_rank)';
		$sql .= " WHERE e.rowid =" . ((int) $object->id);

		//      echo $sql;

		$resql = $db->query($sql);
		$Tab = array();

		if ($resql) {
			$num = 0;
			while ($obj = $db->fetch_object($resql)) {
				if ($obj->userRankForSkill < $obj->required_rank) {
					$Tab[$num] = new stdClass();
					$class = '';
					$Tab[$num]->skill_type = $obj->skill_type;
					$Tab[$num]->skill_id = $obj->fk_skill;
					$Tab[$num]->skilllabel = $obj->skilllabel;
					$Tab[$num]->description = $obj->description;
					$Tab[$num]->userRankForSkill = '<span title="' . $obj->userRankForSkillDesc . '" class="radio_js_bloc_number TNote_1">' . $obj->userRankForSkill . '</span>';
					$Tab[$num]->required_rank = '<span title="' . $obj->required_rank_desc . '" class="radio_js_bloc_number TNote_1">' . $obj->required_rank . '</span>';

					$title = $langs->trans('MaxLevelLowerThanShort');
					$class .= 'fail diffnote-custom';
					$content = $obj->userRankForSkill - $obj->required_rank;

					$Tab[$num]->result = '<span title="' . $title . '" class="classfortooltip ' . $class . ' note">' . $content . '</span>';

					$num++;
				}
			}

			print '<div class="underbanner clearboth"></div>';
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("TypeSkill") . ' </th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Label") . '</th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">' . $langs->trans("Description") . '</th>';
			print '<th style="width:auto;text-align:auto" class="liste_titre">Gap</th>';
			print '</tr>';

			$sk = new Skill($db);
			foreach ($Tab as $t) {
				$sk->fetch($t->skill_id);
				print '<tr>';
				print ' <td>' . Skill::typeCodeToLabel($t->skill_type) . '</td>';
				print ' <td>' . $sk->getNomUrl(1) . '</td>';
				print ' <td>' . $t->description . '</td>';
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

	print '</div>';

	print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();
