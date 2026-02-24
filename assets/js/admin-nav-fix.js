(function () {
    'use strict';

    function bindSidebarDropdown(toggleId, forceOpen) {
        var original = document.getElementById(toggleId);
        if (!original || !original.parentNode) return null;

        // Remove conflicting legacy listeners from admin.js by replacing the node.
        var toggle = original.cloneNode(true);
        original.parentNode.replaceChild(toggle, original);

        var group = toggle.closest('.nav-item-group');
        if (!group) return null;
        var submenu = group.querySelector('.nav-submenu');
        if (!submenu) return null;

        function setOpen(open) {
            group.classList.toggle('open', !!open);
            submenu.classList.toggle('show', !!open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (forceOpen) setOpen(true);
        else setOpen(group.classList.contains('open') || submenu.classList.contains('show'));

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!group.classList.contains('open'));
        });

        return { group: group, setOpen: setOpen };
    }

    document.addEventListener('DOMContentLoaded', function () {
        if ((window.location.pathname || '').toLowerCase().indexOf('/admin/') === -1) return;

        var isContractorsPage = (window.location.pathname || '').toLowerCase().indexOf('/admin/registered_contractors.php') !== -1;
        var isApplicationsPage = (document.body && (document.body.getAttribute('data-page') || '').indexOf('applications-') === 0);

        // Apply on pages reported with dropdown issues.
        if (!isContractorsPage && !isApplicationsPage) return;

        var projectReg = bindSidebarDropdown('projectRegToggle', false);
        var contractors = bindSidebarDropdown('contractorsToggle', true);

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (projectReg && !projectReg.group.contains(t)) projectReg.setOpen(false);
            if (contractors && !contractors.group.contains(t)) contractors.setOpen(false);
        });
    });
})();

