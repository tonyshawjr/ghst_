<?php
/**
 * Media Processing Library
 * Handles image resizing, optimization, thumbnail generation, and format conversion
 */

class MediaProcessor {
    private $gdSupported;
    private $imagickSupported;
    private $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $videoFormats = ['mp4', 'mov', 'avi', 'webm'];
    
    // Platform-specific requirements
    private $platformRequirements = [
        'instagram' => [
            'image' => [
                'max_width' => 1080,
                'max_height' => 1350,
                'aspect_ratios' => ['1:1', '4:5', '16:9'],
                'formats' => ['jpg', 'png'],
                'max_size' => 8 * 1024 * 1024 // 8MB
            ],
            'video' => [
                'max_duration' => 60,
                'max_size' => 100 * 1024 * 1024, // 100MB
                'formats' => ['mp4', 'mov'],
                'min_width' => 320,
                'max_width' => 1080
            ]
        ],
        'facebook' => [
            'image' => [
                'max_width' => 2048,
                'max_height' => 2048,
                'formats' => ['jpg', 'png', 'gif'],
                'max_size' => 4 * 1024 * 1024 // 4MB
            ],
            'video' => [
                'max_duration' => 240,
                'max_size' => 4 * 1024 * 1024 * 1024, // 4GB
                'formats' => ['mp4', 'mov']
            ]
        ],
        'twitter' => [
            'image' => [
                'max_width' => 4096,
                'max_height' => 4096,
                'formats' => ['jpg', 'png', 'gif', 'webp'],
                'max_size' => 5 * 1024 * 1024 // 5MB
            ],
            'video' => [
                'max_duration' => 140,
                'max_size' => 512 * 1024 * 1024, // 512MB
                'formats' => ['mp4']
            ]
        ],
        'linkedin' => [
            'image' => [
                'max_width' => 7680,
                'max_height' => 4320,
                'formats' => ['jpg', 'png', 'gif'],
                'max_size' => 10 * 1024 * 1024 // 10MB
            ],
            'video' => [
                'max_duration' => 600,
                'max_size' => 5 * 1024 * 1024 * 1024, // 5GB
                'formats' => ['mp4']
            ]
        ]
    ];
    
    public function __construct() {
        $this->gdSupported = extension_loaded('gd');
        $this->imagickSupported = extension_loaded('imagick');
        
        if (!$this->gdSupported && !$this->imagickSupported) {
            throw new Exception('No image processing library available (GD or ImageMagick required)');
        }
    }
    
    /**
     * Process uploaded media file
     */
    public function processUploadedMedia($filePath, $clientId) {
        $fileInfo = pathinfo($filePath);
        $extension = strtolower($fileInfo['extension']);
        
        if (in_array($extension, $this->supportedFormats)) {
            return $this->processImage($filePath, $clientId);
        } elseif (in_array($extension, $this->videoFormats)) {
            return $this->processVideo($filePath, $clientId);
        }
        
        return ['success' => false, 'error' => 'Unsupported file format'];
    }
    
