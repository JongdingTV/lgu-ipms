document.addEventListener('DOMContentLoaded', function () {
    const newPassword = document.getElementById('newPassword');
    const strength = document.getElementById('passwordStrength');

    if (newPassword && strength) {
        function scorePassword(value) {
            let score = 0;
            if (value.length >= 8) score += 1;
            if (/[A-Z]/.test(value)) score += 1;
            if (/[0-9]/.test(value)) score += 1;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(value)) score += 1;
            return score;
        }

        newPassword.addEventListener('input', function () {
            const score = scorePassword(newPassword.value);
            if (score <= 1) {
                strength.textContent = 'Password strength: Weak';
                strength.style.color = '#b91c1c';
            } else if (score <= 3) {
                strength.textContent = 'Password strength: Medium';
                strength.style.color = '#b45309';
            } else {
                strength.textContent = 'Password strength: Strong';
                strength.style.color = '#166534';
            }
        });
    }

    const idViewBtn = document.querySelector('.id-view-btn');
    const idViewerModal = document.getElementById('idViewerModal');
    const idViewerClose = document.getElementById('idViewerClose');
    const idViewerImage = document.getElementById('idViewerImage');
    const idViewerFrontImage = document.getElementById('idViewerFrontImage');
    const idViewerBackImage = document.getElementById('idViewerBackImage');
    const idViewerDualWrap = document.getElementById('idViewerDualWrap');
    const idViewerPdf = document.getElementById('idViewerPdf');
    const idViewerImageWrap = document.getElementById('idViewerImageWrap');
    const idViewerZoomControls = document.getElementById('idViewerZoomControls');
    const idZoomRange = document.getElementById('idZoomRange');
    const idZoomIn = document.getElementById('idZoomIn');
    const idZoomOut = document.getElementById('idZoomOut');
    const idZoomReset = document.getElementById('idZoomReset');

    let idZoom = 1;
    function applyIdZoom() {
        if (!idViewerImage) return;
        idViewerImage.style.transform = 'scale(' + idZoom.toFixed(2) + ')';
    }

    function setIdZoom(next) {
        const clamped = Math.max(1, Math.min(3, next));
        idZoom = clamped;
        if (idZoomRange) idZoomRange.value = idZoom.toFixed(1);
        applyIdZoom();
    }

    function closeIdViewer() {
        if (!idViewerModal) return;
        idViewerModal.hidden = true;
        if (idViewerImage) {
            idViewerImage.src = '';
            idViewerImage.style.display = 'none';
            idViewerImage.style.transform = 'scale(1)';
        }
        if (idViewerFrontImage) idViewerFrontImage.src = '';
        if (idViewerBackImage) idViewerBackImage.src = '';
        if (idViewerDualWrap) idViewerDualWrap.style.display = 'none';
        if (idViewerImageWrap) {
            idViewerImageWrap.style.display = 'none';
            idViewerImageWrap.scrollTop = 0;
            idViewerImageWrap.scrollLeft = 0;
        }
        if (idViewerPdf) {
            idViewerPdf.src = '';
            idViewerPdf.style.display = 'none';
        }
        if (idViewerZoomControls) idViewerZoomControls.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (idViewBtn && idViewerModal) {
        idViewBtn.addEventListener('click', function () {
            const url = idViewBtn.getAttribute('data-id-url') || '';
            const frontUrl = idViewBtn.getAttribute('data-id-front-url') || '';
            const backUrl = idViewBtn.getAttribute('data-id-back-url') || '';
            if (url === '' && frontUrl === '' && backUrl === '') return;

            if (frontUrl || backUrl) {
                if (idViewerFrontImage) {
                    idViewerFrontImage.src = frontUrl || backUrl || '';
                    idViewerFrontImage.style.display = 'block';
                }
                if (idViewerBackImage) {
                    idViewerBackImage.src = backUrl || frontUrl || '';
                    idViewerBackImage.style.display = 'block';
                }
                if (idViewerDualWrap) idViewerDualWrap.style.display = 'grid';
                if (idViewerImage) {
                    idViewerImage.src = '';
                    idViewerImage.style.display = 'none';
                }
                if (idViewerPdf) {
                    idViewerPdf.src = '';
                    idViewerPdf.style.display = 'none';
                }
                if (idViewerImageWrap) idViewerImageWrap.style.display = 'block';
                if (idViewerZoomControls) idViewerZoomControls.style.display = 'none';
                idViewerModal.hidden = false;
                document.body.style.overflow = 'hidden';
                return;
            }

            const isPdf = /\.pdf($|\?)/i.test(url);

            if (isPdf) {
                if (idViewerPdf) {
                    idViewerPdf.src = url;
                    idViewerPdf.style.display = 'block';
                }
                if (idViewerImage) {
                    idViewerImage.src = '';
                    idViewerImage.style.display = 'none';
                }
                if (idViewerImageWrap) idViewerImageWrap.style.display = 'none';
                if (idViewerZoomControls) idViewerZoomControls.style.display = 'none';
            } else {
                if (idViewerImage) {
                    idViewerImage.src = url;
                    idViewerImage.style.display = 'block';
                }
                if (idViewerImageWrap) idViewerImageWrap.style.display = 'block';
                if (idViewerPdf) {
                    idViewerPdf.src = '';
                    idViewerPdf.style.display = 'none';
                }
                if (idViewerZoomControls) idViewerZoomControls.style.display = 'flex';
                setIdZoom(1);
            }

            idViewerModal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    }

    if (idViewerClose) {
        idViewerClose.addEventListener('click', closeIdViewer);
    }

    if (idViewerModal) {
        idViewerModal.addEventListener('click', function (event) {
            if (event.target === idViewerModal) {
                closeIdViewer();
            }
        });
    }

    if (idZoomRange) {
        idZoomRange.addEventListener('input', function () {
            setIdZoom(parseFloat(idZoomRange.value || '1'));
        });
    }
    if (idZoomIn) {
        idZoomIn.addEventListener('click', function () {
            setIdZoom(idZoom + 0.1);
        });
    }
    if (idZoomOut) {
        idZoomOut.addEventListener('click', function () {
            setIdZoom(idZoom - 0.1);
        });
    }
    if (idZoomReset) {
        idZoomReset.addEventListener('click', function () {
            setIdZoom(1);
        });
    }
    const idFrontInput = document.getElementById('idFrontInput');
    const idBackInput = document.getElementById('idBackInput');
    const idFrontStage = document.getElementById('idFrontStage');
    const idBackStage = document.getElementById('idBackStage');
    const idFrontNextBtn = document.getElementById('idFrontNextBtn');
    const idBackPrevBtn = document.getElementById('idBackPrevBtn');
    const idUploadForm = document.getElementById('idUploadForm');
    if (idFrontInput && idBackInput && idUploadForm && idFrontStage && idBackStage) {
        function showFront() {
            idFrontStage.style.display = '';
            idBackStage.style.display = 'none';
        }
        function showBack() {
            idFrontStage.style.display = 'none';
            idBackStage.style.display = '';
        }
        if (idFrontNextBtn) {
            idFrontNextBtn.addEventListener('click', function () {
                if (!idFrontInput.files || idFrontInput.files.length === 0) {
                    alert('Please choose the front side of your ID first.');
                    return;
                }
                showBack();
            });
        }
        if (idBackPrevBtn) {
            idBackPrevBtn.addEventListener('click', function () {
                showFront();
            });
        }
        idUploadForm.addEventListener('submit', function (event) {
            if (!idFrontInput.files || idFrontInput.files.length === 0) {
                event.preventDefault();
                alert('Please choose the front side of your ID.');
                showFront();
                return;
            }
            if (!idBackInput.files || idBackInput.files.length === 0) {
                event.preventDefault();
                alert('Please choose the back side of your ID.');
                showBack();
            }
        });
    }
    const fileInput = document.getElementById('profilePhotoInput');
    const modal = document.getElementById('avatarCropModal');
    const closeBtn = document.getElementById('avatarCropClose');
    const cancelBtn = document.getElementById('avatarCropCancel');
    const applyBtn = document.getElementById('avatarCropApply');
    const zoomRange = document.getElementById('avatarZoomRange');
    const canvas = document.getElementById('avatarCropCanvas');
    const previewContainer = document.querySelector('.avatar-upload-trigger');

    if (!fileInput || !modal || !closeBtn || !cancelBtn || !applyBtn || !zoomRange || !canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    const size = canvas.width;
    const radius = Math.floor(size * 0.47);
    const centerX = size / 2;
    const centerY = size / 2;

    let img = null;
    let sourceFile = null;
    let zoom = 1.2;
    let minZoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let dragBaseX = 0;
    let dragBaseY = 0;

    function clampOffsets() {
        if (!img) return;
        const drawW = img.width * zoom;
        const drawH = img.height * zoom;
        const halfW = drawW / 2;
        const halfH = drawH / 2;
        const maxX = Math.max(0, halfW - radius);
        const maxY = Math.max(0, halfH - radius);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
    }

    function drawCropper() {
        if (!img) return;
        ctx.clearRect(0, 0, size, size);
        ctx.fillStyle = '#f1f5f9';
        ctx.fillRect(0, 0, size, size);

        const drawW = img.width * zoom;
        const drawH = img.height * zoom;
        const drawX = centerX - drawW / 2 + offsetX;
        const drawY = centerY - drawH / 2 + offsetY;

        ctx.save();
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        ctx.drawImage(img, drawX, drawY, drawW, drawH);
        ctx.restore();

        ctx.fillStyle = 'rgba(15, 23, 42, 0.18)';
        ctx.fillRect(0, 0, size, size);
        ctx.save();
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
        ctx.stroke();
    }

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        dragging = false;
    }

    function setPreviewFromBlob(blob) {
        if (!previewContainer) return;
        const previewUrl = URL.createObjectURL(blob);
        let previewImg = previewContainer.querySelector('.avatar-upload-image');
        if (!previewImg) {
            const existingInitial = previewContainer.querySelector('.avatar-upload-initial');
            if (existingInitial) existingInitial.remove();
            previewImg = document.createElement('img');
            previewImg.className = 'avatar-upload-image';
            previewImg.alt = 'Profile photo';
            previewContainer.insertBefore(previewImg, previewContainer.firstChild);
        }
        previewImg.src = previewUrl;
    }

    function handleFilePick(file) {
        if (!file) return;
        if (!file.type.startsWith('image/')) return;

        sourceFile = file;
        const reader = new FileReader();
        reader.onload = function (e) {
            const loaded = new Image();
            loaded.onload = function () {
                img = loaded;
                const scaleX = (radius * 2) / img.width;
                const scaleY = (radius * 2) / img.height;
                minZoom = Math.max(scaleX, scaleY);
                zoom = Math.max(minZoom, 1.2);
                zoomRange.min = String(minZoom);
                zoomRange.max = String(Math.max(minZoom + 2, 3));
                zoomRange.value = String(zoom);
                offsetX = 0;
                offsetY = 0;
                clampOffsets();
                drawCropper();
                openModal();
            };
            loaded.src = String(e.target && e.target.result ? e.target.result : '');
        };
        reader.readAsDataURL(file);
    }

    fileInput.addEventListener('change', function () {
        const f = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        handleFilePick(f);
    });

    zoomRange.addEventListener('input', function () {
        zoom = parseFloat(zoomRange.value || '1');
        clampOffsets();
        drawCropper();
    });

    function getPointerPos(evt) {
        const rect = canvas.getBoundingClientRect();
        const touch = evt.touches && evt.touches[0] ? evt.touches[0] : null;
        const clientX = touch ? touch.clientX : evt.clientX;
        const clientY = touch ? touch.clientY : evt.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function startDrag(evt) {
        if (!img) return;
        evt.preventDefault();
        dragging = true;
        const pos = getPointerPos(evt);
        dragStartX = pos.x;
        dragStartY = pos.y;
        dragBaseX = offsetX;
        dragBaseY = offsetY;
    }

    function moveDrag(evt) {
        if (!dragging || !img) return;
        evt.preventDefault();
        const pos = getPointerPos(evt);
        offsetX = dragBaseX + (pos.x - dragStartX);
        offsetY = dragBaseY + (pos.y - dragStartY);
        clampOffsets();
        drawCropper();
    }

    function endDrag() {
        dragging = false;
    }

    canvas.addEventListener('mousedown', startDrag);
    window.addEventListener('mousemove', moveDrag);
    window.addEventListener('mouseup', endDrag);
    canvas.addEventListener('touchstart', startDrag, { passive: false });
    window.addEventListener('touchmove', moveDrag, { passive: false });
    window.addEventListener('touchend', endDrag);

    applyBtn.addEventListener('click', function () {
        if (!img || !sourceFile) {
            closeModal();
            return;
        }

        const out = document.createElement('canvas');
        out.width = 512;
        out.height = 512;
        const octx = out.getContext('2d');
        const outCenter = out.width / 2;
        const outRadius = outCenter - 2;

        const drawW = img.width * zoom;
        const drawH = img.height * zoom;
        const drawX = centerX - drawW / 2 + offsetX;
        const drawY = centerY - drawH / 2 + offsetY;

        const scale = out.width / size;
        octx.save();
        octx.beginPath();
        octx.arc(outCenter, outCenter, outRadius, 0, Math.PI * 2);
        octx.clip();
        octx.drawImage(
            img,
            drawX * scale,
            drawY * scale,
            drawW * scale,
            drawH * scale
        );
        octx.restore();

        out.toBlob(function (blob) {
            if (!blob) return;
            const finalFile = new File([blob], 'profile-cropped.png', { type: 'image/png' });
            const dt = new DataTransfer();
            dt.items.add(finalFile);
            fileInput.files = dt.files;
            setPreviewFromBlob(blob);
            closeModal();
        }, 'image/png', 0.95);
    });

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });
});

