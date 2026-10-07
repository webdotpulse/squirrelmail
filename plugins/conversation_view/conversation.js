/**
 * conversation.js - Conversation View Plugin Client-Side Interactivity
 *
 * Micro-interactions:
 * - Dynamic inline card expansion and async body loading via AJAX.
 * - Smooth discard draft action with instant DOM removal.
 * - Smooth scroll handler for thread jump links.
 */

(function() {
    'use strict';

    /**
     * Toggle expand/collapse for a conversation message card
     *
     * @param {string} cardId DOM id of the card
     */
    window.cvToggleCard = function(cardId) {
        var card = document.getElementById(cardId);
        if (!card) return;

        var body = card.querySelector('.cv-card-body');
        if (!body) return;

        var isExpanded = card.classList.contains('cv-card-expanded');

        if (isExpanded) {
            card.classList.remove('cv-card-expanded');
            body.style.display = 'none';
        } else {
            card.classList.add('cv-card-expanded');
            body.style.display = 'block';

            // Check if body needs to be loaded via AJAX
            var spinner = body.querySelector('.cv-loading-spinner');
            if (spinner) {
                var container = document.getElementById('cv-conversation-thread');
                var ajaxUrl = container ? container.getAttribute('data-ajax-url') : '';
                var token = container ? container.getAttribute('data-token') : '';
                var mailbox = card.getAttribute('data-mailbox');
                var uid = card.getAttribute('data-uid');

                if (ajaxUrl && mailbox && uid) {
                    var url = ajaxUrl + '?action=get_body&mailbox=' + encodeURIComponent(mailbox) +
                              '&uid=' + encodeURIComponent(uid) +
                              '&smtoken=' + encodeURIComponent(token);

                    fetch(url)
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data && data.success && data.body) {
                                body.innerHTML = '<div class="cv-body-rendered">' + data.body + '</div>';
                            } else {
                                body.innerHTML = '<div style="color: var(--sm-danger, #dc2626); padding: 8px;">' +
                                    (data.error || 'Failed to load message content.') + '</div>';
                            }
                        })
                        .catch(function(err) {
                            body.innerHTML = '<div style="color: var(--sm-danger, #dc2626); padding: 8px;">' +
                                'Network error loading message body.</div>';
                        });
                }
            }
        }
    };

    /**
     * Discard a draft reply asynchronously
     *
     * @param {Event} e Click event
     * @param {string} mailbox Mailbox name of the draft
     * @param {number} uid UID of the draft
     * @param {string} cardId DOM id of the card
     */
    window.cvDiscardDraft = function(e, mailbox, uid, cardId) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (!confirm('Are you sure you want to discard this draft reply?')) {
            return;
        }

        var card = document.getElementById(cardId);
        var container = document.getElementById('cv-conversation-thread');
        var ajaxUrl = container ? container.getAttribute('data-ajax-url') : '';
        var token = container ? container.getAttribute('data-token') : '';

        if (!ajaxUrl || !mailbox || !uid) return;

        var url = ajaxUrl + '?action=discard_draft&mailbox=' + encodeURIComponent(mailbox) +
                  '&uid=' + encodeURIComponent(uid) +
                  '&smtoken=' + encodeURIComponent(token);

        if (card) {
            card.style.opacity = '0.5';
            card.style.pointerEvents = 'none';
        }

        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.transform = 'translateX(20px)';
                        card.style.opacity = '0';
                        setTimeout(function() {
                            card.remove();
                            // Update or decrement draft badges
                            var draftBadge = container.querySelector('.cv-badge-draft');
                            if (draftBadge) {
                                draftBadge.remove();
                            }
                        }, 300);
                    }
                } else {
                    alert(data.error || 'Could not discard draft.');
                    if (card) {
                        card.style.opacity = '1';
                        card.style.pointerEvents = 'auto';
                    }
                }
            })
            .catch(function(err) {
                alert('Network error discarding draft.');
                if (card) {
                    card.style.opacity = '1';
                    card.style.pointerEvents = 'auto';
                }
            });
    };

    // Smooth scroll if anchor is in URL or clicked
    function cvScrollToThread() {
        var el = document.getElementById('cv-conversation-thread');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.addEventListener('click', function(e) {
        var anchor = e.target.closest('a[href*="#cv-conversation-thread"]');
        if (anchor) {
            var thread = document.getElementById('cv-conversation-thread');
            if (thread) {
                e.preventDefault();
                cvScrollToThread();
            }
        }
    });

    window.addEventListener('hashchange', function() {
        if (window.location.hash === '#cv-conversation-thread') {
            setTimeout(cvScrollToThread, 150);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash === '#cv-conversation-thread') {
            setTimeout(cvScrollToThread, 250);
        }
    });

})();
