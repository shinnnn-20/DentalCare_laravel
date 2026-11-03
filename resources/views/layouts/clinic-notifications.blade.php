<div class="clinic-notifications" data-clinic-notifications
    data-list-url="{{ route('clinic.notifications.index') }}"
    data-mark-all-url="{{ route('clinic.notifications.read-all') }}">
    <input type="hidden" value="{{ csrf_token() }}" data-notification-csrf>
    <button class="notification-bell" type="button" data-notification-toggle aria-expanded="false" aria-controls="clinic-notification-panel">
        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path>
        </svg>
        <span>Notifications</span>
        <span @class(['notification-count', 'hidden' => $clinicUnreadNotificationCount === 0]) data-notification-count aria-live="polite">{{ $clinicUnreadNotificationCount > 99 ? '99+' : $clinicUnreadNotificationCount }}</span>
    </button>
    <section class="notification-panel hidden" id="clinic-notification-panel" data-notification-panel aria-label="Clinic notifications">
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Notifications</h2>
                <p class="text-xs text-slate-500" data-notification-summary>Recent clinic activity</p>
            </div>
            <button class="text-xs font-semibold text-teal-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400" type="button" data-notification-read-all disabled>Mark all read</button>
        </header>
        <div class="max-h-[min(28rem,65vh)] overflow-y-auto p-2" data-notification-list aria-live="polite">
            <p class="px-3 py-6 text-center text-sm text-slate-500">Loading notifications…</p>
        </div>
    </section>
</div>

<script>
    document.querySelectorAll('[data-clinic-notifications]').forEach((widget) => {
        const toggle = widget.querySelector('[data-notification-toggle]');
        const panel = widget.querySelector('[data-notification-panel]');
        const count = widget.querySelector('[data-notification-count]');
        const list = widget.querySelector('[data-notification-list]');
        const summary = widget.querySelector('[data-notification-summary]');
        const markAll = widget.querySelector('[data-notification-read-all]');
        const csrfToken = widget.querySelector('[data-notification-csrf]').value;
        let requestPending = false;

        const showMessage = (message, isError = false) => {
            const item = document.createElement('p');
            item.className = isError
                ? 'px-3 py-6 text-center text-sm text-rose-700'
                : 'px-3 py-6 text-center text-sm text-slate-500';
            item.textContent = message;
            list.replaceChildren(item);
        };

        const renderNotifications = (notifications) => {
            list.replaceChildren();
            if (notifications.length === 0) {
                showMessage('You are all caught up.');
                return;
            }

            notifications.forEach((notification) => {
                const form = document.createElement('form');
                const button = document.createElement('button');
                const heading = document.createElement('span');
                const message = document.createElement('span');
                const timestamp = document.createElement('span');
                const indicator = document.createElement('span');
                form.method = 'POST';
                form.action = notification.open_url;
                form.dataset.notificationOpen = '';
                button.type = 'submit';
                button.className = notification.is_read
                    ? 'notification-item notification-item-read'
                    : 'notification-item notification-item-unread';
                heading.className = 'block text-sm font-semibold text-slate-900';
                heading.textContent = notification.title;
                message.className = 'mt-1 block text-sm leading-5 text-slate-600';
                message.textContent = notification.message;
                timestamp.className = 'mt-2 block text-xs text-slate-400';
                timestamp.textContent = notification.created_at;
                indicator.className = notification.is_read
                    ? 'notification-indicator notification-indicator-read'
                    : 'notification-indicator notification-indicator-unread';
                indicator.setAttribute('aria-label', notification.is_read ? 'Read' : 'Unread');
                button.append(indicator, heading, message, timestamp);
                form.append(button);
                list.append(form);
            });
        };

        const refreshNotifications = async () => {
            if (requestPending) {
                return;
            }
            requestPending = true;
            try {
                const response = await fetch(widget.dataset.listUrl, {
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    throw new Error(`Notification request failed with status ${response.status}.`);
                }
                const data = await response.json();
                const unreadCount = Number(data.unread_count);
                count.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                count.classList.toggle('hidden', unreadCount === 0);
                markAll.disabled = unreadCount === 0;
                summary.textContent = unreadCount === 1 ? '1 unread notification' : `${unreadCount} unread notifications`;
                renderNotifications(data.notifications);
            } catch (error) {
                console.error(error);
                showMessage('Unable to load notifications. Please try again.', true);
            } finally {
                requestPending = false;
            }
        };

        toggle.addEventListener('click', () => {
            const isOpening = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !isOpening);
            toggle.setAttribute('aria-expanded', String(isOpening));
            if (isOpening) {
                refreshNotifications();
            }
        });

        markAll.addEventListener('click', async () => {
            markAll.disabled = true;
            try {
                const response = await fetch(widget.dataset.markAllUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    throw new Error(`Mark-all-read request failed with status ${response.status}.`);
                }
                await refreshNotifications();
            } catch (error) {
                console.error(error);
                showMessage('Unable to mark notifications as read. Please try again.', true);
            }
        });

        list.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-notification-open]');
            if (!form) {
                return;
            }
            event.preventDefault();
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                if (!response.ok) {
                    throw new Error(`Open-notification request failed with status ${response.status}.`);
                }
                const data = await response.json();
                window.location.assign(data.redirect_url);
            } catch (error) {
                console.error(error);
                button.disabled = false;
                showMessage('Unable to open this notification. Please try again.', true);
            }
        });

        document.addEventListener('click', (event) => {
            if (!widget.contains(event.target)) {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        refreshNotifications();
        window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                refreshNotifications();
            }
        }, 30000);
    });
</script>
