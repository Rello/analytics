<?php
/**
 * Data Analytics
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
        ['name' => 'page#config', 'url' => '/c/', 'verb' => 'GET'],
        ['name' => 'page#indexPublic', 'url' => '/p/{token}', 'verb' => 'GET'],
        ['name' => 'page#authenticatePassword', 'url' => '/p/{token}', 'verb' => 'POST'],
        ['name' => 'PublicDisplay#showShare', 'url' => '/pp/{token}', 'verb' => 'GET'],
        ['name' => 'PublicDisplay#showShare', 'url' => '/pp/{token}', 'verb' => 'GET'],
        ['name' => 'PublicDisplay#showAuthenticate', 'url' => '/pp/{token}/authenticate/{redirect}', 'verb' => 'GET'],
        ['name' => 'publicdisplaycontroller#showAuthenticate', 'url' => '/pp/{token}/authenticate/{redirect}', 'verb' => 'GET'],

        // dataset
        ['name' => 'dataset#index', 'url' => '/dataset', 'verb' => 'GET'],
        ['name' => 'dataset#create', 'url' => '/dataset', 'verb' => 'POST'],
        ['name' => 'dataset#read', 'url' => '/dataset/{datasetId}', 'verb' => 'GET'],
        ['name' => 'dataset#update', 'url' => '/dataset/{datasetId}', 'verb' => 'PUT'],
        ['name' => 'dataset#delete', 'url' => '/dataset/{datasetId}', 'verb' => 'DELETE'],

        // Data Output
        ['name' => 'output#index', 'url' => '/data', 'verb' => 'GET'],
        ['name' => 'output#create', 'url' => '/data', 'verb' => 'POST'],
        ['name' => 'output#read', 'url' => '/data/{datasetId}', 'verb' => 'GET'],
        ['name' => 'output#readPublic', 'url' => '/data/public/{token}', 'verb' => 'GET'],

        // Data Maintenance
        ['name' => 'dataload#updateData', 'url' => '/data/{datasetId}', 'verb' => 'PUT'],
        ['name' => 'dataload#deleteData', 'url' => '/data/{datasetId}', 'verb' => 'DELETE'],
        ['name' => 'dataload#importClipboard', 'url' => '/data/importCSV', 'verb' => 'POST'],
        ['name' => 'dataload#importFile', 'url' => '/data/importFile', 'verb' => 'POST'],
        // Dataloads
        ['name' => 'dataload#create', 'url' => '/dataload', 'verb' => 'POST'],
        ['name' => 'dataload#read', 'url' => '/dataload/{datasetId}', 'verb' => 'GET'],
        ['name' => 'dataload#update', 'url' => '/dataload/{dataloadId}', 'verb' => 'PUT'],
        ['name' => 'dataload#delete', 'url' => '/dataload/{dataloadId}', 'verb' => 'DELETE'],
        ['name' => 'dataload#simulate', 'url' => '/dataload/simulate', 'verb' => 'POST'],
        ['name' => 'dataload#execute', 'url' => '/dataload/execute', 'verb' => 'POST'],

        // share
        ['name' => 'share#create', 'url' => '/share', 'verb' => 'POST'],
        ['name' => 'share#read', 'url' => '/share/{datasetId}', 'verb' => 'GET'],
        ['name' => 'share#update', 'url' => '/share/{shareId}', 'verb' => 'PUT'],
        ['name' => 'share#delete', 'url' => '/share/{shareId}', 'verb' => 'DELETE'],

        // threashold
        ['name' => 'threshold#create', 'url' => '/threshold', 'verb' => 'POST'],
        ['name' => 'threshold#read', 'url' => '/threshold/{datasetId}', 'verb' => 'GET'],
        ['name' => 'threshold#delete', 'url' => '/threshold/{thresholdId}', 'verb' => 'DELETE'],

        ['name' => 'ApiData#preflighted_cors', 'url' => '/api/1.0/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'ApiData#addData', 'url' => '/api/1.0/adddata/{datasetId}', 'verb' => 'POST'],
    ]
];