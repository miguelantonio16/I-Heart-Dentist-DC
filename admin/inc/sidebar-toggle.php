<?php
// Accessible off-canvas sidebar toggle include
// Usage: include 'inc/sidebar-toggle.php' from admin pages (inside .content)
?>
<div class="top-toggle-area" aria-hidden="false">
    <button id="sidebarToggle" class="btn btn-primary-soft" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation" style="padding:6px 10px; font-size:16px;">☰</button>
</div>
<div id="sidebarOverlay" class="sidebar-overlay" hidden tabindex="-1"></div>
<script>
(function(){
    // Off-canvas accessible toggle
    var toggle = null;
    var sidebar = null;
    var overlay = null;
    var previouslyFocused = null;

    function focusableElements(container) {
        return container.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])');
    }

    function openSidebar() {
        if (!sidebar || !toggle || !overlay) return;
        previouslyFocused = document.activeElement;
        sidebar.classList.add('offcanvas-open');
        sidebar.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        overlay.hidden = false;
        overlay.classList.add('visible');
        // trap focus: focus first focusable inside sidebar
        var f = focusableElements(sidebar)[0];
        if (f) f.focus();
        localStorage.setItem('sidebarHidden','0');
    }

    function closeSidebar() {
        if (!sidebar || !toggle || !overlay) return;
        sidebar.classList.remove('offcanvas-open');
        sidebar.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        overlay.classList.remove('visible');
        overlay.hidden = true;
        if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus();
        localStorage.setItem('sidebarHidden','1');
    }

    document.addEventListener('DOMContentLoaded', function(){
        toggle = document.getElementById('sidebarToggle');
        sidebar = document.querySelector('.sidebar');
        overlay = document.getElementById('sidebarOverlay');
        if (!toggle || !sidebar) return;

        // ensure ARIA roles
        sidebar.setAttribute('role','navigation');
        sidebar.setAttribute('id','sidebar');

        // init state from saved preference (0 = shown, 1 = hidden)
        var saved = localStorage.getItem('sidebarHidden');
        if (saved === null) saved = '0';
        // For larger screens we use 'sidebar-collapsed'; for small screens we use off-canvas hidden
        function applySavedState() {
            if (window.innerWidth <= 1024) {
                // small screens: saved === '1' means hidden off-canvas
                if (saved === '1') {
                    sidebar.classList.remove('offcanvas-open');
                    sidebar.setAttribute('aria-hidden','true');
                    toggle.setAttribute('aria-expanded','false');
                    if (overlay) { overlay.hidden = true; overlay.classList.remove('visible'); }
                } else {
                    sidebar.classList.remove('offcanvas-open');
                    sidebar.setAttribute('aria-hidden','false');
                    toggle.setAttribute('aria-expanded','true');
                }
            } else {
                // larger screens: saved === '1' means collapsed
                var collapsed = localStorage.getItem('sidebarCollapsed');
                if (collapsed === '1') {
                    sidebar.classList.add('sidebar-collapsed');
                    sidebar.setAttribute('aria-hidden','false');
                    toggle.setAttribute('aria-expanded','false');
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                    sidebar.setAttribute('aria-hidden','false');
                    toggle.setAttribute('aria-expanded','true');
                }
            }
        }
        applySavedState();
        window.addEventListener('resize', applySavedState);

        // On small screens, toggle has opposite meaning: open shows overlay and slides in
        toggle.addEventListener('click', function(e){
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            if (window.innerWidth <= 1024) {
                // on small screens do off-canvas
                if (sidebar.classList.contains('offcanvas-open')) {
                    // currently visible -> close
                    closeSidebar();
                } else {
                    // show
                    openSidebar();
                }
            } else {
                // on larger screens just toggle collapsed state
                sidebar.classList.toggle('sidebar-collapsed');
                var now = sidebar.classList.contains('sidebar-collapsed');
                toggle.setAttribute('aria-expanded', now ? 'false' : 'true');
                // persist
                localStorage.setItem('sidebarCollapsed', now ? '1' : '0');
            }
        });

        // overlay click closes
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Esc key closes when overlay visible
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                if (overlay && overlay.classList.contains('visible')) closeSidebar();
            }
        });

        // Keep toggle visible on small screens
        function updateToggleVisibility(){
            var areas = document.getElementsByClassName('top-toggle-area');
            for (var i=0;i<areas.length;i++){
                if (window.innerWidth <= 1024) {
                    areas[i].style.display = 'flex';
                } else {
                    areas[i].style.display = 'none';
                }
            }
        }
        updateToggleVisibility();
        window.addEventListener('resize', updateToggleVisibility);
    });
})();
</script>
