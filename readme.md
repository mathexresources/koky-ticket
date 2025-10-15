# Koky Ticket System

A lightweight PHP + Tailwind CSS ticket desk tailored for engineering teams. End-users can raise tickets, receive a shareable tracking link, and watch status changes in real-time. Administrators get a live-updating queue with quick status toggles, spam deletion tools, CSV export, and audible alerts when new tickets arrive.

## Features

- **Ticket creation** with first name, title, and rich description supporting developer-friendly code blocks (wrap snippets in triple backticks).
- **Instant redirect** to `/ticket/{id}` after submission with live AJAX updates for status and timestamps.
- **Admin console** protected by an in-code password (`admin`) with:
  - Live queue refresh and tone notification for new tickets.
  - One-click status progression: New → In Progress → Done by clicking the ticket card.
  - Individual ticket deletion, clipboard-friendly share links, and CSV export.
  - Bulk "Delete all" action guarded by a typed confirmation prompt to mitigate accidents.
  - Status filters and KPI tiles for quick triage insights.
- **Modern UI** built with Tailwind CSS, Animate.css transitions, and glassmorphism touches.
- **MariaDB persistence** using PDO.

## Requirements

- PHP 8.1+
- MariaDB or MySQL 10+
- Web server with URL rewriting enabled (e.g., Apache with mod_rewrite or nginx fastcgi rewrites).

## Database Setup

Create a database (default name `ticket_system`) and run:

```sql
CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description MEDIUMTEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX status_idx (status),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Update the connection credentials in [`config.php`](config.php) or via environment variables (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

## Running Locally

1. Install dependencies (`pdo_mysql`, `mbstring` extensions enabled).
2. Serve the `public` directory with PHP's built-in server:

   ```bash
   php -S localhost:8000 -t public
   ```

3. Visit `http://localhost:8000/` to file a ticket, or `http://localhost:8000/admin` to log in (password: `admin`).

## Notes

- Description code blocks are rendered using custom sanitisation to preserve formatting while preventing XSS.
- Audio alerts rely on the Web Audio API and require a user interaction on the page before audio can play (standard browser policy).
- The "Delete all" action truncates the ticket table; use cautiously.

## License

MIT
