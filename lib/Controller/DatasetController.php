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

use OCA\Analytics\Service\DatasetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DatasetController extends Controller
{
    private $logger;
    private $DatasetService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        DatasetService $DatasetService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
    }

    /**
     * get all datasets
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function index()
    {
        return $this->DatasetService->index();
    }

    /**
     * create new dataset
     *
     * @NoAdminRequired
     * @param string $file
     * @return int
     */
    public function create($file = '')
    {
        return $this->DatasetService->create($file);
    }

    /**
     * get own dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        return $this->DatasetService->getOwnDataset($datasetId);
    }

    /**
     * Delete Dataset and all depending objects
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return bool
     */
    public function delete(int $datasetId)
    {
        return $this->DatasetService->delete($datasetId);
    }

    /**
     * get dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param int $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return bool
     */
    public function update(int $datasetId, $name, $subheader, int $parent, int $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->DatasetService->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value);
    }

    /**
     * get dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateOptions(int $datasetId, $chartoptions, $dataoptions, $filteroptions)
    {
        return $this->DatasetService->updateOptions($datasetId, $chartoptions, $dataoptions, $filteroptions);
    }

    /**
     * get own datasets which are marked as favorites
     *
     * @NoAdminRequired
     * @return array|bool
     */
    public function getOwnFavoriteDatasets()
    {
        return $this->DatasetService->getOwnFavoriteDatasets();
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param string $favorite
     * @return bool
     */
    public function setFavorite(int $datasetId, string $favorite)
    {
        return $this->DatasetService->setFavorite($datasetId, $favorite);
    }

    /**
     * Export Dataset
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $datasetId
     * @return \OCP\AppFramework\Http\DataDownloadResponse
     */
    public function export(int $datasetId)
    {
        return $this->DatasetService->export($datasetId);
    }

    /**
     * Import Dataset
     *
     * @NoAdminRequired
     * @param string|null $path
     * @param string|null $raw
     * @return DataResponse
     * @throws \OCP\Files\NotFoundException
     * @throws \OCP\Files\NotPermittedException
     */
    public function import(string $path = null, string $raw = null)
    {
        return new DataResponse($this->DatasetService->import($path, $raw));
    }

}