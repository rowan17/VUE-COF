# Vue.js Order Form with PHP Email Backend

This project consists of a Vue.js frontend for taking customer orders and a PHP backend script (`mail.php`) responsible for sending these order details via email.

## Project Structure

-   `src/components/OrderForm.vue`: The main Vue component for the order form.
-   `mail.php`: (Located in the project root or `public/` directory) The PHP script that handles form submissions and sends emails.

## How the Email Sending Works (`mail.php`)

The `mail.php` script is designed to receive order data via an HTTP POST request from the `OrderForm.vue` component.

1.  **Request Handling:**
    *   The script first checks if the request method is `POST`.
    *   It includes CORS (Cross-Origin Resource Sharing) headers to allow requests from the Vue development server (or your deployed frontend domain). It also handles `OPTIONS` pre-flight requests.

2.  **Data Reception & Sanitization:**
    *   It expects the following POST variables from the Vue form:
        *   `realemail`: The customer's email address (can be semicolon-separated for multiple, but only the first valid one is used).
        *   `company_name`: The customer's company name.
        *   `url_link`: The URL of the order form page the customer was using.
        *   `order_details`: A string containing the formatted details of the order (items, quantities, prices, totals, notes, customer info).
    *   Basic sanitization is applied to these inputs (trimming, `htmlspecialchars`, `filter_var`).

3.  **Email Construction:**
    *   **Recipients (`$to`):** The email is sent to a primary internal address (`orders@paracay.com`) and also to the customer's email address.
    *   **Subject (`$subject`):** Dynamically generated using the company name.
    *   **Body (`$emailMessage`):** A plain text email body is constructed using the received data. HTML `<br>` tags in the `order_details` are converted to newlines.
    *   **Headers (`$headers`):** Standard email headers are set for a plain text email, including `From`, `Reply-To`, `MIME-Version`, `Content-Type` (as `text/plain; charset=UTF-8`), and `Content-Transfer-Encoding`.

4.  **Email Sending:**
    *   The PHP `mail()` function is used to send the email.

5.  **JSON Response:**
    *   Regardless of success or failure of the `mail()` function, the script sends a JSON response back to the Vue frontend.
    *   **On success:** `{"success": true, "message": "Order submitted successfully! A confirmation email has been sent."}`
    *   **On failure (e.g., `mail()` returns false):** `{"success": false, "message": "There was an issue sending the order email. Please check server logs or contact support."}`
    *   **Other errors (e.g., invalid input):** A JSON response with `success: false` and an appropriate error message.

## Local Testing & Debugging

Testing email functionality locally requires a bit of setup because most development machines don't have a full mail server running.

### 1. Setting up MailHog (Recommended for Local Email Catching)

MailHog is a tool that runs a fake SMTP server. Instead of sending emails out, it catches them and displays them in a web UI, which is perfect for development.

*   **Download MailHog:**
    *   Go to MailHog GitHub Releases.
    *   Download `MailHog_windows_amd64.exe` (or the appropriate version for your OS).
*   **Run MailHog:**
    *   Open a terminal/command prompt.
    *   Navigate to where you saved `MailHog.exe`.
    *   Run it: `MailHog.exe`
    *   MailHog will typically start its SMTP server on `localhost:1025` and its web UI on `http://localhost:8025`.
*   **Configure PHP to use MailHog:**
    1.  **Find your `php.ini`:**
        *   In your terminal, run `php --ini`. This will show you the "Loaded Configuration File" path. If it says "(none)", you need to rename `php.ini-development` or `php.ini-production` (usually found in your PHP installation directory, e.g., `C:\Program Files\php-8.4.7-Win32-vs17-x64\`) to `php.ini`.
    2.  **Edit `php.ini`:**
        *   Open the loaded `php.ini` file.
        *   Find the `[mail function]` section.
        *   Set the following:
            ```ini
            [mail function]
            SMTP = localhost
            smtp_port = 1025
            ; Comment out or remove any sendmail_path if present for Windows
            ; sendmail_path =
            ```
    3.  **Restart your PHP Server:** If you're using PHP's built-in server (`php -S ...`), stop it (Ctrl+C) and restart it for the `php.ini` changes to take effect.

### 2. Running the Application Locally

*   **Vue Frontend:**
    *   Open a terminal in the project root (`c:\Users\Owner\Documents\VSCode Projects\VUEjs test\test\`).
    *   Run `npm install` (if you haven't).
    *   Run `npm run dev` (or `npm run serve` depending on your Vue project setup).
    *   Access the Vue app in your browser (e.g., `http://localhost:5173`).
*   **PHP Backend:**
    *   Ensure `mail.php` is in the correct location (e.g., project root or `public/`).
    *   Open a *separate* terminal.
    *   Navigate to the directory containing `mail.php`.
    *   Run PHP's built-in server: `php -S localhost:8001` (or your chosen port).
    *   Make sure the `axios.post` URL in `OrderForm.vue` points to this PHP server (e.g., `http://localhost:8001/mail.php`).

### 3. Debugging `mail.php`

*   **Check PHP Server Console:** Errors from `mail.php` or PHP itself might appear in the terminal where you're running `php -S ...`.
*   **Browser Developer Tools (Network Tab):**
    *   When you submit the form, inspect the `POST` request to `mail.php`.
    *   Check the "Response" tab. If PHP encounters an error before it can output JSON, you might see PHP error messages or HTML here.
    *   The "Headers" tab will show the status code (e.g., 200 OK, 500 Internal Server Error).
*   **`error_log()`:** You can add `error_log("Debug message here");` statements within `mail.php` to write messages to your PHP error log. The location of this log depends on your PHP configuration.
*   **Temporarily `var_dump()` and `exit;`:** To inspect variables at certain points in `mail.php`:
    ```php
    // ... some code ...
    var_dump($_POST);
    exit; // Stop execution here
    // ... rest of the code ...
    ```
    Remember to remove these after debugging. The response in the Network tab will show the `var_dump` output.
*   **MailHog UI:** If `mail()` is successful, the email will appear in the MailHog web UI (`http://localhost:8025`). You can inspect its content and headers there. If it doesn't appear, `mail()` likely failed, and you should see the error message from `mail.php` in your Vue app's submission status.

This setup should allow you to effectively test and debug the email sending functionality.