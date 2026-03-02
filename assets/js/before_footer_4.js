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
 * @package     mod_glaaster
 * @copyright   2025 Glaaster
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    // Supported file types for Glaaster integration.
    const SUPPORTEDFILEEXTENSIONS = ['.pdf', '.png', '.jpeg', '.jpg', '.docx', '.pptx', '.odt', '.odp'];
    const SUPPORTEDEXTS = new Set(SUPPORTEDFILEEXTENSIONS);

    // Moodle file type icons that correspond to supported extensions.
    const SUPPORTEDFILEICONS = ['f/pdf', 'f/image', 'f/document', 'f/powerpoint', 'f/writer', 'f/impress'];

    /**
     * Debug logging helper.
     * Only logs when Moodle debug mode is enabled.
     * @param {...*} args - Arguments to log
     */
    function warn(...args) {
        // Only show warnings if debug mode is enabled.
        if (typeof glaasterDebugEnabled !== 'undefined' && glaasterDebugEnabled === true) {
            try {
                console.warn('Glaaster WARN:', ...args);
            } catch (e) {
                // Silent fail if console not available.
            }
        }
    }

    /**
     * Base64 encode string with UTF-8 support.
     * @param {string} str - String to encode
     * @return {string} Base64 encoded string
     */
    function safeBtoa(str) {
        try {
            return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (match, p1) {
                return String.fromCharCode('0x' + p1);
            }));
        } catch (e) {
            warn('Unable to base64-encode string', str, e);
            return '';
        }
    }

    /**
     * Check if text contains any supported file extension.
     * @param {string} text - Text to check
     * @return {boolean} True if contains supported extension
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
     * @param {string} src - Image source URL
     * @return {boolean} True if supported file type
     */
    function hasSupportedFileIcon(src) {
        if (!src) {
            return false;
        }
        return SUPPORTEDFILEICONS.some(icon => src.includes(icon));
    }

    /**
     * Check if container already has a Glaaster link to avoid duplicates.
     * @param {HTMLElement} container - Container element to check
     * @return {boolean} True if already has Glaaster link
     */
    function hasGlaasterLink(container) {
        return !!(container && container.querySelector('a[data-glaaster-link="true"]'));
    }

    /**
     * Create a Glaaster link element with proper attributes.
     * @param {string} url - Link URL
     * @param {string} title - Link title
     * @param {string} imgClass - CSS class for image
     * @return {HTMLElement} Created link element
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
     * @param {Object} params - URL parameters
     * @return {string} Complete URL
     */
    function buildGlaasterUrl(params) {
        const base = `${M.cfg.wwwroot}/mod/glaaster/view.php`;
        const usp = new URLSearchParams(params);
        return `${base}?${usp.toString()}`;
    }

    /**
     * Extract ID parameter from Moodle URLs.
     * @param {string} href - URL to extract from
     * @return {string|null} Extracted ID or null
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
     * @param {string} href - URL to extract from
     * @return {string|null} Extracted file path or null
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
     * @param {NodeList} fileLinks - File link elements
     * @param {string} folderModuleId - Folder module ID
     * @param {string} translation - Translation string
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
                    l: String(glaasterInstanceId),
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

    // Main function to add Glaaster buttons to supported files.
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof M === 'undefined' || !M.cfg || !M.cfg.wwwroot) {
            warn('Moodle config not available (M.cfg.wwwroot). Aborting.');
            return;
        }

        // Check if webservices are enabled before proceeding.
        // Webservices are required for AJAX validation calls.
        if (typeof glaasterWebservicesEnabled !== 'undefined' && glaasterWebservicesEnabled === false) {
            warn('Moodle web services are not enabled. Cannot use AJAX validation. Aborting.');
            return;
        }

        // Check if Glaaster webservice is properly configured (user, token, external functions).
        if (typeof glaasterWebserviceConfigured !== 'undefined' && glaasterWebserviceConfigured === false) {
            warn('Glaaster webservice not configured. User with email system@glaaster.com, valid token, or external functions missing. Aborting.');
            return;
        }

        const hasInstance = typeof glaasterInstanceId !== 'undefined' && !!glaasterInstanceId;

        if (typeof window.require !== 'function') {
            warn('Moodle AMD loader (require) not found. Aborting.');
            return;
        }

        window.require(['core/str', 'core/ajax'], function (str, ajax) {
            str.get_string('view_document_adaptive', 'mod_glaaster').then(function(translation) {

                if (!hasInstance) {
                    warn('glaasterInstanceId is undefined/empty. Skipping link injection.');
                    return;
                }

                // Validate instance via AJAX before showing buttons.
                // This ensures buttons only appear for valid, non-deleted instances.
                ajax.call([{
                    methodname: 'mod_glaaster_validate_instance',
                    args: {instanceid: parseInt(glaasterInstanceId)},
                }])[0].done(function (response) {
                    if (!response.isvalid) {
                        warn('glaasterInstanceId is not valid (deleted or course removed). Skipping link injection.');
                        return;
                    }

                    // Instance is valid - add buttons and setup deletion watcher.
                    addButtonsToPage(translation, ajax);
                    setupDeletionWatcher(ajax);
                }).fail(function (error) {
                    warn('Failed to validate instance:', error);
                });
            }).catch(function(error) {
                warn('Failed to load translations:', error);
            });
        });
    });

    /**
     * Remove all Glaaster buttons from the page.
     *
     * Finds all buttons by data-glaaster-link attribute and removes them from the DOM.
     * Called when instance validation fails (activity deleted or course removed).
     */
    function removeAllGlaasterButtons() {
        const buttons = document.querySelectorAll('a[data-glaaster-link="true"]');
        buttons.forEach(button => button.remove());
    }

    /**
     * Setup MutationObserver to watch for dynamically loaded content (Tiles format).
     * When new content is added to the DOM, inject Glaaster buttons automatically.
     *
     * @param {string} translation - Translation string for button text
     */
    function setupContentObserver(translation) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                // Check if nodes were added
                if (mutation.addedNodes.length > 0) {
                    for (const node of mutation.addedNodes) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if added node is a resource/folder or contains resources/folders
                            if (node.classList && (
                                node.classList.contains('modtype_resource') ||
                                node.classList.contains('modtype_folder') ||
                                node.classList.contains('modtype_page')
                            )) {
                                // Inject buttons in this specific node
                                injectButtonsInContainer(node.parentElement, translation);
                            } else if (node.querySelector) {
                                // Check if node contains resources/folders
                                const hasActivities = node.querySelector('li.modtype_resource, li.modtype_folder, li.modtype_page');
                                if (hasActivities) {
                                    // Inject buttons in this container
                                    injectButtonsInContainer(node, translation);
                                }
                            }
                        }
                    }
                }
            }
        });

        // Observe course content area for new content being added
        const courseContent = document.querySelector('#region-main, .course-content, main');
        if (courseContent) {
            observer.observe(courseContent, {
                childList: true,  // Watch for added/removed elements
                subtree: true,    // Watch entire subtree
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
     *
     * Detection criteria:
     * - Element has class 'modtype_glaaster'
     * - Element ID contains 'module-'
     * - Element has data-activityname containing 'glaaster'
     *
     * @param {Object} ajax - Moodle AJAX module for making validation calls
     */
    function setupDeletionWatcher(ajax) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.removedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Detect if removed node is a Glaaster activity.
                        if (node.classList && (
                            node.classList.contains('modtype_glaaster') ||
                            node.id && node.id.includes('module-') ||
                            node.matches && node.matches('[data-activityname*="glaaster"]')
                        )) {
                            // Activity removed - revalidate instance via AJAX.
                            ajax.call([{
                                methodname: 'mod_glaaster_validate_instance',
                                args: {instanceid: parseInt(glaasterInstanceId)},
                            }])[0].done(function (response) {
                                if (!response.isvalid) {
                                    removeAllGlaasterButtons();
                                }
                            }).fail(function () {
                                // On validation error, assume invalid.
                                removeAllGlaasterButtons();
                            });
                            return;
                        }
                    }
                }
            }
        });

        // Observe course content area for changes.
        const courseContent = document.querySelector('#region-main, .course-content, main');
        if (courseContent) {
            observer.observe(courseContent, {
                childList: true,  // Watch for added/removed elements
                subtree: true,    // Watch entire subtree, not just direct children
            });
        }
    }

    /**
     * Inject Glaaster buttons for a specific container (or whole page if no container specified).
     * This function can be called multiple times as content is dynamically loaded.
     * Supports both Tiles format and standard Moodle formats.
     * @param {HTMLElement|null} container - Container to search within (null = whole document)
     * @param {string} translation - Translation string
     */
    function injectButtonsInContainer(container, translation) {
        const root = container || document;

        // Handle resource and page modules
        const resources = root.querySelectorAll('li.modtype_resource, li.modtype_page');

        resources.forEach((resource) => {
            try {
                // Check if this is a Tiles format resource
                const isTileFormat = resource.classList.contains('activity') && resource.classList.contains('subtile');

                if (isTileFormat) {
                    // Handle Tiles format
                    // Check if button already exists to avoid duplicates
                    if (hasGlaasterLink(resource)) {
                        return;
                    }

                    // Find the image in tile format
                    const imgElement = resource.querySelector('.tileiconcontainer img, .tile-icon img');
                    if (!imgElement) {
                        return;
                    }

                    // Check if the image indicates a supported file type
                    if (!hasSupportedFileIcon(imgElement.src)) {
                        return;
                    }

                    // Get the module ID from the resource's data attribute (data-cmid for tiles)
                    const moduleId = resource.getAttribute('data-cmid') || resource.getAttribute('data-id');
                    if (!moduleId) {
                        return;
                    }

                    // Build the URL
                    const url = buildGlaasterUrl({
                        l: String(glaasterInstanceId),
                        course_module_id: String(moduleId)
                    });

                    // Create button element positioned at bottom right (like completioncheckbox)
                    const glaasterButton = document.createElement('a');
                    glaasterButton.setAttribute('data-glaaster-link', 'true');
                    glaasterButton.href = url;
                    glaasterButton.title = translation;
                    glaasterButton.innerHTML = `<img src="${M.cfg.wwwroot}/mod/glaaster/pix/icon.svg" ` +
                        `class="iconlarge activityicon" alt="${translation}" role="presentation" ` +
                        `aria-hidden="true" width="24" height="24" style="display: block;">`;

                    // Position at bottom right of the tile
                    glaasterButton.style.position = 'absolute';
                    glaasterButton.style.bottom = '10px';
                    glaasterButton.style.right = '6px';
                    glaasterButton.style.width = '36px';
                    glaasterButton.style.height = '36px';
                    glaasterButton.style.display = 'flex';
                    glaasterButton.style.alignItems = 'center';
                    glaasterButton.style.justifyContent = 'center';
                    glaasterButton.style.zIndex = '10';

                    // Ensure parent tile has relative positioning
                    if (window.getComputedStyle(resource).position === 'static') {
                        resource.style.position = 'relative';
                    }

                    // Append button to the tile element
                    resource.appendChild(glaasterButton);

                } else {
                    // Handle standard format (activity-grid, activity-basis)
                    const activityLink = resource.querySelector('div.activityname a, .activityname .aalink');
                    if (!activityLink) {
                        return;
                    }

                    const href = activityLink.getAttribute('href');
                    const resourceId = extractIdFromHref(href);
                    if (!resourceId) {
                        return;
                    }

                    // Get the activity container for button placement
                    let activityContainer = resource.querySelector('.activity-grid, .activity-basis');
                    if (!activityContainer) {
                        return;
                    }

                    // Check if button already exists to avoid duplicates
                    if (hasGlaasterLink(activityContainer)) {
                        return;
                    }

                    // Check if it's a supported file type by looking at the file icon
                    const img = activityContainer.querySelector('img');
                    if (!img || !hasSupportedFileIcon(img.src)) {
                        return;
                    }

                    // Build the URL
                    const url = buildGlaasterUrl({
                        l: String(glaasterInstanceId),
                        course_module_id: String(resourceId)
                    });

                    // Create the link element with improved styling
                    const glaasterLink = createGlaasterLink(url, translation, 'iconlarge activityicon');
                    glaasterLink.style.alignItems = 'center';
                    glaasterLink.style.display = 'flex';
                    glaasterLink.style.marginLeft = '10px';
                    glaasterLink.style.marginRight = '10px';
                    glaasterLink.style.height = '50px';
                    glaasterLink.style.zIndex = '30';

                    // Insert the new link after the activity-name-area or media-body element
                    const activityNameArea = activityContainer.querySelector('.activity-name-area');
                    const mediaBody = activityContainer.querySelector('.media-body');
                    if (activityNameArea) {
                        activityNameArea.after(glaasterLink);
                    } else if (mediaBody) {
                        mediaBody.after(glaasterLink);
                    } else {
                        // Fallback: prepend if activity-name-area not found
                        activityContainer.prepend(glaasterLink);
                    }
                }
            } catch (e) {
                warn('Failed processing a resource element', e);
            }
        });

        // Handle folder modules
        const folders = root.querySelectorAll('li.modtype_folder');
        folders.forEach((folderLi) => {
            // Try multiple methods to get folder module ID
            // For Tiles format: check data-cmid first
            let folderModuleId = folderLi.getAttribute('data-cmid') || folderLi.getAttribute('data-id');

            if (!folderModuleId) {
                // Fallback: try data-cmid from .activity-grid
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
     * Supports both traditional formats (Topics/Weeks) and Tiles format (including dynamic content).
     * @param {string} translation - Translation string
     * @param {Object} ajax - Moodle AJAX module
     */
    function addButtonsToPage(translation, ajax) {
        // Initial injection for already-visible content
        injectButtonsInContainer(null, translation);

        // Setup observer for dynamically loaded content (Tiles format)
        setupContentObserver(translation);

        // Handle individual folder view pages.
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

})();
