(function () {
        var app = document.getElementById('btchat-app');
        if (!app || !window.fetch) {
            return;
        }

        var chatBaseUrl = app.dataset.chatBaseUrl || 'apps-chat.php';
        var listEl = document.getElementById('btchat-list');
        var threadEl = document.getElementById('btchat-thread');
        var headerEl = document.getElementById('btchat-chat-header');
        var formEl = document.getElementById('btchat-compose-form');
        var inputEl = document.getElementById('btchat-message-input');
        var replyToInputEl = document.getElementById('chat-reply-to-message-id');
        var replyPreviewEl = document.getElementById('chat-reply-preview');
        var replyLabelEl = document.getElementById('chat-reply-label');
        var replyRemoveEl = document.getElementById('chat-reply-remove');
        var sendBtnEl = document.getElementById('btchat-send-btn');
        var alertEl = document.getElementById('btchat-alert');
        var composeWarningEl = document.getElementById('btchat-compose-warning');
        var composeWarningTextEl = document.getElementById('btchat-compose-warning-text');
        var searchEl = document.getElementById('btchat-search');
        var confirmModalEl = document.getElementById('chat-confirm-modal');
        var confirmTitleEl = document.getElementById('chat-confirm-title');
        var confirmTextEl = document.getElementById('chat-confirm-text');
        var confirmOkEl = document.getElementById('chat-confirm-ok');
        var confirmCancelEl = document.getElementById('chat-confirm-cancel');
        var contactModalEl = document.getElementById('chat-contact-modal');
        var contactCloseEl = document.getElementById('chat-contact-close');
        var contactAvatarHostEl = document.getElementById('chat-contact-avatar-host');
        var contactNameEl = document.getElementById('chat-contact-name');
        var contactSubEl = document.getElementById('chat-contact-sub');
        var contactUserIdEl = document.getElementById('chat-contact-user-id');
        var contactUsernameEl = document.getElementById('chat-contact-username');
        var contactEmailEl = document.getElementById('chat-contact-email');
        var contactStatusEl = document.getElementById('chat-contact-status');
        var contactMutedStateEl = document.getElementById('chat-contact-muted-state');
        var contactLastActiveEl = document.getElementById('chat-contact-last-active');
        var contactLastMessageEl = document.getElementById('chat-contact-last-message');
        var contactUnreadEl = document.getElementById('chat-contact-unread');
        var contactTotalEl = document.getElementById('chat-contact-total');
        var contactReportableEl = document.getElementById('chat-contact-reportable');
        var contactMuteEl = document.getElementById('chat-contact-mute');
        var contactReportUserEl = document.getElementById('chat-contact-report-user');
        var contactCloseSecondaryEl = document.getElementById('chat-contact-close-secondary');
        var reportModalEl = document.getElementById('chat-report-modal');
        var reportReasonEl = document.getElementById('chat-report-reason');
        var reportNoteEl = document.getElementById('chat-report-note');
        var reportOkEl = document.getElementById('chat-report-ok');
        var reportCancelEl = document.getElementById('chat-report-cancel');
        var messageActionMenuEl = document.getElementById('msg-action-menu');
        var reactionsModalEl = document.getElementById('chat-reactions-modal');
        var reactionsTabsEl = document.getElementById('chat-reactions-tabs');
        var reactionsListEl = document.getElementById('chat-reactions-list');
        var reactionsCloseEl = document.getElementById('chat-reactions-close');
        var mediaModalEl = document.getElementById('chat-media-modal');
        var mediaModalBodyEl = document.getElementById('chat-media-body');
        var mediaModalTitleEl = document.getElementById('chat-media-title');
        var mediaModalCloseEl = document.getElementById('chat-media-close');
        var mediaModalDownloadEl = document.getElementById('chat-media-download');
        var currentUserId = parseInt(app.dataset.currentUserId || '0', 10) || 0;
        var selectedUserId = parseInt(app.getAttribute('data-selected-user-id') || '0', 10) || 0;
        var selectedContactRef = null;
        var messageCache = {};
        var replyTarget = null;
        var pendingConfirmFn = null;
        var pendingReportFn = null;
        var activeMessageActionId = 0;
        var activeMessageActionTriggerEl = null;
        var pollHandle = null;
        var currentSearch = '';
        var reactionsModalState = null;
        var mediaModalState = null;
        var fetchAbortController = null;
        var stateRequestToken = 0;
        var lastContactsSignature = '';
        var lastContactsData = [];
        var lastHeaderSignature = '';
        var lastMessagesSignature = '';
        var lastRenderedUserId = 0;
        var contactModalUserId = 0;
        var suppressHeaderToggleUntil = 0;
        var mobileLayoutQuery = (typeof window.matchMedia === 'function') ? window.matchMedia('(max-width: 991px)') : null;

        // Render media viewer at document level so it is never clipped by app containers.
        if (mediaModalEl && document.body && mediaModalEl.parentNode !== document.body) {
            document.body.appendChild(mediaModalEl);
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function nl2br(value) {
            return escapeHtml(value).replace(/\n/g, '<br>');
        }

        function scrubActionTooltips(rootEl) {
            var scope = rootEl || document;
            var buttons = scope.querySelectorAll('.btchat-menu-toggle, .msg-hover-menu-btn');

            buttons.forEach(function (btn) {
                btn.removeAttribute('title');
                btn.removeAttribute('data-bs-toggle');
                btn.removeAttribute('data-bs-original-title');
                btn.removeAttribute('data-original-title');
                btn.removeAttribute('aria-describedby');

                if (window.bootstrap && window.bootstrap.Tooltip && typeof window.bootstrap.Tooltip.getInstance === 'function') {
                    var tip = window.bootstrap.Tooltip.getInstance(btn);
                    if (tip && typeof tip.dispose === 'function') {
                        tip.dispose();
                    }
                }
            });

            document.querySelectorAll('.tooltip').forEach(function (tipEl) {
                var tipId = tipEl.getAttribute('id');
                if (!tipId) {
                    tipEl.remove();
                    return;
                }

                var triggerEl = document.querySelector('[aria-describedby="' + tipId + '"]');
                if (!triggerEl || triggerEl.matches('.btchat-menu-toggle, .msg-hover-menu-btn')) {
                    tipEl.remove();
                }
            });
        }

        function parseJsonResponse(response) {
            return response.text().then(function (text) {
                var payload = null;
                try {
                    payload = text ? JSON.parse(text) : null;
                } catch (error) {
                    payload = null;
                }
                return {
                    ok: response.ok,
                    payload: payload,
                    text: text
                };
            });
        }

        function postChatAction(formData, userId) {
            return fetch(chatBaseUrl + '?user_id=' + encodeURIComponent(userId), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            }).then(parseJsonResponse);
        }

        function buildContactsSignature(contacts) {
            var items = Array.isArray(contacts) ? contacts : [];
            return items.map(function (item) {
                return [
                    item.id || 0,
                    item.last_message_at || '',
                    item.unread_count || 0,
                    item.message_count || 0,
                    item.last_message || ''
                ].join(':');
            }).join('|');
        }

        function buildHeaderSignature(contact) {
            if (!contact || !contact.id) { return ''; }
            return [
                String(contact.id || 0),
                String(contact.name || ''),
                String(contact.email || ''),
                String(contact.username || ''),
                contact.is_online ? '1' : '0',
                isConversationMuted(contact.id) ? '1' : '0'
            ].join('|');
        }

        function buildMessagesSignature(messages) {
            var items = Array.isArray(messages) ? messages : [];
            return items.map(function (item) {
                return [
                    item.message_id || 0,
                    item.time_exact || '',
                    item.message || '',
                    item.media_path || '',
                    item.is_unsent ? 1 : 0,
                    item.unsent_at || '',
                    item.reaction_count || 0,
                    item.reaction_emoji || '',
                    item.is_pinned ? 1 : 0,
                    item.is_read ? 1 : 0,
                    item.read_at || ''
                ].join(':');
            }).join('|');
        }

        function deliveryStatusLabel(msg) {
            if (!msg || !msg.is_own) {
                return '';
            }
            if (msg.is_read) {
                if (msg.read_time_exact) {
                    return 'Seen at ' + msg.read_time_exact;
                }
                return 'Seen';
            }
            return 'Delivered';
        }

        function showAvatarFallback(imageEl) {
            if (!imageEl) {
                return;
            }
            imageEl.classList.add('chat-avatar-fallback-hidden');
            var fallbackEl = imageEl.nextElementSibling;
            if (fallbackEl && (
                fallbackEl.classList.contains('btchat-avatar-text') ||
                fallbackEl.classList.contains('chat-reaction-avatar-text') ||
                fallbackEl.classList.contains('msg-thread-avatar-text')
            )) {
                fallbackEl.classList.remove('chat-avatar-fallback-hidden');
            }
        }

        function bindAvatarFallback(scopeEl) {
            var root = scopeEl || document;
            var avatarImages = root.querySelectorAll('img.js-avatar-fallback');
            avatarImages.forEach(function (img) {
                if (img.dataset.avatarFallbackBound === '1') {
                    return;
                }
                img.dataset.avatarFallbackBound = '1';
                img.addEventListener('error', function () {
                    showAvatarFallback(img);
                });
                if (img.complete && img.naturalWidth === 0) {
                    showAvatarFallback(img);
                }
            });
        }

        function avatarMarkup(contact) {
            var imgTag = '<img src="' + escapeHtml(contact ? contact.avatar_path : '') + '" alt="' + escapeHtml(contact ? contact.name : '') + '" class="btchat-avatar js-avatar-fallback">';
            var spanTag = '<span class="btchat-avatar-text chat-avatar-fallback-hidden">' + escapeHtml(contact ? contact.initials : 'BT') + '</span>';
            var dotClass = contact && contact.is_online ? 'btchat-status-dot online' : 'btchat-status-dot';
            return '<span class="btchat-avatar-wrap">' + imgTag + spanTag + '<span class="' + dotClass + '"></span></span>';
        }

        function showAlert(type, message) {
            if (!alertEl) {
                return;
            }
            if (!message) {
                alertEl.innerHTML = '';
                return;
            }
            var klass = type === 'error' ? 'alert-danger' : 'alert-success';
            alertEl.innerHTML = '<div class="alert ' + klass + ' mb-0">' + escapeHtml(message) + '</div>';
            window.setTimeout(function () {
                if (alertEl) {
                    alertEl.innerHTML = '';
                }
            }, 2500);
        }

        function clearComposeWarning() {
            if (!composeWarningEl) {
                return;
            }
            composeWarningEl.classList.remove('show');
            composeWarningEl.setAttribute('data-warning-visible', '0');
            composeWarningEl.setAttribute('aria-hidden', 'true');
            if (composeWarningTextEl) {
                composeWarningTextEl.textContent = '';
            }
        }

        function showComposeWarning(message) {
            var warningText = String(message || '').trim();
            if (!composeWarningEl || !composeWarningTextEl || warningText === '') {
                clearComposeWarning();
                return;
            }
            composeWarningTextEl.textContent = warningText;
            composeWarningEl.classList.add('show');
            composeWarningEl.setAttribute('data-warning-visible', '1');
            composeWarningEl.setAttribute('aria-hidden', 'false');
        }

        function isModerationWarning(message) {
            var text = String(message || '').toLowerCase();
            return text.indexOf('message blocked') !== -1 || text.indexOf('disallowed symbol') !== -1;
        }

        function setThreadLoading(isLoading) {
            if (!threadEl) {
                return;
            }
            threadEl.classList.toggle('is-loading', !!isLoading);
        }

        function setActiveContactVisual(userId) {
            if (!listEl) {
                return;
            }
            var items = listEl.querySelectorAll('a[data-user-id]');
            items.forEach(function (item) {
                var itemUserId = parseInt(item.getAttribute('data-user-id') || '0', 10) || 0;
                item.classList.toggle('active', itemUserId === userId);
            });
        }

        function isMobileLayout() {
            return !!(mobileLayoutQuery && mobileLayoutQuery.matches);
        }

        function setMobileConversationOpen(shouldOpen) {
            if (!app) {
                return;
            }
            if (!isMobileLayout()) {
                app.classList.remove('btchat-mobile-convo-open');
                return;
            }
            app.classList.toggle('btchat-mobile-convo-open', !!shouldOpen);
        }

        function setMobileHistoryState(view, userId) {
            if (!isMobileLayout() || !window.history || typeof window.history.replaceState !== 'function') {
                return;
            }
            var normalizedView = view === 'conversation' ? 'conversation' : 'list';
            var normalizedUserId = (userId && userId > 0) ? userId : 0;
            var state = {
                btchatView: normalizedView,
                userId: normalizedUserId
            };
            var url = normalizedView === 'conversation' && normalizedUserId > 0
                ? (chatBaseUrl + '?user_id=' + normalizedUserId)
                : chatBaseUrl;
            try {
                window.history.replaceState(state, '', url);
            } catch (e) {
                // Ignore history API issues on restricted environments.
            }
        }

        function pushMobileConversationState(userId) {
            if (!isMobileLayout() || !window.history || typeof window.history.pushState !== 'function' || !(userId > 0)) {
                return;
            }
            var url = chatBaseUrl + '?user_id=' + userId;
            try {
                window.history.pushState({ btchatView: 'conversation', userId: userId }, '', url);
            } catch (e) {
                // Ignore history API issues on restricted environments.
            }
        }

        function primeHeaderFromListItem(link) {
            if (!headerEl || !link) {
                return;
            }
            var contactNameEl = link.querySelector('.btchat-name');
            var contactSnippetEl = link.querySelector('.btchat-snippet');
            var avatarImg = link.querySelector('.btchat-avatar');
            var avatarText = link.querySelector('.btchat-avatar-text');
            var contact = {
                id: selectedUserId,
                name: contactNameEl ? contactNameEl.textContent.trim() : 'Conversation',
                email: contactSnippetEl ? contactSnippetEl.textContent.trim() : '',
                username: '',
                avatar_path: avatarImg ? (avatarImg.getAttribute('src') || '') : '',
                initials: avatarText ? avatarText.textContent.trim() : 'BT',
                is_online: !!link.querySelector('.btchat-status-dot.online')
            };
            renderHeader(contact);
        }

        function renderContacts(contacts) {
            if (!listEl) {
                return;
            }
            var items = Array.isArray(contacts) ? contacts : [];
            if (currentSearch) {
                var term = currentSearch.toLowerCase();
                items = items.filter(function (item) {
                    var haystack = ((item.name || '') + ' ' + (item.username || '') + ' ' + (item.email || '')).toLowerCase();
                    return haystack.indexOf(term) !== -1;
                });
            }
            if (!items.length) {
                listEl.innerHTML = '<div class="px-3 py-4 text-white-50">No matching users.</div>';
                return;
            }
            items = items.slice().sort(function (left, right) {
                var leftOrder = parseInt(left && left.group_order != null ? left.group_order : 99, 10) || 99;
                var rightOrder = parseInt(right && right.group_order != null ? right.group_order : 99, 10) || 99;
                if (leftOrder !== rightOrder) {
                    return leftOrder - rightOrder;
                }

                var leftTs = left && left.last_message_at ? Date.parse(left.last_message_at) : 0;
                var rightTs = right && right.last_message_at ? Date.parse(right.last_message_at) : 0;
                leftTs = isNaN(leftTs) ? 0 : leftTs;
                rightTs = isNaN(rightTs) ? 0 : rightTs;
                if (leftTs !== rightTs) {
                    return rightTs - leftTs;
                }

                return String((left && left.name) || '').localeCompare(String((right && right.name) || ''));
            });

            var currentGroupKey = '';
            listEl.innerHTML = items.map(function (contact) {
                var activeClass = contact.id === selectedUserId ? ' active' : '';
                var unread = contact.unread_count > 0 ? '<span class="badge rounded-pill bg-primary">' + contact.unread_count + '</span>' : '';
                var snippet = contact.last_message ? contact.last_message : 'No messages yet';
                var markup = '';
                if (contact.group_key && contact.group_key !== currentGroupKey) {
                    currentGroupKey = contact.group_key;
                    markup += '<div class="btchat-group-label">' + escapeHtml(contact.group_label || '') + '</div>';
                }
                markup += '' +
                    '<a class="btchat-item' + activeClass + '" href="' + chatBaseUrl + '?user_id=' + contact.id + '" data-user-id="' + contact.id + '">' +
                        avatarMarkup(contact) +
                        '<div class="btchat-meta">' +
                            '<div class="btchat-name-row">' +
                                '<span class="btchat-name">' + escapeHtml(contact.name) + '</span>' +
                                '<span class="btchat-time">' + escapeHtml(contact.last_message_label || 'No messages yet') + '</span>' +
                            '</div>' +
                            '<div class="btchat-snippet-row">' +
                                '<span class="btchat-snippet">' + escapeHtml(snippet) + '</span>' +
                                unread +
                            '</div>' +
                        '</div>' +
                    '</a>';
                return markup;
            }).join('');
            bindAvatarFallback(listEl);
        }

        function renderHeader(contact) {
            if (!headerEl || !contact) {
                return;
            }
            var subtitle = contact.is_online ? 'Online' : (contact.email || contact.username || '');
            var muteLabel = isConversationMuted(contact.id) ? 'Unmute conversation' : 'Mute conversation';
            headerEl.innerHTML = '' +
                '<div class="btchat-chat-title">' +
                    '<button type="button" class="btchat-back-btn" id="btchat-mobile-back" aria-label="Back to conversations" title="Back">&#8592;</button>' +
                    avatarMarkup(contact) +
                    '<div class="min-w-0">' +
                        '<div class="btchat-chat-name">' + escapeHtml(contact.name) + '</div>' +
                        '<div class="btchat-chat-sub">' + escapeHtml(subtitle) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="btchat-actions">' +
                    '<button type="button" class="btchat-menu-toggle"><i class="feather-more-horizontal"></i></button>' +
                    '<div class="btchat-menu" role="menu">' +
                        '<button type="button" class="btchat-menu-item" data-action="view-contact">Contact details</button>' +
                        '<button type="button" class="btchat-menu-item" data-action="mute-conversation">' + escapeHtml(muteLabel) + '</button>' +
                        '<div class="btchat-menu-divider" role="separator"></div>' +
                        '<button type="button" class="btchat-menu-item" data-action="refresh-chat">Refresh chat</button>' +
                        '<button type="button" class="btchat-menu-item" data-action="scroll-bottom">Jump to recent</button>' +
                        '<div class="btchat-menu-divider" role="separator"></div>' +
                        '<button type="button" class="btchat-menu-item danger" data-action="delete-conversation">Delete conversation</button>' +
                    '</div>' +
                '</div>';
            bindHeaderMenu();
            scrubActionTooltips(headerEl);
            bindAvatarFallback(headerEl);
        }

        function muteStorageKey(userId) {
            return 'chatMutedUser:' + String(userId || 0);
        }

        function isConversationMuted(userId) {
            try {
                return window.localStorage.getItem(muteStorageKey(userId)) === '1';
            } catch (e) {
                return false;
            }
        }

        function setConversationMuted(userId, muted) {
            try {
                if (muted) {
                    window.localStorage.setItem(muteStorageKey(userId), '1');
                } else {
                    window.localStorage.removeItem(muteStorageKey(userId));
                }
            } catch (e) {
                // localStorage can fail in private mode; ignore silently.
            }
        }

        function togglePinMessageById(messageId, shouldPin) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'toggle-pin');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('pin_state', shouldPin ? 'pin' : 'unpin');
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to update pin.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to update pin.');
            });
        }

        function closeConfirmModal() {
            if (!confirmModalEl) { return; }
            confirmModalEl.classList.remove('show');
            confirmModalEl.setAttribute('aria-hidden', 'true');
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingConfirmFn = null;
        }

        function closeContactModal() {
            if (!contactModalEl) { return; }
            contactModalEl.classList.remove('show');
            contactModalEl.setAttribute('aria-hidden', 'true');
            contactModalUserId = 0;
            closeHeaderMenus();
        }

        function latestReportableMessageIdForUser(userId) {
            if (!(userId > 0)) {
                return 0;
            }

            var latestId = 0;
            Object.keys(messageCache).forEach(function (key) {
                var msg = messageCache[key];
                if (!msg || msg.is_unsent) {
                    return;
                }
                if (parseInt(msg.sender_id || '0', 10) !== userId) {
                    return;
                }
                var mid = parseInt(msg.message_id || '0', 10);
                if (mid > latestId) {
                    latestId = mid;
                }
            });

            return latestId;
        }

        function openContactModal(contact) {
            if (!contactModalEl || !contact) { return; }

            contactModalUserId = parseInt(contact.id || '0', 10) || 0;
            var displayName = String(contact.name || contact.username || 'Unknown user');
            var username = String(contact.username || '-');
            var email = String(contact.email || '-');
            var subtitle = email !== '-' ? email : username;
            var onlineState = contact.is_online ? 'Online' : 'Offline';
            var lastActive = String(contact.last_message_label || 'No messages yet');
            var lastMessagePreview = String(contact.last_message || 'No messages yet');
            var unreadCount = Math.max(0, parseInt(contact.unread_count || '0', 10));
            var totalMessages = Math.max(0, parseInt(contact.message_count || '0', 10));
            var isMuted = isConversationMuted(contactModalUserId);
            var reportableCount = 0;

            if (lastMessagePreview.length > 180) {
                lastMessagePreview = lastMessagePreview.slice(0, 177) + '...';
            }

            Object.keys(messageCache).forEach(function (key) {
                var msg = messageCache[key];
                if (!msg || msg.is_unsent) {
                    return;
                }
                if (parseInt(msg.sender_id || '0', 10) === contactModalUserId) {
                    reportableCount += 1;
                }
            });

            if (contactAvatarHostEl) {
                contactAvatarHostEl.innerHTML = avatarMarkup(contact);
            }
            if (contactNameEl) {
                contactNameEl.textContent = displayName;
            }
            if (contactSubEl) {
                contactSubEl.textContent = subtitle;
            }
            if (contactUserIdEl) {
                contactUserIdEl.textContent = contactModalUserId > 0 ? String(contactModalUserId) : '-';
            }
            if (contactUsernameEl) {
                contactUsernameEl.textContent = username;
            }
            if (contactEmailEl) {
                contactEmailEl.textContent = email;
            }
            if (contactStatusEl) {
                contactStatusEl.textContent = onlineState;
            }
            if (contactMutedStateEl) {
                contactMutedStateEl.textContent = isMuted ? 'Muted' : 'Unmuted';
            }
            if (contactLastActiveEl) {
                contactLastActiveEl.textContent = lastActive;
            }
            if (contactLastMessageEl) {
                contactLastMessageEl.textContent = lastMessagePreview;
            }
            if (contactUnreadEl) {
                contactUnreadEl.textContent = String(unreadCount);
            }
            if (contactTotalEl) {
                contactTotalEl.textContent = String(totalMessages);
            }
            if (contactReportableEl) {
                contactReportableEl.textContent = reportableCount + (reportableCount === 1 ? ' message' : ' messages');
            }
            if (contactMuteEl) {
                contactMuteEl.textContent = isMuted ? 'Unmute conversation' : 'Mute conversation';
            }

            closeHeaderMenus();
            closeMessageActionMenu();
            contactModalEl.classList.add('show');
            contactModalEl.setAttribute('aria-hidden', 'false');
            if (contactCloseEl) {
                contactCloseEl.focus();
            }
        }

        function openConfirmModal(title, text, onConfirm, confirmLabel) {
            if (!confirmModalEl) {
                if (typeof onConfirm === 'function') { onConfirm(); }
                return;
            }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingConfirmFn = typeof onConfirm === 'function' ? onConfirm : null;
            if (confirmTitleEl) { confirmTitleEl.textContent = title || 'Confirm action'; }
            if (confirmTextEl) { confirmTextEl.textContent = text || 'Are you sure?'; }
            if (confirmOkEl) { confirmOkEl.textContent = confirmLabel || 'Confirm'; }
            confirmModalEl.classList.add('show');
            confirmModalEl.setAttribute('aria-hidden', 'false');
            if (confirmOkEl) { confirmOkEl.focus(); }
        }

        function closeMessageActionMenu() {
            if (!messageActionMenuEl) { return; }
            messageActionMenuEl.classList.remove('show');
            messageActionMenuEl.setAttribute('aria-hidden', 'true');
            messageActionMenuEl.removeAttribute('data-message-id');
            messageActionMenuEl.removeAttribute('data-placement');
            messageActionMenuEl.style.removeProperty('--msg-menu-arrow-left');
            messageActionMenuEl.style.removeProperty('left');
            messageActionMenuEl.style.removeProperty('top');
            if (activeMessageActionTriggerEl && typeof activeMessageActionTriggerEl.blur === 'function') {
                activeMessageActionTriggerEl.blur();
            }
            activeMessageActionTriggerEl = null;
            activeMessageActionId = 0;
        }

        function closeReactionsModal() {
            if (!reactionsModalEl) { return; }
            reactionsModalEl.classList.remove('show');
            reactionsModalEl.setAttribute('aria-hidden', 'true');
            reactionsModalState = null;
            if (reactionsTabsEl) { reactionsTabsEl.innerHTML = ''; }
            if (reactionsListEl) { reactionsListEl.innerHTML = ''; }
        }

        function closeMediaModal() {
            if (!mediaModalEl) { return; }
            mediaModalEl.classList.remove('show');
            mediaModalEl.setAttribute('aria-hidden', 'true');
            mediaModalState = null;
            document.documentElement.classList.remove('chat-media-open');
            if (document.body) {
                document.body.classList.remove('chat-media-open');
            }
            if (mediaModalBodyEl) {
                var activeVideo = mediaModalBodyEl.querySelector('video');
                if (activeVideo) {
                    try {
                        activeVideo.pause();
                    } catch (e) {
                        // Ignore pause issues on detached media nodes.
                    }
                }
                mediaModalBodyEl.innerHTML = '';
            }
            if (mediaModalTitleEl) {
                mediaModalTitleEl.textContent = 'Image';
            }
            if (mediaModalDownloadEl) {
                mediaModalDownloadEl.disabled = true;
            }
        }

        function getMediaFilename(src, type) {
            var fallback = type === 'video' ? 'chat-video' : 'chat-image';
            if (!src) { return fallback; }
            var cleanSrc = String(src).split('#')[0].split('?')[0];
            var parts = cleanSrc.split('/');
            var name = parts.length ? parts[parts.length - 1] : '';
            name = name ? name.trim() : '';
            return name || fallback;
        }

        function downloadMediaFromModal() {
            if (!mediaModalState || !mediaModalState.src) {
                return;
            }
            var src = mediaModalState.src;
            var anchor = document.createElement('a');
            anchor.href = src;
            anchor.download = getMediaFilename(src, mediaModalState.type);
            anchor.rel = 'noopener';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
        }

        function openMediaModal(type, src, label) {
            if (!mediaModalEl || !mediaModalBodyEl || !src) {
                return;
            }

            var mediaType = type === 'video' ? 'video' : 'image';
            var title = label || (mediaType === 'video' ? 'Video' : 'Image');
            var mediaHtml = '';

            if (mediaType === 'video') {
                mediaHtml = '<video src="' + escapeHtml(src) + '" class="chat-media-view video" controls autoplay playsinline preload="auto"></video>';
            } else {
                mediaHtml = '<img src="' + escapeHtml(src) + '" class="chat-media-view image" alt="' + escapeHtml(title) + '" loading="eager">';
            }

            mediaModalState = {
                type: mediaType,
                src: src,
                title: title
            };

            mediaModalBodyEl.innerHTML = '<div class="chat-media-stage"><div class="chat-media-frame">' + mediaHtml + '</div></div>';
            if (mediaModalTitleEl) {
                mediaModalTitleEl.textContent = title;
            }
            if (mediaModalDownloadEl) {
                mediaModalDownloadEl.disabled = false;
            }
            mediaModalEl.classList.add('show');
            mediaModalEl.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('chat-media-open');
            if (document.body) {
                document.body.classList.add('chat-media-open');
            }

            var loadedMediaEl = mediaModalBodyEl.querySelector('.chat-media-view');
            if (loadedMediaEl) {
                loadedMediaEl.addEventListener('error', function () {
                    mediaModalBodyEl.innerHTML = '<div class="chat-reactions-empty">Failed to load media.</div>';
                }, { once: true });
            }
            if (mediaModalCloseEl) {
                mediaModalCloseEl.focus();
            }
        }

        function renderReactionsModal() {
            if (!reactionsModalState || !reactionsTabsEl || !reactionsListEl) { return; }
            var summary = Array.isArray(reactionsModalState.summary) ? reactionsModalState.summary : [];
            var users = Array.isArray(reactionsModalState.users) ? reactionsModalState.users : [];
            var activeFilter = reactionsModalState.filter || 'all';
            var totalCount = parseInt(reactionsModalState.total || '0', 10);
            if (!(totalCount > 0)) {
                totalCount = users.length;
            }

            var tabsHtml = '<button type="button" class="chat-reactions-tab' + (activeFilter === 'all' ? ' active' : '') + '" data-reaction-filter="all">All ' + totalCount + '</button>';
            tabsHtml += summary.map(function (item) {
                return '<button type="button" class="chat-reactions-tab' + (activeFilter === item.emoji ? ' active' : '') + '" data-reaction-filter="' + escapeHtml(item.emoji) + '">' + escapeHtml(item.emoji) + ' ' + parseInt(item.count || '0', 10) + '</button>';
            }).join('');
            reactionsTabsEl.innerHTML = tabsHtml;

            var filteredUsers = users.filter(function (item) {
                return activeFilter === 'all' || item.emoji === activeFilter;
            });

            if (!filteredUsers.length) {
                reactionsListEl.innerHTML = '<div class="chat-reactions-empty">No reactions found.</div>';
                return;
            }

            reactionsListEl.innerHTML = filteredUsers.map(function (item) {
                var name = item.name || 'Unknown user';
                var avatar = item.avatar_path || '';
                var initials = item.initials || 'BT';
                return '' +
                    '<div class="chat-reaction-row">' +
                        '<div class="chat-reaction-user">' +
                            '<img src="' + escapeHtml(avatar) + '" class="chat-reaction-avatar js-avatar-fallback" alt="' + escapeHtml(name) + '">' +
                            '<span class="chat-reaction-avatar-text chat-avatar-fallback-hidden">' + escapeHtml(initials) + '</span>' +
                            '<div class="chat-reaction-user-meta">' +
                                '<div class="chat-reaction-user-name">' + escapeHtml(name) + '</div>' +
                                '<div class="chat-reaction-user-sub">' + (item.is_own ? 'You reacted' : 'Reacted') + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<span class="chat-reaction-emoji">' + escapeHtml(item.emoji || '') + '</span>' +
                    '</div>';
            }).join('');
            bindAvatarFallback(reactionsListEl);
        }

        function openReactionsModal(messageId) {
            if (!reactionsModalEl || !messageId) { return; }
            var msg = messageCache[String(messageId)];
            if (!msg) { return; }

            var summaryRaw = Array.isArray(msg.reaction_summary) ? msg.reaction_summary : [];
            var summary = summaryRaw
                .map(function (item) {
                    return {
                        emoji: String(item && item.emoji ? item.emoji : ''),
                        count: parseInt(item && item.count ? item.count : '0', 10)
                    };
                })
                .filter(function (item) {
                    return item.emoji !== '' && item.count > 0;
                });

            var usersRaw = Array.isArray(msg.reaction_users) ? msg.reaction_users : [];
            var users = usersRaw
                .map(function (item) {
                    var name = String(item && item.name ? item.name : '').trim() || 'Unknown user';
                    return {
                        user_id: parseInt(item && item.user_id ? item.user_id : '0', 10) || 0,
                        name: name,
                        avatar_path: String(item && item.avatar_path ? item.avatar_path : ''),
                        initials: String(item && item.initials ? item.initials : 'BT'),
                        emoji: String(item && item.emoji ? item.emoji : ''),
                        is_own: !!(item && item.is_own)
                    };
                })
                .filter(function (item) {
                    return item.emoji !== '';
                });

            if (!summary.length && msg.reaction_emoji) {
                var fallbackCount = parseInt(msg.reaction_count || '1', 10);
                summary = [{ emoji: String(msg.reaction_emoji), count: fallbackCount > 0 ? fallbackCount : 1 }];
            }

            var total = parseInt(msg.reaction_count || '0', 10);
            if (!(total > 0)) {
                total = users.length;
            }

            reactionsModalState = {
                messageId: messageId,
                summary: summary,
                users: users,
                total: total,
                filter: 'all'
            };

            renderReactionsModal();
            reactionsModalEl.classList.add('show');
            reactionsModalEl.setAttribute('aria-hidden', 'false');
        }

        function getMessagePreview(msg) {
            if (!msg) { return ''; }
            var txt = (msg.message || '').trim();
            if (!txt && msg.media_type === 'image') { return '[Image]'; }
            if (!txt && msg.media_type === 'video') { return '[Video]'; }
            if (msg.media_path && txt === msg.media_path.split('/').pop()) {
                return msg.media_type === 'video' ? '[Video]' : '[Image]';
            }
            return txt;
        }

        function clearReplyTarget() {
            replyTarget = null;
            if (replyToInputEl) { replyToInputEl.value = '0'; }
            if (replyLabelEl) { replyLabelEl.textContent = ''; }
            if (replyPreviewEl) { replyPreviewEl.classList.remove('has-reply'); }
        }

        function setReplyTarget(msg) {
            if (!msg) { clearReplyTarget(); return; }
            replyTarget = msg;
            if (replyToInputEl) { replyToInputEl.value = String(msg.message_id || 0); }
            if (replyLabelEl) {
                var preview = getMessagePreview(msg);
                replyLabelEl.textContent = 'Replying to ' + (msg.is_own ? 'yourself' : 'them') + ': ' + (preview || '[Message]');
            }
            if (replyPreviewEl) { replyPreviewEl.classList.add('has-reply'); }
        }

        function openMessageActionMenu(messageId, triggerEl) {
            if (!messageActionMenuEl || !triggerEl) { return; }
            var msg = messageCache[String(messageId)];
            if (!msg) { return; }

            activeMessageActionId = messageId;
            activeMessageActionTriggerEl = triggerEl;
            messageActionMenuEl.dataset.messageId = String(messageId);

            var emojiRow = messageActionMenuEl.querySelector('.msg-action-emoji-row');
            var replyBtn = messageActionMenuEl.querySelector('[data-msg-action="reply"]');
            var unsendBtn = messageActionMenuEl.querySelector('[data-msg-action="unsend"]');
            var removeBtn = messageActionMenuEl.querySelector('[data-msg-action="remove"]');
            var reportBtn = messageActionMenuEl.querySelector('[data-msg-action="report"]');
            var pinBtn = messageActionMenuEl.querySelector('[data-msg-action="pin"]');
            var isPinned = !!msg.is_pinned;
            var isUnsent = !!msg.is_unsent;

            if (pinBtn) {
                pinBtn.textContent = isPinned ? 'Unpin message' : 'Pin message';
                pinBtn.classList.toggle('is-hidden', isUnsent);
            }
            if (replyBtn) {
                replyBtn.classList.toggle('is-hidden', isUnsent);
            }
            if (unsendBtn) {
                unsendBtn.classList.toggle('is-hidden', !msg.is_own || isUnsent);
            }
            if (removeBtn) {
                removeBtn.classList.toggle('is-hidden', !(msg.is_own && isUnsent));
            }
            if (reportBtn) {
                reportBtn.classList.toggle('is-hidden', !!msg.is_own);
            }
            if (emojiRow) {
                emojiRow.classList.toggle('is-hidden', isUnsent);
            }

            messageActionMenuEl.classList.add('show');
            messageActionMenuEl.setAttribute('aria-hidden', 'false');

            var rect = triggerEl.getBoundingClientRect();
            var menuRect = messageActionMenuEl.getBoundingClientRect();
            var menuW = menuRect.width || 220;
            var menuH = menuRect.height || 180;
            var left = rect.left + (rect.width / 2) - (menuW / 2);
            var top = rect.top - menuH - 12;
            var placement = 'top';
            var viewportPadding = 8;
            var headerBottomLimit = viewportPadding;

            if (headerEl) {
                var headerRect = headerEl.getBoundingClientRect();
                if (headerRect && isFinite(headerRect.bottom)) {
                    headerBottomLimit = Math.max(headerBottomLimit, headerRect.bottom + 10);
                }
            }

            if (left < viewportPadding) {
                left = viewportPadding;
            }
            if (left + menuW > window.innerWidth - viewportPadding) {
                left = window.innerWidth - menuW - viewportPadding;
            }
            if (top < headerBottomLimit) {
                top = rect.bottom + 12;
                placement = 'bottom';
            }

            if (top + menuH > window.innerHeight - viewportPadding) {
                top = Math.max(headerBottomLimit, window.innerHeight - menuH - viewportPadding);
            }

            var arrowLeft = rect.left + (rect.width / 2) - left;
            if (arrowLeft < 18) { arrowLeft = 18; }
            if (arrowLeft > menuW - 18) { arrowLeft = menuW - 18; }

            messageActionMenuEl.dataset.placement = placement;
            messageActionMenuEl.style.setProperty('--msg-menu-arrow-left', arrowLeft + 'px');
            messageActionMenuEl.style.left = left + 'px';
            messageActionMenuEl.style.top = top + 'px';
        }

        function reactToMessage(messageId, reactionEmoji) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'react-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('reaction_emoji', reactionEmoji || '');
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;

                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                    return;
                }

                if (payload && payload.error) {
                    showAlert('error', payload.error);
                    return;
                }

                if (result.ok) {
                    fetchState(true);
                    return;
                }

                showAlert('error', 'Failed to react to message.');
            }).catch(function () {
                fetchState(true);
            });
        }

        function deleteConversationByUserId(userId) {
            if (!userId) { return; }
            var fd = new FormData();
            fd.set('action', 'delete-conversation');
            fd.set('user_id', String(userId));
            fd.set('ajax', '1');
            postChatAction(fd, userId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: false, forceScroll: true });
                    clearMediaPreview();
                    showAlert('success', payload.success || 'Conversation deleted.');
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to delete conversation.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to delete conversation.');
            });
        }

        function unsendMessageById(messageId) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'unsend-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to unsend message.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to unsend message.');
            });
        }

        function removeMessageById(messageId) {
            if (!selectedUserId || !messageId) { return; }
            var fd = new FormData();
            fd.set('action', 'remove-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    applyState(payload, { keepInput: true });
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to remove message.');
                }
            }).catch(function () {
                showAlert('error', 'Failed to remove message.');
            });
        }

        function closeReportModal() {
            if (!reportModalEl) { return; }
            reportModalEl.classList.remove('show');
            reportModalEl.setAttribute('aria-hidden', 'true');
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            // Ensure all form elements are properly reset
            if (reportReasonEl) {
                reportReasonEl.blur();
            }
            if (reportNoteEl) {
                reportNoteEl.blur();
            }
            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }
            pendingReportFn = null;
        }

        function buildReportReason() {
            var baseReason = reportReasonEl ? String(reportReasonEl.value || '').trim() : '';
            var extraNote = reportNoteEl ? String(reportNoteEl.value || '').trim() : '';
            if (!baseReason) {
                baseReason = 'Inappropriate message';
            }
            var reason = extraNote ? (baseReason + ': ' + extraNote) : baseReason;
            return reason.slice(0, 255);
        }

        function openReportModal(onConfirm) {
            if (!reportModalEl) {
                if (typeof onConfirm === 'function') {
                    onConfirm('Inappropriate message');
                }
                return;
            }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            pendingReportFn = typeof onConfirm === 'function' ? onConfirm : null;
            if (reportReasonEl) {
                reportReasonEl.value = 'Harassment or abusive language';
            }
            if (reportNoteEl) {
                reportNoteEl.value = '';
            }
            reportModalEl.classList.add('show');
            reportModalEl.setAttribute('aria-hidden', 'false');
            if (reportReasonEl) {
                reportReasonEl.focus();
            }
        }

        function reportMessageById(messageId, reason) {
            if (!selectedUserId || !messageId) { return; }
            closeHeaderMenus();
            closeMessageActionMenu();
            scrubActionTooltips(document);
            var fd = new FormData();
            fd.set('action', 'report-message');
            fd.set('user_id', String(selectedUserId));
            fd.set('message_id', String(messageId));
            fd.set('reason', String(reason || 'Inappropriate message'));
            fd.set('ajax', '1');
            postChatAction(fd, selectedUserId).then(function (result) {
                var payload = result.payload;
                if (payload && payload.ok) {
                    showAlert('success', payload.success || 'Message reported.');
                } else {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to report message.');
                }
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            }).catch(function () {
                showAlert('error', 'Failed to report message.');
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            });
        }

        function reportUserFromContactModal() {
            if (!(contactModalUserId > 0)) {
                showAlert('error', 'Select a contact first.');
                return;
            }

            var latestMessageId = latestReportableMessageIdForUser(contactModalUserId);
            if (!(latestMessageId > 0)) {
                showAlert('error', 'No recent message from this user to report.');
                return;
            }

            closeContactModal();
            openReportModal(function (selectedReason) {
                openConfirmModal(
                    'Report this user?',
                    'This will report their most recent message for moderation review. Reason: ' + selectedReason,
                    function () { reportMessageById(latestMessageId, selectedReason); },
                    'Report'
                );
            });
        }

        function closeDeleteConfirm() {
            if (!deleteConfirmEl) { return; }
            deleteConfirmEl.classList.remove('show');
            deleteConfirmEl.setAttribute('aria-hidden', 'true');
            pendingDeleteUserId = 0;
        }

        function openDeleteConfirm(userId) {
            if (!deleteConfirmEl || !userId) { return; }
            pendingDeleteUserId = userId;
            deleteConfirmEl.classList.add('show');
            deleteConfirmEl.setAttribute('aria-hidden', 'false');
            if (deleteConfirmOkEl) { deleteConfirmOkEl.focus(); }
        }

        function closeHeaderMenus() {
            if (!headerEl) { return; }
            var menus = headerEl.querySelectorAll('.btchat-menu.show');
            menus.forEach(function (menuEl) {
                menuEl.classList.remove('show');
            });
        }

        function bindHeaderMenu() {
            if (!headerEl) { return; }
            var backBtn = headerEl.querySelector('.btchat-back-btn');
            var toggle = headerEl.querySelector('.btchat-menu-toggle');
            var menu = headerEl.querySelector('.btchat-menu');

            if (backBtn) {
                backBtn.onclick = function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    setMobileConversationOpen(false);
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    closeEmojiPicker();
                };
            }

            if (!toggle || !menu) { return; }

            toggle.onclick = function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (Date.now() < suppressHeaderToggleUntil) {
                    return;
                }
                menu.classList.toggle('show');
                if (menu.classList.contains('show')) {
                    var firstItem = menu.querySelector('.btchat-menu-item');
                    if (firstItem) { firstItem.focus(); }
                }
            };

            toggle.onkeydown = function (event) {
                if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (Date.now() < suppressHeaderToggleUntil) {
                        return;
                    }
                    menu.classList.add('show');
                    var firstItem = menu.querySelector('.btchat-menu-item');
                    if (firstItem) { firstItem.focus(); }
                } else if (event.key === 'Escape') {
                    menu.classList.remove('show');
                }
            };

            menu.onkeydown = function (event) {
                var items = Array.prototype.slice.call(menu.querySelectorAll('.btchat-menu-item'));
                if (!items.length) { return; }
                var activeIndex = items.indexOf(document.activeElement);
                if (event.key === 'Escape') {
                    event.preventDefault();
                    menu.classList.remove('show');
                    toggle.focus();
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    items[(activeIndex + 1 + items.length) % items.length].focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    items[(activeIndex - 1 + items.length) % items.length].focus();
                }
            };

            menu.onclick = function (event) {
                var btn = event.target.closest('.btchat-menu-item');
                if (!btn) { return; }
                var action = btn.getAttribute('data-action') || '';
                menu.classList.remove('show');
                if (action === 'refresh-chat') {
                    fetchState(true);
                } else if (action === 'scroll-bottom') {
                    scrollThreadToBottom(true);
                    updateScrollBtn();
                } else if (action === 'view-contact') {
                    var c = selectedContactRef;
                    if (!c) { return; }
                    openContactModal(c);
                } else if (action === 'mute-conversation') {
                    if (!selectedContactRef) { return; }
                    var currentlyMuted = isConversationMuted(selectedContactRef.id);
                    setConversationMuted(selectedContactRef.id, !currentlyMuted);
                    renderHeader(selectedContactRef);
                    clearComposeWarning();
                } else if (action === 'delete-conversation') {
                    if (!selectedUserId) { return; }
                    openConfirmModal(
                        'Delete conversation?',
                        'This will remove all messages in this conversation for your account.',
                        function () { deleteConversationByUserId(selectedUserId); },
                        'Delete'
                    );
                }
            };
        }

        function isAtBottom() {
            if (!threadEl) { return true; }
            return (threadEl.scrollHeight - threadEl.scrollTop - threadEl.clientHeight) < 120;
        }

        function scrollThreadToBottom(force) {
            if (!threadEl) { return; }
            if (force || isAtBottom()) {
                threadEl.scrollTop = threadEl.scrollHeight;
            }
        }

        var scrollBtnEl = document.getElementById('chat-scroll-btn');

        function updateScrollBtn() {
            if (!scrollBtnEl) { return; }
            if (isAtBottom()) {
                scrollBtnEl.classList.remove('visible');
            } else {
                scrollBtnEl.classList.add('visible');
            }
        }

        function updateSendBtn() {
            if (!sendBtnEl || !inputEl) { return; }
            var hasMedia = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
            var hasText = inputEl.value.replace(/[\s\n\r\t]+/g, '') !== '';
            if (hasText || hasMedia) {
                sendBtnEl.classList.remove('is-like');
                sendBtnEl.innerHTML = '<i class="feather-send"></i>';
                sendBtnEl.dataset.mode = 'send';
            } else {
                sendBtnEl.classList.add('is-like');
                sendBtnEl.innerHTML = '&#128077;';
                sendBtnEl.dataset.mode = 'like';
            }
        }

        function dateSepLabel(dateKey) {
            if (!dateKey) { return ''; }
            var today = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var todayKey = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
            var yest = new Date(today);
            yest.setDate(today.getDate() - 1);
            var yestKey = yest.getFullYear() + '-' + pad(yest.getMonth() + 1) + '-' + pad(yest.getDate());
            if (dateKey === todayKey) { return 'Today'; }
            if (dateKey === yestKey) { return 'Yesterday'; }
            var parts = dateKey.split('-');
            var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        }

        function renderMessages(messages, selectedContact, forceScroll) {
            if (!threadEl) { return; }
            var items = Array.isArray(messages) ? messages : [];
            if (!items.length) {
                threadEl.innerHTML = '<div class="btchat-empty"><div><h6 class="mb-2 text-white">No messages yet</h6><div class="text-white-50">Start the conversation with ' + escapeHtml(selectedContact ? selectedContact.name : 'this user') + '.</div></div></div>';
                return;
            }

            var wasBottom = forceScroll || isAtBottom();
            var n = items.length;
            messageCache = {};

            // Compute grouping: consecutive same-sender in same date_key
            var groups = new Array(n);
            for (var gi = 0; gi < n; gi++) {
                var prevSame = gi > 0 && items[gi - 1].sender_id === items[gi].sender_id && items[gi - 1].date_key === items[gi].date_key;
                var nextSame = gi < n - 1 && items[gi + 1].sender_id === items[gi].sender_id && items[gi].date_key === items[gi + 1].date_key;
                if (prevSame && nextSame) { groups[gi] = 'msg-group-middle'; }
                else if (prevSame) { groups[gi] = 'msg-group-last'; }
                else if (nextSame) { groups[gi] = 'msg-group-first'; }
                else { groups[gi] = 'msg-group-only'; }
            }

            // Find last own message index for Sent indicator
            var lastOwnIdx = -1;
            for (var li = n - 1; li >= 0; li--) {
                if (items[li].is_own) { lastOwnIdx = li; break; }
            }

            var html = '';
            var lastDateKey = '';

            for (var mi = 0; mi < n; mi++) {
                var msg = items[mi];
                var grp = groups[mi];
                var dk = msg.date_key || '';
                var isUnsent = !!msg.is_unsent;
                messageCache[String(msg.message_id)] = msg;

                // Date separator between days
                if (dk && dk !== lastDateKey) {
                    html += '<div class="msg-date-sep">' + escapeHtml(dateSepLabel(dk)) + '</div>';
                    lastDateKey = dk;
                }

                // Media
                var mediaHtml = '';
                if (!isUnsent && msg.media_type === 'image' && msg.media_path) {
                    mediaHtml = '<img src="' + escapeHtml(msg.media_path) + '" class="msg-media" alt="image" data-media-viewer="image">';
                } else if (!isUnsent && msg.media_type === 'video' && msg.media_path) {
                    mediaHtml = '<video src="' + escapeHtml(msg.media_path) + '" class="msg-media-video" controls preload="metadata" data-media-viewer="video"></video>';
                }

                var displayMsg = msg.message || '';
                if (msg.media_path && displayMsg === msg.media_path.split('/').pop()) {
                    displayMsg = '';
                }
                if (msg.media_path && looksLikeStandaloneMediaFilename(displayMsg)) {
                    displayMsg = '';
                }

                // Meta (time): only on last/only of a group
                var metaHtml = '';
                if (grp === 'msg-group-last' || grp === 'msg-group-only') {
                    var metaText = escapeHtml(msg.time_label || '');
                    if (msg.time_exact) {
                        metaText += ' &middot; <span class="msg-time-exact">' + escapeHtml(msg.time_exact) + '</span>';
                    }
                    metaHtml = '<div class="msg-meta">' + metaText + '</div>';
                }

                // Avatar for other's messages (left side)
                var avatarHtml = '';
                if (!msg.is_own) {
                    if (grp === 'msg-group-last' || grp === 'msg-group-only') {
                        var avSrc = escapeHtml(selectedContact ? selectedContact.avatar_path : '');
                        var avInit = escapeHtml(selectedContact ? selectedContact.initials : 'BT');
                        avatarHtml = '<span class="msg-thread-avatar-wrap">' +
                            '<img src="' + avSrc + '" class="msg-thread-avatar js-avatar-fallback" title="' + escapeHtml(selectedContact ? selectedContact.name : '') + '">' +
                            '<span class="msg-thread-avatar-text chat-avatar-fallback-hidden">' + avInit + '</span>' +
                            '</span>';
                    } else {
                        avatarHtml = '<span class="msg-thread-avatar-placeholder"></span>';
                    }
                }

                // Bubble title = full time for all messages (accessible via hover)
                var bubbleTitle = msg.time_full ? ' title="' + escapeHtml(msg.time_full) + '"' : '';
                var pinnedClass = msg.is_pinned ? ' is-pinned' : '';
                var reactionSummaryRaw = Array.isArray(msg.reaction_summary) ? msg.reaction_summary : [];
                var reactionSummary = reactionSummaryRaw
                    .map(function (item) {
                        return {
                            emoji: String(item && item.emoji ? item.emoji : ''),
                            count: parseInt(item && item.count ? item.count : '0', 10)
                        };
                    })
                    .filter(function (item) {
                        return item.emoji !== '' && item.count > 0;
                    });
                var reactionCount = parseInt(msg.reaction_count || '0', 10);
                if (!(reactionCount > 0)) {
                    reactionCount = reactionSummary.reduce(function (sum, item) {
                        return sum + (item.count || 0);
                    }, 0);
                }
                if (!reactionSummary.length && msg.reaction_emoji) {
                    reactionSummary = [{ emoji: String(msg.reaction_emoji), count: reactionCount > 0 ? reactionCount : 1 }];
                }
                var hasReaction = !isUnsent && reactionCount > 0 && reactionSummary.length > 0;
                var reactionIconsHtml = reactionSummary.slice(0, 2).map(function (item) {
                    return '<span class="msg-reaction-icon">' + escapeHtml(item.emoji) + '</span>';
                }).join('');

                html += '<div class="msg-row' + (msg.is_own ? ' own' : '') + ' ' + grp + (hasReaction ? ' has-reaction' : '') + '">';
                if (!msg.is_own) { html += avatarHtml; }
                if (msg.is_own) {
                    html += '<button type="button" class="msg-hover-menu-btn" data-message-id="' + msg.message_id + '">&#8226;&#8226;&#8226;</button>';
                }
                html += '<div class="msg-bubble ' + grp + (msg.media_path ? ' has-media' : '') + (isUnsent ? ' is-unsent' : '') + pinnedClass + '"' + bubbleTitle + '>';
                if (msg.reply_preview && !isUnsent) {
                    html += '<div class="msg-reply-quote"><strong>' + escapeHtml(msg.reply_author || '') + '</strong><span class="msg-reply-quote-text">' + escapeHtml(msg.reply_preview) + '</span></div>';
                }
                html += mediaHtml;
                if (displayMsg) { html += nl2br(displayMsg); }
                html += metaHtml;
                if (hasReaction) {
                    html += '<button type="button" class="msg-reaction-badge" data-reaction-mid="' + msg.message_id + '" aria-label="View reactions">' +
                        '<span class="msg-reaction-icons">' + reactionIconsHtml + '</span>' +
                        '<span class="msg-reaction-count">' + reactionCount + '</span>' +
                    '</button>';
                }
                html += '</div>';
                if (!msg.is_own && !isUnsent) {
                    html += '<button type="button" class="msg-hover-menu-btn" data-message-id="' + msg.message_id + '">&#8226;&#8226;&#8226;</button>';
                }
                html += '</div>';

                // Sent indicator under the last own message
                if (mi === lastOwnIdx) {
                    var statusLabel = deliveryStatusLabel(msg);
                    html += '<div class="msg-seen">' + escapeHtml(statusLabel || 'Sent') + '</div>';
                }
            }

            threadEl.innerHTML = html;
            bindAvatarFallback(threadEl);

            if (wasBottom) {
                threadEl.scrollTop = threadEl.scrollHeight;
            }
            updateScrollBtn();
            scrubActionTooltips(threadEl);
        }

        function applyState(payload, options) {
            var state = payload || {};
            var previousSelectedUserId = selectedUserId;
            if (typeof state.selectedUserId === 'number' && state.selectedUserId > 0) {
                selectedUserId = state.selectedUserId;
                app.setAttribute('data-selected-user-id', String(selectedUserId));
            }
            if (selectedUserId !== previousSelectedUserId) {
                clearReplyTarget();
            }
            var contacts = state.contacts || [];
            lastContactsData = contacts;
            var messages = state.messages || [];
            var contactsSignature = buildContactsSignature(contacts);
            var messagesSignature = buildMessagesSignature(messages);
            var forceScroll = !!(options && options.forceScroll);
            var contactChanged = selectedUserId !== previousSelectedUserId;

            if (contactsSignature !== lastContactsSignature || contactChanged) {
                renderContacts(contacts);
                lastContactsSignature = contactsSignature;
            }

            if (state.selectedContact) {
                selectedContactRef = state.selectedContact;
                var headerSignature = buildHeaderSignature(state.selectedContact);
                if (contactChanged || headerSignature !== lastHeaderSignature) {
                    renderHeader(state.selectedContact);
                    lastHeaderSignature = headerSignature;
                }
                if (contactChanged && selectedUserId > 0) {
                    setMobileConversationOpen(true);
                }
                if (forceScroll || contactChanged || messagesSignature !== lastMessagesSignature || selectedUserId !== lastRenderedUserId) {
                    renderMessages(messages, state.selectedContact, forceScroll);
                    lastMessagesSignature = messagesSignature;
                    lastRenderedUserId = selectedUserId;
                }
            } else {
                selectedContactRef = null;
                lastHeaderSignature = '';
                setMobileConversationOpen(false);
                clearReplyTarget();
                lastMessagesSignature = '';
                lastRenderedUserId = 0;
            }
            if (formEl && selectedUserId > 0) {
                formEl.setAttribute('action', chatBaseUrl + '?user_id=' + selectedUserId);
                var userField = formEl.querySelector('input[name="user_id"]');
                if (userField) {
                    userField.value = String(selectedUserId);
                }
            }
            if (!options || !options.keepInput) {
                if (inputEl) {
                    inputEl.value = '';
                }
            }
            setActiveContactVisual(selectedUserId);
            setThreadLoading(false);
        }

        function fetchState(showErrors, options) {
            if (!selectedUserId) {
                return Promise.resolve(null);
            }
            var requestToken = ++stateRequestToken;
            if (fetchAbortController && typeof fetchAbortController.abort === 'function') {
                fetchAbortController.abort();
            }
            fetchAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var requestUrl = chatBaseUrl + '?ajax=1&user_id=' + encodeURIComponent(selectedUserId);
            return fetch(requestUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: fetchAbortController ? fetchAbortController.signal : undefined
            }).then(parseJsonResponse).then(function (result) {
                var payload = result.payload;
                if (requestToken !== stateRequestToken) {
                    return null;
                }
                if (payload && payload.ok) {
                    applyState(payload, {
                        keepInput: true,
                        forceScroll: !!(options && options.forceScroll)
                    });
                } else if (showErrors) {
                    showAlert('error', payload && payload.error ? payload.error : 'Failed to refresh chat.');
                }
                return payload;
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return null;
                }
                if (showErrors) {
                    showAlert('error', 'Failed to refresh chat.');
                }
                setThreadLoading(false);
                return null;
            });
        }

        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var link = event.target.closest('a[data-user-id]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                selectedUserId = parseInt(link.getAttribute('data-user-id') || '0', 10) || 0;
                if (!selectedUserId) {
                    window.location.href = link.getAttribute('href') || chatBaseUrl;
                    return;
                }
                var wasMobileConversationOpen = isMobileLayout() && app.classList.contains('btchat-mobile-convo-open');
                setActiveContactVisual(selectedUserId);
                primeHeaderFromListItem(link);
                setThreadLoading(true);
                setMobileConversationOpen(true);
                if (isMobileLayout()) {
                    if (wasMobileConversationOpen) {
                        setMobileHistoryState('conversation', selectedUserId);
                    } else {
                        pushMobileConversationState(selectedUserId);
                    }
                } else {
                    history.replaceState(null, '', chatBaseUrl + '?user_id=' + selectedUserId);
                }
                fetchState(true, { forceScroll: true }).then(function (payload) {
                    if (!payload || !payload.ok) {
                        setThreadLoading(false);
                        window.location.href = link.getAttribute('href') || (chatBaseUrl + '?user_id=' + selectedUserId);
                    }
                });
            });
        }

        // â”€â”€ Media attach button & preview â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        var mediaInputEl = document.getElementById('chat-media-input');
        var attachBtnEl = document.getElementById('chat-attach-btn');
        var previewEl = document.getElementById('chat-media-preview');
        var previewThumbEl = document.getElementById('chat-preview-thumb');
        var previewNameEl = document.getElementById('chat-preview-name');
        var previewRemoveEl = document.getElementById('chat-preview-remove');
        var emojiBtnEl = document.getElementById('chat-emoji-btn');
        var emojiPickerEl = document.getElementById('chat-emoji-picker');
        var emojiGridEl = document.getElementById('chat-emoji-grid');
        var emojiSearchEl = document.getElementById('chat-emoji-search');
        var emojiTabsEl = document.getElementById('chat-emoji-tabs');
        var emojiEmptyEl = document.getElementById('chat-emoji-empty');
        var activeEmojiCategory = 'smileys';
        var blockedChatEmojis = ['🖕', '🍆', '🍑', '💦', '👅'];

        var emojiCatalog = {
            smileys: ['😀','😁','😂','🤣','😊','🙂','😉','😍','😘','😎','🤔','🙄','😢','😭','😡','🤬'],
            people: ['👍','👎','👏','🙌','🙏','👌','🤝','💪','👀','🫶','❤️','💔','🔥','✨','🎉','💯'],
            animals: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🦄'],
            food: ['🍎','🍌','🍇','🍉','🍓','🍍','🥑','🍅','🍔','🍕','🍟','🌮','🍣','🍜','🍩','🍪'],
            travel: ['🚗','🚌','🚕','🚓','🚑','🚒','🚲','✈️','🚆','🚀','🛳️','🏝️','🏙️','🗺️','🌋','🌉'],
            objects: ['⌚','📱','💻','⌨️','🖥️','📷','🎥','📞','💡','🔦','🔋','🔌','🧰','⚙️','💰','💎'],
            symbols: ['❤️','💔','❗','❓','✅','☑️','⚠️','🚫','🔔','🔕','♻️','▶️','⏸️','⏹️','⏺️','🔁'],
            flags: ['🏁','🚩','🏳️','🏴','🏳️‍🌈','🏳️‍⚧️','🇵🇭','🇺🇸','🇬🇧','🇯🇵','🇰🇷','🇨🇦','🇦🇺','🇫🇷','🇮🇹','🇪🇸']
        };

        function emojiMatchesQuery(emoji, query) {
            if (!query) { return true; }
            // Simple fallback matching on known category and direct emoji glyph.
            return emoji.indexOf(query) !== -1;
        }

        function getChatModerationError(message) {
            var text = String(message || '').trim();
            if (!text) { return ''; }

            for (var i = 0; i < blockedChatEmojis.length; i++) {
                if (text.indexOf(blockedChatEmojis[i]) !== -1) {
                    return 'Message blocked due to unsupported or offensive emoji.';
                }
            }

            var symbolCompact = text.replace(/\s+/g, '');
            var symbolCompactLower = symbolCompact.toLowerCase();
            var blockedSymbolTokens = ['./.', '/./', '.|.', '<==3', '<===3', '<====3', '8==d', '8===d', '8====d', 'b==d', 'b===d', 'b====d'];
            for (var si = 0; si < blockedSymbolTokens.length; si++) {
                if (symbolCompactLower.indexOf(blockedSymbolTokens[si]) !== -1) {
                    return 'Message blocked due to disallowed symbol patterns.';
                }
            }
            if (/(?:<|8|b|c)[=\-~_]{2,}(?:3|d)/.test(symbolCompactLower)) {
                return 'Message blocked due to disallowed symbol patterns.';
            }

            var normalized = text
                .toLowerCase()
                .replace(/[013457@$]/g, function (char) {
                    return ({ '0': 'o', '1': 'i', '3': 'e', '4': 'a', '5': 's', '7': 't', '@': 'a', '$': 's' })[char] || char;
                })
                .replace(/[^a-z0-9\s]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            var compact = normalized.replace(/\s+/g, '');
            var blockedTerms = [
                'fuck', 'fucking', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy',
                'nude', 'nudes', 'porn', 'sext', 'blowjob', 'handjob', 'cum', 'kys',
                'fck', 'fvck', 'phuck', 'btch', 'biatch',
                'cunt', 'whore', 'slut', 'rape', 'rapist', 'pedo', 'pedophile',
                'jizz', 'boner', 'wank', 'wanker', 'fap', 'hentai', 'horny',
                'orgasm', 'masturbate', 'masturbation', 'threesome', 'gangbang', 'creampie',
                'anal', 'erection', 'ejaculate', 'ejaculation', 'xxx',
                'putangina', 'potangina', 'puta', 'punyeta', 'gago', 'gaga', 'tangina',
                'leche', 'buwisit', 'kupal', 'tarantado', 'pakshet', 'pakyu', 'putcha',
                'kantot', 'iyot', 'jakol', 'tite', 'pekpek', 'ulol', 'bobo',
                'ptngina', 'tngina', 'ulul',
                'tanga', 'inutil', 'ogag', 'engot', 'gagu',
                'putaragis', 'putaena', 'bwiset', 'bwisit', 'bwakanangina', 'bwakananginamo',
                'hindot', 'libog', 'salsal', 'bayag', 'burat', 'pokpok',
                'biot', 'bayot', 'bading',
                'gunggong', 'kolokoy', 'hinayupak', 'lintik', 'demonyo',
                'punyemas', 'burikat', 'pokpokin',
                'yawa', 'yawaa', 'buang', 'otin', 'bilat', 'pisti', 'piste', 'atay', 'amaw', 'yati',
                'cono', 'joder', 'cabron', 'mierda', 'pendejo', 'verga', 'chinga', 'culero',
                'sibal', 'ssibal', 'gaeseki', 'jiral', 'byeongsin',
                'kuso', 'kutabare', 'chinko', 'manko'
            ];
            var blockedPhrases = [
                'kill yourself', 'kill ur self', 'kill your self',
                'putang ina', 'putang ina mo', 'tang ina', 'tangina mo',
                'anak ng puta', 'anak ka ng puta', 'bwakanang ina', 'bwakanang ina mo',
                'gago ka', 'ulol ka', 'biot ka', 'bayot ka',
                'kupal ka', 'tarantado ka',
                'puta ka', 'gago mo'
            ];
            var blockedNativeTerms = [
                '\uC2DC\uBC1C', '\uC528\uBC1C', '\uAC1C\uC0C8\uB07C', '\uBCD1\uC2E0', '\uC9C0\uB784', '\uCC3D\uB140', '\uBCF4\uC9C0', '\uC790\uC9C0',
                '\u304F\u305D', '\u304F\u305F\u3070\u308C', '\u3061\u3093\u3053', '\u307E\u3093\u3053', '\u6B7B\u306D', '\u3046\u3093\u3053',
                '\u64CD\u4F60\u5988', '\u4F60\u5988\u7684', '\u4ED6\u5988\u7684', '\u53BB\u6B7B', '\u50BB\u903C', '\u8349\u6CE5\u9A6C', '\u8085\u4F60',
                'co\u00F1o', 'cabr\u00F3n'
            ];

            for (var pi = 0; pi < blockedPhrases.length; pi++) {
                var phrasePattern = new RegExp('\\b' + blockedPhrases[pi].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                if (phrasePattern.test(normalized)) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            var textLower = text.toLowerCase();
            for (var ni = 0; ni < blockedNativeTerms.length; ni++) {
                if (textLower.indexOf(blockedNativeTerms[ni].toLowerCase()) !== -1) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            for (var ti = 0; ti < blockedTerms.length; ti++) {
                var term = blockedTerms[ti];
                var pattern = new RegExp('\\b' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                if (pattern.test(normalized) || compact.indexOf(term) !== -1) {
                    return 'Message blocked due to inappropriate language. Please edit and try again.';
                }
            }

            return '';
        }

        function looksLikeStandaloneMediaFilename(value) {
            var text = String(value || '').trim();
            if (!text || /\s/.test(text)) { return false; }
            return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(text);
        }

        function renderEmojiPicker() {
            if (!emojiGridEl) { return; }
            var query = emojiSearchEl ? emojiSearchEl.value.trim() : '';
            var source = emojiCatalog[activeEmojiCategory] || [];
            var filtered = source.filter(function (e) {
                return blockedChatEmojis.indexOf(e) === -1 && emojiMatchesQuery(e, query);
            });

            emojiGridEl.innerHTML = filtered.map(function (emoji) {
                return '<button type="button" class="chat-emoji-item" data-chat-emoji="' + emoji + '">' + emoji + '</button>';
            }).join('');

            if (emojiEmptyEl) {
                emojiEmptyEl.style.display = filtered.length ? 'none' : 'block';
            }
        }

        function closeEmojiPicker() {
            if (emojiPickerEl) {
                emojiPickerEl.classList.remove('show');
            }
        }

        function insertEmojiAtCursor(emoji) {
            if (!inputEl || !emoji) { return; }
            var start = inputEl.selectionStart || 0;
            var end = inputEl.selectionEnd || 0;
            var value = inputEl.value || '';
            inputEl.value = value.slice(0, start) + emoji + value.slice(end);
            var caret = start + emoji.length;
            inputEl.focus();
            inputEl.setSelectionRange(caret, caret);
            autoGrowInput();
            updateSendBtn();
        }

        function clearMediaPreview() {
            if (mediaInputEl) { mediaInputEl.value = ''; }
            if (previewEl) { previewEl.classList.remove('has-file'); }
            if (previewThumbEl) { previewThumbEl.style.display = 'none'; previewThumbEl.src = ''; }
            if (previewNameEl) { previewNameEl.textContent = ''; }
        }

        if (attachBtnEl && mediaInputEl) {
            attachBtnEl.addEventListener('click', function () {
                mediaInputEl.click();
            });
            mediaInputEl.addEventListener('change', function () {
                var file = mediaInputEl.files && mediaInputEl.files[0];
                if (!file) { clearMediaPreview(); updateSendBtn(); return; }
                if (file.type.indexOf('image/') !== 0) {
                    showAlert('error', 'Only image files are allowed.');
                    clearMediaPreview();
                    updateSendBtn();
                    return;
                }
                if (file.size > (10 * 1024 * 1024)) {
                    showAlert('error', 'Image is too large (max 10 MB).');
                    clearMediaPreview();
                    updateSendBtn();
                    return;
                }
                if (previewEl) { previewEl.classList.add('has-file'); }
                if (previewNameEl) { previewNameEl.textContent = file.name; }
                if (previewThumbEl) {
                    if (file.type.indexOf('image') === 0) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            previewThumbEl.src = e.target.result;
                            previewThumbEl.style.display = '';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewThumbEl.style.display = 'none';
                    }
                }
                updateSendBtn();
                if (inputEl) {
                    inputEl.focus();
                }
            });
        }

        if (previewRemoveEl) {
            previewRemoveEl.addEventListener('click', function () {
                clearMediaPreview();
                updateSendBtn();
            });
        }

        if (emojiBtnEl && emojiPickerEl) {
            emojiBtnEl.addEventListener('click', function (event) {
                event.preventDefault();
                emojiPickerEl.classList.toggle('show');
                if (emojiPickerEl.classList.contains('show')) {
                    renderEmojiPicker();
                    if (emojiSearchEl) { emojiSearchEl.focus(); }
                }
            });

            if (emojiGridEl) {
                emojiGridEl.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-chat-emoji]');
                    if (!btn) { return; }
                    insertEmojiAtCursor(btn.getAttribute('data-chat-emoji') || '');
                    closeEmojiPicker();
                });
            }

            if (emojiTabsEl) {
                emojiTabsEl.addEventListener('click', function (event) {
                    var tab = event.target.closest('[data-emoji-cat]');
                    if (!tab) { return; }
                    activeEmojiCategory = tab.getAttribute('data-emoji-cat') || 'smileys';
                    var allTabs = emojiTabsEl.querySelectorAll('[data-emoji-cat]');
                    allTabs.forEach(function (el) {
                        el.classList.toggle('active', el === tab);
                    });
                    renderEmojiPicker();
                });
            }

            if (emojiSearchEl) {
                emojiSearchEl.addEventListener('input', renderEmojiPicker);
            }
        }

        if (scrollBtnEl && threadEl) {
            scrollBtnEl.addEventListener('click', function () {
                scrollThreadToBottom(true);
                updateScrollBtn();
            });
            threadEl.addEventListener('scroll', function () {
                updateScrollBtn();
                closeMessageActionMenu();
            });
        }

        if (threadEl) {
            threadEl.addEventListener('click', function (event) {
                var mediaEl = event.target.closest('[data-media-viewer]');
                if (mediaEl) {
                    event.preventDefault();
                    event.stopPropagation();
                    openMediaModal(
                        mediaEl.getAttribute('data-media-viewer') || '',
                        mediaEl.getAttribute('src') || '',
                        mediaEl.getAttribute('data-media-viewer') === 'video' ? 'Video' : 'Image'
                    );
                    return;
                }

                var reactionBtn = event.target.closest('.msg-reaction-badge[data-reaction-mid]');
                if (reactionBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    var reactionMid = parseInt(reactionBtn.getAttribute('data-reaction-mid') || '0', 10);
                    openReactionsModal(reactionMid);
                    return;
                }

                var actionBtn = event.target.closest('.msg-hover-menu-btn');
                if (actionBtn) {
                    event.preventDefault();
                    event.stopPropagation();
                    var mid = parseInt(actionBtn.getAttribute('data-message-id') || '0', 10);
                    openMessageActionMenu(mid, actionBtn);
                }
            });
        }

        if (messageActionMenuEl) {
            messageActionMenuEl.addEventListener('click', function (event) {
                var emojiBtn = event.target.closest('.msg-emoji-btn');
                if (emojiBtn) {
                    var emoji = emojiBtn.getAttribute('data-emoji') || '';
                    if (!emoji || !activeMessageActionId) { return; }
                    var reactionMessageId = activeMessageActionId;
                    closeMessageActionMenu();
                    reactToMessage(reactionMessageId, emoji);
                    return;
                }

                var action = event.target.closest('.msg-action-item');
                if (!action || !activeMessageActionId) { return; }
                var type = action.getAttribute('data-msg-action') || '';
                var mid = activeMessageActionId;
                var msg = messageCache[String(mid)];
                closeMessageActionMenu();
                if (!msg) { return; }

                if (type === 'reply') {
                    setReplyTarget(msg);
                    autoGrowInput();
                    updateSendBtn();
                    inputEl.focus();
                } else if (type === 'pin') {
                    var wasPinned = !!msg.is_pinned;
                    togglePinMessageById(mid, !wasPinned);
                } else if (type === 'unsend') {
                    if (!msg.is_own) { return; }
                    openConfirmModal(
                        'Unsend this message?',
                        'This message will be removed from the conversation.',
                        function () { unsendMessageById(mid); },
                        'Unsend'
                    );
                } else if (type === 'remove') {
                    if (!msg.is_own || !msg.is_unsent) { return; }
                    openConfirmModal(
                        'Remove this unsent bubble?',
                        'This will permanently delete this unsent message.',
                        function () { removeMessageById(mid); },
                        'Remove'
                    );
                } else if (type === 'report') {
                    if (msg.is_own) { return; }
                    openReportModal(function (selectedReason) {
                        openConfirmModal(
                            'Report this message?',
                            'This will send the message to chat reports for review. Reason: ' + selectedReason,
                            function () { reportMessageById(mid, selectedReason); },
                            'Report'
                        );
                    });
                }
            });
        }

        // â”€â”€ BioTern Chat compose behavior: auto-grow + Enter to send â”€â”€
        function autoGrowInput() {
            if (!inputEl) { return; }
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
        }

        if (inputEl) {
            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    if (isMobileLayout()) {
                        return;
                    }
                    event.preventDefault();
                    var hasMediaOnEnter = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
                    var hasTextOnEnter = inputEl.value.replace(/[\r\n\s]+/g, '') !== '';
                    if (!hasTextOnEnter && !hasMediaOnEnter) {
                        return;
                    }
                    if (formEl) {
                        formEl.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            });
            inputEl.addEventListener('input', function () {
                autoGrowInput();
                updateSendBtn();
                clearComposeWarning();
            });
            autoGrowInput();
        }

        if (formEl) {
            formEl.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!selectedUserId || !inputEl) {
                    return;
                }
                var message = inputEl.value.trim();
                var normalizedMessage = inputEl.value.replace(/[\r\n\s]+/g, '');
                var hasMedia = mediaInputEl && mediaInputEl.files && mediaInputEl.files.length > 0;
                var mode = sendBtnEl && sendBtnEl.dataset ? sendBtnEl.dataset.mode : 'send';
                var moderationError = getChatModerationError(message);

                if (!hasMedia && normalizedMessage === '') {
                    message = mode === 'like' ? '\uD83D\uDC4D' : '';
                }

                if (!message && !hasMedia) {
                    return;
                }
                if (moderationError) {
                    showComposeWarning(moderationError);
                    if (inputEl) { inputEl.focus(); }
                    return;
                }
                var formData = new FormData(formEl);
                formData.set('action', 'send-message');
                formData.set('ajax', '1');
                formData.set('user_id', String(selectedUserId));
                formData.set('message', message);
                if (sendBtnEl) {
                    sendBtnEl.disabled = true;
                }
                postChatAction(formData, selectedUserId).then(function (result) {
                    if (result.payload && result.payload.ok) {
                        clearComposeWarning();
                        applyState(result.payload, { keepInput: false, forceScroll: true });
                        clearReplyTarget();
                        clearMediaPreview();
                        updateSendBtn();
                        autoGrowInput();
                    } else if (result.payload && !result.payload.ok) {
                        var errorMessage = result.payload.error ? result.payload.error : 'Failed to send.';
                        if (isModerationWarning(errorMessage)) {
                            showComposeWarning(errorMessage);
                        } else {
                            showAlert('error', errorMessage);
                        }
                    } else if (result.ok) {
                        clearComposeWarning();
                        fetchState(true, { forceScroll: true });
                    } else {
                        showAlert('error', 'Failed to send.');
                    }
                }).catch(function () {
                    fetchState(true, { forceScroll: true });
                }).finally(function () {
                    if (sendBtnEl) { sendBtnEl.disabled = false; }
                    if (inputEl) { inputEl.focus(); }
                });
            });
        }

        if (searchEl) {
            searchEl.addEventListener('input', function () {
                currentSearch = searchEl.value.trim();
                if (lastContactsData.length) {
                    renderContacts(lastContactsData);
                    return;
                }
                fetchState(false);
            });
        }

        if (replyRemoveEl) {
            replyRemoveEl.addEventListener('click', function () {
                clearReplyTarget();
                if (inputEl) { inputEl.focus(); }
            });
        }

        document.addEventListener('click', function (event) {
            if (!headerEl) { return; }
            if (!event.target.closest('.btchat-actions')) {
                closeHeaderMenus();
            }
            if (messageActionMenuEl && !event.target.closest('#msg-action-menu') && !event.target.closest('.msg-hover-menu-btn')) {
                closeMessageActionMenu();
            }
            if (emojiPickerEl && !event.target.closest('#chat-emoji-picker') && !event.target.closest('#chat-emoji-btn')) {
                closeEmojiPicker();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeHeaderMenus();
                closeConfirmModal();
                closeContactModal();
                closeReportModal();
                closeMessageActionMenu();
                closeEmojiPicker();
                closeReactionsModal();
                closeMediaModal();
            }
        });

        if (confirmCancelEl) {
            confirmCancelEl.addEventListener('click', closeConfirmModal);
        }

        if (contactCloseEl) {
            contactCloseEl.addEventListener('click', closeContactModal);
        }

        if (contactCloseSecondaryEl) {
            contactCloseSecondaryEl.addEventListener('click', closeContactModal);
        }

        if (contactMuteEl) {
            contactMuteEl.addEventListener('click', function () {
                if (!(contactModalUserId > 0)) {
                    return;
                }
                var currentlyMuted = isConversationMuted(contactModalUserId);
                setConversationMuted(contactModalUserId, !currentlyMuted);
                contactMuteEl.textContent = currentlyMuted ? 'Mute conversation' : 'Unmute conversation';
                if (contactMutedStateEl) {
                    contactMutedStateEl.textContent = currentlyMuted ? 'Unmuted' : 'Muted';
                }

                if (selectedContactRef && parseInt(selectedContactRef.id || '0', 10) === contactModalUserId) {
                    renderHeader(selectedContactRef);
                }
                clearComposeWarning();
            });
        }

        if (contactReportUserEl) {
            contactReportUserEl.addEventListener('click', function () {
                reportUserFromContactModal();
            });
        }

        if (reportCancelEl) {
            reportCancelEl.addEventListener('click', closeReportModal);
        }

        if (reportOkEl) {
            reportOkEl.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                suppressHeaderToggleUntil = Date.now() + 650;
                var reason = buildReportReason();
                var fn = pendingReportFn;
                closeReportModal();
                if (typeof fn === 'function') {
                    fn(reason);
                }
            });
        }

        if (confirmOkEl) {
            confirmOkEl.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                suppressHeaderToggleUntil = Date.now() + 650;
                var fn = pendingConfirmFn;
                closeConfirmModal();
                if (typeof fn === 'function') {
                    fn();
                }
                window.setTimeout(function () {
                    closeHeaderMenus();
                    closeMessageActionMenu();
                    scrubActionTooltips(document);
                }, 0);
            });
        }

        if (confirmModalEl) {
            confirmModalEl.addEventListener('click', function (event) {
                if (event.target === confirmModalEl) {
                    closeConfirmModal();
                }
            });
        }

        if (contactModalEl) {
            contactModalEl.addEventListener('click', function (event) {
                if (event.target === contactModalEl) {
                    closeContactModal();
                }
            });
        }

        if (reportModalEl) {
            reportModalEl.addEventListener('click', function (event) {
                if (event.target === reportModalEl) {
                    closeReportModal();
                }
            });
        }

        if (reactionsTabsEl) {
            reactionsTabsEl.addEventListener('click', function (event) {
                var tab = event.target.closest('[data-reaction-filter]');
                if (!tab || !reactionsModalState) { return; }
                reactionsModalState.filter = tab.getAttribute('data-reaction-filter') || 'all';
                renderReactionsModal();
            });
        }

        if (reactionsCloseEl) {
            reactionsCloseEl.addEventListener('click', closeReactionsModal);
        }

        if (reactionsModalEl) {
            reactionsModalEl.addEventListener('click', function (event) {
                if (event.target === reactionsModalEl) {
                    closeReactionsModal();
                }
            });
        }

        if (mediaModalCloseEl) {
            mediaModalCloseEl.addEventListener('click', closeMediaModal);
        }

        if (mediaModalDownloadEl) {
            mediaModalDownloadEl.addEventListener('click', downloadMediaFromModal);
        }

        if (mediaModalEl) {
            mediaModalEl.addEventListener('click', function (event) {
                if (event.target === mediaModalEl) {
                    closeMediaModal();
                }
            });
        }

        if (mobileLayoutQuery) {
            mobileLayoutQuery.addEventListener('change', function () {
                if (!isMobileLayout()) {
                    app.classList.remove('btchat-mobile-convo-open');
                    return;
                }
                if (selectedUserId > 0 && app.classList.contains('btchat-mobile-convo-open')) {
                    setMobileConversationOpen(true);
                }
            });
        }

        window.addEventListener('popstate', function (event) {
            if (!isMobileLayout()) {
                return;
            }
            var state = event.state || {};
            if (state.btchatView === 'conversation' && state.userId > 0) {
                selectedUserId = parseInt(state.userId, 10) || 0;
                if (!selectedUserId) {
                    setMobileConversationOpen(false);
                    return;
                }
                app.setAttribute('data-selected-user-id', String(selectedUserId));
                setActiveContactVisual(selectedUserId);
                setMobileConversationOpen(true);
                setThreadLoading(true);
                fetchState(false, { forceScroll: false });
                return;
            }

            setMobileConversationOpen(false);
            closeHeaderMenus();
            closeMessageActionMenu();
            closeEmojiPicker();
            closeReactionsModal();
        });

        bindHeaderMenu();
        scrubActionTooltips(app);
        bindAvatarFallback(app);

        if (isMobileLayout()) {
            setMobileHistoryState('list', 0);
        }

        clearReplyTarget();
        renderEmojiPicker();
        updateSendBtn();
        autoGrowInput();
        scrollThreadToBottom(true);
        updateScrollBtn();
        fetchState(false, { forceScroll: true });

        pollHandle = window.setInterval(function () {
            fetchState(false);
        }, 5000);
    })();
