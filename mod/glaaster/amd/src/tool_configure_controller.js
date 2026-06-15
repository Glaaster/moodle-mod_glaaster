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
 * Controls the tool configuration page behaviour.
 *
 * @module     mod_glaaster/tool_configure_controller
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'mod_glaaster/events',
        'mod_glaaster/tool_types_and_proxies', 'core/config'],
    function($, ajax, notification, str, ltiEvents, toolTypesAndProxies, config) {

        var SELECTORS = {
            EXTERNAL_REGISTRATION_CONTAINER: '#external-registration-container',
            EXTERNAL_REGISTRATION_PAGE_CONTAINER: '#external-registration-page-container',
            EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER: '#external-registration-template-container',
            TOOL_STATUS_CONTAINER: '#tool-status-container',
            TOOL_CREATE_BUTTON: '#tool-create-button',
            REGISTRATION_CHOICE_CONTAINER: '#registration-choice-container',
        };

        var getExternalRegistrationContainer = function() {
            return $(SELECTORS.EXTERNAL_REGISTRATION_CONTAINER);
        };

        var getRegistrationChoiceContainer = function() {
            return $(SELECTORS.REGISTRATION_CHOICE_CONTAINER);
        };

        var getToolStatusContainer = function() {
            return $(SELECTORS.TOOL_STATUS_CONTAINER);
        };

        var closeLTIAdvRegistration = function(e) {
            if (e.data && 'org.imsglobal.lti.close' === e.data.subject) {
                $(SELECTORS.EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER).empty();
                hideExternalRegistration();
                showRegistrationChoices();
                reloadToolStatus();
            }
        };

        var initiateRegistration = function(url) {
            $(SELECTORS.EXTERNAL_REGISTRATION_PAGE_CONTAINER).removeClass('hidden');
            var container = $(SELECTORS.EXTERNAL_REGISTRATION_TEMPLATE_CONTAINER);
            container.append($('<iframe src="' + config.wwwroot + '/mod/glaaster/startltiadvregistration.php?url='
                + encodeURIComponent(url) + '&sesskey=' + config.sesskey + '"></iframe>'));
            showExternalRegistration();
            window.addEventListener("message", closeLTIAdvRegistration, false);
        };

        var hideExternalRegistration = function() {
            getExternalRegistrationContainer().addClass('hidden');
        };

        var hideRegistrationChoices = function() {
            getRegistrationChoiceContainer().addClass('hidden');
        };

        var showExternalRegistration = function() {
            hideRegistrationChoices();
            getExternalRegistrationContainer().removeClass('hidden');
            screenReaderAnnounce(getExternalRegistrationContainer());
        };

        var showRegistrationChoices = function() {
            hideExternalRegistration();
            getRegistrationChoiceContainer().removeClass('hidden');
            screenReaderAnnounce(getRegistrationChoiceContainer());
        };

        var screenReaderAnnounce = function(element) {
            var children = element.children().detach();
            children.appendTo(element);
        };

        var showRegistrationFeedback = function(data) {
            var type = data.error ? 'error' : 'success';
            notification.addNotification({message: data.message, type: type});
        };

        /**
         * Render a minimal inline tool status row: status pill + edit + delete buttons.
         *
         * @param {Object} tool Tool type data object from the webservice.
         */
        var renderToolStatus = function(tool) {
            var container = getToolStatusContainer();
            container.empty();

            if (!tool) {
                return;
            }

            var element = $('<div class="tool-status-row d-flex align-items-center gap-1"></div>');
            element.attr('data-type-id', tool.id);
            element.attr('data-platformid', tool.platformid || '');
            element.attr('data-clientid', tool.clientid || '');
            element.attr('data-deploymentid', tool.deploymentid || '');
            element.attr('data-statusurl', tool.statusurl || '');

            // Status pill with animated dot — class updated by checkConnectionStatus.
            var badge = $(
                '<span class="glaaster-status-pill status-pending tool-connection-status">' +
                '<span class="status-dot"></span>' +
                '<span class="status-text"></span>' +
                '</span>'
            );
            element.append(badge);

            // Edit button.
            if (tool.urls && tool.urls.edit) {
                var editBtn = $('<a class="glaaster-tool-action glaaster-tool-action-edit" title="Edit"></a>');
                editBtn.attr('href', tool.urls.edit);
                editBtn.append($('<span class="fa fa-pencil" aria-hidden="true"></span>'));
                element.append(editBtn);
            }

            // Delete button.
            var deleteBtn = $(
                '<button type="button" class="glaaster-tool-action glaaster-tool-action-delete"'+
                'title="Delete"></button>'
            );
            deleteBtn.append($('<span class="fa fa-trash" aria-hidden="true"></span>'));
            deleteBtn.on('click', function() {
                deleteTool(tool.id, element);
            });
            element.append(deleteBtn);

            container.append(element);
            checkConnectionStatus(element, tool);
        };

        /**
         * Check tool connection status and update the badge.
         *
         * @param {Object} element jQuery element containing the tool row.
         * @param {Object} tool Tool data object.
         */
        var checkConnectionStatus = function(element, tool) {
            if (!tool.statusurl || !tool.clientid || !tool.platformid) {
                element.find('.tool-connection-status').addClass('d-none');
                return;
            }

            str.get_strings([
                {key: 'connect_status_pending', component: 'mod_glaaster'},
                {key: 'connect_status_validated', component: 'mod_glaaster'},
                {key: 'connect_status_error', component: 'mod_glaaster'},
                {key: 'connect_status_api_pending', component: 'mod_glaaster'},
            ]).then(function(strings) {
                var pill = element.find('.tool-connection-status');
                pill.find('.status-text').text(strings[0]);

                ajax.call([{
                    methodname: 'mod_glaaster_check_tool_status',
                    args: {statusurl: tool.statusurl, iss: tool.platformid, client_id: tool.clientid}
                }])[0]
                    .then(function(result) {
                        pill.removeClass('status-pending status-validated status-error');
                        if (result.active === true) {
                            pill.addClass('status-validated').find('.status-text').text(strings[1]);
                            // Enable email notification button (step 5).
                            var notifyBtn = document.getElementById('apistep5-notify-btn');
                            if (notifyBtn) {
                                notifyBtn.classList.remove('disabled');
                                notifyBtn.removeAttribute('aria-disabled');
                                notifyBtn.removeAttribute('tabindex');
                            }
                            // Collapse the full setup card.
                            var collapseEl = document.getElementById('setup-collapse');
                            if (collapseEl && typeof window.bootstrap !== 'undefined' && window.bootstrap.Collapse) {
                                window.bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
                            }
                        } else if (result.status === 'PENDING' || result.status === '') {
                            pill.addClass('status-pending').find('.status-text').text(strings[3]);
                        } else {
                            pill.addClass('status-error').find('.status-text').text(strings[2]);
                        }
                        return result;
                    })
                    .catch(function() {
                        pill.removeClass('status-pending status-validated')
                            .addClass('status-error')
                            .find('.status-text').text(strings[2]);
                    });

                return strings;
            }).catch(function() {
                element.find('.tool-connection-status').addClass('d-none');
            });
        };

        /**
         * Delete a tool type with confirmation.
         *
         * @param {number} typeId Tool type ID.
         * @param {Object} element jQuery element to remove on success.
         */
        var deleteTool = function(typeId, element) {
            str.get_strings([
                {key: 'delete', component: 'mod_glaaster'},
                {key: 'delete_confirmation', component: 'mod_glaaster'},
                {key: 'delete', component: 'mod_glaaster'},
                {key: 'cancel', component: 'core'},
            ]).done(function(strs) {
                notification.confirm(strs[0], strs[1], strs[2], strs[3], function() {
                    ajax.call([{
                        methodname: 'mod_glaaster_delete_tool_type',
                        args: {id: typeId}
                    }])[0]
                        .then(function() {
                            element.remove();
                            // Re-enable the connect button since there are no more tools.
                            $(SELECTORS.TOOL_CREATE_BUTTON).prop('disabled', false).removeAttr('disabled');
                            return;
                        })
                        .catch(notification.exception);
                });
            }).fail(notification.exception);
        };

        /**
         * Fetch the first orphaned tool type and render its inline status row.
         */
        var reloadToolStatus = function() {
            M.util.js_pending('reloadToolStatus');

            toolTypesAndProxies.query({orphanedonly: true, limit: 1, offset: 0})
                .then(function(data) {
                    var tool = (data.types && data.types.length > 0) ? data.types[0] : null;

                    if (tool) {
                        $(SELECTORS.TOOL_CREATE_BUTTON).prop('disabled', true).attr('disabled', 'disabled');
                    } else {
                        $(SELECTORS.TOOL_CREATE_BUTTON).prop('disabled', false).removeAttr('disabled');
                    }

                    renderToolStatus(tool);
                    return data;
                })
                .catch(function(error) {
                    notification.exception(error);
                })
                .always(function() {
                    M.util.js_complete('reloadToolStatus');
                });
        };

        var registerEventListeners = function() {
            $(document).on(ltiEvents.NEW_TOOL_TYPE, function() {
                reloadToolStatus();
            });

            $(document).on(ltiEvents.STOP_EXTERNAL_REGISTRATION, function() {
                showRegistrationChoices();
            });

            $(document).on(ltiEvents.REGISTRATION_FEEDBACK, function(event, data) {
                showRegistrationFeedback(data);
            });

            $(SELECTORS.TOOL_CREATE_BUTTON).click(function(e) {
                e.preventDefault();
                var url = $(this).data('registerurl');
                var token = $(this).data('apitoken');
                if (token) {
                    var separator = url.indexOf('?') !== -1 ? '&' : '?';
                    url = url + separator + 'token=' + encodeURIComponent(token);
                }
                initiateRegistration(url);
            });
        };

        return /** @alias module:mod_glaaster/tool_configure_controller */ {
            init: function() {
                registerEventListeners();
                reloadToolStatus();
            }
        };
    });
