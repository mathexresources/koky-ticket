<?php

use App\Database;
use App\TicketRepository;
use function App\isAdminAuthenticated;
use function App\nextStatus;
use function App\renderDescription;
use function App\requireAdmin;
use function App\sanitize;
use function App\statusBadgeClass;
use function App\statusLabel;
use function App\validateTicket;

session_start();

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/TicketRepository.php';
require __DIR__ . '/../src/helpers.php';

$config = require __DIR__ . '/../config.php';

$database = new Database($config['db']);
$repository = new TicketRepository($database);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/' && $method === 'GET') {
    renderHomePage();
    return;
}

if ($path === '/create-ticket' && $method === 'POST') {
    handleTicketCreation($repository);
    return;
}

if (preg_match('#^/ticket/(\\d+)$#', $path, $matches)) {
    $ticketId = (int) $matches[1];

    if ($method === 'GET') {
        renderTicketPage($repository, $ticketId);
        return;
    }
}

if ($path === '/admin' && $method === 'GET') {
    renderAdminPage($repository);
    return;
}

if ($path === '/admin/login' && $method === 'POST') {
    handleAdminLogin($config['admin_password']);
    return;
}

if ($path === '/admin/logout' && $method === 'POST') {
    session_destroy();
    header('Location: /admin');
    return;
}
if ($path === '/api/admin/tickets' && $method === 'GET') {
    requireAdmin();
    header('Content-Type: application/json');
    echo json_encode(['tickets' => enrichTickets($repository->all())]);
    return;
}

if (preg_match('#^/api/tickets/(\\d+)$#', $path, $matches) && $method === 'GET') {
    $ticketId = (int) $matches[1];
    $ticket = $repository->find($ticketId);

    header('Content-Type: application/json');
    echo json_encode(['ticket' => $ticket ? enrichTicket($ticket) : null]);
    return;
}

