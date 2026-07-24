# Visitor Management System

The Visitor Management System is a web application built with Laravel that allows users to register and manage visitors.

## Prerequisites

Make sure you have the following software installed on your local machine:

- PHP 8.4 or higher
- Composer
- MySQL
- Python 3 with OpenCV and NumPy (for local face verification)
- Tesseract OCR (for local identity-document text extraction)

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

5. Install the local face-verification dependencies:

   ```bash
   python -m pip install -r requirements-face.txt
   ```

6. Create a copy of the `.env.example` file and rename it to `.env`:

   ```bash
   cp .env.example .env
   ```

7. Generate the application key:

   ```bash
   php artisan key:generate
   ```

8. Update the `.env` file with your database credentials. Set
   `FACE_PYTHON_PATH` when the web server uses a different Python executable.

9. Run the database migrations:

   ```bash
   php artisan migrate
   ```

10. Start the local development server:

   ```bash
   php artisan serve
   ```

11. Access the application in your web browser at `http://localhost:8000`.

## Additional Configuration

- If you want to use a different web server (e.g., Apache or Nginx), configure it to point to the `public` directory of the project.

## Hosting checklist

The document-verification endpoint needs server-side OCR and face-verification dependencies; uploading only the Laravel files is not sufficient.

- Install Tesseract OCR and PHP GD, then set `TESSERACT_PATH` to the server executable (for example `/usr/bin/tesseract`), or configure Google Vision with `GOOGLE_VISION_API_KEY` or `GOOGLE_APPLICATION_CREDENTIALS`.
- Install Python 3, OpenCV, and NumPy from `requirements-face.txt`; set `FACE_PYTHON_PATH` if Python is not on the web server's PATH.
- Point the web server document root at `public`, make `storage` and `bootstrap/cache` writable, and run `php artisan storage:link`.
- After changing production `.env` values, run `php artisan config:clear` followed by `php artisan config:cache`.

If neither Tesseract nor Google Vision is configured, the upload endpoint returns an `ocr_not_configured` error instead of attempting verification without an OCR engine.
