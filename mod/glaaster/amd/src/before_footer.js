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
 * Glaaster plugin JavaScript for adding file links to Moodle course pages.
 * Adds Glaaster buttons to supported file types in resource and folder modules.
 *
 * @module      mod_glaaster/before_footer
 * @copyright   2025 Glaaster
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import {call as ajaxCall} from 'core/ajax';

// Supported file types for Glaaster integration.
const SUPPORTEDFILEEXTENSIONS = ['.pdf', '.png', '.jpeg', '.jpg', '.docx', '.pptx', '.odt', '.odp'];
const SUPPORTEDEXTS = new Set(SUPPORTEDFILEEXTENSIONS);

// Moodle file type icons that correspond to supported extensions.
const SUPPORTEDFILEICONS = ['f/pdf', 'f/image', 'f/document', 'f/powerpoint', 'f/writer', 'f/impress'];

/**
 * Initialise the Glaaster before-footer integration.
 *
 * @param {Object} config - Configuration passed from PHP via js_call_amd
 * @param {string} config.instanceId - Glaaster instance ID
 * @param {boolean} config.webservicesEnabled - Whether Moodle web services are enabled
 * @param {boolean} config.webserviceConfigured - Whether Glaaster webservice is configured
 * @param {boolean} config.debugEnabled - Whether debug mode is active
 */
