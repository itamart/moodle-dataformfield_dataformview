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
 * @copyright 2014 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class dataformfield_dataformview_dataformview extends \mod_dataform\pluginbase\dataformfield_nocontent {
    protected $_config;
    protected $_refdataform = null;
    protected $_refview = null;
    protected $_localview = null;

    /**
     * Overriding parent to return no sort/search options.
     *
     * @return array
     */
    public function get_sort_options_menu() {
        return array();
    }

    /**
     * Returns default data for the field config.
     *
     * @return array.
     */
    public function get_default_config() {
        $config = array(
            'refdataform' => 0,
            'refview' => 0,
            'reffilter' => 0,
            'filterby' => array(
                'userid' => 0,
                'groupid' => 0,
                'state' => 0,
            ),
            'customsort' => null,
            'customsearch' => null,
            'overrides' => array(
                'groupmode' => $this->df->groupmode,
                'currentgroup' => $this->df->currentgroup,
            ),
        );

        return $config;
    }

    /**
     * Returns the field config data that is stored in param1.
     *
     * @return array|null.
     */
    public function get_config() {
        if (!$this->_config) {
            $this->_config = $this->default_config;
            if ($this->param1) {
                $config = unserialize(base64_decode($this->param1));
                if (is_array($config)) {
                    // Merge to default config.
                    $this->_config = array_merge($this->_config, $config);
                }
            }
        }
        return $this->_config;
    }

    /**
     * Returns a list of dataform objects in which the current user has managetemplates
     * capability.
     *
     * @return array|bool Associative array dataformid => mod_dataform_dataform.
     */
    public function get_applicable_dataforms() {
        global $DB;

        if ($dataforms = $DB->get_records('dataform')) {
            foreach ($dataforms as $dfid => $dataform) {
                $df = \mod_dataform_dataform::instance($dfid);
                // Remove if user doesn't have managetemplates capability.
                if (!has_capability('mod/dataform:managetemplates', $df->context)) {
                    unset($dataforms[$dfid]);
                    continue;
                }
                $dataforms[$dfid] = $df;
            }
        } else {
            $dataforms = array();
        }

        return $dataforms;
    }

    /*
     * Returns the target dataform.
     *
     * @return \mod_dataform_dataform
     */
    public function get_ref_dataform() {
        if (!$this->_refdataform) {
            // Get the field config.
            $config = (object) $this->config;

            // Get the target dataform.
            if ($dataformid = $config->refdataform) {
                try {
                    $df = new \mod_dataform_dataform($dataformid, null, true);

                    $this->_refdataform = $df;
                } catch (Exception $e) {
                    $this->_refdataform = null;
                }
            }
        }

        return $this->_refdataform;
    }

    /*
     * Returns the target view.
     *
     * @return \dataformview
     */
    public function get_ref_view() {
        if (!$this->_refview) {
            // Get the field config.
            $config = (object) $this->config;

            // Get the dataform.
            if ($df = $this->ref_dataform) {
                // Get the target view.
                if ($viewid = $config->refview) {
                    if ($view = $df->view_manager->get_view_by_id($viewid)) {
                        $this->_refview = $view;
                    }
                }
            }
        }

        return $this->_refview;
    }

    /*
     *
     */
    public function get_local_view() {
        if (!$this->_localview) {
            $this->_localview = $this->df->currentview;
        }

        return $this->_localview;
    }

    /**
     *
     */
    public function get_view_display_content($entry, array $options = array()) {
        global $DB, $USER;

        // Must have remote dataform.
        if (!$refdataform = $this->ref_dataform) {
            return '';
        }

        // Must have remote view.
        if (!$refview = $this->ref_view) {
            return '';
        }

        $accessparams = array('dataformid' => $refdataform->id, 'viewid' => $refview->id);
        if (!\mod_dataform\access\view_access::validate($accessparams)) {
            return '';
        }

        $localview = $this->local_view;

        $config = (object) $this->config;

        // Complete missing properties of entry.
        $entry->userid = !isset($entry->userid) ? $USER->id : $entry->userid;
        $entry->groupid = !isset($entry->groupid) ? $this->df->currentgroup : $entry->groupid;
        $entry->state = !isset($entry->state) ? 0 : $entry->state;

        // Generate the filter.
        $fm = \mod_dataform_filter_manager::instance($refdataform->id);
        if (!empty($config->reffilter)) {
            $filter = $fm->get_filter_by_id($fid, array('view' => $refview));
        } else {
            $filter = $refview->filter;
        }
        // Filter by entry author.
        if (!empty($config->filterby['userid'])) {
            $filter->users = array($entry->userid);
        }
        // Filter by entry group.
        if (!empty($config->filterby['groupid'])) {
            $filter->groups = array($entry->groupid);
        }
        // Filter by entry state.
        if (!empty($config->filterby['state'])) {
            $filter->states = array($entry->state);
        }
        // Custom search.
        if ($customsearch = $this->prepare_custom_search($entry)) {
            $filter->customsearch = serialize($customsearch);
        }
        // Custom sort.
        if ($customsort = $config->customsort) {
            $filter->customsort = serialize($customsort);
        }
        // Apply overrides.
        $refdf = $this->ref_dataform;
        if ($refdf->groupmode != $config->overrides['groupmode']) {
            $filter->groupmode = $config->overrides['groupmode'];
            if (!$config->overrides['groupmode']) {
                $filter->currentgroup = 0;
            }
        }

        // Get the ref dataform page output.
        $params = array(
                'js' => true,
                'css' => true,
                'completion' => true,
                'comments' => true,
                'nologin' => true,
        );
        $refpagetype = !empty($options['pagetype']) ? $options['pagetype'] : 'external';
        $pageoutput = $refdataform->set_page($refpagetype, $params);

        // Get the ref view content.
        $viewcontent = $refview->display(array('filter' => $filter));
        return "$pageoutput\n$viewcontent";
    }

    /**
     *
     */
    public function get_view_url_params($entry, array $options = array()) {
        $config = (object) $this->config;

        if (!$config->refdataform or !$config->refview) {
            return array();
        }

        // Construct the src url.
        $params = array(
            'd' => $this->ref_dataform->id,
            'view' => $this->ref_view->id
        );
        if ($config->reffilter) {
            $params['filter'] = $config->reffilter;
        }
        // Search filter by entry author or group.
        $params = $field->get_filter_by_options($params, $entry, true);

        // Custom sort.
        if ($soptions = $field->get_filter_sort_options()) {
            $fm = mod_dataform_filter_manager::instance($df->id);
            $usort = $fm::get_sort_url_query($soptions);
            $params['usort'] = $usort;
        }
        // Custom search.
        if ($soptions = $field->get_filter_search_options($entry)) {
            $fm = mod_dataform_filter_manager::instance($df->id);
            $ucsearch = $fm::get_search_url_query($soptions);
            $params['ucsearch'] = $ucsearch;
        }

        return $params;
    }

    /**
     *
     */
    protected function prepare_custom_search($entry) {
        global $DB;

        $config = (object) $this->config;
        $refview = $this->ref_view;
        $localview = $this->local_view;

        // Custom search.
        if ($customsearch = $config->customsearch) {
            // Convert value patterns to actual values.
            foreach ($customsearch as $fieldid => $andors) {
                foreach ($andors as $andor => $criteria) {
                    foreach ($criteria as $key => $criterion) {
                        list($elem, $not, $op, $value) = $criterion;

                        // Is there a value to process?
                        if (!$value) {
                            continue;
                        }
                        // Is the value a field pattern? (Currently hard coded to field patterns).
                        if (strpos($value, '[[') === 0) {
                            $localpattern = $value;

                            // Get the field by name.
                            list($fieldname, ) = array_pad(explode(':', trim($localpattern, '[]'), 2), 2, null);
                            if (!$localfield = $this->df->field_manager->get_field_by_name($fieldname)) {
                                continue;
                            }

                            // Load field content in entry, if needed.
                            $entry = $localfield->load_entry_content($entry);
                            // Get the pattern value replacement.
                            if ($replacements = $localfield->renderer->get_replacements(array($localpattern), $entry)) {
                                // Take the first: should be value.
                                $value = reset($replacements);
                            }
                        }
                        $customsearch[$fieldid][$andor][$key] = array($elem, $not, $op, $value);
                    }
                }
            }

            return $customsearch;
        }

        return null;
    }

}
