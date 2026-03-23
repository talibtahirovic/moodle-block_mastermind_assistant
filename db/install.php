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
 * Install script for block_mastermind_assistant
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation script for the block.
 * Creates a sticky block instance so the block appears site-wide automatically.
 */
function xmldb_block_mastermind_assistant_install() {
    global $DB;

    // Create sticky block instance so the block appears on all pages automatically.
    $existing = $DB->get_record('block_instances', [
        'blockname' => 'mastermind_assistant',
        'showinsubcontexts' => 1,
    ]);

    if (!$existing) {
        $blockinstance = new stdClass();
        $blockinstance->blockname = 'mastermind_assistant';
        $blockinstance->parentcontextid = SYSCONTEXTID;
        $blockinstance->showinsubcontexts = 1;
        $blockinstance->pagetypepattern = '*';
        $blockinstance->subpagepattern = null;
        $blockinstance->defaultregion = 'side-pre';
        $blockinstance->defaultweight = 0;
        $blockinstance->configdata = '';
        $blockinstance->timecreated = time();
        $blockinstance->timemodified = time();

        $DB->insert_record('block_instances', $blockinstance);
    }

    return true;
}

