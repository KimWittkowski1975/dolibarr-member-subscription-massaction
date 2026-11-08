<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Escaped module translations, including interpolation. */
function msmText($key, ...$values)
{
	global $langs;
	return dol_escape_htmltag($langs->transnoentities($key, ...$values));
}

/** ISO native date control, usable without JavaScript; no Today link. */
function msmDateField($name, $value, $label)
{
	return '<input type="date" min="1970-01-01" max="9998-12-31" style="min-width:10rem" name="'.dol_escape_htmltag($name).'" aria-label="'.dol_escape_htmltag($label).'" value="'.dol_escape_htmltag($value).'">';
}

/** Pure rendering; dates/amounts/statuses come from the server-side services. */
function msmRenderPreview(array $state, $key)
{
	global $conf, $langs, $user;
	$html = '<div class="info" role="status">'.msmText('MSMPreviewNotice').'</div>';
	foreach (msmPreviewWarnings($state['rows']) as $warning) { $html .= '<div class="warning">'.msmText($warning).'</div>'; }
	$html .= '<form method="post"><input type="hidden" name="token" value="'.dol_escape_htmltag(newToken()).'">';
	$html .= '<input type="hidden" name="preview" value="'.dol_escape_htmltag($key).'"><input type="hidden" name="revision" value="'.$state['revision'].'">';
	$html .= '<fieldset><legend>'.msmText('MSMOptions').'</legend>';
	$html .= msmDateField('common', $state['common'], $langs->transnoentities('SubscriptionBulkCommonStart'));
	$html .= '<button class="button" name="action" value="common">'.msmText('SubscriptionBulkApplyCommon').'</button><br>';
	foreach (array('invoice' => 'MSMPlanInvoice', 'sendmail' => 'MSMPlanMail', 'autocreate' => 'MSMPlanThirdParty') as $option => $label) {
		$disabled = $option === 'autocreate' && (!isModEnabled('societe') || !$user->hasRight('societe', 'creer'));
		$html .= '<label><input type="checkbox" name="'.$option.'" value="1"'.($state['options'][$option] ? ' checked' : '').($disabled ? ' disabled' : '').'> '.msmText($label).'</label><br>';
	}
	$html .= '<label>'.msmText('SubscriptionBulkDescriptionLabel').' <input class="minwidth300" type="text" name="description" maxlength="1024" value="'.dol_escape_htmltag($state['options']['description']).'"></label>';
	$html .= '<p>'.msmText('SubscriptionBulkDescriptionHelp').'</p></fieldset>';
	$month = getDolGlobalInt('MEMBER_SUBSCRIPTION_SUGGEST_END_OF_MONTH');
	$year = getDolGlobalInt('MEMBER_SUBSCRIPTION_SUGGEST_END_OF_YEAR');
	if ($month || $year) {
		$html .= '<div class="info">'.msmText($month ? ($year ? 'SubscriptionBulkCalendarEndBothInfo' : 'SubscriptionBulkCalendarEndMonthInfo') : 'SubscriptionBulkCalendarEndYearInfo');
		$html .= '<br><button class="button" name="action" value="calendar">'.msmText('SubscriptionBulkApplyCalendar').'</button></div>';
	}
	$html .= '<p>'.msmText('SubscriptionBulkDateHelp').'</p><button class="button" name="action" value="refresh">'.msmText('SubscriptionBulkRefresh').'</button>';
	$html .= '<div class="div-table-responsive"><table class="noborder centpercent"><thead><tr class="liste_titre">';
	foreach (array('Member', 'Type', 'Amount', 'DateSubscription', 'DateEndSubscription', 'ThirdParty', 'Status', 'MSMDetails') as $label) { $html .= '<th>'.msmText($label).'</th>'; }
	$html .= '</tr></thead><tbody>';
	$warnings = 0; $sum = 0.0;
	foreach ($state['rows'] as $id => $row) {
		$units = $row['quantity'] == 1 ? array('d' => 'Day', 'w' => 'Week', 'm' => 'Month', 'y' => 'Year') : array('d' => 'Days', 'w' => 'Weeks', 'm' => 'Months', 'y' => 'Years');
		$name = ($row['member'] ?: $langs->transnoentities('Member')).' #'.$id;
		$html .= '<tr class="oddeven" data-member="'.$id.'"><td>'.dol_escape_htmltag($name).'</td><td>'.dol_escape_htmltag($row['type']);
		if ($row['quantity']) { $html .= ' ('.dol_escape_htmltag($row['quantity']).' '.msmText($units[$row['unit']] ?? $row['unit']).')'; }
		$html .= '</td><td class="nowrap">'.($row['amount'] === null ? '' : dol_escape_htmltag(price($row['amount']).' '.$conf->currency)).'</td>';
		foreach (array('start', 'end') as $field) {
			$html .= '<td class="nowrap">'.msmDateField($field.'['.$id.']', $row[$field] > 0 ? dol_print_date($row[$field], '%Y-%m-%d') : '', $name.' '.$langs->transnoentities($field === 'start' ? 'DateSubscription' : 'DateEndSubscription'));
			if ($field === 'end') { $html .= ' <button class="button small" style="min-width:0;width:1.8rem;padding:.2rem;margin:0" name="action" value="reset:'.$id.'" aria-label="'.dol_escape_htmltag($langs->transnoentities('SubscriptionBulkRecalculateEnd').' '.$name).'">&#8635;</button>'; }
			$html .= '</td>';
		}
		$html .= '<td>'.dol_escape_htmltag($row['thirdparty']).'</td><td>'.msmText($row['status'] === 'error' ? 'SubscriptionBulkError' : ($row['status'] === 'warning' ? 'SubscriptionBulkWarning' : 'SubscriptionBulkReady')).'</td><td>';
		foreach ($row['notices'] as $notice) { $html .= '<div>'.msmText($notice).'</div>'; }
		$html .= '<div>'.dol_escape_htmltag($row['description']).'</div></td></tr>';
		if ($row['warnings']) { $warnings++; }
		if (!$row['errors'] && $row['amount'] !== null) { $sum += $row['amount']; }
	}
	$counts = msmPreviewCounts($state['rows']);
	$html .= '</tbody></table></div><p role="status">'.msmText('MSMCounts', (string) $counts['selected'], (string) $counts['ready'], (string) $counts['skipped'], (string) $warnings, price($sum).' '.$conf->currency).'</p>';
	$html .= '<button class="button" name="action" value="refresh">'.msmText('SubscriptionBulkRefresh').'</button> ';
	$html .= '<button class="button" name="action" value="cancel" formnovalidate>'.msmText('MSMCancel').'</button></form>';
	return $html.'<p><a href="'.dol_escape_htmltag(msmListUrl()).'">'.msmText('MSMBack').'</a></p>';
}
