<?php
/**
 * Input Validation Helper Functions
 */

/**
 * Validate a URL
 * @param string $url URL to validate
 * @return bool
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false && strlen($url) <= 2048;
}

/**
 * Validate a positive integer
 * @param mixed $value Value to validate
 * @return bool
 */
function validatePositiveInt($value) {
    return is_numeric($value) && (int)$value > 0;
}

/**
 * Validate a non-negative integer
 * @param mixed $value Value to validate
 * @return bool
 */
function validateNonNegativeInt($value) {
    return is_numeric($value) && (int)$value >= 0;
}

/**
 * Sanitize HTML input
 * @param string $input Input to sanitize
 * @return string
 */
function sanitizeHtml($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate string length
 * @param string $str String to validate
 * @param int $maxLength Maximum length
 * @param int $minLength Minimum length
 * @return bool
 */
function validateLength($str, $maxLength, $minLength = 0) {
    $len = strlen($str);
    return $len >= $minLength && $len <= $maxLength;
}

/**
 * Validate required fields in array
 * @param array $data Data array
 * @param array $requiredFields Array of required field names
 * @return array Array of missing fields (empty if all present)
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Validate display mode enum
 * @param string $mode Display mode to validate
 * @return bool
 */
function validateDisplayMode($mode) {
    return in_array($mode, ['tab', 'collapsible_tile', 'collapsible_tree'], true);
}

/**
 * Validate sort mode enum
 * @param string $mode Sort mode to validate
 * @return bool
 */
function validateSortMode($mode) {
    return in_array($mode, ['manual', 'name', 'created', 'accessed', 'frequency'], true);
}

/**
 * Validate query mode enum
 * @param string $mode Query mode to validate
 * @return bool
 */
function validateQueryMode($mode) {
    return in_array($mode, ['all', 'any'], true);
}
