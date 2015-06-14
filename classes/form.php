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


class dataformfield_dataformview_form extends \mod_dataform\pluginbase\dataformfieldform {

    /**
     * The field settings.
     *
     * @return void
     */
    protected function field_definition() {
        $mform = &$this->_form;
        $field = $this->_field;
        $config = (object) $field->config;
        $filterformhelper = '\mod_dataform\helper\filterform';

        // Dataform menu (config[refdataform]).
        if ($dataforms = $field->get_applicable_dataforms()) {
            $dfmenu = array('' => array(0 => get_string('choosedots')));
            foreach ($dataforms as $dfid => $df) {
                if (!isset($dfmenu[$df->course->shortname])) {
                    $dfmenu[$df->course->shortname] = array();
                }
                $dfmenu[$df->course->shortname][$dfid] = strip_tags(format_string($df->name, true));
            }
        } else {
            $dfmenu = array('' => array(0 => get_string('nodataforms', 'dataformfield_dataformview')));
        }
        $mform->addElement('selectgroups', 'config[refdataform]', get_string('dataform', 'dataformfield_dataformview'), $dfmenu);
        $mform->addHelpButton('config[refdataform]', 'dataform', 'dataformfield_dataformview');

        // Views menu (config[refview]).
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'config[refview]', get_string('view', 'dataformfield_dataformview'), $options);
        $mform->disabledIf('config[refview]', 'config[refdataform]', 'eq', 0);
        $mform->addHelpButton('config[refview]', 'view', 'dataformfield_dataformview');

        // Filters menu (config[reffilter]).
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'config[reffilter]', get_string('filter', 'dataformfield_dataformview'), $options);
        $mform->disabledIf('config[reffilter]', 'config[refview]', 'eq', 0);
        $mform->addHelpButton('config[reffilter]', 'filter', 'dataformfield_dataformview');

        // Filter by entry attributes (config[filterby]).
        $entryauthorstr = get_string('entryauthor', 'dataformfield_dataformview');
        $entrygroupstr = get_string('entrygroup', 'dataformfield_dataformview');
        $entrystatestr = get_string('entrystate', 'dataformfield_dataformview');
        $grp = array();
        $grp[] = &$mform->createElement('advcheckbox', 'config[filterby][userid]', null, $entryauthorstr, null, array(0, 1));
        $grp[] = &$mform->createElement('advcheckbox', 'config[filterby][groupid]', null, $entrygroupstr, null, array(0, 1));
        $grp[] = &$mform->createElement('advcheckbox', 'config[filterby][state]', null, $entrystatestr, null, array(0, 1));
        $mform->addGroup($grp, 'filterbyarr', get_string('filterby', 'dataformfield_dataformview'), '<br />', false);
        $mform->disabledIf('filterbyarr', 'config[refview]', 'eq', 0);
        $mform->addHelpButton('filterbyarr', 'filterby', 'dataformfield_dataformview');

        // Custom sort.
        $mform->addElement('header', 'customfilterhdr', get_string('customsort', 'dataformfield_dataformview'));
        $filterformhelper::custom_sort_definition($mform, $config->refdataform, $config->customsort);

        // Custom search.
        $mform->addElement('header', 'customfilterhdr', get_string('customsearch', 'dataformfield_dataformview'));
        $filterformhelper::custom_search_definition($mform, $config->refdataform, $config->customsearch);

        // Target dataform overrides.
        $this->definition_dataform_overrides();
    }

    /**
     * Fieldsets for setting target dataform settings overrides.
     *
     * @return void
     */
    protected function definition_dataform_overrides() {
        $mform = &$this->_form;
        $field = $this->_field;
        $config = (object) $field->config;

        // Header.
        $label = get_string('targetdataformoverrides', 'dataformfield_dataformview');
        $mform->addElement('header', 'targetdataformoverrideshdr', $label);

        // Group mode.
        $choices = array();
        $choices[NOGROUPS] = get_string('groupsnone', 'group');
        $choices[SEPARATEGROUPS] = get_string('groupsseparate', 'group');
        $choices[VISIBLEGROUPS] = get_string('groupsvisible', 'group');
        $mform->addElement('select', 'config[overrides][groupmode]', get_string('groupmode', 'group'), $choices);
    }

    /**
     * The field default content fieldset. Override parent to display no defaults.
     *
     * @return void
     */
    protected function definition_defaults() {
    }

    /**
     *
     */
    public function definition_after_data() {
        global $DB;

        $mform = &$this->_form;
        $field = $this->_field;
        $config = (object) $field->config;
        $filterformhelper = '\mod_dataform\helper\filterform';

        // Get the dataform id, if selected.
        $dataformid = 0;
        if ($selectedarr = $mform->getElement('config[refdataform]')->getSelected()) {
            $dataformid = reset($selectedarr);
        }

        if (!$dataformid) {
            return;
        }

        $configview = &$mform->getElement('config[refview]');
        $configfilter = &$mform->getElement('config[reffilter]');

        // Update views menu if needed.
        $viewid = 0;
        if ($selectedarr = $configview->getSelected()) {
            $viewid = reset($selectedarr);
        }

        // Update views menu.
        if ($views = $DB->get_records_menu('dataform_views', array('dataid' => $dataformid), 'name', 'id,name')) {
            $configview = &$mform->getElement('config[refview]');
            foreach ($views as $key => $value) {
                $configview->addOption(strip_tags(format_string($value, true)), $key);
            }
        }

        // Update filters menu.
        if ($filters = $DB->get_records_menu('dataform_filters', array('dataid' => $dataformid), 'name', 'id,name')) {
            $configfilter = &$mform->getElement('config[reffilter]');
            foreach ($filters as $key => $value) {
                $configfilter->addOption(strip_tags(format_string($value, true)), $key);
            }
        }

        if (empty($config->refdataform) or $config->refdataform != $dataformid) {
            $refdf = new mod_dataform_dataform($dataformid);

            // Get the dataform fields.
            $fields = $refdf->field_manager->get_fields();

            // Reload custom sort fields menu.
            $filterformhelper::reload_field_sort_options($mform, $fields);

            // Reload custom search fields menu.
            $filterformhelper::reload_field_search_options($mform, $fields);

            // Set group mode.
            $groupmode = $refdf->groupmode;
            $mform->getElement('config[overrides][groupmode]')->setValue($groupmode);
        }
    }

    /**
     *
     */
    public function data_preprocessing(&$data) {
        $field = $this->_field;

        $data->config = $field->config;
    }

    /**
     *
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     *
     */
    public function get_data() {
        $field = $this->_field;

        if ($data = parent::get_data()) {

            if (empty($data->config['refdataform'])) {
                return $data;
            }

            $filterformhelper = '\mod_dataform\helper\filterform';

            // Config.
            $config = $data->config;

            // Add custom sort and search to config.
            $config['customsort'] = $filterformhelper::get_custom_sort_from_form($data);
            $config['customsearch'] = $filterformhelper::get_custom_search_from_form($data, $config['refdataform']);

            // Set param1 with config.
            if (!$config['refdataform']) {
                $data->param1 = null;
            } else {
                $data->param1 = base64_encode(serialize($config));
            }
        }
        return $data;
    }

    /**
     *
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $errors = array();

        if (!empty($data['config']['refdataform']) and empty($data['config']['refview'])) {
            $errors['config[refview]'] = get_string('missingview', 'dataformfield_dataformview');
        }

        return $errors;
    }
}
