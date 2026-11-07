<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Use the standard authenticated bootstrap. Server paths, never request paths.
define('CSRFCHECK_WITH_TOKEN', '1');
$loaded = false;
$candidates = array();
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$candidates[] = rtrim($_SERVER['CONTEXT_DOCUMENT_ROOT'], '/').'/main.inc.php';
}
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
	$candidates[] = dirname($_SERVER['SCRIPT_FILENAME'], 3).'/main.inc.php';
}
$candidates[] = dirname(__DIR__, 2).'/main.inc.php';
foreach (array_unique($candidates) as $bootstrap) {
	if (is_file($bootstrap)) {
		require_once $bootstrap;
		$loaded = true;
		break;
	}
}
if (!$loaded) {
	http_response_code(500);
	exit('Dolibarr bootstrap unavailable.');
}
header('Cache-Control: no-store');
require_once __DIR__.'/lib/membersubscriptionmassaction.lib.php';
$langs->load('membersubscriptionmassaction@membersubscriptionmassaction');
if (!msmCanUse($user)) {
	accessforbidden();
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET' && $method !== 'POST') {
	http_response_code(405);
	header('Allow: GET, POST');
	exit;
}
// Read the original scalar, so filtering cannot turn an invalid token into a valid one.
$token = $method === 'POST' ? ($_POST['run'] ?? null) : ($_GET['run'] ?? null);
try {
	$run = msmReadRun($_SESSION, $token, (int) $user->id, (int) $conf->entity, dol_now());
	$ids = msmSelectionIds($run['ids'], getDolGlobalInt('MAIN_LIMIT_FOR_MASS_ACTIONS', 1000));
	msmCheckMembers($db, $user, $ids);
	if ($method === 'POST') {
		if (GETPOST('action', 'aZ09', 2) !== 'cancel' || !msmValidCsrf($_POST['token'] ?? null, $_SESSION)) {
			accessforbidden();
		}
		msmCancelRun($_SESSION, $token, (int) $user->id, (int) $conf->entity, dol_now());
		header('Location: '.msmListUrl(), true, 303);
		exit;
	}
} catch (Exception $e) {
	accessforbidden($langs->trans('MSMInvalidRun'));
}

llxHeader('', $langs->trans('MSMTitle'));
print load_fiche_titre($langs->trans('MSMTitle'));
print '<div class="info">'.dol_escape_htmltag($langs->trans('MSMTechnicalNotice')).'</div>';
print '<p>'.dol_escape_htmltag($langs->trans('MSMDevelopmentStatus')).'</p>';
print '<p>'.dol_escape_htmltag($langs->trans('MSMSelectedCount', count($ids))).'</p>';
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.dol_escape_htmltag($langs->trans('MSMMemberId')).'</th></tr>';
foreach ($ids as $id) {
	print '<tr class="oddeven"><td>'.((int) $id).'</td></tr>';
}
print '</table></div>';
print '<form method="POST" action="'.dol_escape_htmltag(dol_buildpath('/membersubscriptionmassaction/subscription.php', 1)).'">';
print '<input type="hidden" name="token" value="'.dol_escape_htmltag(newToken()).'">';
print '<input type="hidden" name="run" value="'.dol_escape_htmltag($token).'">';
print '<button class="button" type="submit" name="action" value="cancel">'.dol_escape_htmltag($langs->trans('MSMCancel')).'</button>';
print '</form>';
print '<p><a href="'.dol_escape_htmltag(msmListUrl()).'">'.dol_escape_htmltag($langs->trans('MSMBack')).'</a></p>';
llxFooter();
$db->close();
