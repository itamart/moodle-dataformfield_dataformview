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

defined('MOODLE_INTERNAL') or die();

/**
 *
 */
class dataformfield_dataformview_renderer extends \mod_dataform\pluginbase\dataformfieldrenderer {

    /**
     *
     */
    protected function replacements(array $patterns, $entry, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->name;

        $replacements = array_fill_keys($patterns, '');

        foreach ($patterns as $pattern) {
            // No edit mode.
            list(, $displaytype) = array_pad(explode(':', trim($pattern, '[]')), 2, null);
            if (!$displaytype or $displaytype == 'inline') {
                $replacements[$pattern] = $this->display_inline($entry);
            } else {
                $displayfunc = "display_$displaytype";
                $replacements[$pattern] = $this->$displayfunc($entry);
            }
        }
        return $replacements;
    }

    /**
     *
     */
    public function display_inline($entry) {
        $field = $this->_field;

        $voptions = array('controls' => false);
        return $field->get_view_display_content($entry, $voptions);
    }

    /**
     *
     */
    public function display_overlay($entry) {
        $field = $this->_field;

        $this->add_overlay_support();

        $voptions = array('controls' => false);
        $viewcontent = $field->get_view_display_content($entry, $voptions);
        $viewcontent = $field->get_view_display_content($entry, $voptions);
        $widgetbody = html_writer::tag('div', $viewcontent, array('class' => "yui3-widget-bd"));
        $panel = html_writer::tag('div', $widgetbody, array('class' => 'panelContent hide'));
        $button = html_writer::tag('div', get_string('viewbutton', 'dataformfield_dataformview'));
        $wrapper = html_writer::tag('div', $button. $panel, array('class' => 'dataformfield-dataformview overlay'));
        return $wrapper;
    }

    /**
     *
     */
    public function display_embedded($entry) {
        $field = $this->_field;
        $fieldname = str_replace(' ', '_', $field->name);

        $urlparams = $field->get_view_url_params($entry);
        $srcurl = new moodle_url('/mod/dataform/embed.php', $urlparams);

        // Frame.
        $froptions = array(
            'src' => $srcurl,
            'width' => '100%',
            'height' => '100%',
            'style' => 'border:0;',
        );
        $iframe = html_writer::tag('iframe', null, $froptions);
        return html_writer::tag('div', $iframe, array('class' => "dataformfield-dataformview-$fieldname embedded"));
    }

    /**
     *
     */
    public function display_embeddedoverlay($entry, $type = null) {
        $this->add_overlay_support();

        $widgetbody = html_writer::tag('div', $this->display_embedded($entry), array('class' => "yui3-widget-bd"));
        $panel = html_writer::tag('div', $widgetbody, array('class' => 'panelContent hide'));
        $button = html_writer::tag('button', get_string('viewbutton', 'dataformfield_dataformview'));
        $wrapper = html_writer::tag('div', $button. $panel, array('class' => 'dataformfield-dataformview embedded overlay'));
        return $wrapper;
    }

    /**
     *
     */
    protected function add_overlay_support() {
        global $PAGE;

        static $added = false;

        if (!$added) {
            $module = array(
                'name' => 'M.dataformfield_dataformview_overlay',
                'fullpath' => '/mod/dataform/field/dataformview/dataformview.js',
                'requires' => array('base', 'node')
            );

            $PAGE->requires->js_init_call('M.dataformfield_dataformview_overlay.init', null, false, $module);
        }
    }

    /**
     * Array of patterns this field supports.
     */
    protected function patterns() {
        $fieldname = $this->_field->name;

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:overlay]]"] = array(true, $fieldname);
        $patterns["[[$fieldname:embedded]]"] = array(false, $fieldname);
        $patterns["[[$fieldname:embeddedoverlay]]"] = array(false, $fieldname);

        return $patterns;
    }
}
