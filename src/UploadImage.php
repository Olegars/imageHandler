<?php

/**
 * Class for work with images.
 */

namespace Olegars\imageHandler;

use Illuminate\Filesystem\Filesystem as File;
use Spatie\Glide\GlideImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UploadImage
{
    /**
     * Use thumbnails or not.
     */
    protected $thumbnail_status;

    /**
     * Base store for images.
     */
    protected $baseStore;

    /**
     * Original folder for images.
     */
    protected $original;

    /**
     * Original image will be resizing to 800px.
     */
    protected $originalResize;

    /**
     * Image quality for save image in percent.
     */
    protected $quality;

    /**
     * Width thumbnails for images.
     */
    protected $thumbnails;

    /**
     * Watermark image.
     */
    protected $watermark_path;

    /**
     * Watermark image for video.
     */
    protected $watermark_video_path;

    /**
     * Watermark text.
     */
    protected $watermark_text;

    /**
     * Minimal width for image.
     */
    protected $min_width;

    /**
     * Width for preview image.
     */
    protected $previewWidth;

    /**
     * Folder name for upload images from WYSIWYG editor.
     */
    protected $editor_folder;

    /**
     * Object for work with files.
     */
    public $file;

    /**
     *  Get settings from config file.
     */
    public function __construct($config)
    {
        $this->thumbnail_status = $config['thumbnail_status'];
        $this->baseStore = $config['baseStore'];
        $this->original = $config['original'];
        $this->originalResize = $config['originalResize'];
        $this->quality = $config['quality'];
        $this->thumbnails = $config['thumbnails'];
        $this->watermark_path = $config['watermark_path'];
        $this->watermark_video_path = $config['watermark_video_path'];
        $this->watermark_text = $config['watermark_text'];
        $this->min_width = $config['min_width'];
        $this->previewWidth = $config['previewWidth'];
        $this->editor_folder = $config['editor_folder'];

        $this->file = new File();
    }

    /**
     * Upload image to disk.
     *
     * @param $file object instance image or image string
     * @param $contentName string content name (use for create and named folder)
     * @param bool $watermark bool watermark status (by default = false)
     * @param bool $video if true then add watermark with video player image to an image
     * @param bool $thumbnails create thumbnails for original image
     *
     * @return object image
     * @throws UploadImageException
     */
    public function upload($file, $contentName, $watermark = false, $video = false, $thumbnails = false, $size = false)
    {
        //$thumbnails = $this->thumbnail_status;

        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        // Path to file system.
        $imagePath = public_path() . $imageStorage;
        // If file URL string.
        if (is_string($file) && !empty($file)) {
            $newName = $this->saveLinkImage($file, $contentName);
        }

        // If file from form. Save file to disk.
        if (is_object($file)) {
            $newName = $this->saveFileToDisk($file, $contentName,$thumbnails,$size);
        }

        // If file was uploaded then make resize and add watermark.
        if (!isset($newName)) {
            throw new UploadImageException('Can\'t upload image!');
        }

        $originalPath = $imagePath . $this->original . $newName;


        $url = $imageStorage . $this->original . $newName;
//
        $newImage = new UploadImageGet($newName, $url, $originalPath);

        return $newImage;
    }

    public function delete($imageName, $contentName, $size)
    {
        if ($size && is_array($size))
        {
            $thumbnails = $size;
        }
        else{
            $thumbnails = $this->thumbnail_status;
        }

        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        $imagePath = public_path() . $imageStorage;

        // Make array for once image.
        if (is_string($imageName)) {
            $imageName = [$imageName];
        }

        // If need delete array of images.
        if (is_array($imageName)) {
            // Delete each image.
            foreach ($imageName as $image) {
                // Delete old original image from disk.
                $this->file->delete($imagePath . $this->original . $image);
                // Delete all thumbnails if exist.
                if ($thumbnails) {
                    $this->deleteThumbnails($imagePath, $image, $thumbnails);
                }
            }
        }
    }

    public function saveFileToDisk($file, $contentName, $thumbnails,$size)
    {
        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        $imagePath = public_path() . $imageStorage;

        // Check if image.
        if (!getimagesize($file)) {
            throw new UploadImageException('File should be image format!');
        }

        // Get real path to file.
        $pathToFile = $file->getPathname();
        // Get image size.
        $imageSize = getimagesize($pathToFile);

        // If width image < $this->min_width (default 500px).
        if ($imageSize[0] < $this->min_width) {
            throw new UploadImageException('Image should be more then ' . $this->min_width . 'px');
        }

        // Get real image extension.
        $ext = explode('/', $imageSize['mime'])[1];

        // Generate new file name.
        $newName = $this->generateNewName($contentName, $ext);
//        $this->createThumbnails($file,$newName);
        // Save image to disk.
        $file->store('original');
//        $file->move($imagePath . $this->original, $newName);
        if ($thumbnails) {
            // If exist array with size
            if ($size && is_array($size))
            {
                $this->thumbnails = $size;
            }

            // Create thumbnails.
            $this->createThumbnails($file,$newName);
        }

        return $newName;
    }

    /**
     * Generate new name for image.
     *
     * @param $contentName string Model name
     * @param $ext string extension of image file
     *
     * @return string
     */
    public function generateNewName($contentName, $ext)
    {
        $ind = time() . '_' . mb_strtolower(str_random(8));

        // New file name.
        $newName = $contentName . '_' . $ind . '.' . $ext;

        return $newName;
    }
    public function createThumbnails($file,$newName)
    {
        // Get all thumbnails and save it.
        foreach ($this->thumbnails as $width) {
            // Path to folder where will be save image.
            $directory = 'w' . $width;

            // Create new folder.
//            $this->file->makeDirectory($directory, $mode = 0755, true, true);
            Storage::makeDirectory($directory);
            // Resize saved image and save to thumbnail folder
            // (help about attributes http://glide.thephpleague.com/1.0/api/quick-reference/).
            GlideImage::create($file)
                ->modify(['w' => $width]);
            $file->store($directory);
        }
    }

    /**
     * Delete Thumbnails from disk.
     *
     * @param $imagePath string path to image on the disk
     * @param $imageName string image name
     */
    public function deleteThumbnails($imagePath, $imageName, $thumbnails)
    {
        // Get all thumbnails and delete it.
        foreach ($thumbnails as $width) {
            // Delete old image from disk.
            $this->file->delete($imagePath . 'w' . $width . '/' . $imageName);
        }
    }
}
