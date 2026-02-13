document.addEventListener('DOMContentLoaded', function () {
    var mq = window.matchMedia('(max-width: 991px)');
    var navLinks = document.querySelectorAll('#navbar .nav-links a');

    function syncMobileSidebarDefault() {
        if (mq.matches) {
            document.body.classList.add('sidebar-hidden');
        } else {
            document.body.classList.remove('sidebar-hidden');
        }
    }

    syncMobileSidebarDefault();
    window.addEventListener('resize', syncMobileSidebarDefault);

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (mq.matches) {
                document.body.classList.add('sidebar-hidden');
            }
        });
    });
});
