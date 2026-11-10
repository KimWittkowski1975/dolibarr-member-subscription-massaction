<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
/** Read-only invoice plan. Only server-derived values may enter confirmation. */
class MsmInvoiceProfile
{
	protected $db;
	public function __construct($db) { $this->db = $db; }
	protected function product($id, $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$product = new Product($this->db);
		if ($product->fetch($id) <= 0 || $product->status != 1
			|| !(isModEnabled('product') || isModEnabled('service'))
			|| !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)
			|| !checkUserAccessToObject($user, array('produit', 'service'), $product)) { throw new RuntimeException('MSMProductUnavailable'); }
		return $product;
	}
	protected function paymentTerm()
	{
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/paymentterm.class.php';
		$term = new PaymentTerm($this->db);
		return (int) $term->getDefaultId();
	}
	protected function vat($product)
	{
		global $mysoc;
		return getDolGlobalString('ADHERENT_VAT_FOR_SUBSCRIPTIONS') === 'defaultforfoundationcountry' ? get_default_tva($mysoc, $mysoc, $product) : 0;
	}
	/** No arbitrary module paths from configuration are accepted. */
	protected function models($number, $pdf)
	{
		foreach (array($number, $pdf) as $name) {
			if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $name)) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		}
		if (!is_readable(DOL_DOCUMENT_ROOT.'/core/modules/facture/'.$number.'.php')
			|| !is_readable(DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_'.$pdf.'.modules.php')) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
	}
	public static function fields($object, array $names)
	{
		$result = array();
		foreach ($names as $name) { $result[$name] = $object->$name ?? null; }
		return $result;
	}
	public function resolve($member, $user, $autocreate, $sendmail)
	{
		global $conf, $mysoc;
		if (!MsmPreviewService::canCreateSubscriptionWithOptions($user, true, $sendmail)) { throw new RuntimeException('NotEnoughPermissions'); }
		if ((int) $member->entity !== (int) $conf->entity || empty($conf->currency)) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		$customer = $member->thirdparty ?? null;
		if (!$customer && (!$autocreate || !empty($member->socid) || !$user->hasRight('societe', 'creer'))) { throw new RuntimeException('SubscriptionBulkThirdPartyMissing'); }
		if ($customer && ((int) $customer->entity !== (int) $conf->entity
			|| (!empty($customer->multicurrency_code) && $customer->multicurrency_code !== $conf->currency))) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		if ((isModEnabled('stock') && getDolGlobalInt('STOCK_CALCULATE_ON_BILL'))
			|| !empty($mysoc->localtax1_assuj) || !empty($mysoc->localtax2_assuj)) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		$productid = getDolGlobalInt('ADHERENT_PRODUCT_ID_FOR_SUBSCRIPTIONS');
		$product = $productid ? $this->product($productid, $user) : null;
		// Core packaging may change quantity; the supported profile is exactly one unit.
		if ($product && !empty($product->packaging)) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		$vat = $this->vat($productid);
		if (!is_numeric($vat) || !is_finite((float) $vat) || $vat < 0) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		$term = !empty($customer->cond_reglement_id) ? (int) $customer->cond_reglement_id : $this->paymentTerm();
		if ($term <= 0) { throw new RuntimeException('MSMInvoiceProfileUnsupported'); }
		$number = getDolGlobalString('FACTURE_ADDON', 'mod_facture_terre');
		$pdf = getDolGlobalString('FACTURE_ADDON_PDF');
		if ($pdf === '') { $pdf = 'crabe'; }
		$this->models($number, $pdf);
		$settings = array();
		foreach (array('ADHERENT_BANK_USE', 'ADHERENT_VAT_FOR_SUBSCRIPTIONS', 'ADHERENT_PRODUCT_ID_FOR_SUBSCRIPTIONS', 'FACTURE_ADDON', 'FACTURE_ADDON_PDF', 'FACTURE_ADDON_PDF_STANDARD', 'FACTURE_RIB_NUMBER', 'SOCIETE_CODECLIENT_ADDON', 'THIRDPARTY_DEFAULT_CREATE_CONTACT', 'THIRDPARTY_CUSTOMERCODE_EQUALS_MEMBERREF', 'SOCIETE_USEPREFIX', 'MAIN_USE_ADVANCED_PERMS', 'MAIN_ROUNDOFTOTAL_NOT_TOTALOFROUND', 'MAIN_MAX_DECIMALS_TOT', 'MAIN_MAX_DECIMALS_UNIT', 'MAIN_MAX_DECIMALS_SHOWN', 'MAIN_DISABLE_ALL_MAILS') as $name) { $settings[$name] = getDolGlobalString($name); }
		return array('entity' => (int) $conf->entity, 'currency' => $conf->currency,
			'date' => dol_print_date(dol_now(), '%Y-%m-%d'), 'product' => $productid, 'vat' => (float) $vat,
			'quantity' => 1, 'type' => 0, 'price_base' => 'TTC', 'term' => $term,
			'payment_mode' => (int) ($customer->mode_reglement_id ?? 0), 'bank' => (int) ($customer->fk_account ?? getDolGlobalInt('FACTURE_RIB_NUMBER')),
			'number_model' => $number, 'pdf_model' => $pdf, 'customer_id' => (int) ($customer->id ?? 0), 'create_customer' => !$customer,
			'customer_snapshot' => self::fields($customer, array('id', 'entity', 'client', 'code_client', 'name', 'address', 'zip', 'town', 'country_id', 'tva_assuj', 'tva_intra', 'localtax1_assuj', 'localtax2_assuj', 'default_lang', 'email', 'cond_reglement_id', 'mode_reglement_id', 'fk_account', 'multicurrency_code')),
			'product_snapshot' => self::fields($product, array('id', 'entity', 'status', 'type', 'packaging', 'tva_tx', 'localtax1_tx', 'localtax2_tx', 'fk_unit')),
			'company_snapshot' => self::fields($mysoc, array('country_id', 'tva_assuj', 'tva_intra', 'localtax1_assuj', 'localtax2_assuj', 'default_lang')),
			'settings' => $settings);
	}
}
