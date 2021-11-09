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

namespace local_ace\reportbuilder\datasource;

use core_reportbuilder\datasource;
use local_ace\local\entities\userentity;
use local_ace\local\entities\enrolmententity;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use lang_string;

/**
 * Users datasource
 *
 * @package   local_ace
 * @copyright 2021 University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('users');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $CFG, $COURSE;

        // User entity.
        $userentity = new userentity();
        $usertablealias = $userentity->get_table_alias('user');
        $usercoursealias = $userentity->get_table_alias('course');
        $this->set_main_table('user', $usertablealias);
        $this->add_entity($userentity);

        // Enrolment entity.
        $enrolmententity = new enrolmententity();
        $enrolmenttablealias = $enrolmententity->get_table_alias('enrol');

        // Join Enrolments entity to Users entity.
        $userenrolmentjoin = "INNER JOIN {user_enrolments} {$enrolmenttablealias}
                              ON {$enrolmenttablealias}.userid = {$usertablealias}.id";

        $this->add_entity($enrolmententity->add_join($userenrolmentjoin));

        // Course entity.
        $courseentity = new course();
        $coursetablealias = $courseentity->get_table_alias('course');

        // Join Enrolments entity to Users entity.
        $courseenroljoin = "INNER JOIN {course} {$coursetablealias}
                            ON {$enrolmenttablealias}.courseid = {$coursetablealias}.id";

        $this->add_entity($courseentity->add_join($courseenroljoin));

        $userparamguest = database::generate_param_name();
        $this->add_base_condition_sql("{$usertablealias}.id != :{$userparamguest} AND {$usertablealias}.deleted = 0"
            , [$userparamguest => $CFG->siteguest,
            ]);

        // Add all columns from entities to be available in custom reports.
        $this->add_entity($userentity);

        $userentityname = $userentity->get_entity_name();

        $this->add_columns_from_entity($userentity->get_entity_name());
        $this->add_columns_from_entity($enrolmententity->get_entity_name());

        $this->add_filters_from_entity($userentity->get_entity_name());
        $this->add_filters_from_entity($enrolmententity->get_entity_name());

        $this->add_conditions_from_entity($userentityname);

        $emailselected = new lang_string('bulkactionbuttonvalue', 'local_ace');

        $this->add_action_button([
            'formaction' => '/local/ace/bulkaction.php',
            'buttonvalue' => $emailselected,
            'buttonid' => 'emailallselected',
        ], true);

        $this->is_downloadable(true);
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['userentity:fullname', 'userentity:username', 'userentity:email', 'userentity:lastaccessedtocourse'];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['userentity:fullname', 'userentity:username', 'userentity:email'];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['userentity:fullname', 'userentity:username', 'userentity:email'];
    }
}
