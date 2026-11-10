<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Reuse the real V22 API bootstrap and synthetic preview fixtures, never main/conf/DB.
require __DIR__.'/preview.php';
require __DIR__.'/../../class/booking.class.php';
require __DIR__.'/../../class/bookingmember.class.php';
require __DIR__.'/../../class/mail.class.php';
require __DIR__.'/../../lib/booking.lib.php';
require __DIR__.'/../../lib/booking_renderer.lib.php';
$baseline = $passed;
class BookingDatabase extends PreviewDatabase
{
	public $type = 'mysqli'; public $transaction_opened = 0; public $active = false;
	public $saved = null; public $datefin = null; public $commitFail = false; public $rollbackFail = false;
	public $events = array(); public $lockFail = false; public $engine = 'InnoDB';
	public function escape($s) { return $s; }
	public function begin() { $this->events[] = 'begin'; $this->transaction_opened++; $this->active = true; return 1; }
	public function commit() { $this->events[] = 'commit'; if ($this->commitFail && $this->transaction_opened === 1) { return 0; } $this->transaction_opened--; if (!$this->transaction_opened) { $this->active = false; } return 1; }
	public function rollback() { $this->events[] = 'rollback'; $this->transaction_opened = 0; $this->active = false; $this->saved = null; return !$this->rollbackFail; }
	public function query($sql)
	{
		$this->queries[] = $sql;
		if ($sql === 'SELECT @@in_transaction AS active') { $row = (object) array('active' => (int) $this->active); }
		elseif (strpos($sql, 'SELECT ENGINE AS engine') === 0) { $row = (object) array('engine' => $this->engine); }
		elseif (strpos($sql, 'FOR UPDATE') !== false) { if ($this->lockFail) { return false; } $this->events[] = 'lock'; $row = (object) array('rowid' => 1, 'fk_adherent_type' => 1); }
		elseif (strpos($sql, 'SELECT fk_adherent, fk_type') === 0) { $row = $this->saved; }
		elseif (strpos($sql, 'SELECT datef FROM') === 0) { $row = (object) array('datef' => $this->datefin); }
		elseif (strpos($sql, 'SELECT datefin FROM') === 0) { $row = (object) array('datefin' => $this->datefin); }
		else { throw new LogicException('Unexpected booking query '.$sql); }
		return (object) array('rows' => $row ? array($row) : array());
	}
}
class BookingMemberDouble extends Adherent
{
	public $msmEndUpdate = 1; public $mode = ''; public $arguments;
	public function subscription($date, $amount, $accountid = 0, $operation = '', $label = '', $num_chq = '', $emetteur_nom = '', $emetteur_banque = '', $datesubend = 0, $fk_type = null)
	{
		$this->arguments = func_get_args(); $this->db->events[] = 'subscription';
		$this->db->begin(); $this->db->begin(); $this->db->commit(); $this->db->begin(); $this->db->commit(); $this->db->commit();
		$this->db->saved = (object) array('fk_adherent' => $this->id, 'fk_type' => $fk_type, 'subscription' => $amount, 'dateadh' => $this->db->idate($date), 'datef' => $this->db->idate($datesubend), 'note' => $label);
		$this->db->datefin = $this->db->saved->datef;
		if ($this->mode === 'r16') { $this->msmEndUpdate = -1; }
		if ($this->mode === 'wrong_amount') { $this->db->saved->subscription++; }
		if ($this->mode === 'historical') { $this->db->datefin = '2030-12-31 00:00:00'; }
		if ($this->mode === 'depth') { $this->db->transaction_opened = 0; $this->db->active = false; }
		if ($this->mode === 'fail') { return -1; }
		return 9;
	}
}
class BookingPreviewDouble
{
	public $row;
	public function previewSubscriptionWithOptions(...$args) { return $this->row; }
}
class BookingServiceDouble extends MsmBookingService
{
	public $object; public $reader;
	protected function currentUser($user) { return $user; }
	protected function member($id) { $this->object->id = $id; return $this->object; }
	protected function preview($member) { return $this->reader; }
}
function bookingFixture()
{
	$db = new BookingDatabase(); $member = new BookingMemberDouble($db); $member->id = 1; $member->typeid = 1; $member->entity = 1; $member->email = 'synthetic@example.invalid';
	$reader = new BookingPreviewDouble();
	$reader->row = array('start' => stamp('2026-10-10'), 'end' => stamp('2027-10-09'), 'amount' => 24.5, 'description' => 'Synthetic fee', 'fingerprint' => 'same', 'errors' => array(), 'overlaps' => array());
	$service = new BookingServiceDouble($db); $service->object = $member; $service->reader = $reader;
	return array($db, $service, $reader->row);
}
$options = array('invoice' => false, 'autocreate' => false, 'sendmail' => false, 'description' => 'Synthetic fee');
$user = new PreviewUser(); $conf->modules['member'] = 1; $conf->global = new stdClass();
foreach (array('', 'historical', 'r16', 'wrong_amount', 'depth', 'fail') as $mode) {
	list($db, $service, $row) = bookingFixture(); $service->object->mode = $mode;
	$result = $service->book(1, $row, $options, $user, false);
	verify($result['status'] === (in_array($mode, array('', 'historical'), true) ? 'success' : ($mode === 'depth' ? 'unknown' : 'failed')), 'booking mode '.$mode);
	if ($result['status'] === 'success') { verify($db->saved->subscription === 24.5 && $service->object->arguments[2] === 0 && $service->object->arguments[3] === '', 'business API amount and no payment'); }
	elseif ($mode !== 'depth') { verify($db->saved === null && in_array('rollback', $db->events, true), 'owned rollback '.$mode); }
}
list($db, $service, $row) = bookingFixture(); $row['amount'] = 0; $service->reader->row = $row;
verify($service->book(1, $row, $options, $user, false)['status'] === 'success' && $db->saved->subscription === 0, 'zero amount booking');
foreach (array('fingerprint', 'overlap', 'duplicate', 'permission', 'invoice', 'autocreate', 'engine', 'lock') as $case) {
	list($db, $service, $row) = bookingFixture(); $opts = $options; $user->denied = array();
	if ($case === 'fingerprint') { $service->reader->row['fingerprint'] = 'changed'; }
	if ($case === 'overlap') { $service->reader->row['overlaps'] = array(array('id' => 1)); }
	if ($case === 'duplicate') { $service->reader->row['errors'] = array('SubscriptionBulkDuplicateStart'); }
	if ($case === 'permission') { $user->denied = array('adherent.cotisation.creer'); }
	if ($case === 'invoice' || $case === 'autocreate') { $opts[$case] = true; }
	if ($case === 'engine') { $db->engine = 'MyISAM'; }
	if ($case === 'lock') { $db->lockFail = true; }
	$res = $service->book(1, $row, $opts, $user, false);
	verify($res['status'] !== 'success' && !in_array('subscription', $db->events, true), 'no write '.$case);
}
$user->denied = array();
list($db, $service, $row) = bookingFixture(); $row['overlaps'] = array(array('id' => 3)); $service->reader->row = $row;
verify($service->book(1, $row, $options, $user, true)['status'] === 'success', 'explicit current overlap consent');
list($db, $service, $row) = bookingFixture(); $service->reader->row['fingerprint'] = 'changed overlap';
verify($service->book(1, $row, $options, $user, true)['status'] === 'skipped', 'changed overlap defeats old consent');
foreach (array('commit', 'rollback', 'foreign') as $case) {
	list($db, $service, $row) = bookingFixture();
	if ($case === 'commit') { $db->commitFail = true; }
	if ($case === 'rollback') { $db->rollbackFail = true; $service->object->mode = 'fail'; }
	if ($case === 'foreign') { $db->transaction_opened = 1; }
	$res = $service->book(1, $row, $options, $user, false);
	verify($res['stop'] && $res['status'] === 'unknown', 'stop ambiguous '.$case);
	if ($case === 'foreign') { verify(!$db->events, 'foreign transaction untouched'); }
}
// Request consumption and server-held selection. No database operations here.
$state = array('phase' => 'confirm', 'revision' => 7, 'ids' => array(1), 'options' => $options, 'rows' => array(1 => $row));
$post = array('action' => 'book', 'revision' => '7');
$consumed = msmConsumeConfirmation($state, $post); verify($consumed['phase'] === 'processing', 'consume before effects');
fails(static function () use ($consumed, $post) { msmConsumeConfirmation($consumed, $post); }, 'MSMInvalidForm');
$invoiceState = $state; $invoiceState['options']['invoice'] = true; $invoiceState['options']['autocreate'] = true;
$invoiceConsumed = msmConsumeConfirmation($invoiceState, $post);
verify($invoiceConsumed['phase'] === 'processing' && $invoiceConsumed['options']['invoice'] && $invoiceConsumed['options']['autocreate'], 'server-held invoice consent consumed');
foreach (array('amount', 'invoice', 'autocreate', 'ids', 'email') as $field) { $bad = $post; $bad[$field] = '1'; fails(static function () use ($state, $bad) { msmConsumeConfirmation($state, $bad); }, 'MSMInvalidForm'); }
$bad = $post; $bad['overlap'] = array(999 => '1'); fails(static function () use ($state, $bad) { msmConsumeConfirmation($state, $bad); }, 'MSMInvalidForm');
// Mail hooks use the production dispatcher, but never a transport or real hook loader.
class MailHooksDouble
{
	public $code = 0; public $replace = false; public $changeEmail = false; public $seen;
	public function executeHooks($name, $parameters, &$object, &$action)
	{
		$this->seen = array($name, $parameters, $action); if ($this->replace) { $object = clone $object; $action = 'replaced'; }
		if ($this->changeEmail) { $object->email = 'different@example.invalid'; }
		return $this->code;
	}
}
class MailDouble extends MsmSubscriptionMail
{
	protected function permitted($user) { return MsmPreviewService::canCreateSubscriptionWithOptions($user); }
	public $manager; public $delivered = 0; public $outcome = 'transport';
	protected function hooks() { return $this->manager; }
	protected function deliver($member, array $row, $user) { $this->delivered++; return $this->outcome; }
}
$hookmanager = (object) array('old' => true); $oldHook = $hookmanager; $oldLangs = $langs;
foreach (array('off', 'transport', 'negative', 'positive', 'replace', 'redirect', 'missing', 'disabled', 'failed') as $case) {
	list($db, $service, $row) = bookingFixture(); $mail = new MailDouble($db); $mail->manager = new MailHooksDouble();
	$expected = 'transport'; $selected = true;
	if ($case === 'off') { $selected = false; $expected = 'not_selected'; }
	if ($case === 'negative') { $mail->manager->code = -1; $expected = 'failed'; }
	if ($case === 'positive') { $mail->manager->code = 1; $expected = 'hook'; }
	if ($case === 'replace') { $mail->manager->replace = true; }
	if ($case === 'redirect') { $mail->manager->changeEmail = true; $expected = 'failed'; }
	if ($case === 'missing') { $service->object->email = ''; $expected = 'missing'; }
	if ($case === 'disabled') { $conf->global->MAIN_DISABLE_ALL_MAILS = 1; $expected = 'disabled'; }
	if ($case === 'failed') { $mail->outcome = 'failed'; $expected = 'failed'; }
	verify($mail->send($service->object, $row, $user, $selected) === $expected, 'mail '.$case);
	verify($hookmanager === $oldHook && $langs === $oldLangs, 'mail context restored '.$case);
	if (!in_array($case, array('transport', 'replace', 'failed'), true)) { verify($mail->delivered === 0, 'no standard transport '.$case); }
	$conf->global = new stdClass();
}
class BatchServiceDouble
{
	public $calls = 0; public $outcomes;
	public function book(...$args) { $this->calls++; return array_shift($this->outcomes); }
}
class BatchMailDouble
{
	public $calls = 0;
	public function send(...$args) { $this->calls++; return 'failed'; }
}
$ok = array('status' => 'success', 'message' => 'MSMBooked', 'id' => 9, 'amount' => 12, 'mail' => 'unknown', 'stop' => false, '_member' => new stdClass());
$failed = array('status' => 'failed', 'message' => 'MSMBookingFailed', 'id' => 0, 'amount' => 0, 'mail' => 'not_selected', 'stop' => false);
$state['ids'] = array(1,2); $state['rows'] = array(1 => $row, 2 => $row); $state['options']['sendmail'] = true;
$state = msmConsumeConfirmation($state, $post); $service = new BatchServiceDouble(); $service->outcomes = array($failed, $ok); $mail = new BatchMailDouble(); $saved = array();
$done = msmProcessBooking($state, $service, $mail, $user, static function ($s) use (&$saved) { $saved[] = $s; });
verify($service->calls === 2 && $mail->calls === 1 && $done['results'][2]['status'] === 'success' && $done['results'][2]['mail'] === 'failed', 'per-member failure isolation and mail failure after booking');
verify($saved[1]['results'][2]['status'] === 'success' && $saved[1]['results'][2]['mail'] === 'unknown', 'persist committed uncertainty before mail');
fails(static function () use ($done, $post) { msmConsumeConfirmation($done, $post); }, 'MSMInvalidForm');
$failed['status'] = 'unknown'; $failed['stop'] = true; $service->outcomes = array($failed, $ok); $service->calls = 0; $mail->calls = 0;
$done = msmProcessBooking($state, $service, $mail, $user, static function ($s) {});
verify($service->calls === 1 && $mail->calls === 0 && $done['results'][2]['status'] === 'skipped', 'unknown commit stops batch and mail');
// Failure to persist a post-commit checkpoint prevents any mail or later booking.
$service->outcomes = array($ok, $ok); $service->calls = 0; $mail->calls = 0;
try {
	msmProcessBooking($state, $service, $mail, $user, static function ($s) { throw new RuntimeException('checkpoint failed'); });
	throw new LogicException('Expected checkpoint failure');
} catch (RuntimeException $e) {
	verify($e->getMessage() === 'checkpoint failed' && $service->calls === 1 && $mail->calls === 0, 'checkpoint failure stops mail and later writes');
}
// R16 observer invokes the actual V22 update_end_date implementation against a failing DB double.
class EndUpdateFailureDatabase extends BookingDatabase
{
	public function jdate($value) { return strtotime($value); }
	public function query($sql)
	{
		if (strpos($sql, 'SELECT rowid, datec as dateop') === 0) { return (object) array('rows' => array((object) array('rowid' => 1, 'dateop' => '2026-01-01', 'datedeb' => '2026-01-01', 'datefin' => '2026-12-31'))); }
		if (strpos($sql, 'UPDATE test_adherent SET') === 0) { return false; }
		return parent::query($sql);
	}
}
$observerDb = new EndUpdateFailureDatabase(); $observer = new MsmBookingMember($observerDb); $observer->id = 1;
verify($observer->update_end_date($user) === -1 && $observer->msmEndUpdate === -1, 'R16 actual V22 failed update observed');
// Model two independent module connections contending on the same member lock.
class ContendedDatabase extends BookingDatabase
{
	public $onLock;
	public function query($sql) {
		if (strpos($sql, 'SELECT rowid, fk_adherent_type') === 0 && $this->onLock) { $callback = $this->onLock; $this->onLock = null; $callback(); }
		return parent::query($sql);
	}
}
list($otherDb, $otherService, $row) = bookingFixture(); $otherDb->lockFail = true;
$db = new ContendedDatabase(); $service = new BookingServiceDouble($db); $service->object = new BookingMemberDouble($db); $service->object->typeid = 1;
$service->reader = new BookingPreviewDouble(); $service->reader->row = $row;
$db->onLock = static function () use ($otherService, $otherDb, $row, $options, $user) {
	$res = $otherService->book(1, $row, $options, $user, false);
	verify($res['status'] !== 'success' && !in_array('subscription', $otherDb->events, true), 'concurrent module lock loser cannot write');
};
verify($service->book(1, $row, $options, $user, false)['status'] === 'success', 'concurrent module lock winner commits');
$otherDb->lockFail = false; $otherService->reader->row['errors'] = array('SubscriptionBulkDuplicateStart');
verify($otherService->book(1, $row, $options, $user, false)['status'] === 'skipped', 'subsequent contender checks duplicate again');
// Render production confirmation/results using hostile synthetic labels.
$conf->global->MAIN_MAX_DECIMALS_UNIT = 2; $conf->global->MAIN_MAX_DECIMALS_TOT = 2; $conf->global->MAIN_MAX_DECIMALS_SHOWN = 2;
$display = array_merge($row, array('member' => '<script>alert(1)</script>', 'type' => '<type>', 'quantity' => 1, 'unit' => 'd', 'notices' => array(), 'warnings' => array(), 'thirdparty' => '', 'status' => 'ready'));
$ui = array('phase' => 'confirm', 'revision' => 1, 'ids' => array(1), 'options' => $options, 'rows' => array(1 => $display), 'common' => '');
$html = msmRenderBooking($ui, str_repeat('a', 32));
verify(strpos($html, '<script>') === false && strpos($html, 'value="book"') !== false && strpos($html, 'MSMOneDay') !== false, 'confirmation escaping booking control and one-day notice');
verify(strpos($html, 'id="msm-confirm-form"') !== false && strpos($html, 'id="msm-final-submit"') !== false && strpos($html, 'id="msm-submit-status"') !== false, 'final-submit loader hooks rendered');
$html = msmRenderPreview($ui, str_repeat('a', 32));
verify(strpos($html, 'name="sendmail"') < strpos($html, 'name="invoice"') && strpos($html, 'name="invoice"') < strpos($html, 'name="autocreate"'), 'required option order');
$conf->file = (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT));
$ui['phase'] = 'done'; $ui['results'] = array(1 => array('status' => 'success', 'message' => 'MSMBooked', 'id' => 9, 'amount' => 12, 'mail' => 'failed'));
$html = msmRenderBooking($ui, str_repeat('a', 32));
verify(strpos($html, '<script>') === false && strpos($html, 'MSMBooked') !== false && strpos($html, 'MSMMail_failed') !== false && strpos($html, 'value="book"') === false, 'result preserves booking success beside mail warning without replay button');
$ui['results'][1]['invoice'] = array('id' => 42, 'ref' => 'INV-<42>', 'entity' => 1, 'customer_id' => 5, 'customer_created' => true,
	'subscription_id' => 9, 'pdf' => array('status' => 'verified', 'message' => 'MSMPdfVerified'));
