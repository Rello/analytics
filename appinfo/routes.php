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

        // data
        ['name' => 'data#index', 'url' => '/data', 'verb' => 'GET'],
        ['name' => 'data#create', 'url' => '/data', 'verb' => 'POST'],
        ['name' => 'data#read', 'url' => '/data/{datasetId}', 'verb' => 'GET'],
        ['name' => 'data#update', 'url' => '/data/{datasetId}', 'verb' => 'PUT'],
        ['name' => 'data#delete', 'url' => '/data/{datasetId}', 'verb' => 'DELETE'],
        ['name' => 'data#importCSV', 'url' => '/data/importCSV', 'verb' => 'POST'],
        ['name' => 'data#importFile', 'url' => '/data/importFile', 'verb' => 'POST'],
        ['name' => 'data#readPublic', 'url' => '/data/public/{token}', 'verb' => 'GET'],

        // share
        ['name' => 'share#create', 'url' => '/share', 'verb' => 'POST'],
        ['name' => 'share#read', 'url' => '/share/{datasetId}', 'verb' => 'GET'],
        ['name' => 'share#update', 'url' => '/share/{shareId}', 'verb' => 'PUT'],
        ['name' => 'share#delete', 'url' => '/share/{shareId}', 'verb' => 'DELETE'],

        ['name' => 'ApiData#preflighted_cors', 'url' => '/api/1.0/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'ApiData#addData', 'url' => '/api/1.0/adddata/{datasetId}', 'verb' => 'POST'],
    ]
];