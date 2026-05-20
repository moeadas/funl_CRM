    </main>

    <script>
    // Mobile sidebar toggle with backdrop
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    // Close sidebar on navigation (mobile)
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) toggleMobileSidebar();
        });
    });
    </script>
    <script>
    // Admin Switch User functionality
    function handleSwitchUser(userId) {
        if (!userId) return;
        var csrfToken = document.querySelector('input[name="csrf_token"]');
        var token = csrfToken ? csrfToken.value : '<?php echo generateCSRFToken(); ?>';
        var fd = new FormData();
        fd.append('csrf_token', token);
        fd.append('action', 'switch');
        fd.append('user_id', userId);
        fetch('/api/switch-user.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to switch user');
                var sel = document.getElementById('switchUserSelect');
                if (sel) sel.value = '';
            }
        })
        .catch(function(){ alert('Error switching user'); });
    }
    function handleSwitchBack() {
        var csrfToken = document.querySelector('input[name="csrf_token"]');
        var token = csrfToken ? csrfToken.value : '<?php echo generateCSRFToken(); ?>';
        var fd = new FormData();
        fd.append('csrf_token', token);
        fd.append('action', 'switch_back');
        fetch('/api/switch-user.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                window.location.href = '/dashboard.php';
            } else {
                alert(data.message || 'Failed to switch back');
            }
        })
        .catch(function(){ alert('Error switching back'); });
    }
    </script>
    <script src="/assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.13.0/dist/twilio.min.js"></script>
    <script src="/assets/js/voip.js?v=20260505e"></script>
    <script src="/assets/js/whatsapp.js"></script>
</body>
</html>