$html = msmRenderBooking($ui, str_repeat('a', 32), array(1 => array('invoice' => true, 'customer' => true)));
verify(strpos($html, '/compta/facture/card.php?facid=42') !== false && strpos($html, '/societe/card.php?socid=5') !== false, 'authorized invoice and created-customer links');
verify(strpos($html, 'INV-&lt;42&gt;') !== false && strpos($html, 'MSMPdfVerified') !== false && strpos($html, 'value="book"') === false, 'escaped invoice result and PDF status without replay');
$ui['results'][1] = array('status' => 'unknown', 'message' => 'MSMTransactionUnknown', 'id' => 0, 'amount' => 0, 'mail' => 'unknown', 'stop' => true,
	'rollback' => 'unconfirmed', 'diagnostic' => array('stage' => '<commit>', 'subscription_id' => 9, 'invoice_id' => 42, 'customer_id' => 5));
$html = msmRenderBooking($ui, str_repeat('a', 32));
verify(strpos($html, '&lt;commit&gt;') !== false && strpos($html, 'MSMRollbackUnconfirmed') !== false && strpos($html, 'MSMUnconfirmedIdentifier') !== false, 'unknown transaction renders reconciliation evidence');
// Narrow UI follow-up: state-specific title, no redundant cancel button, retained content.
verify(msmPageTitleKey('preview') === 'MSMTitle', 'UI preview title unchanged');
verify(msmPageTitleKey('confirm') === 'MSMConfirmTitle', 'UI separate confirmation title');
verify(msmPageTitleKey('done') === 'MSMResultsTitle', 'UI booking results title');
verify(msmPageTitleKey('processing') === 'MSMProcessingTitle', 'UI interrupted state is not completed results');
$de = file_get_contents(__DIR__.'/../../langs/de_DE/membersubscriptionmassaction.lang');
verify(strpos($de, "MSMResultsTitle=Mitgliedsbeiträge gesammelt - Buchungsergebnisse\n") !== false, 'UI exact German result title');
$ui['phase'] = 'preview'; $ui['rows'][1]['member'] = 'Synthetic Synthetic';
$html = msmRenderPreview($ui, str_repeat('a', 32));
verify(strpos($html, 'value="cancel"') === false && strpos($html, msmListUrl()) !== false, 'UI cancel removed and back link retained');
verify(strpos($html, 'Synthetic Synthetic #1') !== false, 'UI repeated name parts preserved');
$ui['phase'] = 'done'; $ui['results'][1] = array('status' => 'success', 'message' => 'MSMBooked', 'id' => 9, 'amount' => 12, 'mail' => 'not_selected');
$html = msmRenderBooking($ui, str_repeat('a', 32));
verify(strpos($html, '/adherents/subscription.php?rowid=1') !== false && strpos($html, ' #9</a>') !== false, 'UI member subscription link and contribution ID retained');
echo ($passed - $baseline)." booking/mail assertions passed (synthetic transactions and transports only).\n";
