<?php
header('Content-Type: application/json');
// Allow requests from any origin. For production, you might want to restrict this
// to your Vue app's domain, e.g., header('Access-Control-Allow-Origin: https://yourvueapp.com');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Handle pre-flight CORS requests
    http_response_code(200);
    exit();
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expected POST variables from your Vue form
    $customerEmailInput = isset($_POST['realemail']) ? trim($_POST['realemail']) : '';
    // Ensure this matches the key sent by Vue. If Vue sends 'company_name', use that.
    // Your original PHP used 'company-name'. Let's assume Vue sends 'company_name' as per our previous Vue diff.
    $companyName = isset($_POST['company_name']) ? htmlspecialchars(trim($_POST['company_name']), ENT_QUOTES, 'UTF-8') : 'N/A';
    $orderFormLink = isset($_POST['url_link']) ? filter_var(trim($_POST['url_link']), FILTER_SANITIZE_URL) : 'N/A';
    // This 'order_details' field should contain the structured order information from Vue
    $orderDetails = isset($_POST['order_details']) ? trim($_POST['order_details']) : 'No order details provided.';

    // Extract the first email if multiple are provided (semicolon separated)
    $customerEmailParts = explode(";", $customerEmailInput);
    $customerEmail = '';
    if (!empty($customerEmailParts[0]) && filter_var(trim($customerEmailParts[0]), FILTER_VALIDATE_EMAIL)) {
       $customerEmail = trim($customerEmailParts[0]);
    }

    if (empty($customerEmail)) {
        $response['message'] = 'Invalid or missing customer email address.';
        echo json_encode($response);
        exit;
    }
    // Email details
    // Ensure 'orders@paracay.com' is a valid sender address on your cPanel
    #$to = "orders@paracay.com," . $customerEmail;
    $to = "rowanmagnuson@gmail.com," . $customerEmail;

    $subject = "Custom Order from " . $companyName;

    // Construct the email message body
    $emailMessage = "Paradise Cay Publications - Custom Order\n\n";
    $emailMessage .= "A custom order was submitted via the Custom Order Form.\n\n";
    $emailMessage .= "Company: " . $companyName . "\n";
    $emailMessage .= "Customer Email: " . $customerEmail . "\n";
    $emailMessage .= "Your order form link is: " . $orderFormLink . "\n";
    $emailMessage .= "Bookmark this link - this order form will be updated regularly with updated order history, and should help with your reordering.\n\n";
    $emailMessage .= "--------------------------------------------------------------------------\n\n";
    $emailMessage .= "ORDER DETAILS:\n";
    $emailMessage .= str_replace(["<br>", "<br/>", "<br />"], "\n", $orderDetails); // Convert <br> to newlines for plain text
    $emailMessage .= "\n\n--------------------------------------------------------------------------\n";

    // Headers for a simple plain text email
    // Using a proper From address is important for deliverability.
    // Ensure 'orders@paracay.com' is a valid sending address on your server.
    $headers = "From: Paradise Cay Orders <orders@paracay.com>\r\n";
    $headers .= "Reply-To: " . $customerEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n"; // Use UTF-8 for better character support
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    // --- MailHog Integration (for development/testing) ---
    // This section will forward the email content to a local MailHog instance.
    // MailHog typically runs its HTTP API on port 8025.
    $mailhogApiUrl = 'http://localhost:8025/api/v2/messages';

    // Prepare the data to send to MailHog's API
    // MailHog's API expects a JSON payload representing the email.
    $mailhogPayload = [
        'From' => 'orders@paracay.com', // Must match the From header
        'To' => explode(',', $to), // Convert comma-separated string to array
        'Subject' => $subject,
        'Body' => $emailMessage,
        'Headers' => [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'Reply-To' => $customerEmail,
        ]
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($mailhogPayload),
            'ignore_errors' => true // Get response even if MailHog returns an error status
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($mailhogApiUrl, false, $context);

    // Check if the request to MailHog was successful
    if ($result === FALSE) {
        error_log("Failed to connect to MailHog at $mailhogApiUrl. Is MailHog running?");
        $response['message'] = 'Order data received, but failed to forward to MailHog. Is MailHog running?';
    } else {
        $http_response_header_str = implode("\n", $http_response_header);
        if (strpos($http_response_header_str, 'HTTP/1.1 200 OK') === false && strpos($http_response_header_str, 'HTTP/1.0 200 OK') === false) {
            error_log("MailHog API returned non-200 status: " . $http_response_header_str);
            $response['message'] = 'Order data received, but MailHog API returned an error.';
        } else {
            $response['message'] = 'Order data received. Email forwarded to MailHog.';
        }
    }

    $response['success'] = true; // Always report success to the frontend for the modal

} else {
    $response['message'] = 'Invalid request method. Only POST requests are accepted.';
    // For non-POST requests, success remains false as initialized.
}

echo json_encode($response);
exit;
?>