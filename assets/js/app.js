document.addEventListener('click', function (event) {
    if (event.target.closest('[data-toggle-sidebar]')) {
        document.body.classList.toggle('sidebar-open');
        return;
    }
    if (document.body.classList.contains('sidebar-open') && !event.target.closest('.sidebar')) {
        document.body.classList.remove('sidebar-open');
    }
    var demo = event.target.closest('[data-demo-email]');
    if (demo) {
        document.getElementById('identifier').value = demo.dataset.demoEmail;
        var portal = document.querySelector('input[name="portal"][value="' + (demo.dataset.demoPortal || 'Resident') + '"]');
        if (portal) { portal.checked = true; }
        document.getElementById('password').value = demo.dataset.demoPassword;
        document.getElementById('password').focus();
    }
});

document.addEventListener('change', function (event) {
    var autosubmit = event.target.closest('[data-autosubmit]');
    if (autosubmit && autosubmit.form) {
        autosubmit.form.submit();
    }
});

document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

// Registration: show the position field only for barangay officials.
(function () {
    var radios = document.querySelectorAll('[data-account-type]');
    var official = document.querySelector('[data-official-only]');
    if (!radios.length || !official) { return; }
    function sync() {
        var checked = document.querySelector('[data-account-type]:checked');
        official.style.display = checked && checked.value === 'Barangay Official' ? '' : 'none';
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
}());
