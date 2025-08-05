<?php
/**
 * Custom Exception Classes for Platform API Errors
 * 
 * These exceptions provide specific error handling for different
 * types of API failures, making it easier to handle and recover
 * from various error conditions.
 */

/**
 * Base exception for all platform API errors
 */
class PlatformApiException extends Exception {
    protected $httpCode;
    protected $platformResponse;
    
    public function __construct($message, $httpCode = 0, $platformResponse = null) {
        parent::__construct($message, $httpCode);
        $this->httpCode = $httpCode;
        $this->platformResponse = $platformResponse;
    }
    
    public function getHttpCode() {
        return $this->httpCode;
    }
    
    public function getPlatformResponse() {
        return $this->platformResponse;
    }
}

/**
 * Network/connection errors
 */
class PlatformNetworkException extends PlatformApiException {
    // Connection timeouts, DNS failures, etc.
}

/**
 * 400 Bad Request - Invalid parameters
 */
class PlatformBadRequestException extends PlatformApiException {
    // Invalid post content, missing required fields, etc.
}

/**
 * 401 Unauthorized - Authentication failed
 */
class PlatformAuthException extends PlatformApiException {
    // Invalid or expired tokens
}

/**
 * 403 Forbidden - Permission denied
 */
class PlatformForbiddenException extends PlatformApiException {
    // Insufficient permissions, disabled features, etc.
}

/**
 * 404 Not Found - Resource doesn't exist
 */
class PlatformNotFoundException extends PlatformApiException {
    // Account not found, post not found, etc.
}

/**
 * 429 Too Many Requests - Rate limit exceeded
 */
class PlatformRateLimitException extends PlatformApiException {
    protected $retryAfter;
    
    public function __construct($message, $httpCode = 429, $retryAfter = null) {
        parent::__construct($message, $httpCode);
        $this->retryAfter = $retryAfter;
    }
    
    public function getRetryAfter() {
        return $this->retryAfter;
    }
}

/**
 * 5xx Server Errors - Platform issues
 */
class PlatformServerException extends PlatformApiException {
    // Platform is down, internal errors, etc.
}

/**
 * Platform-specific validation errors
 */
class PlatformValidationException extends PlatformApiException {
    protected $validationErrors;
    
    public function __construct($message, $validationErrors = []) {
        parent::__construct($message);
        $this->validationErrors = $validationErrors;
    }
    
    public function getValidationErrors() {
        return $this->validationErrors;
    }
}

/**
 * Media upload errors
 */
class PlatformMediaException extends PlatformApiException {
    protected $mediaFile;
    
    public function __construct($message, $mediaFile = null) {
        parent::__construct($message);
        $this->mediaFile = $mediaFile;
    }
    
    public function getMediaFile() {
        return $this->mediaFile;
    }
}