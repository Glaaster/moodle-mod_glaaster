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
 * Handles view.php iframe resize and window-launch for LTI activities.
 *
 * @module     mod_glaaster/view
 * @copyright  2024 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Open LTI tool in a new window.
     *
     * @param {string} url Launch URL
     * @param {number} cmId Course module ID
     */
    const initWindowLaunch = function(url, cmId) {
        window.open(url, 'lti-' + cmId);
    };

    /**
     * Make the LTI iframe fill the available viewport height.
     */
    const initIframeResize = function() {
        const frame = document.getElementById('contentframe');
        if (!frame) {
            return;
        }
        const padding = 15;
        const resize = function() {
            frame.style.height = (window.innerHeight * 0.88 - padding) + 'px';
        };
        frame.style.marginBottom = '60px';
        resize();
        window.addEventListener('resize', resize);
    };

    return {
        initWindowLaunch: initWindowLaunch,
        initIframeResize: initIframeResize,
    };
});
