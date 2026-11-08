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

require_once __DIR__.'/lib/preview.lib.php';
require_once __DIR__.'/class/period.class.php';
require_once __DIR__.'/lib/preview_renderer.lib.php';
$langs->load('members');
$error = '';
try {
	if ($method === 'GET' && isset($_GET['run'])) {
		$selection = msmReadRun($_SESSION, $_GET['run'], (int) $user->id, (int) $conf->entity, dol_now());
		msmCheckMembers($db, $user, $selection['ids']);
		$key = msmCreatePreview($_SESSION, $selection, dol_now());
		header('Location: '.dol_buildpath('/membersubscriptionmassaction/subscription.php', 1).'?preview='.$key, true, 303);
		exit;
	}
	$key = $method === 'POST' ? ($_POST['preview'] ?? null) : ($_GET['preview'] ?? null);
	$state = msmReadPreview($_SESSION, $key, (int) $user->id, (int) $conf->entity, dol_now());
} catch (Exception $e) {
	accessforbidden($langs->trans('MSMInvalidRun'));
}
if ($method === 'POST') {
	if (!msmValidCsrf($_POST['token'] ?? null, $_SESSION)) { accessforbidden(); }
	if (($_POST['action'] ?? null) === 'cancel') {
		unset($_SESSION['msm_previews'][$key]);
		header('Location: '.msmListUrl(), true, 303);
		exit;
	}
	try {
		$state = msmUpdatePreview($state, $_POST);
		$state = msmEvaluatePreview($db, $user, $state);
		$_SESSION['msm_previews'][$key] = $state;
	} catch (InvalidArgumentException $e) {
		$_SESSION['msm_previews'][$key]['flash'] = in_array($e->getMessage(), array('SubscriptionBulkInvalidDate', 'SubscriptionBulkDescriptionInvalid'), true) ? $e->getMessage() : 'MSMInvalidForm';
	} catch (Throwable $e) {
		// Fail closed. Never show an earlier successful result as the current validation.
		$_SESSION['msm_previews'][$key]['rows'] = array();
		$_SESSION['msm_previews'][$key]['flash'] = 'SubscriptionBulkDatabaseError';
	}
	header('Location: '.dol_buildpath('/membersubscriptionmassaction/subscription.php', 1).'?preview='.$key, true, 303);
	exit;
}
try {
	$state = msmEvaluatePreview($db, $user, $state);
	$error = $state['flash'] ?? '';
	unset($state['flash']);
	$_SESSION['msm_previews'][$key] = $state;
} catch (Throwable $e) {
	$error = 'SubscriptionBulkDatabaseError';
	$state['rows'] = array();
}
llxHeader('', $langs->trans('MSMTitle'));
print load_fiche_titre($langs->trans('MSMTitle'));
if ($error) { print '<div class="error">'.msmText($error).'</div>'; }
if ($state['rows']) { print msmRenderPreview($state, $key); }
else { print '<p><a href="'.dol_escape_htmltag(msmListUrl()).'">'.msmText('MSMBack').'</a></p>'; }
llxFooter();
$db->close();
