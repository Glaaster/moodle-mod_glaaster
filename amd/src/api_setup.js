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
 * AMD module for Glaaster API setup autocomplete.
 *
 * @module     mod_glaaster/api_setup
 * @copyright  2026 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const init = () => {
    const searchInput = document.getElementById('apiuser-search');
    const hiddenInput = document.getElementById('apiuserid-value');
    const suggestions = document.getElementById('apiuser-suggestions');

    if (!searchInput) {
        return;
    }

    let debounce;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = searchInput.value.trim();
        if (q.length < 2) {
            suggestions.classList.add('d-none');
            return;
        }

        debounce = setTimeout(() => {
            Ajax.call([{
                methodname: 'mod_glaaster_search_users',
                args: {query: q},
                done: (users) => {
                    suggestions.innerHTML = '';
                    if (!users.length) {
                        suggestions.classList.add('d-none');
                        return;
                    }
                    users.forEach(u => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = `${u.fullname} (${u.username})`;
                        item.addEventListener('click', () => {
                            searchInput.value = `${u.fullname} (${u.username})`;
                            hiddenInput.value = u.id;
                            suggestions.classList.add('d-none');
                        });
                        suggestions.appendChild(item);
                    });
                    suggestions.classList.remove('d-none');
                },
                fail: () => suggestions.classList.add('d-none'),
            }]);
        }, 250);
    });

    // Close suggestions on outside click.
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.classList.add('d-none');
        }
    });

    // Token reveal/copy.
    const tokenDisplay = document.getElementById('apitoken-display');
    const revealBtn = document.getElementById('apitoken-reveal');
    const copyBtn = document.getElementById('apitoken-copy');

    if (revealBtn && tokenDisplay) {
        revealBtn.addEventListener('click', () => {
            const isPassword = tokenDisplay.type === 'password';
            tokenDisplay.type = isPassword ? 'text' : 'password';
            revealBtn.textContent = isPassword ? 'Hide' : 'Show';
        });
    }

    if (copyBtn && tokenDisplay) {
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(tokenDisplay.value);
            const originalText = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            setTimeout(() => {
                copyBtn.textContent = originalText;
            }, 2000);
        });
    }
};
