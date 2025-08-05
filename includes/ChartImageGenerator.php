<?php
/**
 * Chart Image Generator
 * Converts Chart.js charts to static images for PDF embedding
 */

class ChartImageGenerator {
    private $nodeJSPath;
    private $tempDir;
    private $chartJSVersion = '3.9.1';
    
    public function __construct($nodeJSPath = 'node') {
        $this->nodeJSPath = $nodeJSPath;
        $this->tempDir = sys_get_temp_dir() . '/chart-images/';
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    /**
     * Generate chart image from Chart.js configuration
     */
    public function generateChartImage($chartConfig, $outputPath, $options = []) {
        $defaultOptions = [
            'width' => 800,
            'height' => 400,
            'backgroundColor' => '#ffffff',
            'format' => 'png',
            'quality' => 0.9
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        try {
            // Try different methods in order of preference
            $methods = ['puppeteer', 'chartjs-node', 'quickchart', 'placeholder'];
            
            foreach ($methods as $method) {
                if ($this->tryGenerateWithMethod($method, $chartConfig, $outputPath, $options)) {
                    return $outputPath;
                }
            }
            
            throw new Exception('All chart generation methods failed');
            
        } catch (Exception $e) {
            error_log('Chart image generation failed: ' . $e->getMessage());
            return $this->generatePlaceholderImage($outputPath, $options);
        }
    }
    
    /**
     * Try to generate chart with specific method
     */
    private function tryGenerateWithMethod($method, $chartConfig, $outputPath, $options) {
        switch ($method) {
            case 'puppeteer':
                return $this->generateWithPuppeteer($chartConfig, $outputPath, $options);
                
            case 'chartjs-node':
                return $this->generateWithChartJSNode($chartConfig, $outputPath, $options);
                
            case 'quickchart':
                return $this->generateWithQuickChart($chartConfig, $outputPath, $options);
                
            case 'placeholder':
                return $this->generatePlaceholderImage($outputPath, $options);
                
            default:
                return false;
        }
    }
    
    /**
     * Generate chart using Puppeteer (requires Node.js and puppeteer)
     */
    private function generateWithPuppeteer($chartConfig, $outputPath, $options) {
        if (!$this->checkNodeJS() || !$this->checkPuppeteer()) {
            return false;
        }
        
        try {
            // Create HTML template for chart
            $html = $this->createChartHTML($chartConfig, $options);
            $htmlFile = $this->tempDir . 'chart_' . uniqid() . '.html';
            file_put_contents($htmlFile, $html);
            
            // Create Puppeteer script
            $script = $this->createPuppeteerScript($htmlFile, $outputPath, $options);
            $scriptFile = $this->tempDir . 'puppeteer_' . uniqid() . '.js';
            file_put_contents($scriptFile, $script);
            
            // Execute Puppeteer
            $command = $this->nodeJSPath . ' ' . escapeshellarg($scriptFile) . ' 2>&1';
            $output = shell_exec($command);
            
            // Cleanup
            unlink($htmlFile);
            unlink($scriptFile);
            
            return file_exists($outputPath) && filesize($outputPath) > 0;
            
        } catch (Exception $e) {
            error_log('Puppeteer chart generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate chart using chartjs-node-canvas (requires Node.js and chartjs-node-canvas)
     */
    private function generateWithChartJSNode($chartConfig, $outputPath, $options) {
        if (!$this->checkNodeJS()) {
            return false;
        }
        
        try {
            // Create Node.js script for chart generation
            $script = $this->createChartJSNodeScript($chartConfig, $outputPath, $options);
            $scriptFile = $this->tempDir . 'chartjs_' . uniqid() . '.js';
            file_put_contents($scriptFile, $script);
            
            // Execute Node.js script
            $command = $this->nodeJSPath . ' ' . escapeshellarg($scriptFile) . ' 2>&1';
            $output = shell_exec($command);
            
            // Cleanup
            unlink($scriptFile);
            
            return file_exists($outputPath) && filesize($outputPath) > 0;
            
        } catch (Exception $e) {
            error_log('ChartJS Node generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate chart using QuickChart API (requires internet connection)
     */
    private function generateWithQuickChart($chartConfig, $outputPath, $options) {
        try {
            $url = 'https://quickchart.io/chart';
            
            $params = [
                'chart' => json_encode($chartConfig),
                'width' => $options['width'],
                'height' => $options['height'],
                'backgroundColor' => $options['backgroundColor'],
                'format' => $options['format']
            ];
            
            $postData = http_build_query($params);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData,
                    'timeout' => 30
                ]
            ]);
            
            $imageData = file_get_contents($url, false, $context);
            
            if ($imageData !== false) {
                return file_put_contents($outputPath, $imageData) !== false;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('QuickChart generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate placeholder image
     */
    private function generatePlaceholderImage($outputPath, $options) {
        try {
            $width = $options['width'];
            $height = $options['height'];
            $backgroundColor = $options['backgroundColor'];
            
            // Create image using GD
            if (extension_loaded('gd')) {
                return $this->createGDPlaceholder($outputPath, $width, $height, $backgroundColor);
            }
            
            // Create simple SVG placeholder
            return $this->createSVGPlaceholder($outputPath, $width, $height, $backgroundColor);
            
        } catch (Exception $e) {
            error_log('Placeholder generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create placeholder using GD extension
     */
    private function createGDPlaceholder($outputPath, $width, $height, $backgroundColor) {
        $image = imagecreate($width, $height);
        
        // Parse background color
        $bgColor = $this->parseColor($backgroundColor);
        $bg = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        $textColor = imagecolorallocate($image, 100, 100, 100);
        $borderColor = imagecolorallocate($image, 200, 200, 200);
        
        // Fill background
        imagefill($image, 0, 0, $bg);
        
        // Draw border
        imagerectangle($image, 0, 0, $width-1, $height-1, $borderColor);
        
        // Add text
        $text = "Chart Placeholder";
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        imagestring($image, $font, $x, $y, $text, $textColor);
        
        // Add subtitle
        $subtext = "Interactive charts not available in PDF";
        $subtextWidth = imagefontwidth(3) * strlen($subtext);
        $subx = ($width - $subtextWidth) / 2;
        $suby = $y + $textHeight + 10;
        
        imagestring($image, 3, $subx, $suby, $subtext, $textColor);
        
        // Save image
        $success = imagepng($image, $outputPath);
        imagedestroy($image);
        
        return $success;
    }
    
    /**
     * Create SVG placeholder
     */
    private function createSVGPlaceholder($outputPath, $width, $height, $backgroundColor) {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
        <svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="' . htmlspecialchars($backgroundColor) . '" stroke="#ddd" stroke-width="2"/>
            <text x="50%" y="45%" text-anchor="middle" font-family="Arial, sans-serif" font-size="18" fill="#666">
                Chart Placeholder
            </text>
            <text x="50%" y="55%" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#888">
                Interactive charts not available in PDF
            </text>
        </svg>';
        
        return file_put_contents($outputPath, $svg) !== false;
    }
    
    /**
     * Create HTML template for chart rendering
     */
    private function createChartHTML($chartConfig, $options) {
        $configJson = json_encode($chartConfig);
        
        return '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@' . $this->chartJSVersion . '"></script>
            <style>
                body {
                    margin: 0;
                    padding: 20px;
                    background-color: ' . htmlspecialchars($options['backgroundColor']) . ';
                }
                #chartContainer {
                    width: ' . $options['width'] . 'px;
                    height: ' . $options['height'] . 'px;
                }
            </style>
        </head>
        <body>
            <div id="chartContainer">
                <canvas id="chart" width="' . $options['width'] . '" height="' . $options['height'] . '"></canvas>
            </div>
            <script>
                const ctx = document.getElementById("chart").getContext("2d");
                const config = ' . $configJson . ';
                new Chart(ctx, config);
                
                // Signal that chart is ready
                window.chartReady = true;
            </script>
        </body>
        </html>';
    }
    
    /**
     * Create Puppeteer script
     */
    private function createPuppeteerScript($htmlFile, $outputPath, $options) {
        return 'const puppeteer = require("puppeteer");
        
        (async () => {
            try {
                const browser = await puppeteer.launch({ headless: true });
                const page = await browser.newPage();
                
                await page.setViewport({
                    width: ' . $options['width'] . ',
                    height: ' . $options['height'] . ',
                    deviceScaleFactor: 2
                });
                
                await page.goto("file://' . $htmlFile . '", { waitUntil: "networkidle0" });
                
                // Wait for chart to be ready
                await page.waitForFunction("window.chartReady === true", { timeout: 10000 });
                
                // Take screenshot
                await page.screenshot({
                    path: "' . $outputPath . '",
                    type: "' . $options['format'] . '",
                    quality: ' . ($options['format'] === 'jpeg' ? $options['quality'] * 100 : 'undefined') . ',
                    clip: {
                        x: 0,
                        y: 0,
                        width: ' . $options['width'] . ',
                        height: ' . $options['height'] . '
                    }
                });
                
                await browser.close();
                console.log("Chart generated successfully");
                
            } catch (error) {
                console.error("Error generating chart:", error);
                process.exit(1);
            }
        })();';
    }
    
    /**
     * Create ChartJS Node script
     */
    private function createChartJSNodeScript($chartConfig, $outputPath, $options) {
        $configJson = json_encode($chartConfig);
        
        return 'const { ChartJSNodeCanvas } = require("chartjs-node-canvas");
        const fs = require("fs");
        
        const width = ' . $options['width'] . ';
        const height = ' . $options['height'] . ';
        
        const chartJSNodeCanvas = new ChartJSNodeCanvas({
            width,
            height,
            backgroundColor: "' . $options['backgroundColor'] . '"
        });
        
        const configuration = ' . $configJson . ';
        
        (async () => {
            try {
                const buffer = await chartJSNodeCanvas.renderToBuffer(configuration);
                fs.writeFileSync("' . $outputPath . '", buffer);
                console.log("Chart generated successfully");
            } catch (error) {
                console.error("Error generating chart:", error);
                process.exit(1);
            }
        })();';
    }
    
    /**
     * Check if Node.js is available
     */
    private function checkNodeJS() {
        $output = shell_exec($this->nodeJSPath . ' --version 2>&1');
        return strpos($output, 'v') === 0;
    }
    
    /**
     * Check if Puppeteer is available
     */
    private function checkPuppeteer() {
        $output = shell_exec($this->nodeJSPath . ' -e "console.log(require(\'puppeteer\').version)" 2>&1');
        return !empty($output) && !strpos($output, 'Error');
    }
    
    /**
     * Parse color string to RGB array
     */
    private function parseColor($color) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert to RGB
        if (strlen($color) === 6) {
            return [
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2))
            ];
        } elseif (strlen($color) === 3) {
            return [
                'r' => hexdec(str_repeat(substr($color, 0, 1), 2)),
                'g' => hexdec(str_repeat(substr($color, 1, 1), 2)),
                'b' => hexdec(str_repeat(substr($color, 2, 1), 2))
            ];
        }
        
        // Default to white
        return ['r' => 255, 'g' => 255, 'b' => 255];
    }
    
    /**
     * Clean up temporary files
     */
    public function cleanup() {
        $files = glob($this->tempDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && time() - filemtime($file) > 3600) { // 1 hour old
                unlink($file);
            }
        }
    }
    
    /**
     * Get available chart generation methods
     */
    public function getAvailableMethods() {
        $methods = [];
        
        if ($this->checkNodeJS()) {
            $methods[] = 'nodejs';
            
            if ($this->checkPuppeteer()) {
                $methods[] = 'puppeteer';
            }
        }
        
        // Check internet connectivity for QuickChart
        $quickchartAvailable = @file_get_contents('https://quickchart.io/chart?chart={type:"line",data:{datasets:[]}}', false, stream_context_create([
            'http' => ['timeout' => 5]
        ])) !== false;
        
        if ($quickchartAvailable) {
            $methods[] = 'quickchart';
        }
        
        if (extension_loaded('gd')) {
            $methods[] = 'gd_placeholder';
        }
        
        $methods[] = 'svg_placeholder';
        
        return $methods;
    }
}