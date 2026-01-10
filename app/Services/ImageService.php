<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Maximum width for resized images
     */
    protected int $maxWidth = 1200;
    
    /**
     * Maximum height for resized images
     */
    protected int $maxHeight = 1200;
    
    /**
     * JPEG quality (1-100)
     */
    protected int $quality = 80;
    
    /**
     * Process and store an uploaded image with compression
     * 
     * @param UploadedFile $file The uploaded file
     * @param string $directory Storage subdirectory (e.g., 'remarks/123')
     * @return string The stored file path relative to storage/app/public
     */
    public function processAndStore(UploadedFile $file, string $directory): string
    {
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.jpg';
        $storagePath = "public/{$directory}";
        $fullPath = storage_path("app/{$storagePath}/{$filename}");
        
        // Ensure directory exists
        Storage::makeDirectory($storagePath);
        
        // Try to use Intervention Image for compression
        if ($this->canUseIntervention()) {
            return $this->processWithIntervention($file, $storagePath, $filename);
        }
        
        // Fallback to GD library
        if ($this->canUseGD()) {
            return $this->processWithGD($file, $storagePath, $filename);
        }
        
        // Last resort: just store the original (convert extension to jpg for consistency)
        return $this->storeOriginal($file, $storagePath, $filename);
    }
    
    /**
     * Check if Intervention Image is available
     */
    protected function canUseIntervention(): bool
    {
        return class_exists('Intervention\Image\Laravel\Facades\Image');
    }
    
    /**
     * Check if GD library is available with required functions
     */
    protected function canUseGD(): bool
    {
        return extension_loaded('gd') 
            && function_exists('imagejpeg') 
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled');
    }
    
    /**
     * Process image using Intervention Image library
     */
    protected function processWithIntervention(UploadedFile $file, string $storagePath, string $filename): string
    {
        $image = Image::read($file->getRealPath());
        
        // Resize if larger than max dimensions (maintain aspect ratio)
        $image->scaleDown(width: $this->maxWidth, height: $this->maxHeight);
        
        // Encode as JPEG with quality
        $encoded = $image->toJpeg($this->quality);
        
        // Store the processed image
        Storage::put("{$storagePath}/{$filename}", $encoded);
        
        return str_replace('public/', '', "{$storagePath}/{$filename}");
    }
    
    /**
     * Process image using GD library
     */
    protected function processWithGD(UploadedFile $file, string $storagePath, string $filename): string
    {
        try {
            $sourcePath = $file->getRealPath();
            $destPath = storage_path("app/{$storagePath}/{$filename}");
            
            // Get image info
            $imageInfo = \getimagesize($sourcePath);
            if (!$imageInfo) {
                return $this->storeOriginal($file, $storagePath, $filename);
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];
            
            // Create source image based on type
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = \imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $source = \imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $source = \imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    if (\function_exists('imagecreatefromwebp')) {
                        $source = \imagecreatefromwebp($sourcePath);
                    } else {
                        return $this->storeOriginal($file, $storagePath, $filename);
                    }
                    break;
                default:
                    return $this->storeOriginal($file, $storagePath, $filename);
            }
            
            if (!$source) {
                return $this->storeOriginal($file, $storagePath, $filename);
            }
            
            // Calculate new dimensions
            $newWidth = $width;
            $newHeight = $height;
            
            if ($width > $this->maxWidth || $height > $this->maxHeight) {
                $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
                $newWidth = (int) ($width * $ratio);
                $newHeight = (int) ($height * $ratio);
            }
            
            // Create new image
            $destination = \imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG
            if ($type === IMAGETYPE_PNG) {
                \imagealphablending($destination, false);
                \imagesavealpha($destination, true);
            }
            
            // Resize
            \imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Save as JPEG with quality
            \imagejpeg($destination, $destPath, $this->quality);
            
            // Free memory
            \imagedestroy($source);
            \imagedestroy($destination);
            
            return str_replace('public/', '', "{$storagePath}/{$filename}");
        } catch (\Exception $e) {
            \Log::warning("GD processing failed: " . $e->getMessage());
            return $this->storeOriginal($file, $storagePath, $filename);
        }
    }
    
    /**
     * Store original file without processing (fallback)
     */
    protected function storeOriginal(UploadedFile $file, string $storagePath, string $filename): string
    {
        // Keep original extension
        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($storagePath, $filename);
        
        return str_replace('public/', '', $path);
    }
    
    /**
     * Delete an image from storage
     */
    public function delete(string $path): bool
    {
        return Storage::delete("public/{$path}");
    }
    
    /**
     * Delete multiple images
     */
    public function deleteMultiple(array $paths): void
    {
        foreach ($paths as $path) {
            $this->delete($path);
        }
    }
}
