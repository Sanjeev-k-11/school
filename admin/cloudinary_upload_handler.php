<?php
// School/admin/cloudinary_upload_handler.php

// Include Composer's autoloader for Cloudinary library
// Path from 'admin/' up to 'School/' is '../'
// ** Ensure composer install has been run in the root directory (School/) **
require_once "../vendor/autoload.php";

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Exception\Upload\UploadException; // Import the specific upload exception class

// Configure Cloudinary (replace with your actual credentials)
Configuration::instance([
  'cloud' => [
    'cloud_name' => 'do9mane7a', // Replace with your Cloud Name
    'api_key' => '269318732537666',       // Replace with your API Key
    'api_secret' => 'h_bfpaCLme-m3T20BEyyH6TfHEo'], // Replace with your API Secret
  'url' => [
    'secure' => true]]);

/**
 * Uploads a file to Cloudinary.
 *
 * @param array $file The file data from $_FILES (e.g., $_FILES['student_photo']).
 * @param string $folder Optional folder name within Cloudinary.
 * @return array|false|array An associative array with ['secure_url'] and ['public_id'] on success.
 *                         Returns ['error' => 'message'] on validation or Cloudinary error.
 *                         Returns false if no file was uploaded or basic PHP upload error occurred.
 */
function uploadToCloudinary($file, $folder = 'student_photos') {
    // Check if a file was actually uploaded and no basic PHP errors occurred
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
         if ($file['error'] == UPLOAD_ERR_NO_FILE) {
              return false; // Indicate no file was submitted (this is not an "error" to the user, just no photo)
         }
        // Handle other potential PHP upload errors before sending to Cloudinary
         $error_message = "File upload failed: ";
          switch ($file['error']) {
              case UPLOAD_ERR_INI_SIZE:
              case UPLOAD_ERR_FORM_SIZE: $error_message .= "File is too large."; break;
              case UPLOAD_ERR_PARTIAL: $error_message .= "File was only partially uploaded."; break;
              case UPLOAD_ERR_NO_TMP_DIR: $error_message .= "Missing temporary folder on the server."; break;
              case UPLOAD_ERR_CANT_WRITE: $error_message .= "Failed to write file to disk on the server."; break;
              case UPLOAD_ERR_EXTENSION: $error_message .= "A PHP extension blocked the upload."; break;
              default: $error_message .= "Unknown upload error (Code: " . $file['error'] . ")."; break;
          }
         error_log("PHP File Upload Error in uploadToCloudinary: " . $error_message . " for file " . ($file['name'] ?? 'N/A'));
         return ['error' => $error_message]; // Return error array for PHP errors
    }

     // Basic file validation (optional, but good practice before sending to Cloudinary)
     $max_size = 10 * 1024 * 1024; // 10MB limit
     $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

     if ($file['size'] > $max_size) {
         return ['error' => 'File is too large. Maximum size is 10MB.'];
     }

     // Get actual MIME type to prevent malicious uploads
     $real_file_type = false;
     if (function_exists('finfo_open')) {
         $finfo = finfo_open(FILEINFO_MIME_TYPE);
         if ($finfo) {
            $real_file_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
         }
     }
     if ($real_file_type === false && function_exists('mime_content_type')) {
         $real_file_type = mime_content_type($file['tmp_name']);
     }

     if ($real_file_type === false || !in_array($real_file_type, $allowed_types)) {
          // Attempt to get extension for better error message if type check fails
          $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          if (!empty($file_ext)) {
              return ['error' => 'Invalid file type or extension (' . htmlspecialchars($file_ext) . '). Allowed: JPG, PNG, GIF, WEBP.'];
          }
          return ['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP images.'];
     }


    try {
        // Perform the upload to Cloudinary
        // The upload method throws UploadException on API errors
        $uploadResult = (new UploadApi())->upload($file['tmp_name'], [
            'folder' => $folder, // Specify a folder in your Cloudinary account
             'resource_type' => 'image', // Ensure it's treated as an image
             'use_filename' => true, // Optional: Use original filename (cleaned)
             'unique_filename' => false, // Optional: Append unique characters
            // Add other options here if needed, like transformations, quality, etc.
            // Example: 'quality' => 'auto'
        ]);

        // Check if the upload was successful and returned expected keys
        if (isset($uploadResult['secure_url']) && isset($uploadResult['public_id'])) {
            // Return the relevant information on success
            return [
                'secure_url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id']
            ];
        } else {
            // Upload might have completed but didn't return expected keys
             error_log("Cloudinary upload returned unexpected structure for file " . ($file['name'] ?? 'N/A') . ": " . print_r($uploadResult, true));
            return ['error' => 'Cloudinary upload failed with unexpected response from the API.'];
        }

    } catch (UploadException $e) {
        // Catch Cloudinary specific upload exceptions (e.g., API errors, authentication failures)
         error_log("Cloudinary UploadException for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
        return ['error' => 'Cloudinary API upload failed: ' . $e->getMessage()];

    } catch (\Exception $e) {
        // Catch any other general exceptions during the process
         error_log("General Exception during Cloudinary upload for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
         return ['error' => 'An unexpected server error occurred during upload.'];
    }
}

// The example Cloudinary upload code from your original snippet
// `(new UploadApi())->upload('p4.jpg')` is a test call and should NOT be here
// in a production helper file. It has been removed.