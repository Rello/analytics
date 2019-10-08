<?php
/**
 * Audio Player Editor
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IDbConnection;


class DataController extends Controller
{

    private $userId;
    private $l10n;
    private $logger;
    private $DBController;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        IL10N $l10n,
        ILogger $logger,
        DbController $DBController
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->DBController = $DBController;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $id
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return JSONResponse
     */
    public function getData(int $id, $objectDrilldown, $dateDrilldown)
    {
        $header = array();
        if ($objectDrilldown === 'true') array_push($header,'Objekt');
        if ($dateDrilldown === 'true') array_push($header,'Date');
        array_push($header,'Value');

        $dataset = $this->DBController->getDataset($id,$objectDrilldown,$dateDrilldown);

        $result = empty($dataset) ? [
            'status' => 'nodata'
        ] : [
            'header' => $header,
            'data' => $dataset
        ];
        return new JSONResponse($result);
    }
    }