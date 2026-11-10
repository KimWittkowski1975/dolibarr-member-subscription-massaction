<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/invoiceprofile.class.php';
/** Core business APIs only; the caller owns the outer transaction. */
class MsmInvoiceService
{
	protected $db;
	public function __construct($db) { $this->db = $db; }
	protected function one($sql)
	{
		$res = $this->db->query($sql);
		if (!$res) { throw new RuntimeException('SubscriptionBulkDatabaseError'); }
		$row = $this->db->fetch_object($res); $this->db->free($res); return $row;
	}
	protected function invoice() { require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php'; return new Facture($this->db); }
	protected function customer() { require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php'; return new Societe($this->db); }
	public function lock($id)
	{
		foreach (array('societe', 'socpeople', 'societe_commerciaux', 'facture', 'facturedet', 'element_element') as $table) {
			$row = $this->one("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$this->db->escape($this->db->prefix().$table)."'");
			if (!$row || strtoupper($row->engine) !== 'INNODB') { throw new RuntimeException('MSMTransactionUnsupported'); }
		}
		$member = $this->one('SELECT fk_soc FROM '.$this->db->prefix().'adherent WHERE rowid = '.((int) $id).' FOR UPDATE');
		if (!$member) { throw new RuntimeException('NotEnoughPermissions'); }
		if ($member->fk_soc) { $this->one('SELECT rowid FROM '.$this->db->prefix().'societe WHERE rowid = '.((int) $member->fk_soc).' FOR UPDATE'); }
		$product = getDolGlobalInt('ADHERENT_PRODUCT_ID_FOR_SUBSCRIPTIONS');
		if ($product) { $this->one('SELECT rowid FROM '.$this->db->prefix().'product WHERE rowid = '.$product.' FOR UPDATE'); }
	}
	/** Check after every nested API call, before any further business operation. */
	protected function owned()
	{
		$active = $this->one('SELECT @@in_transaction AS active');
		if ((int) $this->db->transaction_opened !== 1 || !$active || (int) $active->active !== 1) { throw new RuntimeException('MSMTransactionUnknown'); }
	}
	public function create($member, $subscription, array $row, $actor, array &$diagnostic)
	{
		global $conf, $langs, $user;
		$plan = $row['invoice']; $diagnostic['stage'] = 'customer';
		$customerid = (int) $plan['customer_id'];
		if ($plan['create_customer']) {
			if (!empty($member->socid) || !$row['autocreate'] || !$actor->hasRight('societe', 'creer') || (int) $member->entity !== (int) $conf->entity) { throw new RuntimeException('MSMChangedConfirmation'); }
			$customer = $this->customer(); $fullname = $member->getFullName($langs);
			$name = $member->morphy === 'mor' ? $member->company : $fullname;
			$alias = $member->morphy === 'mor' ? $fullname : $member->company;
			$previous = $user;
			try { $user = $actor; $customerid = $customer->create_from_member($member, $name, $alias); }
			finally { $user = $previous; }
			$diagnostic['customer_id'] = max(0, (int) $customerid); $this->owned();
			if ($customerid <= 0) { throw new RuntimeException('MSMInvoiceFailed'); }
		}
		$customer = $this->customer();
		if ($customer->fetch($customerid) <= 0 || (int) $customer->entity !== $plan['entity']) { throw new RuntimeException('MSMConsistencyError'); }
		$link = $this->one('SELECT fk_soc FROM '.$this->db->prefix().'adherent WHERE rowid = '.((int) $member->id));
		if (!$link || (int) $link->fk_soc !== $customerid) { throw new RuntimeException('MSMConsistencyError'); }
		$member->socid = $member->fk_soc = $customerid; $member->thirdparty = $customer;
		$invoice = $this->invoice();
		$invoice->type = 0; $invoice->entity = $plan['entity']; $invoice->socid = $customerid;
		$invoice->date = msmReadDate($plan['date']); $invoice->cond_reglement_id = $plan['term'];
		$invoice->mode_reglement_id = $plan['payment_mode']; $invoice->fk_account = $plan['bank'];
		$invoice->multicurrency_code = $plan['currency']; $invoice->model_pdf = $plan['pdf_model'];
		$invoice->linked_objects = array('subscription' => (int) $subscription);
		$diagnostic['stage'] = 'invoice_create';
		$id = $invoice->create($actor); $diagnostic['invoice_id'] = max(0, (int) $id); $this->owned();
		if ($id <= 0) { throw new RuntimeException('MSMInvoiceFailed'); }
		$diagnostic['stage'] = 'invoice_line';
		$line = $invoice->addline($row['description'], 0, 1, $plan['vat'], 0, 0, $plan['product'], 0, $row['start'], '', 0, 0, 0, 'TTC', $row['amount'], 1);
		$this->owned(); if ($line <= 0) { throw new RuntimeException('MSMInvoiceFailed'); }
		$diagnostic['stage'] = 'invoice_validate';
		$validated = $invoice->validate($actor); $this->owned();
		if ($validated <= 0) { throw new RuntimeException('MSMInvoiceFailed'); }
		$diagnostic['stage'] = 'invoice_postconditions';
		$this->verify($id, $customerid, $subscription, $row);
		$stored = $this->invoice();
		if ($stored->fetch($id) <= 0) { throw new RuntimeException('MSMConsistencyError'); }
		return array('id' => (int) $id, 'ref' => (string) $stored->ref, 'entity' => $plan['entity'], 'customer_id' => $customerid,
			'customer_created' => $plan['create_customer'], 'subscription_id' => (int) $subscription, 'member_id' => (int) $member->id,
			'pdf_model' => $plan['pdf_model'], 'pdf' => 'pending');
	}
	protected function verify($id, $customer, $subscription, array $row)
	{
		$p = $row['invoice'];
		$invoice = $this->one('SELECT entity, fk_soc, type, fk_statut, paye, total_ttc, ref FROM '.$this->db->prefix().'facture WHERE rowid = '.((int) $id));
		$line = $this->one('SELECT COUNT(*) AS n, SUM(qty) AS qty, SUM(total_ttc) AS total FROM '.$this->db->prefix().'facturedet WHERE fk_facture = '.((int) $id));
		$link = $this->one("SELECT COUNT(*) AS n FROM ".$this->db->prefix()."element_element WHERE sourcetype = 'subscription' AND targettype = 'facture' AND fk_source = ".((int) $subscription).' AND fk_target = '.((int) $id));
		$party = $this->one('SELECT client FROM '.$this->db->prefix().'societe WHERE rowid = '.((int) $customer));
		if (!$invoice || (int) $invoice->entity !== $p['entity'] || (int) $invoice->fk_soc !== $customer || (int) $invoice->type !== 0
			|| (int) $invoice->fk_statut !== 1 || (int) $invoice->paye !== 0 || !$invoice->ref || strpos($invoice->ref, 'PROV') !== false
			|| abs((float) $invoice->total_ttc - $row['amount']) > 0.00000001
			|| !$line || (int) $line->n !== 1 || (float) $line->qty !== 1.0 || abs((float) $line->total - $row['amount']) > 0.00000001
			|| !$link || (int) $link->n !== 1 || !$party || !in_array((int) $party->client, array(1, 3), true)) { throw new RuntimeException('MSMConsistencyError'); }
	}
}
