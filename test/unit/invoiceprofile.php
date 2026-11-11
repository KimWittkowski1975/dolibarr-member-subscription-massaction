<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require __DIR__.'/preview.php';
require __DIR__.'/../../class/invoiceprofile.class.php';
$before = $passed;
class InvoiceProfileFixture extends MsmInvoiceProfile
{
	public $term = 1; public $rate = 0; public $item;
	protected function paymentTerm() { return $this->term; }
	protected function paymentTermLabel($id) { return 'Synthetic term'; }
	protected function vat($product) { return $this->rate; }
	protected function product($id, $user) { return $this->item; }
	protected function models($number, $pdf) { parent::models($number, $pdf); }
}
$conf->global = new stdClass(); $conf->entity = 1; $conf->currency = 'EUR';
$conf->modules = array('member' => 1, 'invoice' => 1, 'societe' => 1);
$user = new PreviewUser(); $mysoc = (object) array('country_id' => 1);
$member = (object) array('entity' => 1, 'socid' => 0, 'thirdparty' => null);
$profile = new InvoiceProfileFixture(null);
foreach (array('', 'none', 'bankdirect', 'bankviainvoice', 'invoiceonly') as $option) {
	$conf->global->ADHERENT_BANK_USE = $option;
	verify(msmInvoiceDefault($user) === ($option === 'invoiceonly'), 'invoiceonly default without bank');
}
$user->denied = array('societe.lire'); verify(!msmInvoiceDefault($user), 'default respects customer read rights'); $user->denied = array();
$conf->global->ADHERENT_BANK_USE = 'invoiceonly'; $conf->global->ADHERENT_DEFAULT_SENDINFOBYMAIL = 1; $conf->global->MAIN_USE_ADVANCED_PERMS = 1;
$user->denied = array('facture.invoice_advance.send');
verify(msmInvoiceDefault($user), 'invoiceonly default independent from configured mail and advanced send right');
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, true, true); }, 'NotEnoughPermissions');
verify($profile->resolve($member, $user, true, false)['term_label'] === 'Synthetic term', 'invoice without mail preserves confirmed payment term');
$user->denied = array(); unset($conf->global->ADHERENT_DEFAULT_SENDINFOBYMAIL, $conf->global->MAIN_USE_ADVANCED_PERMS);
$plan = $profile->resolve($member, $user, true, false);
verify($plan['quantity'] === 1 && $plan['price_base'] === 'TTC' && $plan['currency'] === 'EUR' && $plan['vat'] === 0.0 && $plan['term_label'] === 'Synthetic term', 'gross unit invoice plan with display metadata');
verify($plan['create_customer'] && $plan['customer_id'] === 0, 'explicit planned customer');
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'SubscriptionBulkThirdPartyMissing');
$member->socid = 4;
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, true, false); }, 'SubscriptionBulkThirdPartyMissing');
$member->thirdparty = (object) array('id' => 4, 'entity' => 1, 'multicurrency_code' => 'USD');
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'MSMInvoiceProfileUnsupported');
$member->thirdparty->multicurrency_code = 'EUR';
$old = $profile->resolve($member, $user, false, false);
$member->thirdparty->name = 'Changed';
verify($old !== $profile->resolve($member, $user, false, false), 'customer changes invalidate profile');
$configured = $profile->resolve($member, $user, false, false); $conf->global->ADHERENT_BANK_USE = 'bankdirect';
verify($configured !== $profile->resolve($member, $user, false, false), 'invoice setting changes invalidate profile'); unset($conf->global->ADHERENT_BANK_USE);
$profile->term = 0;
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'MSMInvoiceProfileUnsupported'); $profile->term = 1;
$profile->rate = '20*';
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'MSMInvoiceProfileUnsupported'); $profile->rate = 0;
$conf->global->FACTURE_ADDON_PDF = '../crabe';
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'MSMInvoiceProfileUnsupported'); unset($conf->global->FACTURE_ADDON_PDF);
$conf->global->ADHERENT_PRODUCT_ID_FOR_SUBSCRIPTIONS = 1; $profile->item = (object) array('packaging' => 2, 'ref' => 'P-<1>', 'label' => 'Synthetic & service');
fails(static function () use ($profile, $member, $user) { $profile->resolve($member, $user, false, false); }, 'MSMInvoiceProfileUnsupported'); unset($conf->global->ADHERENT_PRODUCT_ID_FOR_SUBSCRIPTIONS);
foreach (array('processing', 'done') as $phase) {
	$old = array('format' => 3, 'phase' => $phase, 'revision' => 7, 'options' => array('invoice' => false));
	verify(msmUpgradePreview($old) === $old, 'consumed old state never reopened');
}
$old['phase'] = 'confirm'; $new = msmUpgradePreview($old);
verify($new['phase'] === 'preview' && $new['revision'] === 8 && !$new['options']['invoice'], 'old confirmation needs fresh consent');
verify(msmUpgradePreview($new) === $new, 'format migration idempotent');
echo ($passed - $before)." invoice profile assertions passed (synthetic; no database).\n";
