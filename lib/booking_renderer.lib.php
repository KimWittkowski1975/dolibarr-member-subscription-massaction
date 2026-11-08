<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
function msmRenderBooking(array $state, $key)
{
	global $conf;
	$confirm = $state['phase'] === 'confirm';
	$html = '<div class="info">'.msmText($confirm ? 'MSMConfirmNotice' : 'MSMResultNotice').'</div>';
	if (!$confirm && $state['phase'] !== 'done') { $html .= '<div class="warning">'.msmText('MSMInterrupted').'</div>'; }
	if ($confirm) {
		$html .= '<form method="post"><input type="hidden" name="token" value="'.dol_escape_htmltag(newToken()).'"><input type="hidden" name="preview" value="'.dol_escape_htmltag($key).'"><input type="hidden" name="revision" value="'.$state['revision'].'">';
		$html .= '<p>'.msmText('MSMPlanMail').': '.msmText($state['options']['sendmail'] ? 'Yes' : 'No').'</p>';
	}
	$html .= '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre">';
	foreach (array('Member', 'Type', 'Amount', 'DateSubscription', 'DateEndSubscription', 'SubscriptionBulkDescriptionLabel', 'Status') as $label) { $html .= '<th>'.msmText($label).'</th>'; }
	$html .= '</tr>';
	$counts = array('success' => 0, 'skipped' => 0, 'failed' => 0, 'unknown' => 0, 'transport' => 0, 'hook' => 0, 'mailwarning' => 0); $sum = 0;
	foreach ($state['rows'] as $id => $row) {
		$html .= '<tr><td>'.dol_escape_htmltag($row['member']).'</td><td>'.dol_escape_htmltag($row['type']).'</td><td>'.dol_escape_htmltag(price($row['amount']).' '.$conf->currency).'</td>';
		foreach (array('start', 'end') as $field) { $html .= '<td>'.dol_escape_htmltag($row[$field] > 0 ? dol_print_date($row[$field], 'day') : '').'</td>'; }
		$html .= '<td>'.dol_escape_htmltag($row['description']).'</td><td>';
		if ($confirm) {
			foreach ($row['notices'] as $notice) { $html .= '<div>'.msmText($notice).'</div>'; }
			if ($row['quantity'] == 1 && $row['unit'] === 'd') { $html .= '<div>'.msmText('MSMOneDay', dol_print_date($row['end'], 'day')).'</div>'; }
			foreach ($row['overlaps'] as $overlap) { $html .= '<div>#'.((int) $overlap['id']).' '.dol_escape_htmltag($overlap['start'].' – '.$overlap['end']).'</div>'; }
			if (!$row['errors']) { $counts['success']++; $sum += $row['amount']; }
			else { $counts['skipped']++; }
			if ($row['overlaps'] && !$row['errors']) { $html .= '<label><input type="checkbox" name="overlap['.$id.']" value="1"> '.msmText('MSMConfirmOverlap').'</label>'; }
		} else {
			$result = $state['results'][$id]; $counts[$result['status']]++;
			$html .= msmText('MSMStatus_'.$result['status']).': '.msmText($result['message']);
			if ($result['status'] === 'success') {
				$sum += $result['amount'];
				$html .= '<div><a href="'.dol_escape_htmltag(dol_buildpath('/adherents/subscription.php', 1).'?rowid='.$id).'">'.msmText('Subscription').' #'.((int) $result['id']).'</a></div>';
				$html .= '<div>'.msmText('MSMMail_'.$result['mail']).'</div>';
				if (in_array($result['mail'], array('transport', 'hook'), true)) { $counts[$result['mail']]++; }
				elseif ($result['mail'] !== 'not_selected') { $counts['mailwarning']++; }
			}
		}
		$html .= '</td></tr>';
	}
	$html .= '</table></div><p>'.msmText($confirm ? 'MSMConfirmTotals' : 'MSMResultTotals', $counts['success'], $counts['skipped'], $counts['failed'], $counts['unknown'], price($sum).' '.$conf->currency).'</p>';
	if (!$confirm) { $html .= '<p>'.msmText('MSMMailTotals', $counts['transport'], $counts['hook'], $counts['mailwarning']).'</p>'; }
	if ($confirm) { $html .= '<button class="button" name="action" value="book">'.msmText('MSMBook').'</button> <button class="button" name="action" value="edit">'.msmText('MSMEdit').'</button></form>'; }
	return $html.'<p><a href="'.dol_escape_htmltag(msmListUrl()).'">'.msmText('MSMBack').'</a></p>';
}
