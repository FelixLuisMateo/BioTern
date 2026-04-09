(function () {
    function initPasswordToggle() {
        var toggle = document.querySelector('[data-account-password-toggle]');
        if (!toggle) {
            return;
        }

        var fields = document.querySelectorAll('[data-account-password-field]');
        var label = document.querySelector('[data-account-password-toggle-label]');

        function sync() {
            var visible = !!toggle.checked;
            fields.forEach(function (field) {
                field.type = visible ? 'text' : 'password';
            });
            if (label) {
                label.textContent = visible ? 'Hide passwords' : 'Show passwords';
            }
        }

        toggle.addEventListener('change', sync);
        sync();
    }

    function initAvatarCropper() {
        var form = document.querySelector('[data-avatar-upload-form]');
        if (!form) {
            return;
        }

        var openPickerButton = form.querySelector('[data-avatar-open-picker]');
        var fileInput = form.querySelector('[data-avatar-file-input]');
        var hiddenInput = form.querySelector('[data-avatar-cropped-input]');
        var modalEl = document.querySelector('[data-avatar-crop-modal]');
        var editor = modalEl ? modalEl.querySelector('[data-avatar-crop-editor]') : null;
        var canvas = form.querySelector('[data-avatar-crop-canvas]');
        if (!canvas && modalEl) {
            canvas = modalEl.querySelector('[data-avatar-crop-canvas]');
        }
        var zoomInput = form.querySelector('[data-avatar-crop-zoom]');
        if (!zoomInput && modalEl) {
            zoomInput = modalEl.querySelector('[data-avatar-crop-zoom]');
        }
        var resetButton = form.querySelector('[data-avatar-crop-reset]');
        if (!resetButton && modalEl) {
            resetButton = modalEl.querySelector('[data-avatar-crop-reset]');
        }
        var uploadButton = modalEl ? modalEl.querySelector('[data-avatar-crop-upload]') : null;
        var status = form.querySelector('[data-avatar-crop-status]');
        if (!status && modalEl) {
            status = modalEl.querySelector('[data-avatar-crop-status]');
        }

        var cropModal = null;
        if (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            cropModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        if (!fileInput || !hiddenInput || !editor || !canvas || !zoomInput || !resetButton || !uploadButton) {
            return;
        }

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var state = {
            image: null,
            baseScale: 1,
            scale: 1,
            minScale: 1,
            maxScale: 4,
            offsetX: 0,
            offsetY: 0,
            dragging: false,
            dragX: 0,
            dragY: 0
        };

        function setStatus(text) {
            if (!status) {
                return;
            }
            status.textContent = text;
        }

        function clampOffset() {
            if (!state.image) {
                return;
            }

            var drawWidth = state.image.width * state.scale;
            var drawHeight = state.image.height * state.scale;

            if (drawWidth <= canvas.width) {
                state.offsetX = (canvas.width - drawWidth) / 2;
            } else {
                var minX = canvas.width - drawWidth;
                if (state.offsetX < minX) {
                    state.offsetX = minX;
                }
                if (state.offsetX > 0) {
                    state.offsetX = 0;
                }
            }

            if (drawHeight <= canvas.height) {
                state.offsetY = (canvas.height - drawHeight) / 2;
            } else {
                var minY = canvas.height - drawHeight;
                if (state.offsetY < minY) {
                    state.offsetY = minY;
                }
                if (state.offsetY > 0) {
                    state.offsetY = 0;
                }
            }
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#0b1220';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            if (!state.image) {
                return;
            }

            clampOffset();
            var drawWidth = state.image.width * state.scale;
            var drawHeight = state.image.height * state.scale;
            ctx.drawImage(state.image, state.offsetX, state.offsetY, drawWidth, drawHeight);
        }

        function resetToDefault() {
            if (!state.image) {
                return;
            }

            state.baseScale = Math.max(canvas.width / state.image.width, canvas.height / state.image.height);
            state.minScale = state.baseScale;
            state.maxScale = state.baseScale * 4;
            state.scale = state.baseScale;

            var drawWidth = state.image.width * state.scale;
            var drawHeight = state.image.height * state.scale;
            state.offsetX = (canvas.width - drawWidth) / 2;
            state.offsetY = (canvas.height - drawHeight) / 2;

            zoomInput.value = '100';
            draw();
            setStatus('Drag the image to position the crop area.');
        }

        function updateScaleFromZoom() {
            if (!state.image) {
                return;
            }

            var zoomPercent = parseInt(zoomInput.value, 10);
            if (isNaN(zoomPercent) || zoomPercent < 100) {
                zoomPercent = 100;
            }
            var ratio = zoomPercent / 100;
            state.scale = Math.min(state.maxScale, Math.max(state.minScale, state.baseScale * ratio));
            draw();
        }

        function applyCrop() {
            if (!state.image) {
                return;
            }
            hiddenInput.value = canvas.toDataURL('image/png');
            setStatus('Crop is ready. Upload photo to save changes.');
        }

        function getPoint(event) {
            if (event.touches && event.touches.length) {
                return { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }
            return { x: event.clientX, y: event.clientY };
        }

        function startDrag(event) {
            if (!state.image) {
                return;
            }
            var point = getPoint(event);
            state.dragging = true;
            state.dragX = point.x;
            state.dragY = point.y;
        }

        function moveDrag(event) {
            if (!state.dragging || !state.image) {
                return;
            }
            if (event.cancelable) {
                event.preventDefault();
            }
            var point = getPoint(event);
            state.offsetX += (point.x - state.dragX);
            state.offsetY += (point.y - state.dragY);
            state.dragX = point.x;
            state.dragY = point.y;
            draw();
        }

        function stopDrag() {
            state.dragging = false;
        }

        function openCropModal() {
            if (cropModal) {
                cropModal.show();
            }
        }

        function closeCropModal() {
            if (cropModal) {
                cropModal.hide();
            }
        }

        if (openPickerButton) {
            openPickerButton.addEventListener('click', function () {
                fileInput.click();
            });
        }

        fileInput.addEventListener('change', function () {
            hiddenInput.value = '';
            var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) {
                state.image = null;
                setStatus('Drag the image to position the crop area.');
                return;
            }

            if (!/^image\//i.test(file.type)) {
                setStatus('Please select an image file.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function (loadEvent) {
                var img = new Image();
                img.onload = function () {
                    state.image = img;
                    resetToDefault();
                    openCropModal();
                };
                img.src = String(loadEvent.target && loadEvent.target.result ? loadEvent.target.result : '');
            };
            reader.readAsDataURL(file);
        });

        zoomInput.addEventListener('input', function () {
            hiddenInput.value = '';
            updateScaleFromZoom();
        });

        resetButton.addEventListener('click', function () {
            hiddenInput.value = '';
            resetToDefault();
        });

        uploadButton.addEventListener('click', function () {
            applyCrop();
            closeCropModal();
            form.submit();
        });

        canvas.addEventListener('mousedown', startDrag);
        window.addEventListener('mousemove', moveDrag);
        window.addEventListener('mouseup', stopDrag);
        canvas.addEventListener('touchstart', startDrag, { passive: true });
        canvas.addEventListener('touchmove', moveDrag, { passive: false });
        window.addEventListener('touchend', stopDrag, { passive: true });

        form.addEventListener('submit', function () {
            if (state.image && !hiddenInput.value) {
                applyCrop();
            }
        });

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                state.dragging = false;
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initPasswordToggle();
            initAvatarCropper();
        });
    } else {
        initPasswordToggle();
        initAvatarCropper();
    }
})();
