<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Activity;

interface IEvent {
	public function getApp();

	public function setApp($app);

	public function setType($type);

	public function getType();

	public function setAuthor($author);

	public function getAuthor();

	public function setObject($objectType, $objectId, $objectName = '');

	public function getObjectId();

	public function getObjectName();

	public function setSubject($subject, array $parameters = []);

	public function getSubject();

	public function getSubjectParameters();

	public function setTimestamp($timestamp);

	public function setAffectedUser($user);

	public function setRichSubject($subject, array $parameters = []);

	public function setIcon($icon);
}
