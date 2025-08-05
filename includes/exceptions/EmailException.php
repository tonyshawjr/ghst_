<?php
/**
 * Email Exception Class
 * Custom exception for email-related errors
 */

class EmailException extends Exception {
    private $emailData;
    private $providerError;
    
    public function __construct($message = "", $code = 0, Exception $previous = null, $emailData = null, $providerError = null) {
        parent::__construct($message, $code, $previous);
        $this->emailData = $emailData;
        $this->providerError = $providerError;
    }
    
    public function getEmailData() {
        return $this->emailData;
    }
    
    public function getProviderError() {
        return $this->providerError;
    }
    
    public function getDetailedMessage() {
        $details = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine()
        ];
        
        if ($this->emailData) {
            $details['email_data'] = $this->emailData;
        }
        
        if ($this->providerError) {
            $details['provider_error'] = $this->providerError;
        }
        
        return $details;
    }
}
?>