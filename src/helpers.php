<?php

namespace App;

function isAdminAuthenticated(): bool
{
    return !empty($_SESSION['is_admin']);
}

function requireAdmin(): void
{
    if (!isAdminAuthenticated()) {
        header('Location: /admin');
        exit;
    }
}

function sanitize(string $value): string
{
    return trim($value);
}

function validateTicket(array $data): array
{
    $errors = [];

    if (empty($data['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    }

    if (empty($data['title'])) {
        $errors['title'] = 'Title is required.';
    }

    if (empty($data['description'])) {
        $errors['description'] = 'Description is required.';
    }

    return $errors;
}

function renderDescription(string $text): string
{
    $pattern = '/```(.*?)```/s';
    $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    $html = '';
    $isCodeBlock = false;

    foreach ($segments as $segment) {
        if ($isCodeBlock) {
            $html .= '<pre class="bg-slate-900 text-slate-100 p-4 rounded-lg overflow-x-auto"><code>' . nl2br(htmlspecialchars($segment, ENT_QUOTES, 'UTF-8')) . '</code></pre>';
        } else {
            $html .= '<p class="leading-relaxed">' . nl2br(htmlspecialchars($segment, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $isCodeBlock = !$isCodeBlock;
    }

    return $html;
}

function statusLabel(string $status): string
{
    return match ($status) {
        TicketRepository::STATUS_NEW => 'New',
        TicketRepository::STATUS_IN_PROGRESS => 'In Progress',
        TicketRepository::STATUS_DONE => 'Done',
        default => ucfirst($status),
    };
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        TicketRepository::STATUS_NEW => 'bg-amber-100 text-amber-800',
        TicketRepository::STATUS_IN_PROGRESS => 'bg-blue-100 text-blue-800',
        TicketRepository::STATUS_DONE => 'bg-emerald-100 text-emerald-800',
        default => 'bg-slate-100 text-slate-800',
    };
}

function nextStatus(string $status): string
{
    return match ($status) {
        TicketRepository::STATUS_NEW => TicketRepository::STATUS_IN_PROGRESS,
        TicketRepository::STATUS_IN_PROGRESS => TicketRepository::STATUS_DONE,
        default => TicketRepository::STATUS_DONE,
    };
}
