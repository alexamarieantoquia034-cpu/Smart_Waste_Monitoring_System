/**
 * Live waste classification for the Smart Waste Monitoring System.
 *
 * Runs the trained Teachable Machine model (MobileNetV2 transfer learning)
 * entirely in the browser and feeds it frames pulled from the ESP32-CAM
 * MJPEG stream, so no Python service and no build step is required.
 *
 * Frame source. The camera is reached through the Laravel proxy at
 * /api/esp32cam/stream rather than directly. That matters: a cross-origin
 * <img> would taint the capture canvas and tf.browser.fromPixels() would then
 * throw a security error, and a direct http:// URL is blocked outright on an
 * https page. Relaying it keeps the frame same-origin.
 *
 * Preprocessing. The code below mirrors @teachablemachine/image exactly:
 *   cropTo(source, 224)  ->  centre-square crop onto a 224x224 canvas
 *   fromPixels -> expandDims(0) -> toFloat().div(127).sub(1)
 * The network ends in a softmax Dense layer, so the raw output already is a
 * probability distribution and is never normalised a second time.
 */
(function () {
    'use strict';

    var root = document.getElementById('ml-live');

    if (!root) {
        return;
    }

    var config = JSON.parse(root.getAttribute('data-config')) || {};
    var labels = config.labels || [];
    var classes = {};

    (config.classes || []).forEach(function (item) {
        classes[item.label] = item;
    });

    var ENDPOINTS = config.endpoints || {};

    var IMAGE_SIZE = config.image_size || 224;

    // Logs look better with a frame that is larger than the model's input.
    var SNAPSHOT_SIZE = IMAGE_SIZE * 2;
    var TOP_K = Math.max(1, config.top_k || 3);
    var STABLE_FRAMES = Math.max(1, config.stable_frames || 3);
    var FRAME_INTERVAL = Math.max(120, config.frame_interval_ms || 500);

    var els = {
        stage: document.getElementById('mlStage'),
        stream: document.getElementById('mlStream'),
        webcam: document.getElementById('mlWebcam'),
        stageEmpty: document.getElementById('mlStageEmpty'),
        stageEmptyText: document.getElementById('mlStageEmptyText'),
        stageBadge: document.getElementById('mlStageBadge'),
        cameraPill: document.getElementById('mlCameraPill'),
        cameraPillText: document.getElementById('mlCameraPillText'),
        cameraMeta: document.getElementById('mlCameraMeta'),
        modelPill: document.getElementById('mlModelPill'),
        modelPillText: document.getElementById('mlModelPillText'),
        toggle: document.getElementById('mlToggle'),
        toggleText: document.getElementById('mlToggleText'),
        save: document.getElementById('mlSave'),
        perfText: document.getElementById('mlPerfText'),
        threshold: document.getElementById('mlThreshold'),
        thresholdValue: document.getElementById('mlThresholdValue'),
        autoSave: document.getElementById('mlAutoSave'),
        verdict: document.getElementById('mlVerdict'),
        scores: document.getElementById('mlScores'),
        status: document.getElementById('mlStatus'),
        recent: document.getElementById('mlRecent'),
        fileInput: document.getElementById('mlFileInput')
    };

    var inferCanvas = document.createElement('canvas');
    var snapCanvas = document.createElement('canvas');

    var model = null;
    var running = false;
    var timer = null;
    var source = 'esp32';
    var fileImage = null;
    var mediaStream = null;
    var inflight = false;
    var lastRanked = null;
    var misses = 0;

    var stable = { label: null, count: 0, saved: false };
    var perf = { frames: 0, since: Date.now(), last: 0 };

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // ------------------------------------------------------------ helpers

    function node(tag, className, text) {
        var element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (text !== undefined && text !== null) {
            element.textContent = text;
        }

        return element;
    }

    function say(message, kind) {
        els.status.textContent = message || '';
        els.status.className = 'ml-note mt-3 mb-0' + (kind ? ' ml-note--' + kind : '');
    }

    function setPill(pill, textNode, modifier, text) {
        pill.className = 'sw-pill ' + modifier;
        textNode.textContent = text;
    }

    function threshold() {
        return els.threshold ? Number(els.threshold.value) : (config.threshold || 70);
    }

    function resetStable() {
        stable.label = null;
        stable.count = 0;
        stable.saved = false;
    }


    // ------------------------------------------------------------- model

    function fail(message) {
        say(message, 'danger');
        setPill(els.modelPill, els.modelPillText, 'sw-pill--danger', 'Unavailable');
    }

    function loadModel() {
        if (typeof window.tf === 'undefined') {
            // Name the exact URL that was requested: "did not load" on its own
            // is impossible to act on, because the file is usually present and
            // the real cause is a stale cached 404, a wrong host, or the page
            // being opened from a different machine than the server.
            fail('TensorFlow.js did not load from ' + (config.runtime_url || 'the configured URL')
                + ' — hard-refresh (Ctrl+F5). If it persists, open that URL directly;'
                + ' a 404 means the file is missing, run: php artisan waste:sync-model');

            return Promise.reject(new Error('TensorFlow.js missing'));
        }

        if (!config.installed) {
            fail('Model files are missing. Run: php artisan waste:sync-model');

            return Promise.reject(new Error('Model files missing'));
        }

        say('Loading the trained model…');

        return window.tf.loadLayersModel(config.model_url)
            .then(function (loaded) {
                model = loaded;

                setPill(els.modelPill, els.modelPillText, 'sw-pill--ok', 'Model ready');
                els.save.disabled = false;
                say('Model ready — ' + labels.length + ' classes, '
                    + IMAGE_SIZE + '×' + IMAGE_SIZE + ' input.');

                refreshCameraStatus();

                return model;
            })
            .catch(function (error) {
                fail('Could not load the model: ' + error.message);

                throw error;
            });
    }

    function refreshCameraStatus() {
        if (!config.camera || !config.camera.configured) {
            setPill(els.cameraPill, els.cameraPillText, 'sw-pill--muted', 'Not configured');

            return;
        }

        fetch(ENDPOINTS.status, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.online) {
                    setPill(els.cameraPill, els.cameraPillText, 'sw-pill--ok', 'Online');
                    els.cameraMeta.textContent = data.url + ' · '
                        + (data.width || '?') + '×' + (data.height || '?')
                        + ' · ' + data.latency_ms + ' ms';
                } else {
                    setPill(els.cameraPill, els.cameraPillText, 'sw-pill--danger', 'Offline');
                    els.cameraMeta.textContent = data.message || 'Camera unreachable';
                }
            })
            .catch(function () {
                setPill(els.cameraPill, els.cameraPillText, 'sw-pill--danger', 'Offline');
            });
    }

    // ---------------------------------------------------------- inference

    /**
     * Centre-square crop of a frame rendered into a size x size canvas.
     * Mirrors cropTo() from @teachablemachine/image so the object lands in
     * the same framing the model was trained on.
     */
    function crop(frame, size, target) {
        var width = frame.videoWidth || frame.naturalWidth || frame.width;
        var height = frame.videoHeight || frame.naturalHeight || frame.height;

        if (!width || !height) {
            return false;
        }

        var scale = size / Math.min(width, height);
        var scaledWidth = Math.ceil(width * scale);
        var scaledHeight = Math.ceil(height * scale);

        target.width = size;
        target.height = size;

        target.getContext('2d').drawImage(
            frame,
            ~~((scaledWidth - size) / 2) * -1,
            ~~((scaledHeight - size) / 2) * -1,
            scaledWidth,
            scaledHeight
        );

        return true;
    }

    /**
     * One forward pass over the prepared canvas.
     *
     * The final layer of the graph is a softmax Dense, so the values read
     * back here are already probabilities and must not be normalised again.
     */
    function forward() {
        return window.tf.tidy(function () {
            var input = window.tf.browser.fromPixels(inferCanvas)
                .expandDims(0)
                .toFloat()
                .div(window.tf.scalar(127))
                .sub(window.tf.scalar(1));

            var output = model.predict(input);

            if (Array.isArray(output)) {
                output = output[0];
            }

            return output.dataSync();
        });
    }

    function rank(values) {
        var ranked = [];

        for (var i = 0; i < labels.length; i++) {
            ranked.push({ label: labels[i], score: (values[i] || 0) * 100 });
        }

        return ranked.sort(function (a, b) { return b.score - a.score; });
    }

    // ------------------------------------------------------------ render

    function renderVerdict(top) {
        var meta = classes[top.label] || {};
        var color = meta.color || 'var(--sw-teal-600)';

        els.verdict.textContent = '';

        var row = node('div', 'ml-verdict__row');
        row.style.setProperty('--kpi', color);

        var icon = node('span', 'sw-kpi__icon');
        icon.innerHTML = '<i class="bi ' + (meta.icon || 'bi-trash3') + '"></i>';

        var text = node('div', 'ml-verdict__text');
        text.appendChild(node('div', 'ml-verdict__label', top.label));
        text.appendChild(node('div', 'ml-verdict__bin', meta.compartment_label || 'Unmapped'));

        row.appendChild(icon);
        row.appendChild(text);
        row.appendChild(node('span', 'ml-verdict__pct', top.score.toFixed(1) + '%'));

        els.verdict.appendChild(row);

        var meter = node('div', 'sw-meter');
        meter.style.marginTop = '0';
        meter.style.setProperty('--kpi', color);

        var fill = node('div', 'sw-meter__fill');
        fill.style.width = Math.max(0, Math.min(100, top.score)) + '%';
        meter.appendChild(fill);

        els.verdict.appendChild(meter);
    }

    function renderScores(ranked) {
        els.scores.textContent = '';

        ranked.slice(0, TOP_K).forEach(function (item) {
            var meta = classes[item.label] || {};
            var line = node('div', 'ml-score');
            line.style.setProperty('--kpi', meta.color || 'var(--sw-teal-600)');

            line.appendChild(node('span', 'ml-score__name', item.label));

            var meter = node('div', 'sw-meter ml-score__meter');
            meter.style.marginTop = '0';

            var fill = node('div', 'sw-meter__fill');
            fill.style.width = Math.max(0, Math.min(100, item.score)) + '%';
            meter.appendChild(fill);

            line.appendChild(meter);
            line.appendChild(node('span', 'ml-score__pct', item.score.toFixed(1) + '%'));

            els.scores.appendChild(line);
        });
    }

    function updatePerf(milliseconds) {
        perf.frames++;
        perf.last = milliseconds;

        var now = Date.now();
        var elapsed = now - perf.since;

        if (elapsed < 1000) {
            return;
        }

        els.perfText.textContent = Math.round(perf.frames * 1000 / elapsed)
            + ' fps · ' + perf.last + ' ms/frame';

        perf.frames = 0;
        perf.since = now;
    }

    // -------------------------------------------------------------- save

    /**
     * Only write a log entry once the same class has cleared the confidence
     * threshold for several frames in a row, so a hand hovering near the
     * chute cannot flood the table.
     */
    function maybeAutoSave(ranked) {
        if (!els.autoSave.checked) {
            resetStable();

            return;
        }

        var top = ranked[0];

        if (!top || top.score < threshold()) {
            resetStable();

            return;
        }

        if (stable.label === top.label) {
            stable.count++;
        } else {
            stable.label = top.label;
            stable.count = 1;
        }

        if (stable.count >= STABLE_FRAMES && !stable.saved) {
            stable.saved = true;
            save(top);
        }
    }

    function save(top) {
        if (!top || !model) {
            return;
        }

        var body = new URLSearchParams();

        body.append('label', top.label);
        body.append('confidence', top.score.toFixed(2));
        body.append('source', source);

        if (snapCanvas.width) {
            body.append('image', snapCanvas.toDataURL('image/jpeg', 0.8));
        }

        say('Saving ' + top.label + '…');

        fetch(ENDPOINTS.store, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    say(describeFailure(result.data), 'danger');

                    return;
                }

                var saved = result.data.classification;

                say('Saved #' + saved.id + ' → ' + saved.compartment_label + '.', 'ok');
                prependRecent(saved);
            })
            .catch(function (error) {
                say('Save failed: ' + error.message, 'danger');
            });
    }

    function describeFailure(data) {
        if (data && data.errors) {
            return Object.keys(data.errors)
                .map(function (key) { return data.errors[key][0]; })
                .join(' ');
        }

        return (data && data.message) || 'The detection could not be saved.';
    }


    /**
     * Mirror the new row into the "Recent detections" card without a reload.
     */
    function prependRecent(saved) {
        if (!els.recent) {
            return;
        }

        if (els.recent.querySelector('.sw-empty')) {
            els.recent.textContent = '';
        }

        var list = els.recent.querySelector('.ml-recent');

        if (!list) {
            list = node('ul', 'ml-recent list-unstyled mb-0');
            els.recent.appendChild(list);
        }

        var item = node('li', 'ml-recent__row');
        var thumb = document.createElement('img');

        thumb.className = 'ml-recent__thumb';
        thumb.width = 44;
        thumb.height = 44;
        thumb.alt = saved.waste_type;

        if (saved.image_url) {
            thumb.src = saved.image_url;
        }

        item.appendChild(thumb);

        var meta = node('div', 'ml-recent__meta');
        meta.appendChild(node('div', 'ml-recent__title', saved.waste_type));
        meta.appendChild(node('div', 'ml-recent__sub', saved.compartment_label));
        item.appendChild(meta);

        item.appendChild(node('span', 'sw-pill sw-pill--info ms-auto',
            saved.confidence.toFixed(1) + '%'));

        list.insertBefore(item, list.firstChild);

        while (list.children.length > 6) {
            list.removeChild(list.lastChild);
        }
    }

    // ------------------------------------------------------------ frames

    function currentFrame() {
        if (source === 'file') {
            return fileImage;
        }

        if (source === 'webcam') {
            return els.webcam;
        }

        return els.stream;
    }

    function classify() {
        if (!model || inflight) {
            return;
        }

        var frame = currentFrame();

        if (!frame) {
            return;
        }

        // The MJPEG <img> reports naturalWidth 0 until the first frame lands.
        if (!crop(frame, IMAGE_SIZE, inferCanvas)) {
            misses++;

            if (misses === 12) {
                say('Still no frames from the camera. Check ESP32CAM_URL and that the '
                    + 'camera is streaming.', 'warn');
            }

            return;
        }

        misses = 0;
        crop(frame, SNAPSHOT_SIZE, snapCanvas);
        inflight = true;

        var startedAt = performance.now();
        var ranked;

        try {
            ranked = rank(forward());
        } catch (error) {
            inflight = false;
            say('Inference failed: ' + error.message, 'danger');

            return;
        }

        inflight = false;

        lastRanked = ranked;
        renderVerdict(ranked[0]);
        renderScores(ranked);
        updatePerf(Math.round(performance.now() - startedAt));
        maybeAutoSave(ranked);
    }

    function loop() {
        if (!running) {
            return;
        }

        timer = window.setTimeout(function () {
            loop();
        }, FRAME_INTERVAL);

        classify();
    }


    // ----------------------------------------------------------- sources

    function openSource() {
        els.stageEmpty.hidden = true;
        els.stageBadge.hidden = false;

        if (source === 'esp32') {
            if (!config.camera || !config.camera.configured) {
                return Promise.reject(new Error(
                    'Set ESP32CAM_URL in .env before streaming from the camera.'
                ));
            }

            els.webcam.hidden = true;
            els.stream.hidden = false;
            els.stream.src = ENDPOINTS.stream;

            return Promise.resolve();
        }

        if (source === 'webcam') {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return Promise.reject(new Error('This browser has no camera API.'));
            }

            return navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            }).then(function (stream) {
                stopWebcam();
                mediaStream = stream;
                els.stream.removeAttribute('src');
                els.stream.hidden = true;
                els.webcam.hidden = false;
                els.webcam.srcObject = stream;

                return els.webcam.play();
            });
        }

        return Promise.reject(new Error('Choose an image first.'));
    }

    function stopWebcam() {
        if (mediaStream) {
            mediaStream.getTracks().forEach(function (track) { track.stop(); });
            mediaStream = null;
        }

        els.webcam.srcObject = null;
    }

    function start() {
        if (running || !model) {
            return;
        }

        if (source === 'file') {
            els.fileInput.click();

            return;
        }

        running = true;
        els.toggleText.textContent = 'Stop';
        els.toggle.querySelector('i').className = 'bi bi-stop-fill';
        resetStable();

        openSource()
            .then(function () {
                loop();
            })
            .catch(function (error) {
                say(error.message, 'danger');
                stop();
            });
    }

    function stop() {
        running = false;
        window.clearTimeout(timer);
        timer = null;

        els.toggleText.textContent = source === 'file' ? 'Choose image' : 'Start classifying';
        els.toggle.querySelector('i').className = 'bi bi-play-fill';
        els.stageBadge.hidden = true;
        els.stageEmpty.hidden = false;
        els.perfText.textContent = 'idle';

        els.stream.removeAttribute('src');
        els.stream.hidden = true;
        stopWebcam();
        els.webcam.hidden = true;
    }

    function selectSource(name) {
        if (name === source) {
            return;
        }

        if (running) {
            stop();
        }

        source = name;
        fileImage = null;
        lastRanked = null;

        Array.prototype.forEach.call(
            document.querySelectorAll('.ml-source'),
            function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-source') === name);
            }
        );

        if (name === 'file') {
            els.stageEmptyText.textContent = 'Choose a photo to classify.';
            els.toggleText.textContent = 'Choose image';
        } else {
            els.stageEmptyText.textContent = 'Press start to begin classifying.';
            els.toggleText.textContent = 'Start classifying';
        }

        els.stageEmpty.hidden = false;
    }


    // -------------------------------------------------------------- wire

    els.toggle.addEventListener('click', function () {
        if (running) {
            stop();
        } else {
            start();
        }
    });

    els.save.addEventListener('click', function () {
        if (!lastRanked || !lastRanked.length) {
            say('Classify a frame first.', 'warn');

            return;
        }

        save(lastRanked[0]);
    });

    els.threshold.addEventListener('input', function () {
        els.thresholdValue.textContent = els.threshold.value + '%';
    });

    els.fileInput.addEventListener('change', function () {
        var file = els.fileInput.files && els.fileInput.files[0];

        if (!file) {
            return;
        }

        var url = URL.createObjectURL(file);

        fileImage = new Image();

        fileImage.onload = function () {
            els.stageEmpty.hidden = true;
            els.stageBadge.hidden = false;
            classify();
            URL.revokeObjectURL(url);
        };

        fileImage.onerror = function () {
            URL.revokeObjectURL(url);
            say('That image could not be read.', 'danger');
        };

        fileImage.src = url;
    });

    Array.prototype.forEach.call(
        document.querySelectorAll('.ml-source'),
        function (button) {
            button.addEventListener('click', function () {
                selectSource(button.getAttribute('data-source'));
            });
        }
    );

    window.addEventListener('beforeunload', stop);

    loadModel().catch(function () {
        // The status line already explains what went wrong.
    });

    window.setInterval(refreshCameraStatus, 15000);
})();
