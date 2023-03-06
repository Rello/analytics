<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

class Regex implements IDatasource
{
    private LoggerInterface $logger;
    private IL10N $l10n;

    public function __construct(
        IL10N           $l10n,
        LoggerInterface $logger
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('HTML grabber');
    }

    /**
     * @return int digit unique datasource id
     */
    public function getId(): int
    {
        return 5;
    }

    /**
     * @return array available options of the datasoure
     */
    public function getTemplate(): array
    {
        $template = array();
        $template[] = ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url'];
        $template[] = ['id' => 'name', 'name' => 'Data series description', 'placeholder' => 'optional'];
        $template[] = ['id' => 'regex', 'name' => $this->l10n->t('valid regex'), 'placeholder' => '//'];
        $template[] = ['id' => 'limit', 'name' => $this->l10n->t('Limit'), 'placeholder' => $this->l10n->t('Number of rows'), 'type' => 'number'];
        $template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'true-' . $this->l10n->t('Yes') . '/false-' . $this->l10n->t('No'), 'type' => 'tf'];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the data soure
     */
    public function readData($option): array
    {
        $regex = htmlspecialchars_decode($option['regex'], ENT_NOQUOTES);
        $url = htmlspecialchars_decode($option['url'], ENT_NOQUOTES);

        $context = stream_context_create(
            array(
                "http" => array(
                    "header" => "User-Agent: NextCloud Analytics APP"
                )
            )
        );

        $html = file_get_contents($url, false, $context);
        preg_match_all($regex, $html, $matches);

        $data = array();
        $count = count($matches['dimension']);
        for ($i = 0; $i < $count; $i++) {
            if (isset($option['limit'])) {
                if ($i === (int)$option['limit'] and (int)$option['limit'] !== 0) break;
            }
            $data[] = [$option['name'], $matches['dimension'][$i], $matches['value'][$i]];
        }

        $header = array();
        $header[0] = '';
        $header[1] = 'Dimension2';
        $header[2] = 'Count';

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'error' => 0,
            'rawData' => $html,
            'URL' => $url,
        ];
    }
}
