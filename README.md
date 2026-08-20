# Visitor Management System

The Visitor Management System is a web application built with Laravel that allows users to register and manage visitors.

## Prerequisites

Make sure you have the following software installed on your local machine:

- PHP 8.4 or higher
- Composer
- MySQL
- A Gemini API key

## Getting Started

Follow these steps to set up and run the project on your local machine:

1. Clone the repository:

   ```bash
   git clone https://github.com/yyueniao/visitor-management-system.git
   ```

2. Navigate to the project directory:

   ```bash
   cd visitor-management-system
   ```

3. Install the dependencies via `npm`:

   ```bash
   npm install
   ```

4. Install the dependencies via `composer`:

   ```bash
   composer install
   ```

5. Create a copy of the `.env.example` file and rename it to `.env`:

   ```bash
   cp .env.example .env
   ```

6. Generate the application key:

   ```bash
   php artisan key:generate
   ```

7. Update the `.env` file with your database credentials.

8. Run the database migrations:

   ```bash
   php artisan migrate
   ```

9. Start the local development server:

   ```bash
   php artisan serve
   ```

10. Access the application in your web browser at `http://localhost:8000`.

## Additional Configuration

- If you want to use a different web server (e.g., Apache or Nginx), configure it to point to the `public` directory of the project.

## Hosting checklist

The document-verification endpoint sends the uploaded document side(s) to Gemini and requests schema-validated registration details.

- Set `GEMINI_API_KEY` in `.env`. `GEMINI_MODEL` defaults to `gemini-2.5-flash` and can be changed without editing application code.
- Gemini reads the supplied identity page(s), cross-checks repeated English, Sinhala, and Tamil text, and returns the document number, complete English name, and the address when the document type prints one. Passport visitors enter their address during registration because it is not normally printed on the identity page.
- Document images are sent to Google Gemini for processing. Ensure this is covered by your privacy notice and data-handling policy.
- Point the web server document root at `public`, make `storage` and `bootstrap/cache` writable, and run `php artisan storage:link`.
- After changing production `.env` values, run `php artisan config:clear` followed by `php artisan config:cache`.

### Persistent visitor images

Visitor profile photos and all identity-document images (NIC front/back, driving licence, and passport) are private files served only by the application. They are stored on the `visitor-media` disk, outside the deployed application release by default.

Set a permanent, writable server directory before deploying. It must not be deleted or recreated by the deployment process:

```env
VISITOR_MEDIA_DISK=visitor-media
VISITOR_MEDIA_ROOT=/var/www/visitor-media
```

For an existing system, keep the old `storage/app/verified-visitors` directory available for the first deployment, then copy its files once:

```bash
php artisan visitor-media:migrate --dry-run
php artisan visitor-media:migrate
```

The application can still read old files from the previous local/public locations during this transition. Files that were already deleted by an earlier redeploy can only be restored from a backup.

Registration remains locked unless Gemini returns a plausible document number and full name, plus an address for document types that print one. Empty or malformed AI output is rejected instead of being copied into the registration form.
