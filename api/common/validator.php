<?php
/**
 * Input Validation Functions
 * 
 * Provides validation for API inputs and form data
 * 
 * @package LGU-IPMS
 * @subpackage API
 * @version 1.0.0
 */

/**
 * Validate required fields in array
 * 
 * @param array $data Data to validate
 * @param array $fields Required field names
 * @return bool|array True if valid, array of missing fields if invalid
 */
function validate_required_fields($data, $fields) {
    $missing = [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $missing[] = $field;
        }
    }
    
    return empty($missing) ? true : $missing;
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if valid email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Philippine format)
 * 
 * @param string $phone Phone to validate
 * @return bool True if valid phone
 */
function validate_phone($phone) {
    return preg_match('/^\+?63[0-9]{9,10}$|^0[0-9]{9,10}$|^\+?[0-9\s-]{7,15}$/', $phone) === 1;
}

/**
 * Validate password strength
 * 
 * @param string $password Password to validate
 * @return array Array of validation errors, empty if valid
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8 || strlen($password) > 12) {
        $errors[] = 'Password must be 8-12 characters long';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain lowercase letters';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain uppercase letters';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain numbers';
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/?]/', $password)) {
        $errors[] = 'Password must contain special characters';
    }
    
    return $errors;
}

/**
 * Validate URL
 * 
 * @param string $url URL to validate
 * @return bool True if valid URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate integer
 * 
 * @param mixed $value Value to validate
 * @param int|null $min Minimum value (optional)
 * @param int|null $max Maximum value (optional)
 * @return bool True if valid integer
 */
function validate_int($value, $min = null, $max = null) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return false;
    }
    
    $int = (int)$value;
    
    if ($min !== null && $int < $min) {
        return false;
    }
    
    if ($max !== null && $int > $max) {
        return false;
    }
    
    return true;
}

/**
 * Validate float/decimal
 * 
 * @param mixed $value Value to validate
 * @return bool True if valid float
 */
function validate_float($value) {
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
}

/**
 * Validate positive number
 * 
 * @param mixed $value Value to validate
 * @return bool True if positive number
 */
function validate_positive($value) {
    $float = (float)$value;
    return $float > 0;
}

/**
 * Validate string length
 * 
 * @param string $string String to validate
 * @param int $min Minimum length
 * @param int $max Maximum length
 * @return bool True if valid length
 */
function validate_length($string, $min = 0, $max = null) {
    $length = strlen($string);
    
    if ($length < $min) {
        return false;
    }
    
    if ($max !== null && $length > $max) {
        return false;
    }
    
    return true;
}

/**
 * Validate choice/enum
 * 
 * @param mixed $value Value to validate
 * @param array $choices Allowed choices
 * @return bool True if valid choice
 */
function validate_choice($value, $choices) {
    return in_array($value, $choices, true);
}

/**
 * Validate date format
 * 
 * @param string $date Date string to validate
 * @param string $format Expected format (default Y-m-d)
 * @return bool True if valid date
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate date is in future
 * 
 * @param string $date Date string to validate
 * @param string $format Date format (default Y-m-d)
 * @return bool True if date is in future
 */
function validate_future_date($date, $format = 'Y-m-d') {
    if (!validate_date($date, $format)) {
        return false;
    }
    
    $timestamp = strtotime($date);
    return $timestamp > time();
}

/**
 * Validate file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types or extensions
 * @param int $max_size Maximum file size in bytes
 * @return bool|string True if valid, error message if invalid
 */
function validate_file($file, $allowed_types = [], $max_size = MAX_UPLOAD_SIZE) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return 'File upload error: ' . $file['error'];
    }
    
    if ($file['size'] > $max_size) {
        return 'File exceeds maximum size of ' . format_bytes($max_size);
    }
    
    if (!empty($allowed_types)) {
        $mime_type = mime_content_type($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($mime_type, $allowed_types) && !in_array($extension, $allowed_types)) {
            return 'File type not allowed';
        }
    }
    
    return true;
}

/**
 * Sanitize input
 * 
 * @param mixed $input Input to sanitize
 * @return mixed Sanitized input
 */
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    
    if (is_string($input)) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    return $input;
}

/**
 * Validate data against multiple rules
 * 
 * @param array $data Data to validate
 * @param array $rules Validation rules
 * @return array Array of errors, empty if valid
 * 
 * @example
 * $rules = [
 *     'email' => 'required|email',
 *     'password' => 'required|min:8',
 *     'age' => 'required|int|min:18'
 * ];
 * $errors = validate_data($_POST, $rules);
 */
function validate_data($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule_string) {
        $rules_array = explode('|', $rule_string);
        
        foreach ($rules_array as $rule) {
            $rule = trim($rule);
            
            if ($rule === 'required') {
                if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                    $errors[$field] = "$field is required";
                }
            } elseif (strpos($rule, 'min:') === 0) {
                $min = (int)substr($rule, 4);
                if (isset($data[$field]) && !validate_length($data[$field], $min)) {
                    $errors[$field] = "$field must be at least $min characters";
                }
            } elseif (strpos($rule, 'max:') === 0) {
                $max = (int)substr($rule, 4);
                if (isset($data[$field]) && !validate_length($data[$field], 0, $max)) {
                    $errors[$field] = "$field must not exceed $max characters";
                }
            } elseif ($rule === 'email') {
                if (isset($data[$field]) && !validate_email($data[$field])) {
                    $errors[$field] = "$field must be a valid email";
                }
            } elseif ($rule === 'int') {
                if (isset($data[$field]) && !validate_int($data[$field])) {
                    $errors[$field] = "$field must be an integer";
                }
            } elseif ($rule === 'float') {
                if (isset($data[$field]) && !validate_float($data[$field])) {
                    $errors[$field] = "$field must be a number";
                }
            } elseif ($rule === 'positive') {
                if (isset($data[$field]) && !validate_positive($data[$field])) {
                    $errors[$field] = "$field must be positive";
                }
            }
        }
    }
    
    return $errors;
}
?>