    /**
     * Process image file
     */
    private function processImage($filePath, $clientId) {
        try {
            // Get image info
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                return ['success' => false, 'error' => 'Invalid image file'];
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Generate paths
            $uploadPath = dirname($filePath);
            $filename = basename($filePath);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            
            // Create subdirectories
            $thumbnailPath = $uploadPath . '/thumbnails';
            $optimizedPath = $uploadPath . '/optimized';
            
            if (!is_dir($thumbnailPath)) mkdir($thumbnailPath, 0755, true);
            if (!is_dir($optimizedPath)) mkdir($optimizedPath, 0755, true);
            
            // Generate thumbnail
            $thumbnailFile = $thumbnailPath . '/' . $nameWithoutExt . '_thumb.jpg';
            $this->createThumbnail($filePath, $thumbnailFile, 300, 300);
            
            // Optimize original image
            $optimizedFile = $optimizedPath . '/' . $filename;
            $this->optimizeImage($filePath, $optimizedFile, 1920, 1920, 85);
            
            // Generate platform-specific versions
            $platformVersions = [];
            foreach ($this->platformRequirements as $platform => $requirements) {
                if (isset($requirements['image'])) {
                    $platformPath = $uploadPath . '/platforms/' . $platform;
                    if (!is_dir($platformPath)) mkdir($platformPath, 0755, true);
                    
                    $platformFile = $platformPath . '/' . $filename;
                    $result = $this->createPlatformVersion(
                        $filePath,
                        $platformFile,
                        $platform,
                        $requirements['image']
                    );
                    
                    if ($result['success']) {
                        $platformVersions[$platform] = $result['file'];
                    }
                }
            }
            
            return [
                'success' => true,
                'original' => $filePath,
                'thumbnail' => $thumbnailFile,
                'optimized' => $optimizedFile,
                'platform_versions' => $platformVersions,
                'dimensions' => ['width' => $width, 'height' => $height],
                'size' => filesize($filePath)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create thumbnail
     */
    private function createThumbnail($source, $destination, $thumbWidth, $thumbHeight) {
        if ($this->imagickSupported) {
            return $this->createThumbnailImagick($source, $destination, $thumbWidth, $thumbHeight);
        } else {
            return $this->createThumbnailGD($source, $destination, $thumbWidth, $thumbHeight);
        }
    }
    
    /**
     * Create thumbnail using GD
     */
    private function createThumbnailGD($source, $destination, $thumbWidth, $thumbHeight) {
        $imageInfo = getimagesize($source);
        $srcWidth = $imageInfo[0];
        $srcHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Calculate aspect ratio
        $srcRatio = $srcWidth / $srcHeight;
        $thumbRatio = $thumbWidth / $thumbHeight;
        
        if ($srcRatio > $thumbRatio) {
            $newHeight = $thumbHeight;
            $newWidth = $thumbHeight * $srcRatio;
        } else {
            $newWidth = $thumbWidth;
            $newHeight = $thumbWidth / $srcRatio;
        }
        
        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }
        
        // Create thumbnail
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Handle transparency for PNG and GIF
        if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        // Calculate crop position
        $cropX = ($newWidth - $thumbWidth) / 2;
        $cropY = ($newHeight - $thumbHeight) / 2;
        
        // Resize and crop
        imagecopyresampled(
            $thumb, $srcImage,
            0, 0, $cropX, $cropY,
            $thumbWidth, $thumbHeight,
            $srcWidth, $srcHeight
        );
        
        // Save thumbnail as JPEG
        imagejpeg($thumb, $destination, 90);
        
        // Clean up
        imagedestroy($srcImage);
        imagedestroy($thumb);
        
        return true;
    }
    
    /**
     * Create thumbnail using ImageMagick
     */
    private function createThumbnailImagick($source, $destination, $thumbWidth, $thumbHeight) {
        try {
            $image = new Imagick($source);
            $image->setImageFormat('jpg');
            $image->cropThumbnailImage($thumbWidth, $thumbHeight);
            $image->setImageCompressionQuality(90);
            $image->writeImage($destination);
            $image->destroy();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Optimize image
     */
    private function optimizeImage($source, $destination, $maxWidth, $maxHeight, $quality = 85) {
        if ($this->imagickSupported) {
            return $this->optimizeImageImagick($source, $destination, $maxWidth, $maxHeight, $quality);
        } else {
            return $this->optimizeImageGD($source, $destination, $maxWidth, $maxHeight, $quality);
        }
    }
    
    /**
     * Optimize image using GD
     */
    private function optimizeImageGD($source, $destination, $maxWidth, $maxHeight, $quality) {
        $imageInfo = getimagesize($source);
        $srcWidth = $imageInfo[0];
        $srcHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Skip if already within limits
        if ($srcWidth <= $maxWidth && $srcHeight <= $maxHeight) {
            copy($source, $destination);
            return true;
        }
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
        $newWidth = round($srcWidth * $ratio);
        $newHeight = round($srcHeight * $ratio);
        
        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Handle transparency
        if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        // Resize
        imagecopyresampled(
            $newImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $srcWidth, $srcHeight
        );
        
        // Save optimized image
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($newImage, $destination, $quality);
                break;
            case 'image/png':
                imagepng($newImage, $destination, round(9 - ($quality / 10)));
                break;
            case 'image/gif':
                imagegif($newImage, $destination);
                break;
            case 'image/webp':
                imagewebp($newImage, $destination, $quality);
                break;
        }
        
        // Clean up
        imagedestroy($srcImage);
        imagedestroy($newImage);
        
        return true;
    }
    
    /**
     * Optimize image using ImageMagick
     */
    private function optimizeImageImagick($source, $destination, $maxWidth, $maxHeight, $quality) {
        try {
            $image = new Imagick($source);
            
            // Resize if needed
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            
            if ($width > $maxWidth || $height > $maxHeight) {
                $image->scaleImage($maxWidth, $maxHeight, true);
            }
            
            // Optimize
            $image->setImageCompressionQuality($quality);
            $image->stripImage(); // Remove metadata
            $image->writeImage($destination);
            $image->destroy();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create platform-specific version
     */
    private function createPlatformVersion($source, $destination, $platform, $requirements) {
        try {
            // Get source format
            $sourceExt = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $destExt = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
            
            // Check if format conversion is needed
            if (!in_array($destExt, $requirements['formats'])) {
                // Convert to first supported format
                $newFormat = $requirements['formats'][0];
                $destination = preg_replace('/\.[^.]+$/', '.' . $newFormat, $destination);
            }
            
            // Process image
            $maxWidth = $requirements['max_width'] ?? 2048;
            $maxHeight = $requirements['max_height'] ?? 2048;
            
            if ($this->imagickSupported) {
                $image = new Imagick($source);
                
                // Resize if needed
                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                
                if ($width > $maxWidth || $height > $maxHeight) {
                    $image->scaleImage($maxWidth, $maxHeight, true);
                }
                
                // Convert format if needed
                if ($sourceExt !== $destExt) {
                    $image->setImageFormat($destExt);
                }
                
                // Set quality
                $image->setImageCompressionQuality(85);
                $image->stripImage();
                
                // Check file size
                $blob = $image->getImageBlob();
                if (strlen($blob) > $requirements['max_size']) {
                    // Reduce quality until size is acceptable
                    $quality = 85;
                    while (strlen($blob) > $requirements['max_size'] && $quality > 50) {
                        $quality -= 5;
                        $image->setImageCompressionQuality($quality);
                        $blob = $image->getImageBlob();
                    }
                }
                
                $image->writeImage($destination);
                $image->destroy();
                
            } else {
                // Use GD for platform processing
                $this->optimizeImageGD($source, $destination, $maxWidth, $maxHeight, 85);
                
                // Check file size and recompress if needed
                if (filesize($destination) > $requirements['max_size']) {
                    $quality = 85;
                    while (filesize($destination) > $requirements['max_size'] && $quality > 50) {
                        $quality -= 5;
                        $this->optimizeImageGD($source, $destination, $maxWidth, $maxHeight, $quality);
                    }
                }
            }
            
            return ['success' => true, 'file' => $destination];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process video file
     */
    private function processVideo($filePath, $clientId) {
        // Check if FFmpeg is available
        $ffmpegPath = $this->findFFmpeg();
        if (!$ffmpegPath) {
            return [
                'success' => true,
                'message' => 'Video processing skipped - FFmpeg not available',
                'original' => $filePath
            ];
        }
        
        try {
            // Get video info
            $videoInfo = $this->getVideoInfo($filePath, $ffmpegPath);
            
            // Generate thumbnail from video
            $uploadPath = dirname($filePath);
            $filename = basename($filePath);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            
            $thumbnailPath = $uploadPath . '/thumbnails';
            if (!is_dir($thumbnailPath)) mkdir($thumbnailPath, 0755, true);
            
            $thumbnailFile = $thumbnailPath . '/' . $nameWithoutExt . '_thumb.jpg';
            $this->extractVideoThumbnail($filePath, $thumbnailFile, $ffmpegPath);
            
            // Platform-specific compression would go here
            // For now, we'll keep the original
            
            return [
                'success' => true,
                'original' => $filePath,
                'thumbnail' => $thumbnailFile,
                'info' => $videoInfo
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Find FFmpeg executable
     */
    private function findFFmpeg() {
        $paths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg'
        ];
        
        foreach ($paths as $path) {
            if (@is_executable($path)) {
                return $path;
            }
            
            // Try with 'which' command
            $result = @exec("which $path 2>/dev/null");
            if ($result && is_executable($result)) {
                return $result;
            }
        }
        
        return false;
    }
    
    /**
     * Get video information
     */
    private function getVideoInfo($filePath, $ffmpegPath) {
        $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($filePath) . ' 2>&1';
        $output = shell_exec($cmd);
        
        $info = [];
        
        // Extract duration
        if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})/', $output, $matches)) {
            $info['duration'] = ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
        }
        
        // Extract dimensions
        if (preg_match('/(\d{3,4})x(\d{3,4})/', $output, $matches)) {
            $info['width'] = $matches[1];
            $info['height'] = $matches[2];
        }
        
        // Extract bitrate
        if (preg_match('/bitrate: (\d+) kb\/s/', $output, $matches)) {
            $info['bitrate'] = $matches[1];
        }
        
        return $info;
    }
    
    /**
     * Extract thumbnail from video
     */
    private function extractVideoThumbnail($videoPath, $thumbnailPath, $ffmpegPath, $time = '00:00:01') {
        $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . 
               ' -ss ' . escapeshellarg($time) . 
               ' -vframes 1 -vf scale=300:300:force_original_aspect_ratio=increase,crop=300:300' .
               ' ' . escapeshellarg($thumbnailPath) . ' 2>&1';
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * Convert image format
     */
    /**
     * Get platform requirements
     */
    public function getPlatformRequirements() {
        return $this->platformRequirements;
    }
    
    /**
     * Create platform-specific version (public method)
     */
    public function createPlatformVersionPublic($source, $destination, $platform, $requirements) {
        return $this->createPlatformVersion($source, $destination, $platform, $requirements);
    }
    
    public function convertFormat($source, $destination, $format) {
        $format = strtolower($format);
        
        if (!in_array($format, $this->supportedFormats)) {
            return ['success' => false, 'error' => 'Unsupported format'];
        }
        
        try {
            if ($this->imagickSupported) {
                $image = new Imagick($source);
                $image->setImageFormat($format);
                $image->writeImage($destination);
                $image->destroy();
            } else {
                // Use GD
                $imageInfo = getimagesize($source);
                $mimeType = $imageInfo['mime'];
                
                // Load source image
                switch ($mimeType) {
                    case 'image/jpeg':
                        $srcImage = imagecreatefromjpeg($source);
                        break;
                    case 'image/png':
                        $srcImage = imagecreatefrompng($source);
                        break;
                    case 'image/gif':
                        $srcImage = imagecreatefromgif($source);
                        break;
                    case 'image/webp':
                        $srcImage = imagecreatefromwebp($source);
                        break;
                    default:
                        return ['success' => false, 'error' => 'Unsupported source format'];
                }
                
                // Save in new format
                switch ($format) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($srcImage, $destination, 90);
                        break;
                    case 'png':
                        imagepng($srcImage, $destination, 9);
                        break;
                    case 'gif':
                        imagegif($srcImage, $destination);
                        break;
                    case 'webp':
                        imagewebp($srcImage, $destination, 90);
                        break;
                }
                
                imagedestroy($srcImage);
            }
            
            return ['success' => true, 'file' => $destination];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}