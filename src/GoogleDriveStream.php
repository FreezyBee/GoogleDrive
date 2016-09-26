<?php

namespace FreezyBee\GoogleDrive;

/**
 * Class GoogleDriveStream
 * @package FreezyBee\GoogleDrive
 */
class GoogleDriveStream
{
    /** @var GoogleDrive */
    private $googleDrive = null;

    /** @var \Google_Service_Drive_DriveFile */
    private $folder = null;

    /** @var \Google_Service_Drive_DriveFile[] */
    private $folderContent = [];

    /** @var \Google_Service_Drive_DriveFile */
    private $file;

    /** @var string */
    private $dirPath;

    /** @var string */
    private $filenamePath;

    /** @var string */
    private $buffer;

    /** @var int */
    private $bufferSize = 0;

    /** @var int */
    private $position = 0;

    /** @var bool */
    private $lookForShared = false;

    /**
     * Attempt to open a directory
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir(string $path, int $options)
    {
        $dirPath = $this->preparePath($path);
        $this->folder = $this->getGoogleDrive($path)->findFolderByPath($dirPath);
        $this->dirPath = $dirPath;

        if ($dirPath == '') {
            $this->lookForShared = true;
        }

        return (bool)$this->folder;
    }

    /**
     * Return the next filename in the directory
     * @return string
     */
    public function dir_readdir()
    {
        $gd = $this->getGoogleDrive();
        if (!$this->folderContent) {
            if ($this->lookForShared) {
                $this->folderContent = $gd->getSharedContent();
            }

            $this->folderContent = array_merge(
                $this->folderContent,
                $gd->getContentOfFolder($this->folder)
            );

            // fill cache for metainfo
            $gd->addToHotCache($this->dirPath, $this->folderContent);

            return $this->folder->getName();
        }

        $object = current($this->folderContent);

        if ($object !== false) {
            next($this->folderContent);
            return $object->getName();
        }

        return false;
    }

    /**
     * Open the stream
     *
     * @param  string $path
     * @param  string $mode
     * @param  int $options
     * @param  string $opened_path
     * @return boolean
     */
    public function stream_open(string $path, string $mode, int $options, $opened_path)
    {
        $this->getGoogleDrive($path);
        $this->filenamePath = $this->preparePath($path);
        return true;
    }

    /**
     * Read from the stream
     * @param  int $count
     * @return string
     */
    public function stream_read(int $count)
    {
        $this->getFile();
        $data = substr($this->buffer, $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }

    /**
     * End of the stream?
     * @return boolean
     */
    public function stream_eof()
    {
        return ($this->bufferSize < $this->position);
    }

    /**
     * Returns data array of stream variables
     *
     * @return array
     */
    public function stream_stat()
    {
        $mode = 0111;
        $mode |= $this->getFile()->getMimeType() == GoogleDrive::FOLDER_MIME_TYPE ? 040000 : 0100000;
        return ['mode' => $mode];
    }

    /**
     * Return array of URL variables
     *
     * @param  string $path
     * @param  int $flags
     * @return array
     */
    public function url_stat(string $path, int $flags)
    {
        file_put_contents('/tmp/pokus', $path . "\n", FILE_APPEND);
        $node = $this->getGoogleDrive($path)->getNodeMetaByPath($this->preparePath($path));

        $mode = 0111;

        if ($node) {
            $mode |= $node->getMimeType() === GoogleDrive::FOLDER_MIME_TYPE ? 040000 : 0100000;
            return ['mode' => $mode];
        }

        file_put_contents('/tmp/pokus', 'chyba: ' . $path . "\n", FILE_APPEND);
        // TODO
        return ['mode' => 040111];
    }

    /**
     * @param $name
     * @return bool
     */
    public static function register($name)
    {
        return stream_wrapper_register($name, __CLASS__);
    }

    /**
     * @return \Google_Service_Drive_DriveFile
     */
    private function getFile()
    {
        if ($this->file == null) {
            $gd = $this->getGoogleDrive();
            $this->file = $gd->findFileByPath($this->filenamePath);
            $this->buffer = $gd->downloadMediaFile($this->file);
            $this->bufferSize = strlen($this->buffer);
        }

        return $this->file;
    }

    /**
     * @param string $path
     * @return GoogleDrive
     */
    private function getGoogleDrive(string $path = '')
    {
        if ($this->googleDrive === null) {
            $url = explode('://', $path);

            if (!$url) {
                throw new \InvalidArgumentException("Unable to parse URL $path");
            }

            $this->googleDrive = GoogleDrive::getWrapperClient($url[0]);
            if (!$this->googleDrive instanceof GoogleDrive) {
                throw new \RuntimeException('Unknown client for wrapper ' . $url[0]);
            }
        }

        return $this->googleDrive;
    }

    /**
     * @param string $path
     * @return mixed
     */
    private function preparePath(string $path)
    {
        $url = explode('://', $path);

        if (isset($url[1])) {
            return $url[1];
        } else {
            throw new \InvalidArgumentException("Unable to parse URL $path");
        }
    }
}
