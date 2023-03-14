<?php
/*
Plugin Name: Payment Loader
Description: This plugin allows you to upload payment files to your website and process them.
Version: 1.0
Author: Yan Kaminskiy
*/

// Create database table on plugin activation
function payment_loader_activate()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'payment_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int NOT NULL AUTO_INCREMENT,
        invid int NOT NULL,
        outsum int NOT NULL,
        signature varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'payment_loader_activate');

// Delete database table on plugin deactivation
function payment_loader_deactivate()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'payment_data';

    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_deactivation_hook(__FILE__, 'payment_loader_deactivate');

// Add menu item to admin panel
function payment_loader_menu()
{
    add_menu_page(
        'Payment Loader',
        'Payment Loader',
        'manage_options',
        'payment-loader',
        'payment_loader_form'
    );
}

add_action('admin_menu', 'payment_loader_menu');

// Display file upload form
function payment_loader_form()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="payment_file">
            <?php submit_button('Upload File'); ?>
        </form>
    </div>
    <?php
}

// Preparing data from a file
function preparing_data($filename)
{
    $contents = file_get_contents($filename);
    $lines = explode("----------------------", $contents);
    $result = array();
    foreach ($lines as $line) {
        if (strpos($line, "InvId") !== false) {
            preg_match("/InvId=(\d+)&OutSum=(\d+)&SignatureValue=([A-F0-9]+)/", $line, $matches);
            if (count($matches) === 4) {
                $payment = array(
                    "invid" => $matches[1],
                    "outsum" => $matches[2],
                    "signature" => $matches[3]
                );
                // Check if payment with same InvId already exists
                $exists = false;
                foreach ($result as $existingPayment) {
                    if ($existingPayment["invid"] === $payment["invid"]) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $result[] = $payment;
                }
            }
        }
    }
    return $result;
}

// Process uploaded file
function payment_loader_process_file()
{
    if (isset($_FILES['payment_file'])) {
        $file = $_FILES['payment_file'];

        if ($file['error'] == UPLOAD_ERR_OK) {
            $file_name = sanitize_file_name($file['name']);
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                global $wpdb;

                $table_name = $wpdb->prefix . 'payment_data';
                $data = preparing_data($file_path);
                foreach ($data as $value){
                    $wpdb->insert(
                        $table_name,
                        $value
                    );
                }


                echo '<div class="notice notice-success"><p>File uploaded successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Unable to upload file.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>File upload error.</p></div>';
        }
    }
}

add_action('admin_init', 'payment_loader_process_file');