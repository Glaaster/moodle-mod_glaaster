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
 * Breaks out of a frame and redirects to the course URL.
 *
 * Used by return.php when the LTI tool is embedded and needs to navigate
 * the top frame back to the course after completion.
 *
 * @module     mod_glaaster/return_framebreak
 * @copyright  2024 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Redirect the top frame (or current window) to the given URL.
     *
     * @param {string} url Destination URL
     */
    const init = function(url) {
        if (window !== top) {
            top.location.href = url;
        } else {
            window.location.href = url;
        }
    };

    return {
        init: init,
    };
});
