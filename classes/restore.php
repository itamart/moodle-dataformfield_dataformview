<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dataformfield_dataformview
 * @copyright 2015 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use restore_dataform_activity_structure_step as restore;

class dataformfield_dataformview_restore {

    /**
     * Overriding parent to return no sort/search options.
     *
     * @return array
     */
    public static function after_execute(restore $step) {
        global $DB;

        $dataformid = $step->get_new_parentid('dataform');

        $params = array(
            'dataid' => $dataformid,
            'type' => 'dataformview',
        );
        if (!$instances = $DB->get_records('dataform_fields', $params)) {
            return;
        }

        foreach ($instances as $data) {

            // Adjusting only param1.
            if (!$data->param1) {
                continue;
            }

            $config = unserialize(base64_decode($data->param1));

            // Ref dataform.
            $config['refdataform'] = $step->get_mappingid('dataform', $config['refdataform']);
            // Ref view.
            $config['refview'] = $step->get_mappingid('dataform_view', $config['refview']);
            // Ref filter.
            if (!empty($config['reffilter'])) {
                $config['reffilter'] = $step->get_mappingid('dataform_filter', $config['reffilter']);
            }
            // Custom sort.
            if (!empty($config['customsort'])) {
                $config['customsort'] = self::adjust_custom_sort($step, $config['customsort']);
            }
            // Custom search.
            if (!empty($config['customsearch'])) {
                $config['customsearch'] = self::adjust_custom_search($step, $config['customsearch']);
            }

            $data->param1 = base64_encode(serialize($config));
            $DB->set_field('dataform_fields', 'param1', $data->param1, array('id' => $data->id));
        }
    }

    /*
     *
     */
    protected static function adjust_custom_sort(restore $step, $customsort) {
        if (!$customsort) {
            return null;
        }

        $sortfields = array();
        foreach ($customsort as $fieldid => $sortoption) {
            if ($fieldid > 0) {
                $newfieldid = $step->get_mappingid('dataform_field', $fieldid);
            } else {
                $newfieldid = $fieldid;
            }
            $sortfields[$newfieldid] = $sortoption;
        }

        return $sortfields;
    }

    /*
     *
     */
    protected static function adjust_custom_search(restore $step, $customsearch) {
        if (!$customsearch) {
            return null;
        }

        $searchfields = array();
        foreach ($customsearch as $fieldid => $searchoption) {
            if ($fieldid > 0) {
                $newfieldid = $step->get_mappingid('dataform_field', $fieldid);
            } else {
                $newfieldid = $fieldid;
            }
            $searchfields[$newfieldid] = $searchoption;
        }

        return $searchfields;
    }

}