export function init(config) {
    'use strict';

    const {instanceId, webservicesEnabled, webserviceConfigured, debugEnabled} = config;

    /**
     * Debug logging helper.
     * @param {...*} args
     */
    function warn(...args) {
        if (debugEnabled === true) {
            try {
                console.warn('Glaaster WARN:', ...args); // eslint-disable-line no-console
            } catch (e) {
                // Silent fail if console not available.
            }
        }
    }

    /**
     * Base64 encode string with UTF-8 support.
     * @param {string} str
     * @return {string}
     */
    function safeBtoa(str) {
        try {
            return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
                return String.fromCharCode('0x' + p1);
            }));
        } catch (e) {
            warn('Unable to base64-encode string', str, e);
            return '';
        }
    }

    /**
     * Check if text contains any supported file extension.
     * @param {string} text
     * @return {boolean}
     */
    function hasSupportedExtension(text) {
        if (!text) {
            return false;
        }
        const lower = text.toLowerCase();
        for (const ext of SUPPORTEDEXTS) {
            if (lower.includes(ext)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if image source indicates a supported file type.
     * @param {string} src
     * @return {boolean}
     */
    function hasSupportedFileIcon(src) {
        if (!src) {
            return false;
        }
        return SUPPORTEDFILEICONS.some(icon => src.includes(icon));
    }

    /**
     * Check if container already has a Glaaster link to avoid duplicates.
     * @param {HTMLElement} container
     * @return {boolean}
     */
    function hasGlaasterLink(container) {
        return !!(container && container.querySelector('a[data-glaaster-link="true"]'));
    }

    /**
     * Create a Glaaster link element with proper attributes.
     * @param {string} url
     * @param {string} title
     * @param {string} imgClass
     * @return {HTMLElement}
     */
    function createGlaasterLink(url, title, imgClass) {
        const a = document.createElement('a');
        a.setAttribute('data-glaaster-link', 'true');
        a.href = url;
        a.title = title || '';
        const klass = (imgClass || '').toString().trim();
        a.innerHTML = `<img src="${M.cfg.wwwroot}/mod/glaaster/pix/icon.svg" class="${klass}" ` +
            `alt="${title || ''}" role="presentation" aria-hidden="true">`;
        return a;
    }

    /**
     * Build Glaaster view URL with parameters.
     * @param {Object} params
     * @return {string}
     */
    function buildGlaasterUrl(params) {
        const base = `${M.cfg.wwwroot}/mod/glaaster/view.php`;
        const usp = new URLSearchParams(params);
        return `${base}?${usp.toString()}`;
    }

    /**
     * Extract ID parameter from Moodle URLs.
     * @param {string} href
     * @return {string|null}
     */
    function extractIdFromHref(href) {
        try {
            const u = new URL(href, window.location.origin);
            return u.searchParams.get('id');
        } catch (e) {
            const m = href && href.match(/(?:\?|&)id=(\d+)/);
            return m ? m[1] : null;
        }
    }

    /**
     * Extract file path from Moodle pluginfile URLs for folder content.
     * @param {string} href
     * @return {string|null}
     */
    function extractPluginFilePath(href) {
        if (!href) {
            return null;
        }
        const re = /\/pluginfile\.php\/[^/]+\/mod_folder\/content\/[^/]+\/(.*)$/;
        const m = href.match(re);
        if (!m || !m[1]) {
            return null;
        }
        const raw = m[1].split('?')[0];
        try {
            return decodeURIComponent(raw);
        } catch (e) {
            return raw;
        }
    }

    /**
     * Add Glaaster buttons to folder files.
     * @param {NodeList} fileLinks
     * @param {string} folderModuleId
     * @param {string} translation
     */
    function addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation) {
        fileLinks.forEach((fileAnchor) => {
            try {
                const fileLabel = (fileAnchor.textContent || '').trim();
                if (!hasSupportedExtension(fileLabel)) {
                    return;
                }

                const extractedPath = extractPluginFilePath(fileAnchor.getAttribute('href'));
                const fullFilePath = extractedPath || fileLabel;

                const parts = fullFilePath.split('/').filter(Boolean);
                const fileBaseName = parts.pop() || fullFilePath;
                const fileDir = parts.length ? `/${parts.join('/')}/` : '/';

                const parent = fileAnchor.parentNode || fileAnchor;
                if (hasGlaasterLink(parent)) {
                    return;
                }

                const url = buildGlaasterUrl({
                    l: String(instanceId),
                    course_module_id: String(folderModuleId),
                    file_name: safeBtoa(fileBaseName),
                    file_path: safeBtoa(fileDir)
                });

                parent.appendChild(createGlaasterLink(url, translation, 'icon'));
            } catch (e) {
                warn('Failed adding folder file link', e);
            }
        });
    }

    /**
     * Remove all Glaaster buttons from the page.
     */
    function removeAllGlaasterButtons() {
        const buttons = document.querySelectorAll('a[data-glaaster-link="true"]');
        buttons.forEach(button => button.remove());
    }

    /**
     * Setup MutationObserver to watch for dynamically loaded content (Tiles format).
     * @param {string} translation
     */
    function setupContentObserver(translation) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.addedNodes.length > 0) {
                    for (const node of mutation.addedNodes) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.classList && (
                                node.classList.contains('modtype_resource') ||
                                node.classList.contains('modtype_folder') ||
                                node.classList.contains('modtype_page')
                            )) {
                                injectButtonsInContainer(node.parentElement, translation);
                            } else if (node.querySelector) {
                                const hasActivities = node.querySelector('li.modtype_resource, li.modtype_folder, li.modtype_page');
                                if (hasActivities) {
                                    injectButtonsInContainer(node, translation);
                                }
                            }
                        }
                    }
                }
            }
        });

        const courseContent = document.querySelector('#region-main, .course-content, main');
        if (courseContent) {
            observer.observe(courseContent, {
                childList: true,
                subtree: true,
            });
        }
    }

    /**
     * Setup MutationObserver for real-time deletion detection.
     *
     * Monitors the course content area for DOM changes, specifically watching for
     * Glaaster activity removals. When detected, triggers AJAX revalidation and
     * removes all buttons if the instance is no longer valid.
     *
     * This provides instant button removal (< 500ms) without requiring page refresh.
     */
    function setupDeletionWatcher() {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.removedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList && (
                            node.classList.contains('modtype_glaaster') ||
                            node.id && node.id.includes('module-') ||
                            node.matches && node.matches('[data-activityname*="glaaster"]')
                        )) {
                            ajaxCall([{
                                methodname: 'mod_glaaster_validate_instance',
                                args: {instanceid: parseInt(instanceId)},
                            }])[0].done(function(response) {
                                if (!response.isvalid) {
                                    removeAllGlaasterButtons();
                                }
                            }).fail(function() {
                                removeAllGlaasterButtons();
                            });
                            return;
                        }
                    }
                }
            }
        });

        const courseContent = document.querySelector('#region-main, .course-content, main');
        if (courseContent) {
            observer.observe(courseContent, {
                childList: true,
                subtree: true,
            });
        }
    }

    /**
     * Inject Glaaster buttons for a specific container (or whole page if no container specified).
     * Supports both Tiles format and standard Moodle formats.
     * @param {HTMLElement|null} container
     * @param {string} translation
     */
    function injectButtonsInContainer(container, translation) {
        const root = container || document;

        const resources = root.querySelectorAll('li.modtype_resource, li.modtype_page');

        resources.forEach((resource) => {
            try {
                const isTileFormat = resource.classList.contains('activity') && resource.classList.contains('subtile');

                if (isTileFormat) {
                    if (hasGlaasterLink(resource)) {
                        return;
                    }

                    const imgElement = resource.querySelector('.tileiconcontainer img, .tile-icon img');
                    if (!imgElement) {
                        return;
                    }

                    if (!hasSupportedFileIcon(imgElement.src)) {
                        return;
                    }

                    const moduleId = resource.getAttribute('data-cmid') || resource.getAttribute('data-id');
                    if (!moduleId) {
                        return;
                    }

                    const url = buildGlaasterUrl({
                        l: String(instanceId),
                        course_module_id: String(moduleId)
                    });

                    const glaasterButton = document.createElement('a');
                    glaasterButton.setAttribute('data-glaaster-link', 'true');
                    glaasterButton.href = url;
                    glaasterButton.title = translation;
                    glaasterButton.innerHTML = `<img src="${M.cfg.wwwroot}/mod/glaaster/pix/icon.svg" ` +
                        `class="iconlarge activityicon" alt="${translation}" role="presentation" ` +
                        `aria-hidden="true" width="24" height="24" style="display: block;">`;

                    glaasterButton.style.position = 'absolute';
                    glaasterButton.style.bottom = '10px';
                    glaasterButton.style.right = '6px';
                    glaasterButton.style.width = '36px';
                    glaasterButton.style.height = '36px';
                    glaasterButton.style.display = 'flex';
                    glaasterButton.style.alignItems = 'center';
                    glaasterButton.style.justifyContent = 'center';
                    glaasterButton.style.zIndex = '10';

                    if (window.getComputedStyle(resource).position === 'static') {
                        resource.style.position = 'relative';
                    }

                    resource.appendChild(glaasterButton);

                } else {
                    const activityLink = resource.querySelector('div.activityname a, .activityname .aalink');
                    if (!activityLink) {
                        return;
                    }

                    const href = activityLink.getAttribute('href');
                    const resourceId = extractIdFromHref(href);
                    if (!resourceId) {
                        return;
                    }

                    let activityContainer = resource.querySelector('.activity-grid, .activity-basis');
                    if (!activityContainer) {
                        return;
                    }

                    if (hasGlaasterLink(activityContainer)) {
                        return;
                    }

                    const img = activityContainer.querySelector('img');
                    if (!img || !hasSupportedFileIcon(img.src)) {
                        return;
                    }

                    const url = buildGlaasterUrl({
                        l: String(instanceId),
                        course_module_id: String(resourceId)
                    });

                    const glaasterLink = createGlaasterLink(url, translation, 'iconlarge activityicon');
                    glaasterLink.style.alignItems = 'center';
                    glaasterLink.style.display = 'flex';
                    glaasterLink.style.marginLeft = '10px';
                    glaasterLink.style.marginRight = '10px';
                    glaasterLink.style.height = '50px';
                    glaasterLink.style.zIndex = '30';

                    const activityNameArea = activityContainer.querySelector('.activity-name-area');
                    const mediaBody = activityContainer.querySelector('.media-body');
                    if (activityNameArea) {
                        activityNameArea.after(glaasterLink);
                    } else if (mediaBody) {
                        mediaBody.after(glaasterLink);
                    } else {
                        activityContainer.prepend(glaasterLink);
                    }
                }
            } catch (e) {
                warn('Failed processing a resource element', e);
            }
        });

        const folders = root.querySelectorAll('li.modtype_folder');
        folders.forEach((folderLi) => {
            let folderModuleId = folderLi.getAttribute('data-cmid') || folderLi.getAttribute('data-id');

            if (!folderModuleId) {
                const activityGrid = folderLi.querySelector('.activity-grid');
                if (activityGrid) {
                    folderModuleId = activityGrid.getAttribute('data-cmid');
                }
            }

            if (!folderModuleId) {
                return;
            }

            const fileLinks = folderLi.querySelectorAll('span.fp-filename a');
            if (fileLinks.length) {
                addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation);
            }
        });
    }

    /**
     * Add Glaaster buttons to the page after validation.
     * @param {string} translation
     */
    function addButtonsToPage(translation) {
        injectButtonsInContainer(null, translation);
        setupContentObserver(translation);

        if (window.location.pathname.includes('/mod/folder/view.php')) {
            const urlParams = new URLSearchParams(window.location.search);
            const folderModuleId = urlParams.get('id');
            if (folderModuleId) {
                const fileLinks = document.querySelectorAll('.fp-filename a');
                if (fileLinks.length) {
                    addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation);
                }
            }
        }
    }

    // Entry point — runs after DOMContentLoaded (js_call_amd guarantees DOM ready).
    if (typeof M === 'undefined' || !M.cfg || !M.cfg.wwwroot) {
        warn('Moodle config not available (M.cfg.wwwroot). Aborting.');
        return;
    }

    if (webservicesEnabled === false) {
        warn('Moodle web services are not enabled. Cannot use AJAX validation. Aborting.');
        return;
    }

    if (webserviceConfigured === false) {
        warn('Glaaster webservice not configured. Missing user, token, or external functions. Aborting.');
        return;
    }

    if (!instanceId) {
        warn('instanceId is undefined/empty. Skipping link injection.');
        return;
    }

    getString('view_document_adaptive', 'mod_glaaster').then(function(translation) {
        ajaxCall([{
            methodname: 'mod_glaaster_validate_instance',
            args: {instanceid: parseInt(instanceId)},
        }])[0].done(function(response) {
            if (!response.isvalid) {
                warn('instanceId is not valid (deleted or course removed). Skipping link injection.');
                return;
            }
            addButtonsToPage(translation);
            setupDeletionWatcher();
        }).fail(function(error) {
            warn('Failed to validate instance:', error);
        });
    }).catch(function(error) {
        warn('Failed to load translations:', error);
    });
}
