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
 * @param {string} config.instanceId - Glaaster instance ID (may be empty if none exists)
 * @param {boolean} config.instanceValid - Whether a valid, non-deleted Glaaster instance was found
 * @param {boolean} config.iconsEnabled - Admin toggle: whether contextual icons should render at all
 * @param {boolean} config.webservicesEnabled - Whether Moodle web services are enabled
 * @param {boolean} config.webserviceConfigured - Whether Glaaster webservice is configured
 * @param {boolean} config.debugEnabled - Whether debug mode is active
 * @param {string} config.iconUrl - Full URL of the icon to use for the contextual button
 * @param {string} config.iconPosition - Icon position mode: 'left' (glued to the left of the text),
 *                                        'right' (glued to the right of the text, default), or
 *                                        'blockend' (end of the activity block, legacy layout)
 */
export function init(config) {
    'use strict';

    const {
        instanceId, instanceValid, iconsEnabled, webservicesEnabled, webserviceConfigured,
        debugEnabled, iconUrl, iconPosition,
    } = config;

    // Icons are always shown once enabled by the admin; only their enabled/disabled
    // visual state depends on instance validity. This flag is kept live and flipped
    // by the deletion watcher when the instance disappears without a page reload.
    let currentInstanceValid = instanceValid === true;

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
     * Block navigation on disabled Glaaster links.
     *
     * Pointer-events can't be disabled on the link itself (that would also block
     * hover, so the "not configured" tooltip would never show). Instead every link
     * gets this listener once and it no-ops unless aria-disabled is set at click time.
     * @param {MouseEvent} event
     */
    function blockClickIfDisabled(event) {
        if (event.currentTarget.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
        }
    }

    /**
     * Apply the enabled/disabled state to a Glaaster link element.
     * @param {HTMLElement} a
     * @param {string} url
     * @param {string} enabledTitle
     * @param {string} disabledTitle
     */
    function applyLinkState(a, url, enabledTitle, disabledTitle) {
        if (!a.dataset.glaasterClickBound) {
            a.addEventListener('click', blockClickIfDisabled);
            a.dataset.glaasterClickBound = 'true';
        }
        if (currentInstanceValid) {
            a.href = url;
            a.title = enabledTitle || '';
            a.removeAttribute('aria-disabled');
            a.removeAttribute('tabindex');
            a.classList.remove('glaaster-icon-disabled');
        } else {
            // Keep href present (harmless "#") rather than removing it: an <a> with
            // no href isn't hoverable/focusable in every browser, which would also
            // silently kill the tooltip. aria-disabled + the click blocker above are
            // what actually stop navigation.
            a.href = '#';
            a.title = disabledTitle || '';
            a.setAttribute('aria-disabled', 'true');
            a.setAttribute('tabindex', '-1');
            a.classList.add('glaaster-icon-disabled');
        }
    }

    /**
     * Create a Glaaster link element with proper attributes.
     * @param {string} url
     * @param {string} title
     * @param {string} imgClass
     * @param {string} disabledTitle
     * @return {HTMLElement}
     */
    function createGlaasterLink(url, title, imgClass, disabledTitle) {
        const a = document.createElement('a');
        a.setAttribute('data-glaaster-link', 'true');
        const klass = (imgClass || '').toString().trim();
        a.innerHTML = `<img src="${iconUrl}" class="${klass}" ` +
            `alt="${title || ''}" role="presentation" aria-hidden="true">`;
        applyLinkState(a, url, title, disabledTitle);
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
     * @param {string} disabledTranslation
     */
    function addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation, disabledTranslation) {
        fileLinks.forEach((fileAnchor) => {
            try {
                const fileLabel = (fileAnchor.textContent || '').trim();
                if (!hasSupportedExtension(fileLabel)) {
                    // No recognisable extension in the filename (e.g. extensionless upload):
                    // fall back to Moodle's mimetype-derived file icon. The icon lives in a
                    // sibling .fp-icon span, outside the anchor itself, so look at the closest
                    // shared wrapper (.fp-filename-icon) rather than inside fileAnchor.
                    const wrapper = fileAnchor.closest('.fp-filename-icon') || fileAnchor.parentNode;
                    const iconImg = wrapper && wrapper.querySelector('.fp-icon img');
                    if (!iconImg || !hasSupportedFileIcon(iconImg.src)) {
                        return;
                    }
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

                parent.appendChild(createGlaasterLink(url, translation, 'icon', disabledTranslation));
            } catch (e) {
                warn('Failed adding folder file link', e);
            }
        });
    }

    /**
     * Flip all existing Glaaster buttons on the page into the disabled state
     * (kept visible, but non-clickable with a "not configured" tooltip).
     */
    function disableAllGlaasterButtons() {
        currentInstanceValid = false;
        const buttons = document.querySelectorAll('a[data-glaaster-link="true"]');
        buttons.forEach((button) => applyLinkState(button, button.getAttribute('href') || '', '', disabledTranslationText));
    }

    // Cached translation used by the deletion watcher, set once strings are loaded.
    let disabledTranslationText = '';

    /**
     * Setup MutationObserver to watch for dynamically loaded content (Tiles format).
     * @param {string} translation
     * @param {string} disabledTranslation
     */
    function setupContentObserver(translation, disabledTranslation) {
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
                                injectButtonsInContainer(node.parentElement, translation, disabledTranslation);
                            } else if (node.querySelector) {
                                const hasActivities = node.querySelector('li.modtype_resource, li.modtype_folder, li.modtype_page');
                                if (hasActivities) {
                                    injectButtonsInContainer(node, translation, disabledTranslation);
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
     * disables all buttons (kept visible) if the instance is no longer valid.
     *
     * This provides instant button state update (< 500ms) without requiring page refresh.
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
                                    disableAllGlaasterButtons();
                                }
                            }).fail(function() {
                                disableAllGlaasterButtons();
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
     * @param {string} disabledTranslation
     */
    function injectButtonsInContainer(container, translation, disabledTranslation) {
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
                    glaasterButton.innerHTML = `<img src="${iconUrl}" ` +
                        `class="iconlarge activityicon" alt="${translation}" role="presentation" ` +
                        `aria-hidden="true" width="24" height="24" style="display: block;">`;
                    applyLinkState(glaasterButton, url, translation, disabledTranslation);

                    if (iconPosition === 'blockend') {
                        glaasterButton.classList.add('glaaster-icon-tile-blockend');
                        if (window.getComputedStyle(resource).position === 'static') {
                            resource.style.position = 'relative';
                        }
                    } else {
                        glaasterButton.classList.add('glaaster-icon-tile-inline');
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

                    const glaasterLink = createGlaasterLink(url, translation, 'iconlarge activityicon', disabledTranslation);

                    if (iconPosition === 'blockend') {
                        // Legacy layout: icon appended at the end of the whole activity block,
                        // outside the name text, with its own margin/height styling.
                        glaasterLink.classList.add('glaaster-icon-blockend');
                        const activityNameArea = activityContainer.querySelector('.activity-name-area');
                        const mediaBody = activityContainer.querySelector('.media-body');
                        const anchor = activityNameArea || mediaBody;
                        if (anchor) {
                            anchor.after(glaasterLink);
                        } else {
                            activityContainer.append(glaasterLink);
                        }
                    } else {
                        // The activity row uses CSS Grid with named areas (Boost .activity-grid):
                        // appending a plain sibling ignores DOM order for visual placement. Instead,
                        // inject the link inside the "name" grid-area's own content (.activityname),
                        // which is a normal inline flow, so left/right insertion order is honoured.
                        const activityName = activityContainer.querySelector('.activityname');
                        const activityNameArea = activityContainer.querySelector('.activity-name-area');
                        const mediaBody = activityContainer.querySelector('.media-body');
                        const isLeftPosition = iconPosition === 'left';
                        glaasterLink.classList.add(isLeftPosition ? 'glaaster-icon-left' : 'glaaster-icon-right');
                        if (activityName) {
                            if (isLeftPosition) {
                                activityName.prepend(glaasterLink);
                            } else {
                                activityName.append(glaasterLink);
                            }
                        } else if (activityNameArea) {
                            if (isLeftPosition) {
                                activityNameArea.before(glaasterLink);
                            } else {
                                activityNameArea.after(glaasterLink);
                            }
                        } else if (mediaBody) {
                            if (isLeftPosition) {
                                mediaBody.before(glaasterLink);
                            } else {
                                mediaBody.after(glaasterLink);
                            }
                        } else {
                            activityContainer.prepend(glaasterLink);
                        }
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
                addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation, disabledTranslation);
            }
        });
    }

    /**
     * Add Glaaster buttons to the page. Buttons always render on supported documents;
     * their enabled/disabled state depends on whether a valid Glaaster instance exists.
     * @param {string} translation
     * @param {string} disabledTranslation
     */
    function addButtonsToPage(translation, disabledTranslation) {
        injectButtonsInContainer(null, translation, disabledTranslation);
        setupContentObserver(translation, disabledTranslation);

        if (window.location.pathname.includes('/mod/folder/view.php')) {
            const urlParams = new URLSearchParams(window.location.search);
            const folderModuleId = urlParams.get('id');
            if (folderModuleId) {
                const fileLinks = document.querySelectorAll('.fp-filename a');
                if (fileLinks.length) {
                    addGlaasterButtonsToFiles(fileLinks, folderModuleId, translation, disabledTranslation);
                }
            }
        }
    }

    // Entry point — runs after DOMContentLoaded (js_call_amd guarantees DOM ready).
    if (typeof M === 'undefined' || !M.cfg || !M.cfg.wwwroot) {
        warn('Moodle config not available (M.cfg.wwwroot). Aborting.');
        return;
    }

    if (iconsEnabled === false) {
        warn('Contextual icons disabled by admin setting. Aborting.');
        return;
    }

    if (webservicesEnabled === false) {
        warn('Moodle web services are not enabled. Real-time deletion watcher will be unavailable.');
    }

    if (webserviceConfigured === false) {
        warn('Glaaster webservice not configured. Real-time deletion watcher will be unavailable.');
    }

    Promise.all([
        getString('view_document_adaptive', 'mod_glaaster'),
        getString('not_configured_tooltip', 'mod_glaaster'),
    ]).then(function(translations) {
        const translation = translations[0];
        const disabledTranslation = translations[1];
        disabledTranslationText = disabledTranslation;

        addButtonsToPage(translation, disabledTranslation);

        if (webservicesEnabled !== false && webserviceConfigured !== false) {
            setupDeletionWatcher();
        }
    }).catch(function(error) {
        warn('Failed to load translations:', error);
    });
}
