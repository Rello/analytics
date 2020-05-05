<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\ILogger;

class RegexService
{
    private $logger;

    public function __construct(
        ILogger $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * Grab data from external html via regex
     *
     * @NoAdminRequired
     * @param array $option
     * @return array
     */
    public function read($option)
    {
        // http headers for requests
        $headers = array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'DNT: 1', // :)
        );

        $regex = $option['regex'];
        $url = $option['url'];

        $html = file_get_contents($url);
        preg_match_all($regex, $html, $matches);

        $data = array();
        $count = count($matches['dimension']);
        for ($i = 0; $i < $count; $i++) {
            if (isset($option['limit'])) {
                if ($i === (int)$option['limit'] AND (int)$option['limit'] !== 0) break;
            }
            array_push($data, ['', $matches['dimension'][$i], $matches['value'][$i]]);
        }

        $header = array();
        $header[0] = '';
        $header[1] = 'Dimension2';
        $header[2] = 'Count';

        return [
            'header' => $header,
            'data' => $data,
            'error' => 0,
        ];
    }

    /**
     * template for options & settings
     *
     * @NoAdminRequired
     * @return array
     */
    public function getTemplate()
    {
        $template = array();
        array_push($template, ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url']);
        array_push($template, ['id' => 'regex', 'name' => 'valid regex', 'placeholder' => '//']);
        array_push($template, ['id' => 'limit', 'name' => 'Limit', 'placeholder' => 'Number of records']);
        array_push($template, ['id' => 'timestamp', 'name' => 'Timestamp of dataload', 'placeholder' => 'true/false']);
        return $template;
    }

    private function backup()
    {
        /**
         * $ch = curl_init();
         * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         * curl_setopt($ch, CURLOPT_COOKIESESSION, true );
         * curl_setopt($ch, CURLOPT_COOKIEFILE, '');
         * curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
         * curl_setopt($ch, CURLOPT_HEADER, false);
         * curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
         * curl_setopt($ch, CURLOPT_URL, $url);
         * curl_setopt($ch, CURLOPT_REFERER, $url);
         * curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         * curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36');
         * //$html = curl_exec($ch);
         * curl_close($ch);
         *
         * //$this->logger->debug($result);
         * //$html = str_get_html($result, true, false);
         * //$this->logger->debug($html);
         * //$value = $html->find('a[href=/gp/bestsellers/books/405436/ref=pd_zg_hrsr_books]', 0)->parent->parent->first_child->innertext;
         * //ocument.querySelectorAll("a[href='/gp/bestsellers/books/405436/ref=pd_zg_hrsr_books']")[0].parentNode.parentNode.firstElementChild.innerText
         * //$string = 'http://www.amazon.de/dp/3964433578';
         * //$filter = '/(<span class=\"zg_hrsr_rank\">Nr. )(.*)(<\/span><span class="zg_hrsr_ladder">in&nbsp;<a href="\/gp\/bestsellers)(.*)(hrsr_books">)(.*)(<\/a><\/span>)/';
         * //$filter = '/(<span class="zg_hrsr_rank">Nr. )(.*)(<\/span><span class="zg_hrsr_ladder">in&nbsp;<a href="\/gp\/bestsellers\/books\/405436\/ref=pd_zg_hrsr_books">Vietnamesisch)/';
         * //$filter = '/(<span class="zg_hrsr_rank">Nr. )(.*)(<\/span>)(.*)(hrsr_books">)/';
         * //$filter = '/(<span class="zg_hrsr_rank">Nr. )(?<value>.*)(<\/span>\n    )(.*)(?<dimension>Vietnamesisch lernen)/';
         * //$this->logger->debug('values all: '. json_encode($values));
         * //$values = $values['value'];
         * //$this->logger->debug('values first array: '. json_encode($values));**/
    }
}