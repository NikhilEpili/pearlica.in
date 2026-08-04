document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('toast-container');
    if (!container) return;
    var alerts = document.querySelectorAll('main .alert-success, main .alert-danger');
    alerts.forEach(function (el) {
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-' + (el.classList.contains('alert-success') ? 'success' : 'danger') + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + el.textContent.trim() + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        container.appendChild(toast);
        var t = new bootstrap.Toast(toast, { delay: 4000 });
        t.show();
    });
});

function check_sku(url, sku, exclude_id, callback) {
    var q = '?sku=' + encodeURIComponent(sku);
    if (exclude_id) q += '&exclude_id=' + encodeURIComponent(exclude_id);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url + q);
    xhr.onload = function () {
        try {
            var j = JSON.parse(xhr.responseText);
            callback(j.available === true);
        } catch (e) {
            callback(false);
        }
    };
    xhr.onerror = function () { callback(false); };
    xhr.send();
}
