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
 * Standard Ajax wrapper for Moodle. It calls the central Ajax script,
 * which can call any existing webservice using the current session.
 * In addition, it can batch multiple requests and return multiple responses.
 *
 * @module     mod_glaaster/tool_configure_controller
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define(['jquery', 'core/notification', 'core/templates',
        'mod_glaaster/events',
        'mod_glaaster/tool_types_and_proxies',
        'core/config'],
    function ($,
              notification, templates, ltiEvents,
              toolTypesAndProxies, config) {

        var SELECTORS = {
            EXTERNAL_REGISTRATION_CONTAINER: '#external-registration-container',
            EXTERNAL_REGISTRATION_PAGE_CONTAINER: '#external-registration-page-container',
            EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER: '#external-registration-template-container',
            TOOL_CARD_CONTAINER: '#tool-card-container',
            TOOL_LIST_CONTAINER: '#tool-list-container',
            TOOL_CREATE_BUTTON: '#tool-create-button',
            REGISTRATION_CHOICE_CONTAINER: '#registration-choice-container',
        };

        /**
         * Get the tool list container element.
         *
         * @method getToolListContainer
         * @private
         * @return {Object} jQuery object
         */
        var getToolListContainer = function () {
            return $(SELECTORS.TOOL_LIST_CONTAINER);
        };

        /**
         * Get the tool card container element.
         *
         * @method getToolCardContainer
         * @private
         * @return {Object} jQuery object
         */
        const getToolCardContainer = function () {
            return $(SELECTORS.TOOL_CARD_CONTAINER);
        };

        /**
         * Get the external registration container element.
         *
         * @method getExternalRegistrationContainer
         * @private
         * @return {Object} jQuery object
         */
        var getExternalRegistrationContainer = function () {
            return $(SELECTORS.EXTERNAL_REGISTRATION_CONTAINER);
        };

        /**
         * Get the registration choice container element.
         *
         * @method getRegistrationChoiceContainer
         * @private
         * @return {Object} jQuery object
         */
        var getRegistrationChoiceContainer = function () {
            return $(SELECTORS.REGISTRATION_CHOICE_CONTAINER);
        };

        /**
         * Close the LTI Advantage Registration IFrame.
         *
         * @private
         * @param {Object} e post message event sent from the registration frame.
         */
        var closeLTIAdvRegistration = function (e) {
            if (e.data && 'org.imsglobal.lti.close' === e.data.subject) {
                $(SELECTORS.EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER).empty();
                hideExternalRegistration();
                showRegistrationChoices();
                showToolList();
                showRegistrationChoices();
                reloadToolList();
            }
        };

        /**
         * Load the external registration template and render it in the DOM and display it.
         *
         * @method initiateRegistration
         * @private
         * @param {String} url where to send the registration request
         */
        var initiateRegistration = function (url) {
            // Show the external registration page in an iframe.
            $(SELECTORS.EXTERNAL_REGISTRATION_PAGE_CONTAINER).removeClass('hidden');
            var container = $(SELECTORS.EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER);
            container.append($('<iframe src="' + config.wwwroot + '/mod/glaaster/startltiadvregistration.php?url='
                + encodeURIComponent(url) + '&sesskey=' + config.sesskey + '"></iframe>'));
            showExternalRegistration();
            window.addEventListener("message", closeLTIAdvRegistration, false);
        };

        /**
         * Hide the external registration container.
         *
         * @method hideExternalRegistration
         * @private
         */
        var hideExternalRegistration = function () {
            getExternalRegistrationContainer().addClass('hidden');
        };

        /**
         * Hide the registration choice container.
         *
         * @method hideRegistrationChoices
         * @private
         */
        var hideRegistrationChoices = function () {
            getRegistrationChoiceContainer().addClass('hidden');
        };

        /**
         * Display the external registration panel and hides the other panels.
         *
         * @method showExternalRegistration
         * @private
         */
        var showExternalRegistration = function () {
            hideRegistrationChoices();
            getExternalRegistrationContainer().removeClass('hidden');
            screenReaderAnnounce(getExternalRegistrationContainer());
        };

        /**
         * Display the registration choices panel and hides the other panels.
         *
         * @method showRegistrationChoices
         * @private
         */
        var showRegistrationChoices = function () {
            hideExternalRegistration();
            getRegistrationChoiceContainer().removeClass('hidden');
            screenReaderAnnounce(getRegistrationChoiceContainer());
        };

        /**
         * JAWS does not notice visibility changes with aria-live.
         * Remove and add the content back to force it to read it out.
         * This function can be removed once JAWS supports visibility.
         *
         * @method screenReaderAnnounce
         * @param {Object} element
         * @private
         */
        var screenReaderAnnounce = function (element) {
            var children = element.children().detach();
            children.appendTo(element);
        };

        /**
         * Hides the list of tool types.
         *
         * @method hideToolList
         * @private
         */
        var hideToolList = function () {
            getToolListContainer().addClass('hidden');
        };

        /**
         * Display the list of tool types.
         *
         * @method hideToolList
         * @private
         */
        var showToolList = function () {
            getToolListContainer().removeClass('hidden');
        };

        /**
         * Display the registration feedback alert and hide the other panels.
         *
         * @method showRegistrationFeedback
         * @param {Object} data
         * @private
         */
        var showRegistrationFeedback = function (data) {
            var type = data.error ? 'error' : 'success';
            notification.addNotification({
                message: data.message,
                type: type
            });
        };

        /**
         * Show the loading animation
         *
         * @method startLoading
         * @private
         * @param {Object} element jQuery object
         */
        var startLoading = function (element) {
            element.addClass("loading");
        };

        /**
         * Hide the loading animation
         *
         * @method stopLoading
         * @private
         * @param {Object} element jQuery object
         */
        var stopLoading = function (element) {
            element.removeClass("loading");
        };

        /**
         * Refresh the list of tool types and render the new ones.
         *
         * @method reloadToolList
         * @private
         */
        var reloadToolList = function () {
            // Behat tests should wait for the tool list to load.
            M.util.js_pending('reloadToolList');

            const cardContainer = getToolCardContainer();
            const listContainer = getToolListContainer();
            startLoading(listContainer);

            fetchToolData(1, 0)
                .then(function (data) {
                    // If a tool already exists, disable the create button.
                    const hasTools = data.types.length > 0 || data.proxies.length > 0;
                    if (hasTools) {
                        $(SELECTORS.TOOL_CREATE_BUTTON).prop('disabled', true).attr('disabled', 'disabled');
                    } else {
                        $(SELECTORS.TOOL_CREATE_BUTTON).prop('disabled', false).removeAttr('disabled');
                    }
                    return renderToolData(data);
                })
                .then(function (html, js) {
                    templates.replaceNodeContents(cardContainer, html, js);
                })
                .always(function () {
                    stopLoading(listContainer);
                    M.util.js_complete('reloadToolList');
                });
        };

        /**
         * Fetch the data for tool type and proxy cards.
         *
         * @param {number} limit Maximum number of datasets to get.
         * @param {number} offset Offset count for fetching the data.
         * @return {*|void}
         */
        const fetchToolData = function (limit, offset) {
            const args = {'orphanedonly': true};
            // Only add limit and offset to args if they are integers and not null, otherwise defaults will be used.
            if (limit !== null && !Number.isNaN(limit)) {
                args.limit = limit;
            }
            if (offset !== null && !Number.isNaN(offset)) {
                args.offset = offset;
            }
            return toolTypesAndProxies.query(args)
                .done(function (data) {
                    return data;
                }).catch(function (error) {
                    // Add debug message, then return empty data.
                    notification.exception(error);
                    return {
                        'types': [],
                        'proxies': [],
                        'limit': limit,
                        'offset': offset
                    };
                });
        };

        /**
         * Render Tool and Proxy cards from data.
         *
         * @param {Object} data Contains arrays of data objects to populate cards.
         * @return {*}
         */
        const renderToolData = function (data) {
            const context = {
                tools: data.types,
                proxies: data.proxies,
            };
            return templates.render('mod_glaaster/tool_list', context)
                .done(function (html, js) {
                        return {html, js};
                    }
                );
        };

        /**
         * Sets up the listeners for user interaction on the page.
         *
         * @method registerEventListeners
         * @private
         */
        var registerEventListeners = function () {

            $(document).on(ltiEvents.NEW_TOOL_TYPE, function () {
                reloadToolList();
            });

            $(document).on(ltiEvents.STOP_EXTERNAL_REGISTRATION, function () {
                showToolList();
                showRegistrationChoices();
            });

            $(document).on(ltiEvents.REGISTRATION_FEEDBACK, function (event, data) {
                showRegistrationFeedback(data);
            });

            $(SELECTORS.TOOL_CREATE_BUTTON).click(function (e) {
                e.preventDefault();
                var url = $(this).data('registerurl');
                var token = $(this).data('apitoken');
                if (token) {
                    var separator = url.indexOf('?') !== -1 ? '&' : '?';
                    url = url + separator + 'token=' + encodeURIComponent(token);
                }
                hideToolList();
                initiateRegistration(url);
            });

        };

        return /** @alias module:mod_glaaster/cartridge_registration_form */ {

            /**
             * Initialise this module.
             */
            init: function () {
                registerEventListeners();
                reloadToolList();
            }
        };
    });
