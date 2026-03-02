/**
 * This script modifies the layout of the resource list in the course page.
 * It adds a link to view the document in an adapted way.
 * The script is executed only if the page is not in editing mode for all moodle version 3 but not 4.
 * @author     Jordan Labrosse
 * @copyright  2025 Jordan Labrosse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require(['core/str', 'core/ajax'], function (str, ajax) {

    /**
     * Debug logging helper for Moodle 3.x.
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

    /**
     * Remove all Glaaster buttons from the page.
     *
     * Finds buttons by URL pattern and icon image, then removes from DOM.
     * Called when instance validation fails.
     */
    function removeAllGlaasterButtons() {
        const links = document.querySelectorAll('a[href*="mod/glaaster/view.php"]');
        links.forEach(link => {
            if (link.querySelector('img[src*="mod/glaaster/pix/icon"]')) {
                link.remove();
            }
        });
    }

    /**
     * Setup MutationObserver for real-time deletion detection (Moodle 3 version).
     *
     * Monitors DOM for Glaaster activity removals and triggers AJAX revalidation.
     * Provides instant button removal without page refresh.
     */
    function setupDeletionWatcher() {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.removedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList && (
                            node.classList.contains('modtype_glaaster') ||
                            node.id && node.id.includes('module-')
                        )) {
                            // Activity removed - revalidate via AJAX.
                            ajax.call([{
                                methodname: 'mod_glaaster_validate_instance',
                                args: {instanceid: parseInt(glaasterInstanceId)},
                            }])[0].done(function (response) {
                                if (!response.isvalid) {
                                    removeAllGlaasterButtons();
                                }
                            }).fail(function () {
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

    str.get_string('view_document_adaptive', 'mod_glaaster').then(function (translation) {
// Only execute the script if the page is not in editing mode
        if (!document.body.classList.contains("editing")) {
            if (glaasterInstanceId) {
                // Validate instance via AJAX before showing buttons.
                // Ensures buttons only appear for valid, non-deleted instances.
                ajax.call([{
                    methodname: 'mod_glaaster_validate_instance',
                    args: {instanceid: parseInt(glaasterInstanceId)},
                }])[0].done(function (response) {
                    if (!response.isvalid) {
                        return;
                    }

                    // Instance valid - proceed with adding buttons.
// Proceed only if there are elements with the "modtype_resource" class
                    if (document.getElementsByClassName("modtype_resource").length > 0) {

                        // Iterate over each element with the "modtype_resource" class
                        Array.from(document.getElementsByClassName("modtype_resource")).forEach(element => {
                            // Skip this element if it does not contain an element with the "mod-indent" class
                            if (element.querySelector(".mod-indent") === null) {
                                return;
                            }

                            // Skip if the expected image element is not found in ".activityinstance > a > img"
                            if (element.querySelector(".activityinstance > a > img") === null) {
                                return;
                            }

                            // Remove the "w-100" class from the ".mod-indent-outer" container for layout adjustments
                            const modIndentOuter = element.querySelector('.mod-indent-outer');
                            modIndentOuter.classList.remove('w-100');

                            // Get the source URL of the image from the specified selector
                            const imgSrc = element.querySelector(".activityinstance > a > img").src;

                            // Process only if the image source indicates a PDF, JPEG, or PNG file
                            if (imgSrc.includes("pdf") || imgSrc.includes("jpeg") || imgSrc.includes("png")) {
                                // Get the module id by removing the "module-" prefix from the element's id attribute
                                const module = element.attributes.id.value.replace("module-", "");

                                // Create a link element to view the document in an adapted way
                                const linkGlaaster = document.createElement("a");
                                linkGlaaster.href = `${M.cfg.wwwroot}/mod/glaaster/view.php?l=${glaasterInstanceId}&course_module_id=${module}`;
                                linkGlaaster.title = translation;
                                linkGlaaster.innerHTML = `<img src="${M.cfg.wwwroot}/mod/glaaster/pix/icon.png" class="iconlarge activityicon activityglaaster"
alt="${translation}" role="presentation" aria-hidden="true">`;

                                // Prepend the new link into the ".mod-indent" container, preserving the original HTML structure
                                element.querySelector(".mod-indent").prepend(linkGlaaster);
                            }
                        });
                    }

                    // Setup watcher to detect when Glaaster activity is deleted.
                    setupDeletionWatcher();

                }).fail(function (error) {
                    // Failed to validate instance, skip link injection.
                });
            }
        }
    });
});
