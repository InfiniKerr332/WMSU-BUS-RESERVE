// Notification Bell System
let notificationCheckInterval;

function initNotifications() {
    // Check immediately
    checkNotifications();
    
    // Then check every 30 seconds
    notificationCheckInterval = setInterval(checkNotifications, 30000);
    
    // Bell click handler
    document.getElementById('notificationBell')?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleNotificationDropdown();
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        const bell = document.getElementById('notificationBell');
        
        if (dropdown && !bell.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

function checkNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBell(data.count);
                updateNotificationDropdown(data.notifications);
            }
        })
        .catch(error => {
            console.error('Notification check failed:', error);
        });
}

function updateNotificationBell(count) {
    const countElement = document.getElementById('notificationCount');
    
    if (countElement) {
        if (count > 0) {
            countElement.textContent = count > 99 ? '99+' : count;
            countElement.style.display = 'flex';
        } else {
            countElement.style.display = 'none';
        }
    }
}

function updateNotificationDropdown(notifications) {
    const dropdown = document.getElementById('notificationDropdown');
    
    if (!dropdown) return;
    
    if (notifications.length === 0) {
        dropdown.innerHTML = '<div class="notification-empty">No new notifications</div>';
        return;
    }
    
    let html = '<div class="notification-header">Notifications</div>';
    
    notifications.forEach(notif => {
        const timeAgo = getTimeAgo(notif.created_at);
        html += `
            <div class="notification-item unread" onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                <div class="notification-title">${notif.title}</div>
                <div class="notification-message">${notif.message}</div>
                <div class="notification-time">${timeAgo}</div>
            </div>
        `;
    });
    
    html += '<div class="notification-footer"><button onclick="markAllAsRead()">Mark all as read</button></div>';
    
    dropdown.innerHTML = html;
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

function handleNotificationClick(notificationId, link) {
    // Mark as read
    fetch(`api/mark_notification_read.php?id=${notificationId}`)
        .then(() => {
            checkNotifications();
            if (link) {
                window.location.href = link;
            }
        });
}

function markAllAsRead() {
    fetch('api/get_notifications.php?mark_read=1')
        .then(() => {
            checkNotifications();
            document.getElementById('notificationDropdown').classList.remove('show');
        });
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
    return Math.floor(diff / 86400) + ' days ago';
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initNotifications);