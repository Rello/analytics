<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\Attribute\ApiRoute;
use OCP\IRequest;

class ApiController extends OCSController {
    private ReportService $reportService;

    public function __construct(string $appName, IRequest $request, ReportService $reportService) {
        parent::__construct($appName, $request, 'POST');
        $this->reportService = $reportService;
    }

    #[ApiRoute(verb: 'POST', url: '/createFromDataFile')]
    /**
     * Create an analytics report from an existing data file.
     *
     * @param int $fileId ID of the file to import
     *
     * @return JSONResponse HTTP 200 with a link to the created report
     *
     * @OA\Post(
     *     path="/createFromDataFile",
     *     summary="Create report from data file",
     *     requestBody=@OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fileId"},
     *             @OA\Property(property="fileId", type="integer", description="File identifier")
     *         )
     *     ),
     *     @OA\Response(response="200", description="Report created"),
     *     @OA\Response(response="404", description="File not found")
     * )
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function createFromDataFile(int $fileId): JSONResponse {
        $reportId = $this->reportService->createFromDataFile($fileId);
        $url = '/apps/analytics/r/' . $reportId;
        return new JSONResponse([
            'version' => 0.1,
            'root' => [
                'orientation' => 'vertical',
                'rows' => [
                    [
                        'children' => [
                            [
                                'element' => 'Analytics report created',
                                'text' => $url,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
