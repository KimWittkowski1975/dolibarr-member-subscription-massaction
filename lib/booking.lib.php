<?php
/* Copyright (C) 2026 iLLixM <iLLixM@proton.me>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
/** Strict confirmation request: all business values remain on the server. */
function msmBookingPost(array $state, array $post, $action)
{
	if (array_diff(array_keys($post), array('token', 'preview', 'revision', 'action', 'overlap'))
		|| ($post['action'] ?? null) !== $action || ($post['revision'] ?? null) !== (string) $state['revision']
		|| !empty($state['options']['invoice']) || !empty($state['options']['autocreate'])) { throw new InvalidArgumentException('MSMInvalidForm'); }
	$overlap = $post['overlap'] ?? array();
	if (!is_array($overlap) || array_diff(array_keys($overlap), $state['ids'])) { throw new InvalidArgumentException('MSMInvalidForm'); }
	foreach ($overlap as $value) { if ($value !== '1') { throw new InvalidArgumentException('MSMInvalidForm'); } }
	return $overlap;
}
/** Consume before any business write. The controller must durably save the session first. */
function msmConsumeConfirmation(array $state, array $post)
{
	$overlap = msmBookingPost($state, $post, 'book');
	if (($state['phase'] ?? '') !== 'confirm' || count($state['ids']) > 50) { throw new InvalidArgumentException('MSMInvalidForm'); }
	$state['phase'] = 'processing'; $state['revision']++; $state['overlap'] = $overlap;
	$state['results'] = array();
	foreach ($state['ids'] as $id) { $state['results'][$id] = array('status' => 'unknown', 'message' => 'MSMInterrupted', 'id' => 0, 'amount' => 0, 'mail' => 'unknown', 'stop' => false); }
	return $state;
}
/** Pure batch orchestration with a persistence checkpoint before/after each side effect. */
function msmProcessBooking(array $state, $service, $mailer, $user, callable $save)
{
	$stop = false;
	foreach ($state['ids'] as $id) {
		$row = $state['rows'][$id];
		if ($stop || $row['errors'] || ($row['overlaps'] && empty($state['overlap'][$id]))) {
			$state['results'][$id] = array('status' => 'skipped', 'message' => $stop ? 'MSMBatchStopped' : 'MSMNotConfirmed', 'id' => 0, 'amount' => 0, 'mail' => 'not_selected', 'stop' => false);
			$save($state); continue;
		}
		$result = $service->book($id, $row, $state['options'], $user, !empty($state['overlap'][$id]));
		$member = $result['_member'] ?? null; unset($result['_member']);
		if ($result['status'] === 'success' && $state['options']['sendmail']) { $result['mail'] = 'unknown'; }
		$state['results'][$id] = $result;
		$save($state); // A crash from here must never rebook or resend automatically.
		if ($result['status'] === 'success') {
			$state['results'][$id]['mail'] = $mailer->send($member, $row, $user, $state['options']['sendmail']);
			$save($state);
		}
		$stop = $result['stop'];
	}
	$state['phase'] = 'done'; $save($state);
	return $state;
}
