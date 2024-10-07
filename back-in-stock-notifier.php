<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Back In Stock Notifier
 * Plugin URI:        https://github.com/nicksleap/NS-Back-In-Stock-Notifier-for-Woocommerce
 * Description:       Back In Stock Notifier for Woocommerce
 * Version:           0.0.1
 * Author:            Nikolai Portnov
 * Author URI:        https://github.com/nicksleap/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ns-back-in-stock-notifier
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


class nsNotifier
{

    public $options;

    private string $table;

    public function __construct()
    {
        $this->loadOptions();

        add_action('admin_menu', [$this, 'add_ns_notifier_admin_link']);
        add_action('admin_init', [$this, 'ns_notifier_settings_init']);

        add_action('wp_footer', [$this, 'ns_add_templates']);

        add_action('admin_head', [$this, 'load_admin_scripts_and_styles']);
        add_action('wp_enqueue_scripts', [$this, 'load_scripts_and_styles']);

        add_action('wp_ajax_ns_send_notifier_email', [$this, 'send_notifier_email']);
        add_action('wp_ajax_nopriv_ns_send_notifier_email', [$this, 'send_notifier_email']);
        add_action('wp_ajax_ns_send_emails', [$this, 'send_emails']);
        add_action('wp_ajax_ns_export_emails', [$this, 'export_emails']);

        global $wpdb;
        $this->table = $wpdb->prefix . 'notify_email_list';
        register_activation_hook(__FILE__, [$this, 'create_email_table']);

        // Register Cron hook
        add_action('notifier_auto_send_notifications', array($this, 'cron_auto_send_notifications'), 99, 0);
    }

    /**
     * @return void
     */
    public function loadOptions(): void
    {
        $this->options = get_option('ns-notifier_options');
    }


    public function load_scripts_and_styles()
    {
        if (!is_product()) return;

        wp_enqueue_script(
            'ns-notifier',
            plugin_dir_url(__FILE__) . 'assets/js/notifier.js',
            array('jquery', 'jquery-core'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/notifier.js'),
            true
        );

        wp_enqueue_style('ns-notifier', plugin_dir_url(__FILE__) . 'assets/css/notifier.css', array(), '1.0');
        wp_localize_script('ns-notifier', 'notifier', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'ajax_nonce'    => wp_create_nonce("notify-me"),
        ]);
    }

