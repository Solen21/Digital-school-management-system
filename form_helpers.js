document.addEventListener('DOMContentLoaded', function() {
    // --- Bootstrap form validation ---
    const form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function (event) {
            // Handle captured photo submission
            if (capturedBlob) {
                event.preventDefault(); // Stop normal submission
                const formData = new FormData(form);
                formData.append('photo_path', capturedBlob, 'camera_photo.jpg');

                // Submit the form with FormData via fetch
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Replace the current page content with the response
                    // This will show the success/error message from the server
                    document.open();
                    document.write(html);
                    document.close();
                })
                .catch(error => {
                    console.error('Error submitting form:', error);
                    alert('An error occurred. Please try again.');
                });
            } else {
                 if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }
            form.classList.add('was-validated');
        }, false);
    }

    // --- Camera functionality ---
    const showCameraBtn = document.getElementById('show-camera-btn');
    const cameraSection = document.querySelector('.camera-section');
    const video = document.getElementById('video');
    const snapBtn = document.getElementById('snap-btn');
    const cancelBtn = document.getElementById('cancel-camera-btn');
    const canvas = document.getElementById('canvas');
    const photoInput = document.getElementById('photo_path');
    const photoPreviewContainer = document.getElementById('photo-preview-container');
    const photoPreview = document.getElementById('photo-preview');
    const removePhotoBtn = document.getElementById('remove-photo-btn');
    let stream;
    let capturedBlob = null;

    if (showCameraBtn) {
        showCameraBtn.addEventListener('click', async () => {
            cameraSection.style.display = 'block';
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                video.srcObject = stream;
            } catch (err) {
                console.error("Error accessing camera: ", err);
                alert("Could not access the camera. Please check permissions.");
                cameraSection.style.display = 'none';
            }
        });
    }

    if (snapBtn) {
        snapBtn.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, 320, 240);
            canvas.toBlob(function(blob) {
                capturedBlob = blob;
                const previewUrl = URL.createObjectURL(blob);
                photoPreview.src = previewUrl;
                photoPreviewContainer.style.display = 'inline-block';
                
                // Clear the file input so the captured photo takes precedence
                photoInput.value = '';

            }, 'image/jpeg');
            stopCamera();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            stopCamera();
        });
    }

    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', () => {
            capturedBlob = null;
            photoPreview.src = '';
            photoPreviewContainer.style.display = 'none';
            if (photoPreview.src) {
                URL.revokeObjectURL(photoPreview.src);
            }
        });
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        cameraSection.style.display = 'none';
    }
});