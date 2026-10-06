(function(App) {
    App.utils = App.utils || {};

    App.utils.showAlert = function(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            return;
        }
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertContainer.appendChild(alert);

        setTimeout(() => {
            alert.remove();
        }, 5000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    App.utils.escapeHtml = function(value) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(value).replace(/[&<>"']/g, char => map[char]);
    };
})(window.App);
