<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
/** Observe R16 without changing the core API or bypassing its triggers. */
class MsmBookingMember extends Adherent
{
	public $msmEndUpdate = null;
	public function update_end_date($user)
	{
		$this->msmEndUpdate = parent::update_end_date($user);
		return $this->msmEndUpdate;
	}
}
