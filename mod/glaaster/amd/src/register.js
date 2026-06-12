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
 * Handles the tool proxy registration iframe: warning timer and resize.
 *
 * @module     mod_glaaster/register
 * @copyright  2024 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Initialise the registration page behaviour.
     *
     * Shows a warning banner if the iframe takes more than 20 seconds to load,
     * and makes the iframe fill the available viewport height.
     */
    const init = function() {
        const frame = document.getElementById('contentframe');
        const warning = document.getElementById('id_warning');

        if (!frame || !warning) {
            return;
        }

        // Suppress outer scrollbar so there's no double scrollbar effect.
        document.body.style.overflow = 'hidden';

        // Show warning if iframe hasn't loaded within 20 seconds.
        const timer = window.setTimeout(function() {
            warning.classList.remove('hidden');
        }, 20000);

        frame.addEventListener('load', function() {
            window.clearTimeout(timer);
        });

        // Resize iframe to fill viewport.
        const padding = 15;
        const resize = function() {
            const rect = frame.getBoundingClientRect();
            frame.style.height = (window.innerHeight - rect.top - padding) + 'px';
        };
        resize();
        window.addEventListener('resize', resize);
    };

    return {
        init: init,
    };
});
