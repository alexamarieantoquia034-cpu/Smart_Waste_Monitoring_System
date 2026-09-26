/*
 * Real-time updates for the waste dashboard and live classification page.
 * =====================================================================
 *
 * Subscribes to /api/live-stream (Server-Sent Events) and applies whatever
 * the ESP32-CAM pushes the moment it is written, so the UI never has to be
 * refreshed to show a new reading or detection.
 *
 * The server closes each connection after a TTL so a long-lived stream
 * cannot pin a PHP worker; EventSource reconnects on its own, and a backoff
 * is applied here so an unreachable server is not polled in a tight loop.
 *
 * Every DOM lookup is guarded. This file is shared by two pages that do not
 * have the same markup, and a missing node must never throw and take the
 * page's other scripts down with it.
 */
(function () {
    'use strict';

    var ENDPOINT = document.body ? document.body.getAttribute('data-live-stream') : null;

    if (!ENDPOINT || typeof window.EventSource === 'undefined') {
        return;
    }

    var RECONNECT_MIN = 2000;
    var RECONNECT_MAX = 15000;
    var backoff = RECONNECT_MIN;
    var source = null;
    var retryTimer = null;

    // ----------------------------------------------------------------- util

    function el(id) {
        return document.getElementById(id);
    }

    function on(node, event, handler) {
        if (node) {
            node.addEventListener(event, handler);
        }
    }

    function capitalize(value) {
        if (!value) {
            return '';
        }

        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    /**
     * Each SSE frame is re-dispatched as a DOM CustomEvent, so other scripts
     * can react without knowing anything about EventSource.
     */
    function fire(name, detail) {
        document.dispatchEvent(new CustomEvent('waste:' + name, { detail: detail }));
    }

    function parse(event) {
        try {
            return JSON.parse(event.data) || [];
        } catch (error) {
            return [];
        }
    }

    function setPill(node, state, text) {
        if (!node) {
            return;
        }

        node.classList.remove('sw-pill--ok', 'sw-pill--danger', 'sw-pill--muted');
        node.classList.add(state);
        node.textContent = text;
    }

    // ------------------------------------------------------------- connection

    function scheduleReconnect() {
        if (source) {
            source.close();
            source = null;
        }

        setPill(el('liveStatus'), 'sw-pill--danger', 'Offline');

        if (retryTimer) {
            return;
        }

        retryTimer = window.setTimeout(function () {
            retryTimer = null;
            connect();
        }, backoff);

        backoff = Math.min(RECONNECT_MAX, Math.round(backoff * 1.8));
    }

    function connect() {
        if (retryTimer) {
            window.clearTimeout(retryTimer);
            retryTimer = null;
        }

        source = new EventSource(ENDPOINT);

        source.onopen = function () {
            backoff = RECONNECT_MIN;
            setPill(el('liveStatus'), 'sw-pill--ok', 'Live');
        };

        source.onerror = function () {
            setPill(el('liveStatus'), 'sw-pill--danger', 'Reconnecting');

            // EventSource only retries by itself while the connection is
            // still parseable. Once it reports CLOSED, drive the reconnect.
            if (source.readyState === EventSource.CLOSED) {
                scheduleReconnect();
            }
        };

        on(source, 'reading', function (event) { fire('reading', parse(event)); });
        on(source, 'detection', function (event) { fire('detection', parse(event)); });
        on(source, 'alert', function (event) { fire('alert', parse(event)); });

        // The server ends the response at the TTL; EventSource reconnects, so
        // the only job here is to show that a reconnect is under way.
        on(source, 'bye', function () {
            setPill(el('liveStatus'), 'sw-pill--muted', 'Reconnecting');
        });
    }

    // --------------------------------------------------------- dashboard KPIs

    /*
     * The dashboard names its third card "bio" (that is the CSS modifier
     * class), while the API and the database both call the compartment
     * "biodegradable". Map the two so the id lookup works.
     */
    var CARD_KEYS = {
        plastic: 'plastic',
        paper: 'paper',
        biodegradable: 'bio',
        reject: 'reject'
    };

    /**
     * Mirrors the thresholds in dashboard/cards.blade.php, so a live update
     * and a freshly loaded page describe the same number the same way.
     */
    function hintFor(value) {
        if (value >= 90) {
            return 'Nearly full';
        }

        if (value >= 75) {
            return 'Schedule pickup';
        }

        return 'Within capacity';
    }

    function applyReading(reading) {
        if (!reading || !reading.levels) {
            return;
        }

        Object.keys(reading.levels).forEach(function (compartment) {
            var key = CARD_KEYS[compartment];

            if (!key) {
                return;
            }

            var card = el('liveLevel-' + key);

            if (!card) {
                return;
            }

            var level = reading.levels[compartment];
            var clamped = Math.max(0, Math.min(100, level));

            var bar = card.querySelector('.sw-meter__fill');
            if (bar) {
                bar.style.width = clamped + '%';
            }

            var value = card.querySelector('[data-live-value]');
            if (value) {
                value.textContent = (Math.round(level * 10) / 10) + '%';
            }

            var hint = card.querySelector('[data-live-hint]');
            if (hint) {
                hint.textContent = hintFor(clamped);
            }
        });
    }

    // -------------------------------------------------------------- detections

    function applyDetection(detection) {
        var list = el('mlRecent');

        if (!list) {
            return;
        }

        // Drop the "nothing yet" placeholder the server rendered.
        var placeholder = el('liveNoDetections');
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.removeChild(placeholder);
        }

        // A reconnect can replay a row that is already on the page.
        if (list.querySelector('[data-detection-id="' + detection.id + '"]')) {
            return;
        }

        var fallback = list.getAttribute('data-fallback') || '';
        var row = document.createElement('li');

        row.className = 'ml-recent__row';
        row.setAttribute('data-detection-id', detection.id);

        var thumb = document.createElement('img');
        thumb.className = 'ml-recent__thumb';
        thumb.width = 44;
        thumb.height = 44;
        thumb.alt = detection.waste_type;
        if (detection.image_url) {
            thumb.src = detection.image_url;
        } else if (fallback) {
            thumb.src = fallback;
        }

        var meta = document.createElement('div');
        meta.className = 'ml-recent__meta';

        var title = document.createElement('div');
        title.className = 'ml-recent__title';
        title.textContent = detection.waste_type;

        var sub = document.createElement('div');
        sub.className = 'ml-recent__sub';
        sub.textContent = capitalize(detection.compartment) + ' bin · just now';

        meta.appendChild(title);
        meta.appendChild(sub);

        var pill = document.createElement('span');
        pill.className = 'sw-pill sw-pill--info ms-auto';
        pill.textContent = Number(detection.confidence).toFixed(1) + '%';

        row.appendChild(thumb);
        row.appendChild(meta);
        row.appendChild(pill);

        list.insertBefore(row, list.firstChild);

        // The Blade partial caps the list at six rows; keep it that way.
        while (list.children.length > 6) {
            list.removeChild(list.lastChild);
        }
    }

    // ------------------------------------------------------------------ boot

    // Only the readings and detections are rendered here. Alerts are left to
    // layouts/navbar.blade.php, which already owns the notification badge and
    // refreshes it from these same events.

    on(document, 'waste:reading', function (event) {
        (event.detail || []).forEach(applyReading);
    });

    on(document, 'waste:detection', function (event) {
        (event.detail || []).forEach(applyDetection);
    });

    // Do not hold the socket open while the tab is in the background.
    on(document, 'visibilitychange', function () {
        if (document.hidden) {
            if (source) {
                source.close();
                source = null;
            }
        } else if (!source && !retryTimer) {
            backoff = RECONNECT_MIN;
            connect();
        }
    });

    connect();
})();
