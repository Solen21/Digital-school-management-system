    <script src="assets/lottie.min.js"></script>
    <script src="assets/theme-toggle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize background animation on all pages
        bodymovin.loadAnimation({
            container: document.getElementById('lottie-bg'),
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: 'assets/animations/background.json'
        });

        // --- Notification System ---
        document.addEventListener('DOMContentLoaded', function() {
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notification-list');
            const notificationBadge = document.getElementById('notification-badge');

            function timeAgo(date) {
                const seconds = Math.floor((new Date() - new Date(date)) / 1000);
                let interval = seconds / 31536000;
                if (interval > 1) return Math.floor(interval) + " years ago";
                interval = seconds / 2592000;
                if (interval > 1) return Math.floor(interval) + " months ago";
                interval = seconds / 86400;
                if (interval > 1) return Math.floor(interval) + " days ago";
                interval = seconds / 3600;
                if (interval > 1) return Math.floor(interval) + " hours ago";
                interval = seconds / 60;
                if (interval > 1) return Math.floor(interval) + " minutes ago";
                return "Just now";
            }

            async function fetchNotifications() {
                try {
                    const response = await fetch('get_notifications.php');
                    const notifications = await response.json();
                    
                    notificationList.innerHTML = ''; // Clear current list

                    if (notifications.length === 0) {
                        notificationList.innerHTML = '<div class="text-center p-3 text-muted">No new notifications.</div>';
                        return;
                    }

                    notifications.forEach(notif => {
                        const item = document.createElement('a');
                        item.href = `mark_notification_read.php?id=${notif.notification_id}&redirect=${encodeURIComponent(notif.link || 'view_notifications.php')}`;
                        item.className = `list-group-item list-group-item-action dropdown-item ${notif.is_read == 0 ? 'unread-notification' : ''}`;
                        
                        const timeAgoStr = timeAgo(notif.created_at);

                        item.innerHTML = `
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle-fill text-primary me-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <p class="mb-1 notification-message">${notif.message}</p>
                                    <small class="notification-time text-muted">${timeAgoStr}</small>
                                </div>
                            </div>
                        `;
                        notificationList.appendChild(item);
                    });
                } catch (error) {
                    console.error('Error fetching notifications:', error);
                    notificationList.innerHTML = '<div class="text-center p-3 text-danger">Could not load notifications.</div>';
                }
            }

            notificationDropdown.addEventListener('show.bs.dropdown', function () {
                fetchNotifications();
            });

            notificationDropdown.addEventListener('shown.bs.dropdown', async function () {
                if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
                await fetch('mark_notifications_read.php', { method: 'POST' });
            });
        });
    </script>
</body>
</html>