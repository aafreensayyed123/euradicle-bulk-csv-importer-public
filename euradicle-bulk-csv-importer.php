<?php
/**
 * Plugin Name: Bulk CSV Importer with Datasheet Upload
 * Description: A plugin to bulk upload entries from a CSV file into the "student" custom post type, including uploading images and datasheets.
 * Version: 1.5
 * Author: Aafreen Sayyed
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BulkCSVImporter
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_process_csv_upload', [$this, 'process_csv_upload']);
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'Bulk CSV Importer',
            'Bulk CSV Importer',
            'manage_options',
            'bulk-csv-importer',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Bulk CSV Importer</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_csv_upload">
                <?php wp_nonce_field('bulk_csv_importer_nonce', 'bulk_csv_importer_nonce_field'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csv_file">Upload CSV File</label>
                        </th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload and Import'); ?>
            </form>
        </div>
        <?php
    }

    public function process_csv_upload()
    {
        // Nonce verification
        if (
            !isset($_POST['bulk_csv_importer_nonce_field']) ||
            !wp_verify_nonce($_POST['bulk_csv_importer_nonce_field'], 'bulk_csv_importer_nonce')
        ) {
            wp_die('Nonce verification failed.');
        }

        // Check if a file is uploaded
        if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_die('Invalid file type. Please upload a CSV file.');
        }

        $file_path = $file['tmp_name'];
        if (($handle = fopen($file_path, 'r')) === false) {
            wp_die('Unable to open the uploaded file.');
        }

        $headers = fgetcsv($handle); // Read the header row
        if (!$headers) {
            wp_die('Invalid CSV file format.');
        }

        $row_count = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Create a new post
            $post_id = $this->create_student_post($row);

            if (!is_wp_error($post_id)) {
                // Save meta fields and repeater data
                $this->save_meta_fields($post_id, $row);
                $row_count++;
            }
        }

        fclose($handle);

        wp_redirect(admin_url('tools.php?page=bulk-csv-importer&rows_imported=' . $row_count));
        exit;
    }

    private function create_student_post($row)
    {
        // Check if a post with the same "student-first-name" and "student-last-name" exists
        $existing_posts = get_posts([
            'post_type' => 'student',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'student-first-name',
                    'value' => $row['student-first-name'],
                    'compare' => '=',
                ],
                [
                    'key' => 'student-last-name',
                    'value' => $row['student-last-name'],
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids', // Only fetch post IDs
        ]);

        if (!empty($existing_posts)) {
            // If a matching post exists, return its ID to update the repeater field
            return $existing_posts[0];
        }

        // If no matching post exists, create a new one
        $post_data = [
            'post_title' => $row['student-first-name'] . ' ' . $row['student-last-name'] ?? 'Student',
            'post_status' => 'publish',
            'post_type' => 'student',
        ];

        return wp_insert_post($post_data);
    }

    private function save_meta_fields($post_id, $row)
    {
        $certificate_data = [];

        foreach ($row as $meta_key => $meta_value) {
            // Sanitize the meta key and value
            $meta_key = sanitize_key($meta_key);
            $meta_value = sanitize_text_field($meta_value);

            // Handle certificate-related fields
            if (strpos($meta_key, 'certificate-') === 0) {
                $field_name = str_replace('certificate-', '', $meta_key);

                // Process "upload-certificate" (file uploads)
                if ($field_name === 'upload-certificate' && !empty($meta_value)) {
                    $file_url = trim($meta_value);
                    $attachment_id = $this->upload_file_to_media_library($file_url, $post_id);

                    if ($attachment_id) {
                        $meta_value = wp_get_attachment_url($attachment_id);
                    } else {
                        error_log("Failed to upload file: $file_url");
                        continue;
                    }
                }

                $certificate_data[$field_name] = $meta_value ?: ''; // Ensure no null values
            } else {
                // Save other meta fields, such as student-first-name and student-last-name
                update_post_meta($post_id, $meta_key, $meta_value);
            }
        }

        // Save repeater field data if there is certificate-related data
        if (!empty($certificate_data)) {
            $this->save_repeater_field($post_id, $certificate_data);
        }
    }

    private function save_repeater_field($post_id, $certificate_data)
    {
        // Prepare repeater field item
        $new_certificate_item = [
            'number' => $certificate_data['number'] ?? '',
            'upload-certificate' => $certificate_data['upload-certificate'] ?? '',
            'course' => $certificate_data['course'] ?? '',
            'batches' => $certificate_data['batches'] ?? '',
        ];

        // Retrieve existing repeater data
        $existing_certificates = maybe_unserialize(get_post_meta($post_id, 'certificate', true));
        if (!is_array($existing_certificates)) {
            $existing_certificates = [];
        }

        // Add the new certificate item
        $existing_certificates[] = $new_certificate_item;

        // Save the updated repeater field
        update_post_meta($post_id, 'certificate', $existing_certificates);
    }

    private function upload_file_to_media_library($file_url, $post_id = 0)
    {
        $file_name = basename(parse_url($file_url, PHP_URL_PATH));

        // Check if the file already exists
        $existing_attachment = get_posts([
            'post_type' => 'attachment',
            'name' => sanitize_title($file_name),
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($existing_attachment)) {
            return $existing_attachment[0];
        }

        // Download the file
        $response = wp_remote_get($file_url, ['timeout' => 15]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log("Failed to fetch file from URL: $file_url");
            return false;
        }

        $file_contents = wp_remote_retrieve_body($response);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $file_name;

        if (!file_put_contents($file_path, $file_contents)) {
            error_log("Failed to save file: $file_name");
            return false;
        }

        // Insert into media library
        $filetype = wp_check_filetype($file_path, null);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($file_name),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log("Failed to insert attachment: $file_name");
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }
}

new BulkCSVImporter();