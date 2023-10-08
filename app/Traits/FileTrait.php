<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


trait FileTrait
{
    public $disk = "public";
    public $prefix = "admin";


    /**
     * Store file in $disk directory
     *
     * @param UploadedFile $file
     * @return string
     */
    public function storeFile(UploadedFile $file, $prefix = 'admin'): string
    {
        return Storage::putFile($prefix, $file, $this->disk);
    }

    public function storeAndRemove(UploadedFile $file, string $filename, $prefix = 'admin'): string
    {
        $this->deleteFile($filename);
        return $this->storeFile($file, $prefix);
    }


    /**
     * Delete file from $disk and return 1 for success deletion
     *
     * @param string file
     * @return bool
     */
    public function deleteFile(string $file): bool
    {
        return  Storage::delete($file);
    }


    /**
     * Delete multiple files and return 1 for success deletion
     *
     * @param array $files
     * @return bool
     */
}
