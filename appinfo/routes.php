<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // dataset
        ['name' => 'dataset#index', 'url' => '/dataset', 'verb' => 'GET'],
        ['name' => 'dataset#create', 'url' => '/dataset', 'verb' => 'POST'],
        ['name' => 'dataset#read', 'url' => '/dataset/{datasetId}', 'verb' => 'GET'],
        ['name' => 'dataset#update', 'url' => '/dataset/{datasetId}', 'verb' => 'PUT'],
        ['name' => 'dataset#delete', 'url' => '/dataset/{datasetId}', 'verb' => 'DELETE'],

        // data
        ['name' => 'data#index', 'url' => '/data', 'verb' => 'GET'],
        ['name' => 'data#create', 'url' => '/data', 'verb' => 'POST'],
        ['name' => 'data#read', 'url' => '/data/{datasetId}', 'verb' => 'GET'],
        ['name' => 'data#update', 'url' => '/data/{datasetId}', 'verb' => 'PUT'],
        ['name' => 'data#delete', 'url' => '/data/{datasetId}', 'verb' => 'DELETE'],

    ]
];