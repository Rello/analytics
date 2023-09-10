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

declare(strict_types=1);

namespace OCA\Analytics\Datasource;
/**
 * Interface IDatasource
 *
 * @since 3.1.0
 */
interface IDatasource
{

    /**
     * @return string Display Name of the datasource
     * @since 3.1.0
     */
    public function getName(): string;

    /**
     * @return int 2 digit unique datasource id
     * @since 3.1.0
     */
    public function getId(): int;

    /**
     * available options of the data source
     *
     * return needs to be an array and can consist of many fields.
     * every field needs to provide the following format
     *  id          *mandatory*     = name of the option for the readData
     *  name        *mandatory*     = displayed name of the input field in the UI
     *  type        *optional*      =   'tf' to create a dropdown. Values need to be provided in the placeholder separated with "/".
     *                                  'filePicker' will launch the file picker
     *                                  'columnPicker' will launch the file picker
     *                                  'number' for a number-only input field
     *                                  'section' will create a click-able section header and hide all options afterwards per default
     *                                  'longtext' will create a text area input
     *  placeholder *mandatory*     = help text for the input field in the UI
     *                                for type=tf:
     *                                  e.g. "true/false"
     *                                  if value/text pairs are required for the dropdown/option, the values need to be separated with "-" in addition.
     *                                  e.g. "eq-equal/gt-greater"
     *                                  to avoid translation of the technical strings, separate them
     *                                  'true-' - $this->l10n->t('Yes').'/false-'.$this->l10n->t('No')
     *
     *  example:
     *  {['id' => 'datatype', 'name' => 'Type of Data', 'type' => 'tf', 'placeholder' => 'adaptation/absolute']}
     *
     * @return array
     * @since 3.1.0
     */
    public function getTemplate(): array;

    /**
     * Read the Data
     *
     * return needs to be an array
     *  [
     *      'header' => $header,  //array('column header 1', 'column header 2','column header 3')
     *      'dimensions' => array_slice($header, 0, count($header) - 1),
     *      'data' => $data,
     *      'error' => 0,         // INT 0 = no error
     *  ]
     *
     * @param $option
     * @return array available options of the data source
     * @since 3.1.0
     */
    public function readData($option): array;
}