if (preg_match('#^/admin/ticket/(\\d+)/toggle$#', $path, $matches) && $method === 'POST') {
    requireAdmin();
    $ticketId = (int) $matches[1];
    $ticket = $repository->find($ticketId);

    if ($ticket) {
        $repository->updateStatus($ticketId, nextStatus($ticket['status']));
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    return;
}

if (preg_match('#^/admin/ticket/(\\d+)/delete$#', $path, $matches) && $method === 'POST') {
    requireAdmin();
    $ticketId = (int) $matches[1];
    $repository->delete($ticketId);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    return;
}

if ($path === '/admin/tickets/delete-all' && $method === 'POST') {
    requireAdmin();

    if (($_POST['confirmation'] ?? '') === 'DELETE') {
        $repository->deleteAll();
        $response = ['success' => true];
    } else {
        $response = ['success' => false, 'message' => 'Confirmation text mismatch.'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    return;
}

http_response_code(404);
echo 'Not found';

function renderLayout(string $title, string $content, array $options = []): void
{
    $extraHead = $options['extraHead'] ?? '';
    $extraScripts = $options['extraScripts'] ?? '';
    $bodyClass = $options['bodyClass'] ?? '';

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$title}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" integrity="sha512-b7X1N6VYGm5pGxQzB204MS+d5QwDFi1Cdm42Hcps225y7sY9qsK0kGugHgdGXN6E4668lBxKu1Ih+gJ1ytW8wA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <style>
            body { font-family: 'Inter', sans-serif; background-color: #0f172a; }
            .glass { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(16px); }
            .ticket-card { border: 1px solid rgba(148, 163, 184, 0.25); }
            .ticket-card:hover { border-color: rgba(129, 140, 248, 0.6); box-shadow: 0 24px 40px -24px rgba(79, 70, 229, 0.45); }
        </style>
        {$extraHead}
    </head>
    <body class="{$bodyClass} text-slate-100 min-h-screen flex items-center justify-center py-12">
        {$content}
        {$extraScripts}
    </body>
    </html>
    HTML;
}

function renderHomePage(): void
{
    $content = <<<HTML
    <div class="max-w-3xl mx-auto px-6 w-full">
        <div class="glass rounded-3xl shadow-2xl p-10 border border-slate-700">
            <div class="mb-10 text-center animate__animated animate__fadeInDown">
                <h1 class="text-4xl font-bold mb-3">Developer Support Desk</h1>
                <p class="text-slate-300">Create a ticket and our engineering team will respond shortly. Keep an eye on real-time updates after submission.</p>
            </div>
            <form action="/create-ticket" method="POST" class="space-y-6 animate__animated animate__fadeInUp" id="ticket-form">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-200 mb-2">First name</label>
                    <input type="text" id="first_name" name="first_name" required class="w-full rounded-2xl bg-slate-900/60 border border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 px-4 py-3" placeholder="Ada" />
                </div>
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-200 mb-2">Ticket title</label>
                    <input type="text" id="title" name="title" required class="w-full rounded-2xl bg-slate-900/60 border border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 px-4 py-3" placeholder="Build failing on CI" />
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-200 mb-2">Description</label>
                    <textarea id="description" name="description" rows="6" required class="w-full rounded-2xl bg-slate-900/60 border border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 px-4 py-3" placeholder="Describe the issue and include code using ``` blocks."></textarea>
                    <p class="text-xs text-slate-400 mt-2">Pro tip: Wrap code with triple backticks to preserve formatting.</p>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-slate-400">Estimated first response in &lt; 1 hour</div>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-indigo-500 hover:bg-indigo-400 transition px-6 py-3 font-semibold shadow-lg shadow-indigo-500/30">Submit ticket</button>
                </div>
            </form>
        </div>
        <div class="mt-8 text-center text-slate-400 text-sm">
            <p>Already submitted a ticket? Paste your ticket URL to resume tracking.</p>
        </div>
    </div>
    HTML;

    renderLayout('Create a Ticket', $content);
}

function handleTicketCreation(TicketRepository $repository): void
{
    $data = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
    ];

    $errors = validateTicket($data);

    if ($errors) {
        http_response_code(422);
        renderLayout('Create a Ticket', '<div class="text-red-500">Validation failed. Please go back and correct the highlighted fields.</div>');
        return;
    }

    $ticketId = $repository->create($data['first_name'], $data['title'], $data['description']);

    header('Location: /ticket/' . $ticketId);
}

function renderTicketPage(TicketRepository $repository, int $ticketId): void
{
    $ticket = $repository->find($ticketId);

    if (!$ticket) {
        http_response_code(404);
        renderLayout('Ticket not found', '<div class="text-center text-slate-300">Ticket not found.</div>');
        return;
    }

    $ticketData = json_encode(enrichTicket($ticket), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $safeTitle = htmlspecialchars($ticket['title'], ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($ticket['first_name'], ENT_QUOTES, 'UTF-8');

    $content = <<<HTML
    <div class="max-w-4xl mx-auto px-6 w-full">
        <div class="mb-6 flex items-center justify-between">
            <a href="/" class="text-sm text-slate-400 hover:text-indigo-300 transition">&larr; Submit another ticket</a>
            <span class="text-xs uppercase tracking-widest text-slate-500">Ticket #{$ticket['id']}</span>
        </div>
        <div class="glass rounded-3xl shadow-2xl p-10 border border-slate-700 space-y-8">
            <header class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-semibold">{$safeTitle}</h1>
                    <span id="ticket-status" class="px-3 py-1 rounded-full text-xs font-medium bg-slate-700">Loading...</span>
                </div>
                <p class="text-slate-400 text-sm">Submitted by <span class="font-medium text-slate-200">{$safeName}</span></p>
            </header>
            <section>
                <h2 class="text-lg font-semibold mb-3">Description</h2>
                <div id="ticket-description" class="space-y-4"></div>
            </section>
            <section class="border border-slate-700 rounded-2xl p-6 bg-slate-900/40">
                <h2 class="text-lg font-semibold mb-2">Live status updates</h2>
                <p class="text-sm text-slate-400">Keep this page open. The status updates in real-time while our team works on your request.</p>
                <div class="mt-4 space-y-2" id="ticket-meta"></div>
            </section>
        </div>
    </div>
    HTML;

    $scripts = <<<'HTML'
    <script>
        const initialTicket = __TICKET_DATA__;
        const ticketId = initialTicket.id;
        let lastStatus = '';
        const statusEl = document.getElementById('ticket-status');
        const descriptionEl = document.getElementById('ticket-description');
        const metaEl = document.getElementById('ticket-meta');

        function applyTicket(ticket) {
            if (!ticket) {
                statusEl.textContent = 'Archived';
                statusEl.className = 'px-3 py-1 rounded-full text-xs font-medium bg-slate-600';
                descriptionEl.innerHTML = '<p class="text-slate-400">This ticket has been removed.</p>'
                return;
            }

            if (ticket.status !== lastStatus) {
                statusEl.textContent = ticket.status_label;
                statusEl.className = `px-3 py-1 rounded-full text-xs font-medium ${ticket.status_badge_class}`;
                lastStatus = ticket.status;
            }

            descriptionEl.innerHTML = ticket.rendered_description;
            metaEl.innerHTML = `
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-400">Current status</span>
                    <span class="font-medium">${ticket.status_label}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-400">Created</span>
                    <span>${formatDate(ticket.created_at)}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-400">Last updated</span>
                    <span>${formatDate(ticket.updated_at)}</span>
                </div>
            `;
        }

        function formatDate(value) {
            const date = new Date(value.replace(' ', 'T'));
            return date.toLocaleString();
        }

        async function fetchTicket() {
            const response = await fetch(`/api/tickets/${ticketId}`);
            const data = await response.json();
            applyTicket(data.ticket);
        }

        applyTicket(initialTicket);
        setInterval(fetchTicket, 5000);
    </script>
    HTML;

    $scripts = str_replace('__TICKET_DATA__', $ticketData, $scripts);

    renderLayout('Ticket #' . $ticket['id'], $content, ['extraScripts' => $scripts]);
}

function renderAdminPage(TicketRepository $repository): void
{
    if (!isAdminAuthenticated()) {
        $content = <<<HTML
        <div class="w-full max-w-md mx-auto px-6">
            <div class="glass rounded-3xl shadow-2xl p-8 border border-slate-700">
                <h1 class="text-3xl font-semibold mb-6 text-center">Admin Console</h1>
                <form method="POST" action="/admin/login" class="space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-200 mb-2">Password</label>
                        <input type="password" id="password" name="password" required class="w-full rounded-2xl bg-slate-900/60 border border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 px-4 py-3" placeholder="Enter admin password">
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-indigo-500 hover:bg-indigo-400 transition px-4 py-3 font-semibold shadow-lg shadow-indigo-500/30">Sign in</button>
                </form>
            </div>
        </div>
        HTML;

        renderLayout('Admin Login', $content);
        return;
    }

    $content = <<<HTML
    <div class="max-w-6xl mx-auto px-6 w-full">
        <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-semibold">Ticket Command Center</h1>
                <p class="text-slate-400">Monitor incoming tickets, update statuses, and keep the queue tidy.</p>
            </div>
            <form action="/admin/logout" method="POST">
                <button class="text-sm text-slate-400 hover:text-rose-400">Sign out</button>
            </form>
        </header>
        <section class="glass rounded-3xl shadow-2xl border border-slate-700">
            <div class="p-6 border-b border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
                    <p class="text-sm text-slate-300">Live queue &mdash; refreshed automatically</p>
                </div>
                <div class="flex items-center gap-3 text-sm text-slate-400">
                    <label class="flex items-center gap-2"><input type="checkbox" id="filter-new" class="rounded border-slate-600" checked> New</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="filter-in-progress" class="rounded border-slate-600" checked> In progress</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="filter-done" class="rounded border-slate-600" checked> Done</label>
                </div>
            </div>
            <div class="p-6 border-b border-slate-700 grid grid-cols-1 md:grid-cols-3 gap-4 text-center text-sm text-slate-300" id="ticket-metrics">
                <div class="bg-slate-900/40 rounded-2xl py-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500 mb-1">New</p>
                    <p class="text-2xl font-semibold" id="metric-new">0</p>
                </div>
                <div class="bg-slate-900/40 rounded-2xl py-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500 mb-1">In progress</p>
                    <p class="text-2xl font-semibold" id="metric-in-progress">0</p>
                </div>
                <div class="bg-slate-900/40 rounded-2xl py-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500 mb-1">Done</p>
                    <p class="text-2xl font-semibold" id="metric-done">0</p>
                </div>
            </div>
            <div class="p-6 space-y-4" id="ticket-list"></div>
            <div class="p-6 border-t border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button id="delete-all" class="inline-flex items-center gap-2 text-sm text-rose-400 hover:text-rose-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        Delete all tickets
                    </button>
                    <button id="export-csv" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-indigo-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16M4 10h16M4 16h10" /></svg>
                        Export CSV
                    </button>
                </div>
                <p class="text-xs text-slate-500">Bulk delete requires typing <span class="text-rose-300">DELETE</span> to confirm.</p>
            </div>
        </section>
    </div>
    HTML;

    $scripts = <<<'HTML'
    <script>
        const ticketListEl = document.getElementById('ticket-list');
        const filterNew = document.getElementById('filter-new');
        const filterInProgress = document.getElementById('filter-in-progress');
        const filterDone = document.getElementById('filter-done');
        const metricNew = document.getElementById('metric-new');
        const metricInProgress = document.getElementById('metric-in-progress');
        const metricDone = document.getElementById('metric-done');
        const deleteAllBtn = document.getElementById('delete-all');
        const exportCsvBtn = document.getElementById('export-csv');

        let tickets = [];
        let knownTicketIds = new Set();
        let audioContext;

        function playNewTicketSound() {
            try {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.2, audioContext.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.4);
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.4);
            } catch (error) {
                console.warn('Unable to play sound', error);
            }
        }

        function statusFilters(status) {
            return (status === 'new' && filterNew.checked) ||
                (status === 'in_progress' && filterInProgress.checked) ||
                (status === 'done' && filterDone.checked);
        }

        function renderTickets() {
            const fragment = document.createDocumentFragment();
            let newCount = 0, inProgressCount = 0, doneCount = 0;

            tickets.forEach(ticket => {
                if (ticket.status === 'new') newCount++;
                if (ticket.status === 'in_progress') inProgressCount++;
                if (ticket.status === 'done') doneCount++;

                if (!statusFilters(ticket.status)) {
                    return;
                }

                const card = document.createElement('article');
                card.className = 'ticket-card rounded-3xl bg-slate-900/60 border border-slate-800 p-6 transition cursor-pointer';
                card.dataset.ticketId = ticket.id;

                card.innerHTML = `
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-slate-500">#${ticket.id}</p>
                            <h3 class="text-xl font-semibold mt-1">${ticket.title}</h3>
                            <p class="text-sm text-slate-400 mt-2">Submitted by <span class="text-slate-200">${ticket.first_name}</span></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${ticket.status_badge_class}">${ticket.status_label}</span>
                    </div>
                    <p class="text-sm text-slate-400 mt-4 max-h-20 overflow-hidden">${ticket.description_preview}</p>
                    <div class="mt-6 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span>Updated ${relativeTime(ticket.updated_at)}</span>
                        <span>Created ${relativeTime(ticket.created_at)}</span>
                        <a href="${ticket.public_url}" target="_blank" class="text-indigo-300 hover:text-indigo-200">View public page</a>
                        <button class="copy-link text-slate-400 hover:text-slate-200" data-url="${ticket.public_url}">Copy link</button>
                        <button class="delete-ticket text-rose-400 hover:text-rose-300" data-id="${ticket.id}">Delete</button>
                    </div>
                `;

                fragment.appendChild(card);
            });

            ticketListEl.innerHTML = '';
            ticketListEl.appendChild(fragment);

            metricNew.textContent = newCount;
            metricInProgress.textContent = inProgressCount;
            metricDone.textContent = doneCount;

            if (!tickets.length) {
                ticketListEl.innerHTML = '<p class="text-center text-slate-400 py-10">No tickets yet. Enjoy the quiet! 🚀</p>'
            }
        }

        function relativeTime(dateString) {
            const date = new Date(dateString.replace(' ', 'T'));
            const diff = (Date.now() - date.getTime()) / 1000;
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.round(diff / 60) + ' min ago';
            if (diff < 86400) return Math.round(diff / 3600) + ' hr ago';
            return Math.round(diff / 86400) + ' d ago';
        }

        async function fetchTickets() {
            const response = await fetch('/api/admin/tickets', { credentials: 'same-origin' });
            const data = await response.json();
            const fetched = data.tickets || [];
            const fetchedIds = new Set(fetched.map(t => t.id));
            const newTickets = fetched.filter(t => !knownTicketIds.has(t.id));

            if (knownTicketIds.size && newTickets.length) {
                playNewTicketSound();
            }

            knownTicketIds = fetchedIds;
            tickets = fetched;
            renderTickets();
        }

        ticketListEl.addEventListener('click', async (event) => {
            const card = event.target.closest('article');
            if (!card) return;
            const ticketId = card.dataset.ticketId;

            if (event.target.matches('.delete-ticket')) {
                event.stopPropagation();
                if (!confirm('Delete this ticket?')) return;
                await fetch(`/admin/ticket/${ticketId}/delete`, { method: 'POST' });
                fetchTickets();
                return;
            }

            if (event.target.matches('.copy-link')) {
                event.preventDefault();
                event.stopPropagation();
                const url = event.target.dataset.url;
                navigator.clipboard.writeText(url);
                event.target.textContent = 'Copied!';
                setTimeout(() => event.target.textContent = 'Copy link', 1500);
                return;
            }

            await fetch(`/admin/ticket/${ticketId}/toggle`, { method: 'POST' });
            fetchTickets();
        });

        [filterNew, filterInProgress, filterDone].forEach(filter => {
            filter.addEventListener('change', renderTickets);
        });

        deleteAllBtn.addEventListener('click', async () => {
            const confirmation = prompt('Type DELETE to confirm bulk deletion');
            if (confirmation !== 'DELETE') {
                alert('Confirmation mismatch. No tickets were removed.');
                return;
            }
            await fetch('/admin/tickets/delete-all', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ confirmation })
            });
            fetchTickets();
        });

        exportCsvBtn.addEventListener('click', () => {
            if (!tickets.length) {
                alert('No tickets to export.');
                return;
            }
            const header = ['ID','First Name','Title','Status','Created At','Updated At'];
            const rows = tickets.map(t => [t.id, t.first_name, t.title.replace(/"/g, '""'), t.status_label, t.created_at, t.updated_at]);
            const csv = [header.join(','), ...rows.map(r => r.map(value => `"${value}"`).join(','))].join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'tickets.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        fetchTickets();
        setInterval(fetchTickets, 4000);
    </script>
    HTML;

    renderLayout('Admin Console', $content, ['extraScripts' => $scripts, 'bodyClass' => '']);
}

function handleAdminLogin(string $expectedPassword): void
{
    $password = $_POST['password'] ?? '';

    if (hash_equals($expectedPassword, $password)) {
        $_SESSION['is_admin'] = true;
        header('Location: /admin');
        return;
    }

    renderLayout('Admin Login', '<div class="text-red-400">Incorrect password.</div>');
}

function enrichTicket(array $ticket): array
{
    $ticket['status_label'] = statusLabel($ticket['status']);
    $ticket['status_badge_class'] = statusBadgeClass($ticket['status']);
    $ticket['rendered_description'] = renderDescription($ticket['description']);
    $plain = strip_tags($ticket['description']);
    if (function_exists('mb_strimwidth')) {
        $ticket['description_preview'] = mb_strimwidth($plain, 0, 160, '…');
    } else {
        $ticket['description_preview'] = strlen($plain) > 160 ? substr($plain, 0, 157) . '…' : $plain;
    }
    $ticket['public_url'] = '/ticket/' . $ticket['id'];

    return $ticket;
}

function enrichTickets(array $tickets): array
{
    return array_map('enrichTicket', $tickets);
}
