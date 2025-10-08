<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 * \file    admin/config/digiai.php
 * \ingroup digiriskdolibarr
 * \brief   Digiriskdolibarr digiai page.
 */

// Load DigiriskDolibarr environment
if (file_exists('../digiriskdolibarr.main.inc.php')) {
	require_once __DIR__ . '/../digiriskdolibarr.main.inc.php';
} elseif (file_exists('../../digiriskdolibarr.main.inc.php')) {
	require_once __DIR__ . '/../../digiriskdolibarr.main.inc.php';
} else {
	die('Include of digiriskdolibarr main fails');
}

global $conf, $db, $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/class/html.formprojet.class.php";
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";

require_once __DIR__ . '/../../lib/digiriskdolibarr.lib.php';

// Translations
saturne_load_langs(["admin", 'digiriskdolibarr@digiriskdolibarr']);

// Parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$openAiApiKey = GETPOST('OpenAIApiKey', 'alphanohtml');
$digiaiModel = GETPOST('DigiaiModel', 'alphanohtml');
$digiaiTemperature = GETPOST('DigiaiTemperature', 'alphanohtml');
$digiaiMaxTokens = GETPOST('DigiaiMaxTokens', 'alpha');
$digiaiCacheTtl = GETPOST('DigiaiCacheTtl', 'alpha');
$digiaiEndpoint = GETPOST('DigiaiEndpoint', 'alphanohtml');
$digiaiEnableChatbot = GETPOSTINT('DigiaiEnableChatbot');

// Security check - Protection if external user
$permissiontoread = $user->rights->digiriskdolibarr->adminpage->read;
saturne_check_access($permissiontoread);

/*
 * Actions
 */

$error = 0;

if ($action == 'update') {
    $maxTokensValue = ($digiaiMaxTokens === '' ? '' : (int) $digiaiMaxTokens);
    $cacheTtlValue = ($digiaiCacheTtl === '' ? '' : (int) $digiaiCacheTtl);

    dolibarr_set_const($db, "DIGIRISKDOLIBARR_CHATGPT_API_KEY", $openAiApiKey, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_MODEL", $digiaiModel, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_TEMPERATURE", $digiaiTemperature, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_MAX_TOKENS", $maxTokensValue, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_CACHE_TTL", $cacheTtlValue, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_ENDPOINT", $digiaiEndpoint, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "DIGIRISKDOLIBARR_DIGIAI_ENABLE_CHATBOT", $digiaiEnableChatbot ? 1 : 0, 'chaine', 0, '', $conf->entity);
}
// Actions set_mod, update_mask
require_once __DIR__ . '/../../../saturne/core/tpl/actions/admin_conf_actions.tpl.php';

/*
 * View
 */

if (isModEnabled('project')) {
	$formproject = new FormProjets($db);
}

$form = new Form($db);

$title    = $langs->trans("ModuleSetup", $moduleName);
$helpUrl = 'FR:Module_Digirisk';

saturne_header(0,'', $title, $helpUrl);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

// Configuration header
$head = digiriskdolibarr_admin_prepare_head();
print dol_get_fiche_head($head, 'digiai', $title, -1, "digiriskdolibarr_color@digiriskdolibarr");

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent editmode">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Name") . '</td>';
print '<td>' . $langs->trans("Description") . '</td>';
print '<td>' . $langs->trans("Value") . '</td>';
print '<td>' . $langs->trans("Action") . '</td>';
print '</tr>';

print '<tr class="oddeven"><td><label for="OpenAIApiKey">' . $langs->trans("OpenAIApiKey") . '</label></td>';
print '<td>' . $langs->trans("OpenAIApiKeyDescription") . '</td>';
print '<td><input type="text" name="OpenAIApiKey" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_CHATGPT_API_KEY) . '"></td>';
print '<td><input type="submit" class="button" name="save" value="' . $langs->trans("Save") . '">';
print '</td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiModel">' . $langs->trans("DigiAiModel") . '</label></td>';
print '<td>' . $langs->trans("DigiAiModelDescription") . '</td>';
print '<td><input type="text" name="DigiaiModel" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_DIGIAI_MODEL) . '"></td>';
print '<td></td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiTemperature">' . $langs->trans("DigiAiTemperature") . '</label></td>';
print '<td>' . $langs->trans("DigiAiTemperatureDescription") . '</td>';
print '<td><input type="number" step="0.1" min="0" max="1" name="DigiaiTemperature" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_DIGIAI_TEMPERATURE) . '"></td>';
print '<td></td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiMaxTokens">' . $langs->trans("DigiAiMaxTokens") . '</label></td>';
print '<td>' . $langs->trans("DigiAiMaxTokensDescription") . '</td>';
print '<td><input type="number" min="1" name="DigiaiMaxTokens" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_DIGIAI_MAX_TOKENS) . '"></td>';
print '<td></td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiCacheTtl">' . $langs->trans("DigiAiCacheTtl") . '</label></td>';
print '<td>' . $langs->trans("DigiAiCacheTtlDescription") . '</td>';
print '<td><input type="number" min="0" name="DigiaiCacheTtl" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_DIGIAI_CACHE_TTL) . '"></td>';
print '<td></td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiEndpoint">' . $langs->trans("DigiAiEndpoint") . '</label></td>';
print '<td>' . $langs->trans("DigiAiEndpointDescription") . '</td>';
print '<td><input type="text" name="DigiaiEndpoint" value="' . dol_escape_htmltag($conf->global->DIGIRISKDOLIBARR_DIGIAI_ENDPOINT) . '"></td>';
print '<td></td></tr>';

print '<tr class="oddeven"><td><label for="DigiaiEnableChatbot">' . $langs->trans("DigiAiEnableChatbot") . '</label></td>';
print '<td>' . $langs->trans("DigiAiEnableChatbotDescription") . '</td>';
print '<td>';
print '<select name="DigiaiEnableChatbot" id="DigiaiEnableChatbot">';
print '<option value="0"' . (empty($conf->global->DIGIRISKDOLIBARR_DIGIAI_ENABLE_CHATBOT) ? ' selected' : '') . '>' . $langs->trans('No') . '</option>';
print '<option value="1"' . (!empty($conf->global->DIGIRISKDOLIBARR_DIGIAI_ENABLE_CHATBOT) ? ' selected' : '') . '>' . $langs->trans('Yes') . '</option>';
print '</select>';
print '</td>';
print '<td></td></tr>';

print '</table>';
print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
