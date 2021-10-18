<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#advanced', 'url' => '/a/', 'verb' => 'GET'],
        ['name' => 'page#indexPublic', 'url' => '/p/{token}', 'verb' => 'GET'],
        ['name' => 'page#authenticatePassword', 'url' => '/p/{token}', 'verb' => 'POST'],

        // Report
        ['name' => 'report#index', 'url' => '/report', 'verb' => 'GET'],
        ['name' => 'report#create', 'url' => '/report', 'verb' => 'POST'],
        ['name' => 'report#read', 'url' => '/report/{reportId}', 'verb' => 'GET'],
        ['name' => 'report#delete', 'url' => '/report/{reportId}', 'verb' => 'DELETE'],
        ['name' => 'report#update', 'url' => '/report/{reportId}', 'verb' => 'PUT'],
        ['name' => 'report#createCopy', 'url' => '/report/copy', 'verb' => 'POST'],
        ['name' => 'report#updateOptions', 'url' => '/report/{reportId}/options', 'verb' => 'POST'],
        ['name' => 'report#updateRefresh', 'url' => '/report/{reportId}/refresh', 'verb' => 'POST'],
        ['name' => 'report#getOwnFavoriteReports', 'url' => '/favorites', 'verb' => 'GET'],
        ['name' => 'report#setFavorite', 'url' => '/favorite/{reportId}', 'verb' => 'POST'],
        ['name' => 'report#export', 'url' => '/report/export/{reportId}', 'verb' => 'GET'],
        ['name' => 'report#import', 'url' => '/report/import/', 'verb' => 'POST'],

        // Dataset
        ['name' => 'dataset#index', 'url' => '/dataset', 'verb' => 'GET'],
        ['name' => 'dataset#create', 'url' => '/dataset', 'verb' => 'POST'],
        ['name' => 'dataset#read', 'url' => '/dataset/{datasetId}', 'verb' => 'GET'],
        ['name' => 'dataset#delete', 'url' => '/dataset/{datasetId}', 'verb' => 'DELETE'],
        ['name' => 'dataset#update', 'url' => '/dataset/{datasetId}', 'verb' => 'PUT'],

        // Data Output
        ['name' => 'output#index', 'url' => '/data', 'verb' => 'GET'],
        ['name' => 'output#create', 'url' => '/data', 'verb' => 'POST'],
        ['name' => 'output#read', 'url' => '/data/{reportId}', 'verb' => 'GET'],
        ['name' => 'output#readPublic', 'url' => '/data/public/{token}', 'verb' => 'GET'],

        // Data Maintenance
        ['name' => 'dataload#updateData', 'url' => '/data/{reportId}', 'verb' => 'PUT'],
        ['name' => 'dataload#deleteData', 'url' => '/data/{reportId}', 'verb' => 'DELETE'],
        ['name' => 'dataload#deleteDataSimulate', 'url' => '/data/deleteDataSimulate', 'verb' => 'POST'],
        ['name' => 'dataload#importClipboard', 'url' => '/data/importCSV', 'verb' => 'POST'],
        ['name' => 'dataload#importFile', 'url' => '/data/importFile', 'verb' => 'POST'],

        // Dataloads
        ['name' => 'dataload#create', 'url' => '/dataload', 'verb' => 'POST'],
        ['name' => 'dataload#read', 'url' => '/dataload', 'verb' => 'GET'],
        ['name' => 'dataload#update', 'url' => '/dataload/{dataloadId}', 'verb' => 'PUT'],
        ['name' => 'dataload#delete', 'url' => '/dataload/{dataloadId}', 'verb' => 'DELETE'],
        ['name' => 'dataload#simulate', 'url' => '/dataload/simulate', 'verb' => 'POST'],
        ['name' => 'dataload#execute', 'url' => '/dataload/execute', 'verb' => 'POST'],

        // Datasource
        ['name' => 'datasource#index', 'url' => '/datasource', 'verb' => 'GET'],

        // Share
        ['name' => 'share#create', 'url' => '/share', 'verb' => 'POST'],
        ['name' => 'share#read', 'url' => '/share/{reportId}', 'verb' => 'GET'],
        ['name' => 'share#update', 'url' => '/share/{shareId}', 'verb' => 'PUT'],
        ['name' => 'share#delete', 'url' => '/share/{shareId}', 'verb' => 'DELETE'],

        // Threshold
        ['name' => 'threshold#create', 'url' => '/threshold', 'verb' => 'POST'],
        ['name' => 'threshold#read', 'url' => '/threshold/{reportId}', 'verb' => 'GET'],
        ['name' => 'threshold#delete', 'url' => '/threshold/{thresholdId}', 'verb' => 'DELETE'],

        // API
        // V1
        ['name' => 'ApiData#preflighted_cors', 'url' => '/api/1.0/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'ApiData#addData', 'url' => '/api/1.0/adddata/{datasetId}', 'verb' => 'POST'],

        // V2
        ['name' => 'ApiData#preflighted_cors', 'url' => '/api/2.0/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'ApiData#addDataV2', 'url' => '/api/2.0/adddata/{datasetId}', 'verb' => 'POST'],
        ['name' => 'ApiData#deleteDataV2', 'url' => '/api/2.0/deletedata/{datasetId}', 'verb' => 'POST'],

        // V3
        ['name' => 'ApiData#preflighted_cors', 'url' => '/api/3.0/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'ApiData#dataAddV3', 'url' => '/api/3.0/data/{datasetId}/add', 'verb' => 'POST'],
        ['name' => 'ApiData#dataDeleteV3', 'url' => '/api/3.0/data/{datasetId}/delete', 'verb' => 'POST'],
        ['name' => 'ApiData#dataGetV3', 'url' => '/api/3.0/data/{datasetId}', 'verb' => 'GET'],
        ['name' => 'ApiData#datasetsIndexV3', 'url' => '/api/3.0/datasets', 'verb' => 'GET'],
        ['name' => 'ApiData#datasetsDetailV3', 'url' => '/api/3.0/datasets/{datasetId}', 'verb' => 'GET'],

        // wizard
        ['name' => 'wizard#dismiss', 'url' => '/wizard', 'verb' => 'POST'],

        // whatsnew
        ['name' => 'whatsNew#get', 'url' => '/whatsnew', 'verb' => 'GET'],
        ['name' => 'whatsNew#dismiss', 'url' => '/whatsnew', 'verb' => 'POST'],
    ]
];
