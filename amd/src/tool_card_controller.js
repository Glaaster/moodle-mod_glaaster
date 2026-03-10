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
 * Controls all of the behaviour and interaction with a tool type card. These are
 * listed on the LTI tool type management page.
 *
 * See template: mod_glaaster/tool_card
 *
 * @module     mod_glaaster/tool_card_controller
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/modal',
        'mod_glaaster/tool_type', 'mod_glaaster/keys',
        'core/str'],
    function ($, ajax, notification, templates, Modal, toolType, KEYS, str) {

        var SELECTORS = {
            NAME_ELEMENT: '.name',
            DELETE_BUTTON: '.delete',
        };

        /**
         * Return the element representing the tool type name.
         *
         * @method getNameElement
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         * @return {JQuery} jQuery object
         */
        var getNameElement = function (element) {
            return element.find(SELECTORS.NAME_ELEMENT);
        };

        /**
         * Get the type id.
         *
         * @method getTypeId
         * @private
         * @param {Object} element jQuery object representing the tool card.
         * @return {String} Type ID
         */
        var getTypeId = function (element) {
            return element.attr('data-type-id');
        };

        /**
         * Save a given value in a data attribute on the element.
         *
         * @method setValueSnapshot
         * @private
         * @param {JQuery} element jQuery object representing the element.
         * @param {String} value to be saved.
         */
        var setValueSnapshot = function (element, value) {
            element.attr('data-val-snapshot', value);
        };

        /**
         * Return the saved value from the element.
         *
         * @method getValueSnapshot
         * @private
         * @param {JQuery} element jQuery object representing the element.
         * @return {String} the saved value.
         */
        var getValueSnapshot = function (element) {
            return element.attr('data-val-snapshot');
        };

        /**
         * Save the current value of the tool name.
         *
         * @method snapshotName
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         */
        var snapshotName = function (element) {
            var nameElement = getNameElement(element);

            if (nameElement.hasClass('loading')) {
                return;
            }

            var name = nameElement.text().trim();
            setValueSnapshot(nameElement, name);
        };

        /**
         * Send a request to update the name value for this tool in the Moodle server.
         *
         * @method updateName
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         * @return {Promise} jQuery Deferred object
         */
        var updateName = function (element) {
            var typeId = getTypeId(element);

            if (typeId === "") {
                return $.Deferred().resolve();
            }

            var nameElement = getNameElement(element);

            if (nameElement.hasClass('loading')) {
                return $.Deferred().resolve();
            }

            var name = nameElement.text().trim();
            var snapshotVal = getValueSnapshot(nameElement);

            if (snapshotVal == name) {
                return $.Deferred().resolve();
            }

            nameElement.addClass('loading');
            var promise = toolType.update({id: typeId, name: name});

            promise.done(function (type) {
                nameElement.removeClass('loading');
                nameElement.text(type.name);
            });

            promise.fail(function () {
                nameElement.removeClass('loading');
            });

            return promise;
        };

        /**
         * Delete the tool type from the Moodle server with confirmation.
         *
         * @method deleteType
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         */
        var deleteType = function (element) {
            var typeId = getTypeId(element);

            if (!typeId) {
                return;
            }

            str.get_strings([
                {key: 'delete', component: 'mod_glaaster'},
                {key: 'delete_confirmation', component: 'mod_glaaster'},
                {key: 'delete', component: 'mod_glaaster'},
                {key: 'cancel', component: 'core'},
            ]).done(function (strs) {
                notification.confirm(strs[0], strs[1], strs[2], strs[3], function () {
                    toolType.delete(typeId)
                        .done(function () {
                            element.remove();
                        })
                        .fail(notification.exception);
                });
            }).fail(notification.exception);
        };

        /**
         * Sets up the listeners for user interaction on this tool type card.
         *
         * @method registerEventListeners
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         */
        var registerEventListeners = function (element) {
            var nameElement = getNameElement(element);

            nameElement.focus(function (e) {
                e.preventDefault();
                snapshotName(element);
            });
            nameElement.blur(function (e) {
                e.preventDefault();
                updateName(element);
            });
            nameElement.keypress(function (e) {
                if (!e.metaKey && !e.shiftKey && !e.altKey && !e.ctrlKey) {
                    if (e.keyCode == KEYS.ENTER) {
                        e.preventDefault();
                        nameElement.blur();
                    }
                }
            });

            element.find(SELECTORS.DELETE_BUTTON).click(function (e) {
                e.preventDefault();
                deleteType(element);
            });
        };

        /**
         * Fetch and display the connection status from the tool's /status endpoint.
         *
         * @method checkConnectionStatus
         * @private
         * @param {JQuery} element jQuery object representing the tool card.
         */
        var checkConnectionStatus = function (element) {
            var statusUrl = element.data('statusurl');
            var clientId = element.data('clientid');
            var platformId = element.data('platformid');

            if (!statusUrl || !clientId || !platformId) {
                return;
            }

            var statusEl = element.find('.tool-connection-status');

            str.get_strings([
                {key: 'connect_status_pending', component: 'mod_glaaster'},
                {key: 'connect_status_validated', component: 'mod_glaaster'},
                {key: 'connect_status_error', component: 'mod_glaaster'},
                {key: 'connect_status_api_pending', component: 'mod_glaaster'},
            ]).then(function(strings) {
                var loadingStr = strings[0];
                var validatedStr = strings[1];
                var errorStr = strings[2];
                var apiPendingStr = strings[3];

                statusEl.removeClass('d-none bg-warning bg-success bg-danger text-dark text-white')
                        .addClass('bg-warning text-dark')
                        .text(loadingStr)
                        .removeClass('d-none');

                ajax.call([{
                    methodname: 'mod_glaaster_check_tool_status',
                    args: {
                        statusurl: statusUrl,
                        iss: platformId,
                        client_id: clientId
                    }
                }])[0]
                    .then(function(result) {
                        statusEl.removeClass('bg-warning bg-success bg-danger text-dark text-white');
                        if (result.active === true) {
                            statusEl.addClass('bg-success text-white').text(validatedStr);
                        } else if (result.status === 'PENDING' || result.status === '') {
                            statusEl.addClass('bg-warning text-dark').text(apiPendingStr);
                        } else {
                            statusEl.addClass('bg-danger text-white').text(errorStr);
                        }
                        return result;
                    })
                    .catch(function() {
                        statusEl.removeClass('bg-warning bg-success text-dark')
                                .addClass('bg-danger text-white')
                                .text(errorStr);
                    });

                return strings;
            }).catch(function() {
                // Strings unavailable, status badge remains hidden.
            });
        };

        var registerModal = function (element) {
            const configurationLink = element.find('#' + element.data('uniqid') + '-' + element.data('deploymentid'));
            if (!configurationLink.length) {
                return;
            }
            const trigger = configurationLink.get(0);
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                var context = {
                    'uniqid': element.data('uniqid'),
                    'platformid': element.data('platformid'),
                    'clientid': element.data('clientid'),
                    'deploymentid': element.data('deploymentid'),
                    'urls': {
                        'publickeyset': element.data('publickeyseturl'),
                        'accesstoken': element.data('accesstokenurl'),
                        'authrequest': element.data('authrequesturl')
                    }
                };
                var bodyPromise = templates.render('mod_glaaster/tool_config_modal_body', context);
                var mailTo = 'mailto:?subject=' + encodeURIComponent(element.data('mailtosubject')) +
                    '&body=' + encodeURIComponent(element.data('platformidstr')) + ':%20' +
                    encodeURIComponent(element.data('platformid')) + '%0D%0A' +
                    encodeURIComponent(element.data('clientidstr')) + ':%20' +
                    encodeURIComponent(element.data('clientid')) + '%0D%0A' +
                    encodeURIComponent(element.data('deploymentidstr')) + ':%20' +
                    encodeURIComponent(element.data('deploymentid')) + '%0D%0A' +
                    encodeURIComponent(element.data('publickeyseturlstr')) + ':%20' +
                    encodeURIComponent(element.data('publickeyseturl')) + '%0D%0A' +
                    encodeURIComponent(element.data('accesstokenurlstr')) + ':%20' +
                    encodeURIComponent(element.data('accesstokenurl')) + '%0D%0A' +
                    encodeURIComponent(element.data('authrequesturlstr')) + ':%20' +
                    encodeURIComponent(element.data('authrequesturl')) + '%0D%0A';
                context = {
                    'mailto': mailTo
                };
                var footerPromise = templates.render('mod_glaaster/tool_config_modal_footer', context);
                Modal.create({
                    large: true,
                    title: element.data('modaltitle'),
                    body: bodyPromise,
                    footer: footerPromise,
                    show: true
                });
            });
        };

        return /** @alias module:mod_glaaster/tool_card_controller */ {

            /**
             * Initialise this module.
             *
             * @param {JQuery} element jQuery object representing the tool card.
             */
            init: function (element) {
                registerEventListeners(element);
                registerModal(element);
                checkConnectionStatus(element);
            }
        };
    });
