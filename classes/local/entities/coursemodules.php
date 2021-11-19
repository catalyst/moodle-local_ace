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

declare(strict_types=1);

namespace local_ace\local\entities;

use context_course;
use context_system;
use context_helper;
use core_course_category;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\course_selector;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\custom_fields;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\base;
use core_user\fields;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\entities\user;
use html_writer;
use lang_string;
use stdClass;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Course entity class implementation
 *
 * This entity defines all the course columns and filters to be used in any report.
 *
 * @package     local_ace
 * @copyright   2021 University of Canterbury
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursemodules extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
                'user' => 'au',
                'enrol' => 'ae',
                'user_enrolments' => 'aue',
                'course' => 'ac',
                'course_modules' => 'acm',
                'modules' => 'am',
                'assign' => 'aa',
                'assign_submission' => 'aas',
                'logstore_standard_log' => 'alsl',
                'context' => 'actx',
               ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('pluginname', 'local_ace');
    }

    /**
     * Initialise the entity, add all user fields and all 'visible' user profile fields
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        global $CFG, $DB, $USER, $PAGE;
        // Note this custom report source is restricted to showing activities.
        $course = (int)optional_param('courseid', 0, PARAM_INT);

        // Determine which user to use within the user specific columns - use $PAGE->context if user context or global $USER.
        if ($PAGE->context->contextlevel == CONTEXT_USER) {
            $userid = $PAGE->context->instanceid;
        } else {
            $userid = $USER->id;
        }

        $cmalias = $this->get_table_alias('course_modules');
        $modulesalias = $this->get_table_alias('modules');

        // Build helper sql for getting activity names.

        $modules = [];
        $allmods = $DB->get_records("modules"); // TODO - cache this? find a better way?
        foreach ($allmods as $mod) {
            if (file_exists("$CFG->dirroot/mod/$mod->name/lib.php") && $mod->visible) {
                $modules[$mod->id] = clean_param($mod->name, PARAM_ALPHANUMEXT);
            }
        }

        // Create a table with the instanceid, module id and activity name to match with coursemodule table.
        $modulejoins = [];
        foreach ($modules as $mid => $mname) {
            $duedatecolumn = 0;
            if ($mname == 'assign') {
                $duedatecolumn = 'duedate';
            }
            // This injects params into in-line sql, but we cast and clean all to make safe.
            $modulejoins[] = "SELECT id, name, $mid as module, $duedatecolumn as duedate
                                FROM {".$mname."}
                               WHERE course = $course";
        }
        $modulejoin = implode(' UNION ALL ', $modulejoins);

        $columns = [];

        // Module Icon column.
        $columns[] = (new column(
            'type',
            new lang_string('activity'),
            $this->get_entity_name()
        ))
            ->add_join("JOIN {modules} {$modulesalias} ON {$cmalias}.module = {$modulesalias}.id")
            ->set_is_sortable(true)
            ->add_field("{$modulesalias}.name")
            ->add_callback(static function ($v): string {
                global $OUTPUT;
                return $OUTPUT->pix_icon('icon', $v, $v, array('class' => 'icon'));
            });

        // Module Icon column.
        $columns[] = (new column(
            'name',
            new lang_string('name'),
            $this->get_entity_name()
        ))
            ->add_join("JOIN ({$modulejoin}) mmj ON mmj.id = {$cmalias}.instance AND mmj.module = {$cmalias}.module")
            ->set_is_sortable(true)
            ->add_field("mmj.name");

        // Date due.
        $columns[] = (new column(
            'duedate',
            new lang_string('due', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join("JOIN {modules} {$modulesalias} ON {$cmalias}.module = {$modulesalias}.id")
        ->set_is_sortable(true)
        ->set_type(column::TYPE_TIMESTAMP)
        ->add_fields("mmj.duedate")
        ->set_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $filters = [];
        $cmalias = $this->get_table_alias('course_modules');
        $modulesalias = $this->get_table_alias('modules');

        // Module name filter.
        $filters[] = (new filter(
            text::class,
            'nameselector',
            new lang_string('name'),
            $this->get_entity_name(),
            "{$modulesalias}.name"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
