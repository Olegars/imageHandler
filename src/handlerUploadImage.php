<?php


namespace Olegars\imageHandler;

use Illuminate\Filesystem\Filesystem as File;
use Storage;
use Log;
use Image;
use Olegars\imageHandler\Exceptions\UploadImageException;

class handlerUploadImage
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

//        $this->file = new File();
    }

    /**
     * Upload image to disk.
     *
     * @param $file object instance image or image string
     * @param $storeId string id store
     * @param $contentName string content name (use for create and named folder)
     * @param $server string server name
     * @param bool $watermark bool watermark status (by default = false)
     * @param bool $video if true then add watermark with video player image to an image
     * @param bool $thumbnails create thumbnails for original image
     * @param  $storeId string shop's id
     *
     * @return object image
     * @throws UploadImageException
     */
    public function uploads($file, $storeId, $contentName, $server, $watermark = false, $video = false, $thumbnails = false, $size = false)
    {
        //$thumbnails = $this->thumbnail_status;

        // Create path for storage and full path to image.
        $imageStorage = $this->baseStore . $contentName . 's/';
        // Path to file system.
        $imagePath = public_path() . $imageStorage;
        // If file URL string.
        if (is_string($file) && !empty($file)) {
            $images['name'] = $this->saveLinkImage($file, $contentName);
        }

        // If file from form. Save file to disk.
        if (is_object($file)) {
            $images = $this->saveFileToDisk($file, $storeId, $contentName, $server, $thumbnails,$size);
        }

        // If file was uploaded then make resize and add watermark.
        if (!isset($images['name'])) {
            throw new UploadImageException('Can\'t upload image!');
        }

        $originalPath = $imagePath . $this->original . $images['name'];


        $url = $imageStorage . $this->original . $images['name'];
//
        $newImage = new UploadImageGet($images['name'], $url, $originalPath, $images['size']);

        return $newImage;
    }

    public function delete($imageName, $storeId, $contentName, $server, $size)
    {
//        Log::debug($imageName);
        if ($size && is_array($size))
        {
            $thumbnails = $size;
        }
        else{
            $thumbnails = $this->thumbnail_status;
        }

        // Make array for once image.
        if (is_string($imageName)) {
            $imageName = [$imageName];
        }

        // If need delete array of images.
        if (is_array($imageName)) {
            // Delete each image.
            foreach ($imageName as $image) {
                // Delete old original image from disk.
                Storage::disk($server)->delete('/'.$storeId.'/'.$contentName.'/'.$this->original.'/'.$image);
//                Log::debug($storeId.'/'.$contentName.'/'.$this->original.$image);
                // Delete all thumbnails if exist.
                if ($thumbnails) {
                    $this->deleteThumbnails($image, $storeId, $contentName, $server, $size);
                }
            }
        }
    }

    public function saveFileToDisk($file,$storeId, $contentName, $server, $thumbnails,$size)
    {
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
        // Save image to disk.
        Storage::disk($server)->putFileAs(
            '/'.$storeId.'/'.$contentName.'/'.$this->original, $file, $newName
        );
//        $file->store(
//            $this->original, 'local'
//        );
        $sizeTh=[];
        if ($thumbnails) {
            // If exist array with size
            if ($size && is_array($size))
            {
                $this->thumbnails = $size;
            }

            // Create thumbnails.
            $sizeTh=$this->createThumbnails($file,$storeId,$contentName, $server, $newName);
        }

        return ['name'=>$newName, 'size'=>$sizeTh];
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
    public function createThumbnails($file, $storeId,$contentName, $server, $newName)
    {
        // Get all thumbnails and save it.
        $size=[];
        rsort($this->thumbnails,SORT_NUMERIC);
        foreach ($this->thumbnails as $width) {
            // Path to folder where will be save image.
            $directory = $storeId.'/'.$contentName.'/w' . $width;
            $pathToFile = $file->getPathname();
            $height=Image::make($file)->resize($width, null, function ($constraint) {
                $constraint->aspectRatio();
            })->save($pathToFile)->height();
            Storage::disk($server)->putFileAs(
                '/'.$directory, $file, $newName
            );
            $size[$width]=$height;
        }
        return $size;
    }

    /**
     * Delete Thumbnails from disk.
     *
     * @param $imagePath string path to image on the disk
     * @param $imageName string image name
     */
    public function deleteThumbnails($image, $storeId, $contentName, $server, $size)
    {
        // Get all thumbnails and delete it.
        foreach ($size as $width) {
            // Delete old image from disk.
            Storage::disk($server)->delete('/'.$storeId.'/'.$contentName.'/w'.$width.'/'.$image);
        }
    }
}
