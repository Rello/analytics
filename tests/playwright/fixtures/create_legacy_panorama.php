<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require '/var/www/html/lib/base.php';
$db = \OCP\Server::get(\OCP\IDBConnection::class);
$q = $db->getQueryBuilder();
$q->select('id')->from('analytics_report')->where($q->expr()->eq('user_id', $q->createNamedParameter('admin')))->andWhere($q->expr()->eq('type', $q->createNamedParameter(2)))->setMaxResults(4);
$ids = $q->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
if (!$ids) { throw new \RuntimeException('No internal test reports available'); }
$reports = [];
for ($i = 0; $i < 4; $i++) { $reports[] = ['type' => 0, 'value' => (int)$ids[$i % count($ids)], 'options' => ['legend' => false]]; }
$layout = '<div class="flex-container"><div class="panoramaSubHeaderRow"><div class="panoramaSubHeader editable"></div></div><div class="flex-row" style="height: 50%;"><div class="flex-item"></div><div class="flex-item"></div></div><div class="flex-row" style="height: 50%;"><div class="flex-item"></div><div class="flex-item"></div></div></div>';
$pages = [['page' => 0, 'name' => 'Overview', 'reports' => $reports, 'layout' => $layout]];
$q = $db->getQueryBuilder();
$q->insert('analytics_panorama')->values(['user_id' => $q->createNamedParameter('admin'), 'name' => $q->createNamedParameter('Legacy panorama compatibility test'), 'type' => $q->createNamedParameter(1), 'parent' => $q->createNamedParameter(0), 'pages' => $q->createNamedParameter(json_encode($pages))])->executeStatement();
echo json_encode(['id' => (int)$q->getLastInsertId(), 'reports' => $reports]);
