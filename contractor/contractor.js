(function () {
    'use strict';

    function byId(id) { return document.getElementById(id); }

    function initSidebarToggle() {
        var body = document.body;
        var toggles = [byId('toggleSidebar'), byId('toggleSidebarShow'), byId('navbarMenuIcon')];
        var i;
        function toggleSidebar(evt) {
            if (evt && evt.preventDefault) evt.preventDefault();
            body.classList.toggle('sidebar-hidden');
        }
        for (i = 0; i < toggles.length; i++) {
            if (toggles[i]) toggles[i].addEventListener('click', toggleSidebar);
        }
    }

    function initNavDropdowns() {
        var groups = document.querySelectorAll('.nav-item-group');
        var i;
        for (i = 0; i < groups.length; i++) {
            (function (group) {
                var trigger = group.querySelector('.nav-main-item');
                if (!trigger) return;
                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (group.classList.contains('open')) {
                        group.classList.remove('open');
                    } else {
                        var openGroups = document.querySelectorAll('.nav-item-group.open');
                        var j;
                        for (j = 0; j < openGroups.length; j++) {
                            openGroups[j].classList.remove('open');
                        }
                        group.classList.add('open');
                    }
                });
            })(groups[i]);
        }

        document.addEventListener('click', function (e) {
            var inside = e.target.closest && e.target.closest('.nav-item-group');
            if (inside) return;
            var openGroups = document.querySelectorAll('.nav-item-group.open');
            var i;
            for (i = 0; i < openGroups.length; i++) {
                openGroups[i].classList.remove('open');
            }
        });
    }

    function initLogoutConfirm() {
        var links = document.querySelectorAll('a[href*="logout.php"]');
        var i;
        for (i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function (e) {
                var ok = window.confirm('Are you sure you want to logout?');
                if (!ok) {
                    e.preventDefault();
                }
            });
        }
    }

    function init() {
        initSidebarToggle();
        initNavDropdowns();
        initLogoutConfirm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
