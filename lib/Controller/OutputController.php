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

namespace OCA\Analytics\Controller;

use OCA\Analytics\DataSession;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class OutputController extends Controller
{
    private $logger;
    private $ReportService;
    private $DataSession;
    private $ShareService;
    private $DatasourceController;
    private $StorageService;
    private $ThresholdService;

    public function __construct(
        string $AppName,
        IRequest $request,
        LoggerInterface $logger,
        ReportService $ReportService,
        ShareService $ShareService,
        DataSession $DataSession,
        DatasourceController $DatasourceController,
        StorageService $StorageService,
        ThresholdService $ThresholdService
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->ReportService = $ReportService;
        $this->DataSession = $DataSession;
        $this->ShareService = $ShareService;
        $this->DatasourceController = $DatasourceController;
        $this->StorageService = $StorageService;
        $this->ThresholdService = $ThresholdService;
    }

    /**
     * get the data when requested from internal page
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $filteroptions
     * @param $dataoptions
     * @param $chartoptions
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function read(int $reportId, $filteroptions, $dataoptions, $chartoptions)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (empty($reportMetadata)) $reportMetadata = $this->ShareService->getSharedReport($reportId);

        if (!empty($reportMetadata)) {
            $reportMetadata = $this->evaluateCanFilter($reportMetadata, $filteroptions, $dataoptions, $chartoptions);
            $result = $this->getData($reportMetadata);
            return new DataResponse($result, HTTP::STATUS_OK);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Get the data from backend;
     * pre-evaluation of valid datasetId within read & readPublic is trusted here
     *
     * @NoAdminRequired
     * @param $reportMetadata
     * @return array|NotFoundException
     * @throws Exception
     */
    private function getData($reportMetadata)
    {
        $datasource = (int)$reportMetadata['type'];
        if ($datasource === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            // Internal data
            $result = $this->StorageService->read((int)$reportMetadata['dataset'], $reportMetadata['filteroptions']);
        } else {
            // Realtime data
            $result = $this->DatasourceController->read($datasource, $reportMetadata);
        }
        unset($reportMetadata['parent']
            , $reportMetadata['user_id']
            , $reportMetadata['link']
            , $reportMetadata['dimension1']
            , $reportMetadata['dimension2']
            , $reportMetadata['dimension3']
            , $reportMetadata['value']
            , $reportMetadata['password']
            , $reportMetadata['dataset']
        );
        $result['options'] = $reportMetadata;
        $result['thresholds'] = $this->ThresholdService->read($reportMetadata['id']);
        return $result;
    }

    /**
     * get the data when requested from public page
     *
     * @NoAdminRequired
     * @PublicPage
     * @UseSession
     * @param $token
     * @param $filteroptions
     * @param $dataoptions
     * @param $chartoptions
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function readPublic($token, $filteroptions, $dataoptions, $chartoptions)
    {
        $share = $this->ShareService->getReportByToken($token);
        if (empty($share)) {
            // Dataset not shared or wrong token
            return new NotFoundResponse();
        } else {
            if ($share['password'] !== null) {
                $password = $this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareService->verifyPassword($password, $share['password']);
                if ($passwordVerification === false) {
                    return new NotFoundResponse();
                }
            }
            $share = $this->evaluateCanFilter($share, $filteroptions, $dataoptions, $chartoptions);
            $result = $this->getData($share);
            return new DataResponse($result, HTTP::STATUS_OK);
        }
    }

    /**
     * evaluate if the user did filter in the report and if he is allowed to filter (shared reports)
     *
     * @param $metadata
     * @param $filteroptions
     * @param $dataoptions
     * @param $chartoptions
     * @return mixed
     */
    private function evaluateCanFilter($metadata, $filteroptions, $dataoptions, $chartoptions)
    {
        // send current user filter options to the data request
        // only if the report has update-permissions
        // if nothing is changed by the user, the filter which is stored for the report, will be used
        if ($filteroptions and $filteroptions !== '' and $metadata['permissions'] === Constants::PERMISSION_UPDATE) {
            $metadata['filteroptions'] = $filteroptions;
            $metadata['dataoptions'] = $dataoptions;
            $metadata['chartoptions'] = $chartoptions;
        }
        return $metadata;
    }
}