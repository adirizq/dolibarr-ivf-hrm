<?php
/* Copyright (C) 2021 Gauthier VERDOL <gauthier.verdol@atm-consulting.fr>
 * Copyright (C) 2021 Greg Rastklan <greg.rastklan@atm-consulting.fr>
 * Copyright (C) 2021 Jean-Pascal BOUDET <jean-pascal.boudet@atm-consulting.fr>
 * Copyright (C) 2021 Grégory BLEMAND <gregory.blemand@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/hrm_evaluation.lib.php
 * \ingroup hrm
 * \brief   Library files with common functions for Evaluation
 */

/**
 * Prepare array of tabs for Evaluation
 *
 * @param	Evaluation	$object		Evaluation
 * @return 	array					Array of tabs
 */
function evaluationPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("hrm");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/hrm/evaluation_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("EvaluationCard");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) {
			$nbNote++;
		}
		if (!empty($object->note_public)) {
			$nbNote++;
		}
		$head[$h][0] = dol_buildpath('/hrm/evaluation_note.php', 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
		}
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->hrm->dir_output."/evaluation/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/hrm/evaluation_document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	$head[$h][0] = dol_buildpath("/hrm/evaluation_agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@hrm:/hrm/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@hrm:/hrm/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'evaluation@hrm');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'evaluation@hrm', 'remove');

	return $head;
}

/**
 * @return string
 */
function GetLegendSkills()
{
	global $langs;
	
	// Original code
	// $legendSkills = '<div style="font-style:italic;">
	// 	' . $langs->trans('legend') . '
	// 	<table class="border" width="100%">
	// 		<tr>
	// 			<td><span style="vertical-align:middle" class="toohappy diffnote little"></span>
	// 			' . $langs->trans('CompetenceAcquiredByOneOrMore') . '</td>
	// 		</tr>
	// 		<tr>
	// 			<td><span style="vertical-align:middle" class="veryhappy diffnote little"></span>
	// 				' . $langs->trans('MaxlevelGreaterThan') . '</td>
	// 		</tr>
	// 		<tr>
	// 			<td><span style="vertical-align:middle" class="happy diffnote little"></span>
	// 				' . $langs->trans('MaxLevelEqualTo') . '</td>
	// 		</tr>
	// 		<tr>
	// 			<td><span style="vertical-align:middle" class="sad diffnote little"></span>
	// 				' . $langs->trans('MaxLevelLowerThan') . '</td>
	// 		</tr>
	// 		<tr>
	// 			<td><span style="vertical-align:middle" class="toosad diffnote little"></span>
	// 				' . $langs->trans('SkillNotAcquired') . '</td>
	// 		</tr>
	// 	</table>
	// </div>';

	# Custom legend
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
