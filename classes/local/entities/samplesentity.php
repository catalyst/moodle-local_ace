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

use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Sample entity class implementation
 *
 * This entity defines all the ACE sample columns and filters to be used in any report.
 *
 * @package     local_ace
 * @copyright   2021 University of Canterbury
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class samplesentity extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
                'user' => 'u',
                'local_ace_samples' => 'las',
               ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('sampleentitytitle', 'local_ace');
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
            $this->add_filter($filter);
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

        $columns = [];
        $usertablealias = $this->get_table_alias('user');
        $samplesalias = $this->get_table_alias('local_ace_samples');

        $join = "
            JOIN {local_ace_samples} {$samplesalias}
            ON {$samplesalias}.userid = {$usertablealias}.id
        ";

        // Module starttime column.
        $columns[] = (new column(
            'starttime',
            new lang_string('starttime', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$samplesalias}.starttime")
            ->add_callback(static function(?string $value): string {
                if ($value === null) {
                    return '';
                }
                return userdate($value);
            });

        // Time enrolment ended.
        $columns[] = (new column(
            'endtime',
            new lang_string('endtime', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_fields("{$samplesalias}.endtime")
            ->add_callback(static function ($value): string {
                if ($value === null) {
                    return '';
                }
                return userdate($value);
            });

        // Student engagement percent value.
        $columns[] = (new column(
            'studentengagement',
            new lang_string('studentengagement', 'local_ace'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_fields("{$samplesalias}.value")
            ->add_callback(static function ($value): string {
                if (!$value) {
                    return '0%';
                }
                return $value . '%';
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $filters = [];
        $usertablealias = $this->get_table_alias('user');
        $samplesalias = $this->get_table_alias('local_ace_samples');

        $join = "
            JOIN {local_ace_samples} {$samplesalias}
            ON {$samplesalias}.userid = {$usertablealias}.id
        ";

        // Module name filter.
        $filters[] = (new filter(
            date::class,
            'starttime',
            new lang_string('starttime', 'local_ace'),
            $this->get_entity_name(),
            "{$samplesalias}.starttime"
        ))
            ->add_join($join);

        // Last accessed filter.
        $filters[] = (new filter(
            date::class,
            'endtime',
            new lang_string('endtime', 'local_ace'),
            $this->get_entity_name(),
            "{$samplesalias}.endtime"
        ))
            ->add_join($join);

        // Due date filter.
        $filters[] = (new filter(
            text::class,
            'studentengagement',
            new lang_string('studentengagement', 'local_ace'),
            $this->get_entity_name(),
            "{$samplesalias}.value"
        ))
            ->add_join($join);

        return $filters;
    }
}
