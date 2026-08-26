<?php

/*
 * This file is part of the Jooosi Fon package.
 *
 * (c) Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);
namespace JooosiFon\Utils;

use Exception;
use WP_Error;
/**
 * Upload utility functions for the plugin.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 * @todo Remove the legacy Yabe Webfont filter shim completely in Jooosi Fon 3.0.0.
 */
class Upload
{
    public const UPLOAD_DIRECTORY = 'jooosi-fon/fonts';
    /**
     * Add the font mime types to the allowed upload mimes.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face#description
     * @see https://developer.wordpress.org/reference/hooks/upload_mimes/
     */
    public static function upload_mimes(array $mimes, bool $manual_upload = \false): array
    {
        if (!$manual_upload && (!current_user_can('manage_options') || !isset($_POST['jooosi_fon_font_upload']))) {
            return $mimes;
        }
        $exts = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'font/eot'];
        foreach ($exts as $ext => $ext_mime) {
            if (!isset($mimes[$ext])) {
                $mimes[$ext] = $ext_mime;
            }
        }
        return $mimes;
    }
    /**
     * Disable real MIME check (introduced in WordPress 4.7.1)
     *
     * @see https://wordpress.stackexchange.com/a/252296/44794
     * @see https://developer.wordpress.org/reference/hooks/wp_check_filetype_and_ext/
     */
    public static function disable_real_mime_check(array $data, string $file, string $filename, $mimes)
    {
        $filetype = wp_check_filetype($filename, $mimes);
        return ['ext' => $filetype['ext'], 'type' => $filetype['type'], 'proper_filename' => $data['proper_filename']];
    }
    /**
     * Remote upload file to WordPress media library.
     * The implementation is based on the https://rudrastyh.com/wordpress/how-to-add-images-to-media-library-from-uploaded-files-programmatically.html#upload-image-from-url
     *
     * @param string $file_url URL of the remote file
     * @param string $file_name Name of the remote file to be stored in the media library
     * @param string $mime_type Mime type of the remote file
     * @return int|WP_Error|false Attachment ID on success, WP_Error or false on failure
     * @throws Exception
     */
    public static function remote_upload_media(string $file_url, string $file_name, string $mime_type)
    {
        require_once \ABSPATH . 'wp-admin/includes/file.php';
        $file_url = apply_filters('f!jooosi/fon/utils/upload:remote_upload_media.file_url', $file_url);
        $file_url = apply_filters_deprecated('f!yabe/webfont/utils/upload:remote_upload_media.file_url', [$file_url], '2.1.0', 'f!jooosi/fon/utils/upload:remote_upload_media.file_url');
        $temp_file = download_url($file_url, 30);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        $sideload_path = null;
        add_filter('upload_dir', [self::class, 'font_upload_dir']);
        try {
            $size = filesize($temp_file);
            $max_size = (int) apply_filters('f!jooosi/fon/utils/upload:max_file_size', 20 * \MB_IN_BYTES);
            if (!is_int($size) || $size < 1 || $size > $max_size) {
                return new WP_Error('jooosi_fon_invalid_file_size', 'The font file is empty or exceeds the upload limit.');
            }
            $file = ['name' => $file_name, 'type' => $mime_type, 'tmp_name' => $temp_file, 'size' => $size];
            $sideload = wp_handle_sideload($file, ['test_form' => \false, 'test_size' => \true]);
            if (!empty($sideload['error'])) {
                return new WP_Error('jooosi_fon_sideload_failed', (string) $sideload['error']);
            }
            $sideload_path = $sideload['file'];
            $attachment_id = wp_insert_attachment(['guid' => $sideload['url'], 'post_mime_type' => $sideload['type'], 'post_title' => basename($sideload['file']), 'post_content' => '', 'post_status' => 'inherit'], $sideload['file']);
            if (is_wp_error($attachment_id)) {
                wp_delete_file($sideload_path);
                return $attachment_id;
            }
            if (!is_int($attachment_id) || $attachment_id < 1) {
                wp_delete_file($sideload_path);
                return new WP_Error('jooosi_fon_attachment_failed', 'The font attachment could not be created.');
            }
            return $attachment_id;
        } finally {
            remove_filter('upload_dir', [self::class, 'font_upload_dir']);
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }
    /**
     * Remote upload file to WordPress media library.
     * The implementation is based on the https://rudrastyh.com/wordpress/how-to-add-images-to-media-library-from-uploaded-files-programmatically.html#upload-image-from-url
     *
     * @param string $binary binary-safe string containing the file
     * @param string $file_name Name of the remote file to be stored in the media library
     * @param string $mime_type Mime type of the remote file
     * @return int|WP_Error|false Attachment ID on success, WP_Error or false on failure
     * @throws Exception
     */
    public static function binary_upload_media(string $binary, string $file_name, string $mime_type)
    {
        require_once \ABSPATH . 'wp-admin/includes/file.php';
        $max_size = (int) apply_filters('f!jooosi/fon/utils/upload:max_file_size', 20 * \MB_IN_BYTES);
        if ($binary === '' || strlen($binary) > $max_size) {
            return new WP_Error('jooosi_fon_invalid_file_size', 'The font file is empty or exceeds the upload limit.');
        }
        $temp_file = wp_tempnam($file_name);
        if (!$temp_file) {
            return \false;
        }
        $sideload_path = null;
        add_filter('upload_dir', [self::class, 'font_upload_dir']);
        try {
            $written = file_put_contents($temp_file, $binary);
            if ($written === \false || $written !== strlen($binary)) {
                return new WP_Error('jooosi_fon_temp_write_failed', 'The temporary font file could not be written.');
            }
            $file = ['name' => $file_name, 'type' => $mime_type, 'tmp_name' => $temp_file, 'size' => $written];
            $sideload = wp_handle_sideload($file, ['test_form' => \false, 'test_size' => \true]);
            if (!empty($sideload['error'])) {
                return new WP_Error('jooosi_fon_sideload_failed', (string) $sideload['error']);
            }
            $sideload_path = $sideload['file'];
            $attachment_id = wp_insert_attachment(['guid' => $sideload['url'], 'post_mime_type' => $sideload['type'], 'post_title' => basename($sideload['file']), 'post_content' => '', 'post_status' => 'inherit'], $sideload['file']);
            if (is_wp_error($attachment_id)) {
                wp_delete_file($sideload_path);
                return $attachment_id;
            }
            if (!is_int($attachment_id) || $attachment_id < 1) {
                wp_delete_file($sideload_path);
                return new WP_Error('jooosi_fon_attachment_failed', 'The font attachment could not be created.');
            }
            return $attachment_id;
        } finally {
            remove_filter('upload_dir', [self::class, 'font_upload_dir']);
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }
    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/src#font_formats
     * @param string $mime file extension or mime type
     */
    public static function mime_keyword(string $mime): string
    {
        switch ($mime) {
            case 'woff2':
            case 'font/woff2':
                return 'woff2';
            case 'woff':
            case 'font/woff':
                return 'woff';
            case 'ttf':
            case 'font/ttf':
                return 'truetype';
            case 'otf':
            case 'font/otf':
                return 'opentype';
            case 'eot':
            case 'font/eot':
                return 'embedded-opentype';
            default:
                return 'woff2';
        }
    }
    /**
     * Get the new attachment url of a font face.
     */
    public static function refresh_font_faces_attachment_url(array $font_faces): array
    {
        foreach ($font_faces as $i => $font_face) {
            foreach ($font_face->files as $j => $file) {
                $attachment_url = wp_get_attachment_url($file->attachment_id);
                if ($attachment_url) {
                    $parsed = parse_url($attachment_url);
                    $font_faces[$i]->files[$j]->attachment_url = $parsed['path'];
                }
            }
        }
        return $font_faces;
    }
    /**
     * Get the new attachment url of a Google Fonts.
     */
    public static function refresh_google_fonts_attachment_url(array $font_files): array
    {
        foreach ($font_files as $i => $font_file) {
            if (property_exists($font_file, 'file')) {
                $attachment_url = wp_get_attachment_url($font_file->file->attachment_id);
                if ($attachment_url) {
                    $parsed = parse_url($attachment_url);
                    $font_files[$i]->file->attachment_url = $parsed['path'];
                }
            }
        }
        return $font_files;
    }
    public static function font_upload_dir(array $dir_data): array
    {
        $dir_data['path'] = $dir_data['basedir'] . '/' . self::UPLOAD_DIRECTORY;
        $dir_data['subdir'] = '/' . self::UPLOAD_DIRECTORY;
        $dir_data['url'] = $dir_data['baseurl'] . '/' . self::UPLOAD_DIRECTORY;
        return $dir_data;
    }
    /**
     * @deprecated 2.1.0 Use font_upload_dir() instead.
     * @todo Remove this legacy method alias completely in Jooosi Fon 3.0.0.
     */
    public static function wpse_custom_upload_dir($dir_data): array
    {
        _deprecated_function(__METHOD__, '2.1.0', __CLASS__ . '::font_upload_dir');
        return self::font_upload_dir((array) $dir_data);
    }
}
