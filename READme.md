# Bulk CSV Importer with Datasheet Upload

## Plugin Description

The **Bulk CSV Importer with Datasheet Upload** plugin enables you to bulk upload entries from a CSV file into the "student" custom post type. It also supports uploading images, certificates, and datasheets as attachments while maintaining repeater field data for certificates. This plugin is especially useful for managing student information with associated metadata.

---

## Features

- Bulk import entries from CSV files into the "student" custom post type.
- Automatically create new posts or update existing ones based on matching `student-first-name` and `student-last-name`.
- Supports repeater fields for certificates, appending new data if the student already exists.
- Handles file uploads (e.g., certificates) by fetching files from remote URLs and adding them to the WordPress Media Library.
- Easy-to-use interface available under **Tools > Bulk CSV Importer**.

---

## Installation

1. **Download and Install**:

   - Clone or download this repository as a ZIP file.
   - Log in to your WordPress admin panel.
   - Go to **Plugins > Add New > Upload Plugin**.
   - Select the downloaded ZIP file and click **Install Now**.
   - Activate the plugin.

2. **Dependencies**:
   - The plugin assumes the existence of a custom post type `student`. Ensure the `student` post type is registered in your WordPress setup.
   - For repeater fields, make sure you are using the [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/) plugin or a similar plugin supporting repeaters.

---

## How to Use

1. **Upload CSV**:

   - Navigate to **Tools > Bulk CSV Importer**.
   - Upload your CSV file using the provided form.

2. **CSV Format**:

   - The first row should contain headers matching your meta fields and repeater field structure.
   - Required fields:
     - `student-first-name`
     - `student-last-name`
   - Optional repeater fields for certificates:
     - `certificate-number`
     - `certificate-upload-certificate` (URL for certificate file)
     - `certificate-course`
     - `certificate-batches`

3. **Import Behavior**:
   - The plugin checks if a post with matching `student-first-name` and `student-last-name` exists.
   - If a match is found, it appends new certificate data to the existing repeater field.
   - Otherwise, it creates a new `student` post and adds the associated metadata.

---

## CSV Example

```csv
student-first-name,student-last-name,certificate-number,certificate-upload-certificate,certificate-course,certificate-batches
John,Doe,12345,http://example.com/certificate1.pdf,Math,2024
Jane,Doe,67890,http://example.com/certificate2.pdf,Science,2023
John,Doe,54321,http://example.com/certificate3.pdf,Physics,2025
```
