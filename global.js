document.addEventListener('DOMContentLoaded', function() {
    
    /**
     * Generic password visibility toggle handler.
     * To use, add the following data attributes to your HTML:
     * - On the input-group container: nothing needed.
     * - On the password input: `data-toggle-target`
     * - On the clickable span/button: `data-toggle-trigger`
     * - The icon inside the trigger should be an `<i>` tag.
     */
    document.querySelectorAll('[data-toggle-trigger]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const inputGroup = this.closest('.input-group');
            if (!inputGroup) return;

            const passwordInput = inputGroup.querySelector('[data-toggle-target]');
            const icon = this.querySelector('i');
            if (!passwordInput || !icon) return;

            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            icon.classList.toggle('bi-eye-slash-fill');
            icon.classList.toggle('bi-eye-fill');
        });
    });

});