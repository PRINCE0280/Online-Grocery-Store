<?php
require_once 'config.php';

// Email configuration - Update these with your SMTP settings
define('SMTP_HOST', 'smtp.gmail.com');  
define('SMTP_PORT', 587);              
define('SMTP_USERNAME', 'newkumar322@gmail.com'); 
define('SMTP_PASSWORD', 'fppfqrkstylliasl');     
define('SMTP_FROM_EMAIL', 'newkumar322@gmail.com');
define('SMTP_FROM_NAME', 'FreshMart');

// Alternative: For testing, you can use mail() function instead of SMTP
define('USE_SMTP', true); // Set to false to use PHP's mail() function (no Composer needed)

/**
 * Send email using either SMTP or PHP's mail() function
 */
function sendEmail($to, $subject, $htmlContent, $plainContent = '') {
    if (USE_SMTP) {
        return sendEmailWithSMTP($to, $subject, $htmlContent, $plainContent);
    } else {
        return sendEmailWithMailFunction($to, $subject, $htmlContent);
    }
}

/**
 * Send email using SMTP (requires PHPMailer)
 */
function sendEmailWithSMTP($to, $subject, $htmlContent, $plainContent = '') {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Try to load PHPMailer if composer is available
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
        } else {
            error_log("PHPMailer not found. Please install it using composer or download manually.");
            return false;
        }
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlContent;
        if (!empty($plainContent)) {
            $mail->AltBody = $plainContent;
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHP's mail() function (fallback)
 */
function sendEmailWithMailFunction($to, $subject, $htmlContent) {
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8',
        'MIME-Version: 1.0'
    ];

    $result = mail($to, $subject, $htmlContent, implode("\r\n", $headers));
    
    if (!$result) {
        error_log("Email sending failed using mail() function");
    }
    
    return $result;
}

/**
 * Get user email by user ID
 */