    public function load_admin_scripts_and_styles()
    {
        global $pagenow, $post;
        if ($pagenow !== 'admin.php' || !$_GET['page'] || $_GET['page'] !== 'ns-notifier-plugin') return;

        wp_enqueue_script('ns-notifier-admin', plugin_dir_url(__FILE__) . 'admin/js/notifier-admin.js', array('jquery', 'jquery-core'), '1.0', true);
        wp_enqueue_style('ns-notifier-admin', plugin_dir_url(__FILE__) . 'admin/css/notifier-admin.css', array(), '1.0');
        wp_localize_script('ns-notifier-admin', 'notifier', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce("notify-me"),
        ]);
    }

    public function ns_add_templates()
    {
        global $product;
        if (is_product() && get_field('add_to_email_notifier', $product->get_id())) {
            include(plugin_dir_path(__FILE__) . 'tpl/notify-form.php');
        }
    }


    /**
     * Ajax Send Notify Email
     */
    public function send_notifier_email()
    {
        check_ajax_referer('notify-me', 'security');

        $product_id = absint($_POST['product']);
        $size = $_POST['size'] === '{{size}}' ? '' : $_POST['size'];
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $variation_id = absint($_POST['variation_id']);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            wp_send_json_error(['msg' => __('Email address is invalid')]);
            wp_die();
        }

        if ($this->is_notify_exist($email, $variation_id)) {
            wp_send_json_error(['msg' => __('You have already subscribed to this product')]);
            wp_die();
        }

        $data = array(
            'time' => current_time('mysql'),
            'last_update' => current_time('mysql'),
            'email' => $email,
            'client_ip' => $this->get_user_ip(),
            'client_browser' => $_SERVER['HTTP_USER_AGENT'],
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'size' => $size ?? '',
            'send_status' => 0
        );

        global $wpdb;
        $result = $wpdb->insert(
            $this->table,
            $data,
        );

        if ($result === false) {
            wp_send_json_error([
                'msg' => 'wpdb insert return false',
                // 'error' => $wpdb->print_error(),
                // 'data'  => $data,
                // 'table' => $this->table,
                // 'Last query' => $wpdb->last_query,
                // 'Last error' => $wpdb->last_error,
            ]);
        }

        wp_send_json_success([
            'msg' => __('Your Email Address was saved successfully. We will notify you when product.'),
        ]);
        wp_die();
    }

    /**
     * Ajax Check Stock & Send Emails from Admin
     */
    public function send_emails()
    {
        check_ajax_referer('notify-me', 'security');

        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE send_status = 0"));

        $logger = wc_get_logger();

        if (count($result) > 0) {
            foreach ($result as $item) {
                //$product_id = absint($item->product_id);
                $variation = wc_get_product($item->variation_id);
                //$variation_obj = new WC_Product_variation($variation_id);
                $stock = $variation->get_stock_quantity();
                $logger->debug($item->variation_id . ' in stock: ' . $stock, array('source' => 'ns-notifier'));
                if ($stock > 0) {
                    $logger->debug($item->variation_id . ' sending email: ' . $item->email, array('source' => 'ns-notifier'));
                    $this->send_notify_email($item->id);
                }
            }
        }
        wp_send_json_success();
        wp_die();
    }

    /**
     * Ajax Export Emails
     */
    public function export_emails()
    {
        check_ajax_referer('notify-me', 'security');

        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE 1"));

        //$logger = wc_get_logger();
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/';
        $csv_out = fopen($upload_dir . 'notify-emails.csv', 'w');
        fputcsv($csv_out, ['Date opened', 'Email', 'SKU', 'Date email sent']);

        if (count($result) > 0) {
            foreach ($result as $item) {
                $variation = wc_get_product($item->variation_id);
                $data = [$item->time, $item->email, $variation->get_sku(), ($item->send_status == '1' ? $item->last_update : '')];
                fputcsv($csv_out, $data);
            }
        }
        fclose($csv_out);
        wp_send_json_success(['filename' => home_url('/') . 'wp-content/uploads/notify-emails.csv']);
        wp_die();
    }


    /**
     * Add admin settings
     */
    function add_ns_notifier_admin_link()
    {
        add_submenu_page('woocommerce', 'NotifyMe Emails', 'NotifyMe Emails', 'manage_options', 'ns-notifier-plugin', [$this, 'plugin_function']);
    }

    /**
     * Register plugin settings parameters
     * @return void
     */
    function ns_notifier_settings_init(): void
    {
        register_setting('ns-notifier-setting', 'ns-notifier_options');
        add_settings_section('ns-notifier-plugin-section-emails', __('Notify Me When Available\'s Emails', 'ns-notifier'), '', 'ns-notifier-setting');
    }

    /**
     * Draw settings page
     * @return void
     */
    function plugin_function(): void
    {
        settings_fields('ns-notifier-setting');
        do_settings_sections('ns-notifier-setting');
        $this->email_table();
    }

    function email_table(): void
    {
        global $wpdb;

        echo '<p>';
        $this->email_table_sort();
        $this->email_table_filter();
        $this->send_emails_btn();
        echo '</p>';

        echo '<table id="email-table" class="wp-list-table widefat fixed striped table-view-list posts">';
        echo '<tr><th>Email</th><th>Product</th><th>Variation</th><th>Attributes</th><th>Client IP</th><th>Browser</th><th>Sent</th><th>Last update</th></tr>';

        if (isset($_GET['like']) && $_GET['like'] !== '')
            $sql = "SELECT t.*,p.post_title FROM `{$this->table}` t JOIN `{$wpdb->posts}` p ON t.product_id = p.ID WHERE 1";
        else
            $sql = "SELECT * FROM `{$this->table}` WHERE 1";

        if (isset($_GET['status']) && $_GET['status'] !== '') $sql .= $wpdb->prepare(" AND send_status = %d", $_GET['status']);
        if (isset($_GET['date']) && $_GET['date'] !== '') $sql .= $wpdb->prepare(" AND last_update >= %s", $_GET['date']);
        if (isset($_GET['like']) && $_GET['like'] !== '') $sql .= " AND (email like '%" . $_GET['like'] . "%' OR post_title like '%" . $_GET['like'] . "%') ";
        if ($_GET['sortby']) $sql .= $wpdb->prepare(" ORDER BY %i ASC", $_GET['sortby']);
        else $sql .= " ORDER BY id DESC";
        $result = $wpdb->get_results($sql);
        //var_dump($sql);

        if ($result) {
            foreach ($result as $item) {
                $product = wc_get_product($item->product_id);
                $variation = wc_get_product($item->variation_id);

                echo '<tr>';
                echo '<td>' . $item->email . '</td>';
                echo '<td><a href="/wp-admin/post.php?post=' . $item->product_id . '&action=edit">' . $product->get_name() . '</a></td>';
                echo '<td><a href="' . get_permalink($item->product_id) . '">' . $item->variation_id . '</a></td>';
                echo '<td>Color: ' . urldecode($variation->get_attributes()['pa_color']) . '</td>'; //'<br> Size: <strong>' . strtoupper($item->size) . '</strong></td>';
                echo '<td>' . $item->client_ip . '</td>';
                echo '<td>' . $item->client_browser . '</td>';
                echo '<td>' . $item->send_status . '</td>';
                echo '<td>' . $item->last_update . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }

    function email_table_sort(): void
    {
        echo '<select id="notify-sortby">
			<option value="">Sort by</options>
			<option value="email" ' . (isset($_GET['sortby']) && $_GET['sortby'] === 'email' ? 'selected' : '') . '>Sort by Email</options>
			<option value="product_id" ' . (isset($_GET['sortby']) && $_GET['sortby'] === 'product_id' ? 'selected' : '') . '>Sort by ProductId</options>
			<option value="variation_id" ' . (isset($_GET['sortby']) && $_GET['sortby'] === 'variation_id' ? 'selected' : '') . '>Sort by VariationId</options>
		</select>';
    }

    function email_table_filter(): void
    {
        echo '<label for="notify-filter-status" style="margin-left:50px">Add filter: <select id="notify-filter-status" name="notify-filter-status">
			<option value="">by status</options>
			<option value="1" ' . (isset($_GET['status']) && $_GET['status'] === '1' ? 'selected' : '') . '>Sent</options>
			<option value="0" ' . (isset($_GET['status']) && $_GET['status'] === '0' ? 'selected' : '') . '>Unsent</options>
		</select></label>';

        echo '<label for="notify-filter-date" style="margin-left:10px">by date: <input type="datetime-local" id="notify-filter-date" name="notify-filter-date" value="' . $_GET['date'] . '"></label>';

        echo '<label for="notify-search" style="margin-left:20px">Search: <input type="text" id="notify-search" name="notify-search" value="' . $_GET['like'] . '"></label>';
        echo '<button id="add-search-btn" class="button">Ok</button>';
    }

    function send_emails_btn(): void
    {
        echo '<button style="float:right;margin-right:15px" id="send-emails-btn" class="button button-primary">Send Emails Now<i></i></button>';
        echo '<button style="float:right;margin-right:10px" id="export-emails-btn" class="button">Export Emails<i></i></button>';
    }

    function add_index_table_btn()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<button style="float:right;margin-right:10px" id="add-indexes-btn" class="button">Add indexes<i></i></button>';
    }

    /**
     * Show settings for chain id field
     * @return void
     */
    /*function plugin_api_id(): void
	{
		echo "<input id='plugin_api_id' name='ns-notifier_options[api_id]' type='text' value='" . esc_attr($this->options['api_id']) . "' />";
	}

	function plugin_test_mode(): void
	{
		$isChecked = $this->options['test_mode'] ? 'checked' : '';
		echo "<label for='plugin_test_mode'>
				<input id='plugin_test_mode' name='ns-notifier_options[test_mode]' type='checkbox' " . $isChecked . " value='1' />" .
				__( 'Enable Test Mode', 'ns-notifier' ) .
			"</label>";
	}*/


    /**
     * Create Notify Emails Table
     */
    public function create_email_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `{$this->table}` (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            last_update datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            email varchar(255) NOT NULL,
            client_ip varchar(255) NOT NULL,
            client_browser varchar(255) NOT NULL,
            product_id int NOT NULL,
            variation_id int NOT NULL,
            size varchar(16) NOT NULL,
            send_status tinyint NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        $this->add_indexes_to_table();
    }

    private function add_indexes_to_table()
    {
        global $wpdb;
        $table_name = $this->table;
        $index_name = 'idx_email';

        // Проверяем существование индекса
        $result = $wpdb->get_results("SHOW INDEX FROM `{$table_name}` WHERE Key_name = '{$index_name}'");
        if (!empty($result)) {
            // SQL команда для удаления старого индекса и добавления нового
            $sql = "ALTER TABLE `{$table_name}`
                    DROP INDEX `{$index_name}`,
                    ADD INDEX `{$index_name}` (`email`, `variation_id`, `send_status`) USING BTREE;";
        } else {
            // Добавляем индекс, если он не существует
            $sql = "ALTER TABLE `{$table_name}`
                    ADD INDEX `{$index_name}` (`email`, `variation_id`, `send_status`) USING BTREE;";

        }
        $wpdb->query($sql);
    }

    /**
     * Get User IP
     */
    public function get_user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Send User Notify email
     */
    function send_notify_email($id)
    {
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE id = %d", [$id]));

        //$logger = wc_get_logger();
        //$logger->debug( 'id: ' . $id . ' ' . json_encode($result), array( 'source' => 'ns-notifier' ));

        $product_id = absint($result->product_id);
        $size = strtoupper($result->size);
        $email = $result->email;
        $product = wc_get_product($product_id);
        $product_name = $product->get_name();
        $product_url = get_permalink($product_id);
        $variation = wc_get_product($result->variation_id);
        $color = $variation->get_attributes()['pa_color'];

        $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
        $img = $image[0];

        ob_start();
        include(plugin_dir_path(__FILE__) . '/tpl/html-notify-email.php');
        $email_content = ob_get_contents();
        ob_end_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $ret = wp_mail($email, __('Your product notification', ), $email_content, $headers); //

        if ($ret) $wpdb->update(
            $this->table,
            [
                'last_update' => current_time('mysql'),
                'send_status' => 1
            ],
            [
                'id' => $id
            ]
        );
    }

    /**
     * Cron functions
     */
    public function cron_auto_send_notifications()
    {
        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE send_status = 0"));

        $logger = wc_get_logger();

        if (count($result) > 0) {
            foreach ($result as $item) {
                //$product_id = absint($item->product_id);
                $variation = wc_get_product($item->variation_id);
                //$variation_obj = new WC_Product_variation($variation_id);
                $stock = $variation->get_stock_quantity();
                $logger->debug($item->variation_id . ' in stock: ' . $stock, array('source' => 'ns-notifier'));
                if ($stock > 0) {
                    $logger->debug($item->variation_id . ' sending email: ' . $item->email, array('source' => 'ns-notifier'));
                    $this->send_notify_email($item->id);
                }
            }
        }
        echo json_encode(['success' => true]);
        die();
    }

    private function is_notify_exist($email , $variation_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM $this->table WHERE email = %s AND variation_id = %d AND send_status != 1",
            $email,
            $variation_id
        );

        $result = $wpdb->get_row($query);

        // Return null or string|object with data
        return $result;
    }
}

new nsNotifier();