function getUserEmail($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['email'] : null;
    } catch (Exception $e) {
        error_log("Error fetching user email: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate order confirmation email HTML
 */
function generateOrderConfirmationEmail($orderData) {
    $orderItems = '';
    $subtotal = 0;
    
    foreach ($orderData['items'] as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $subtotal += $itemTotal;
        
        $orderItems .= "
        <tr>
            <td style='padding: 12px; border-bottom: 1px solid #eee;'>
                <strong>" . htmlspecialchars($item['name']) . "</strong>
            </td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>
                " . $item['quantity'] . "
            </td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>
                ₹" . number_format($item['price'], 2) . "
            </td>
            <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>
                <strong>₹" . number_format($itemTotal, 2) . "</strong>
            </td>
        </tr>";
    }

    $paymentMethodText = $orderData['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment';
    $deliveryOptionText = $orderData['delivery_option'] === 'express' ? 'Express Delivery' : 'Standard Delivery';
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Order Confirmation</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
            <!-- Header -->
            <div style='background-color: #16a085; color: white; padding: 30px 20px; text-align: center;'>
                <h1 style='margin: 0; font-size: 28px;'>Order Confirmed!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px;'>Thank you for shopping with FreshMart</p>
            </div>
            
            <!-- Order Info -->
            <div style='padding: 30px 20px;'>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                    <h2 style='margin: 0 0 15px 0; color: #16a085;'>Order Details</h2>
                    <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>
                        <div>
                            <p style='margin: 0; font-weight: bold;'>Order ID:</p>
                            <p style='margin: 5px 0 15px 0; font-size: 18px; color: #16a085;'>" . htmlspecialchars($orderData['order_id']) . "</p>
                        </div>
                        <div>
                            <p style='margin: 0; font-weight: bold;'>Order Date:</p>
                            <p style='margin: 5px 0 15px 0;'>" . date('M d, Y H:i', strtotime($orderData['order_date'])) . "</p>
                        </div>
                        <div>
                            <p style='margin: 0; font-weight: bold;'>Payment Method:</p>
                            <p style='margin: 5px 0 15px 0;'>" . $paymentMethodText . "</p>
                        </div>
                        <div>
                            <p style='margin: 0; font-weight: bold;'>Delivery Option:</p>
                            <p style='margin: 5px 0 15px 0;'>" . $deliveryOptionText . "</p>
                        </div>
                    </div>
                </div>
                
                <!-- Delivery Address -->
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px 0; color: #16a085;'>Delivery Address</h3>
                    <p style='margin: 0;'><strong>" . htmlspecialchars($orderData['name']) . "</strong></p>
                    <p style='margin: 5px 0;'>" . htmlspecialchars($orderData['address']) . "</p>
                    <p style='margin: 5px 0;'>Pin Code: " . htmlspecialchars($orderData['pincode']) . "</p>
                    <p style='margin: 5px 0;'>Mobile: " . htmlspecialchars($orderData['mobile']) . "</p>
                </div>
                
                <!-- Order Items -->
                <h3 style='color: #16a085; margin: 0 0 15px 0;'>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                    <thead>
                        <tr style='background-color: #f8f9fa;'>
                            <th style='padding: 12px; text-align: left; border-bottom: 2px solid #16a085;'>Product</th>
                            <th style='padding: 12px; text-align: center; border-bottom: 2px solid #16a085;'>Qty</th>
                            <th style='padding: 12px; text-align: right; border-bottom: 2px solid #16a085;'>Price</th>
                            <th style='padding: 12px; text-align: right; border-bottom: 2px solid #16a085;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        " . $orderItems . "
                    </tbody>
                </table>
                
                <!-- Order Summary -->
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px;'>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span>Subtotal:</span>
                        <span>₹" . number_format($orderData['subtotal'], 2) . "</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span>Delivery Fee:</span>
                        <span>₹" . number_format($orderData['delivery_fee'], 2) . "</span>
                    </div>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                    <div style='display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #16a085;'>
                        <span>Total Amount:</span>
                        <span>₹" . number_format($orderData['total'], 2) . "</span>
                    </div>
                </div>
                
                <!-- Footer Message -->
                <div style='text-align: center; margin-top: 30px; padding: 20px; background-color: #e8f5e8; border-radius: 8px;'>
                    <p style='margin: 0; font-size: 16px; color: #16a085;'>
                        <strong>Your order has been confirmed and will be delivered soon!</strong>
                    </p>
                    <p style='margin: 10px 0 0 0;'>You'll receive updates about your order status via email.</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background-color: #2c3e50; color: white; padding: 20px; text-align: center;'>
                <p style='margin: 0; font-size: 14px;'>Thank you for choosing FreshMart!</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>For any queries, contact us at princesingh68912@gmail.com</p>
            </div>
        </div>
    </body>
    </html>";

    return $html;
}

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($orderData, $userEmail) {
    $subject = "Order Confirmation - " . $orderData['order_id'] . " | FreshMart";
    $htmlContent = generateOrderConfirmationEmail($orderData);
    
    return sendEmail($userEmail, $subject, $htmlContent);
}

/**
 * Send admin notification email about new order
 */
function sendAdminOrderNotification($orderData, $userEmail) {
    $adminEmail = 'princesingh68912@gmail.com'; // Your admin email
    $subject = "New Order Received - " . $orderData['order_id'] . " | FreshMart";
    
    $orderItems = '';
    foreach ($orderData['items'] as $item) {
        $orderItems .= "- " . $item['name'] . " (Qty: " . $item['quantity'] . ", Price: ₹" . number_format($item['price'], 2) . ")\n";
    }
    
    $paymentMethodText = $orderData['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment';
    $deliveryOptionText = $orderData['delivery_option'] === 'express' ? 'Express Delivery' : 'Standard Delivery';
    
    $htmlContent = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>New Order Notification</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #e74c3c;'>🚨 New Order Received!</h2>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Order Information</h3>
                <p><strong>Order ID:</strong> " . htmlspecialchars($orderData['order_id']) . "</p>
                <p><strong>Customer:</strong> " . htmlspecialchars($orderData['name']) . "</p>
                <p><strong>Customer Email:</strong> " . htmlspecialchars($userEmail) . "</p>
                <p><strong>Mobile:</strong> " . htmlspecialchars($orderData['mobile']) . "</p>
                <p><strong>Order Date:</strong> " . date('M d, Y H:i', strtotime($orderData['order_date'])) . "</p>
                <p><strong>Payment Method:</strong> " . $paymentMethodText . "</p>
                <p><strong>Delivery Option:</strong> " . $deliveryOptionText . "</p>
                <p><strong>Total Amount:</strong> ₹" . number_format($orderData['total'], 2) . "</p>
            </div>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Delivery Address</h3>
                <p>" . htmlspecialchars($orderData['address']) . "</p>
                <p>Pin Code: " . htmlspecialchars($orderData['pincode']) . "</p>
            </div>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Order Items</h3>
                " . nl2br(htmlspecialchars($orderItems)) . "
            </div>
            
            <p style='margin-top: 30px;'>Please process this order promptly.</p>
            <p><a href='http://localhost:8080/grocery-web-app/admin/admin_orders.php' style='background-color: #16a085; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Order Details</a></p>
        </div>
    </body>
    </html>";
    
    return sendEmail($adminEmail, $subject, $htmlContent);
}
?>