<?php

namespace FreezyBee\GoogleDrive;

use Google_Service_Drive_DriveFile;
use GuzzleHttp\Psr7\Response;

/**
 * Class GoogleDrive
 * @package App\Model\FileManagers
 */
class GoogleDrive
{
    /** @var string google folder mimeType */
    const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    /** @var string */
    private $authConfigPath;

    /** @var \Google_Service_Drive */
    private $driveService;

    /** @var GoogleDrive[] */
    private static $wrapperClients;

    /** @var Google_Service_Drive_DriveFile */
    private $hotMetaCacheId = [];

    /** @var Google_Service_Drive_DriveFile */
    private $hotMetaCachePath = [];

    /**
     * GoogleDrive constructor.
     * @param string $authConfigPath
     */
    public function __construct(string $authConfigPath)
    {
        $this->authConfigPath = $authConfigPath;
    }

    /**
     * @param Google_Service_Drive_DriveFile $file
     * @return \GuzzleHttp\Psr7\Stream|\Psr\Http\Message\StreamInterface
     */
    public function downloadMediaFile(Google_Service_Drive_DriveFile $file)
    {
        /** @var Response $response */
        $response = $this->getService()->files->get($file->getId(), ['alt' => 'media']);
        return $response->getBody();
    }

    /**
     * @param string $path
     * @return Google_Service_Drive_DriveFile[]
     */
    public function getContentOfFolderByPath(string $path)
    {
        $folder = $this->findFolderByPath($path);
        return $this->getContentOfFolder($folder);
    }

    /**
     * @param Google_Service_Drive_DriveFile $folder
     * @return Google_Service_Drive_DriveFile[]
     * @throws GoogleDriveException
     */
    public function getContentOfFolder(Google_Service_Drive_DriveFile $folder)
    {
        if (!in_array($folder->getMimeType(), [self::FOLDER_MIME_TYPE, null], true)) {
            throw new GoogleDriveException('Invalid node type:' . $folder->getMimeType());
        }

        $q = "'" . $folder->getId() . "' in parents";
        return $this->getService()->files->listFiles(['q' => $q])->getFiles();
    }

    /**
     * @param string $path
     * @return Google_Service_Drive_DriveFile|null
     * @throws GoogleDriveException
     */
    public function findNodeByPath(string $path)
    {
        $names = explode('/', $path);

        $lastNode = $this->getRoot();

        $i = 0;
        foreach ($names as $name) {
            $lastNode = $this->xFindNode($name, $lastNode, false, $i++ == 0);
        }

        return $lastNode;
    }

    /**
     * @param string $path
     * @return Google_Service_Drive_DriveFile|null
     * @throws GoogleDriveException
     */
    public function findFileByPath(string $path)
    {
        $url = explode('/', $path);
        $name = array_pop($url);
        $path = implode('/', $url);

        $lastFolder = $this->findFolderByPath($path);

        $q = "name = '" . $name . "' and '" . $lastFolder->getId() . "' in parents";

        $files = $this->getService()->files->listFiles(['q' => $q])->getFiles();

        if (!$files) {
            throw new FileNotExistsException("Not found $name  in " . $lastFolder->getName());
        } elseif (count($files) > 1) {
            throw new FileDuplicatedException("Duplicated $name  in " . $lastFolder->getName());
        } else {
            return $files[0];
        }
    }

    /**
     * @param string $path
     * @return Google_Service_Drive_DriveFile|null
     * @throws GoogleDriveException
     */
    public function findFolderByPath(string $path)
    {
        $lastFolder = $this->getRoot();

        $names = explode('/', $path);
        $names = array_filter($names, function ($v) {
            return $v !== '';
        });

        $i = 0;
        foreach ($names as $name) {
            $lastFolder = $this->xFindNode($name, $lastFolder, true, $i++ == 0);
        }

        return $lastFolder;
    }

    /**
     * @return Google_Service_Drive_DriveFile
     */
    public function getRoot()
    {
        return $this->getService()->files->get('root', ['fields' => 'id']);
    }

    /**
     * @param bool $onlyFolders
     * @return \Google_Service_Drive_DriveFile[]
     */
    public function getSharedContent($onlyFolders = false)
    {
        $optParams = [
            'q' => 'sharedWithMe' . ($onlyFolders ? " and mimeType = '" . self::FOLDER_MIME_TYPE . "'" : ''),
            'fields' => 'files'
        ];

        /** @var Google_Service_Drive_DriveFile[] $folders */
        return $this->getService()->files->listFiles($optParams)->getFiles();
    }

    /**
     * @return \Google_Service_Drive
     */
    public function getService()
    {
        if (!$this->driveService) {
            $client = new \Google_Client();
            $client->setAuthConfig($this->authConfigPath);
            $client->setScopes([\Google_Service_Drive::DRIVE_READONLY,]);
            $this->driveService = new \Google_Service_Drive($client);
        }

        return $this->driveService;
    }

    /**
     * @param string $name
     */
    public function registerStreamWrapper($name = 'gdrive')
    {
        GoogleDriveStream::register($name);
        self::$wrapperClients[$name] = $this;
    }

    /**
     * @param $name
     * @return GoogleDrive
     */
    public static function getWrapperClient($name)
    {
        return self::$wrapperClients[$name];
    }

    /**
     * @param $path
     * @return Google_Service_Drive_DriveFile
     */
    public function getNodeMetaByPath($path)
    {
        if (isset($this->hotMetaCachePath[$path])) {
            return $this->hotMetaCachePath[$path];
        } else {
            return $this->hotMetaCachePath[$path] = $this->findNodeByPath($path);
        }
    }

    /* ========== INTERNAL ========== */

    /**
     * @param string $name
     * @param Google_Service_Drive_DriveFile $parent
     * @param bool $mustBeFolder
     * @param bool $inRoot
     * @return Google_Service_Drive_DriveFile
     * @throws GoogleDriveException
     */
    private function xFindNode(string $name, Google_Service_Drive_DriveFile $parent, $mustBeFolder = true, $inRoot = false)
    {
        $q = "name = '$name' and '" . $parent->getId() . "' in parents";
        $q .= $mustBeFolder ? " and mimeType = '" . self::FOLDER_MIME_TYPE . "'" : '';

        $files = $this->getService()->files->listFiles(['q' => $q, 'fields' => 'files'])->getFiles();

        if (!$files && $inRoot) {
            // shared in root
            $q = "sharedWithMe and name = '$name'";
            $q .= $mustBeFolder ? " and mimeType = '" . self::FOLDER_MIME_TYPE . "'" : '';
            /** @var Google_Service_Drive_DriveFile[] $files */
            $files = $this->getService()->files->listFiles(['q' => $q, 'fields' => 'files'])->getFiles();
        }

        if (!$files) {
            if ($mustBeFolder) {
                throw new FolderNotExistsException("Not found $name in " . $parent->getName());
            } else {
                throw new NodeNotExistsException("Not found $name in " . $parent->getName());
            }
        } elseif (count($files) > 1) {
            if ($mustBeFolder) {
                throw new FolderDuplicatedException("Duplicated $name in " . $parent->getName());
            } else {
                throw new NodeDuplicatedException("Duplicated $name in " . $parent->getName());
            }
        }

        return $files[0];
    }

    /**
     * @param string $path
     * @param $data
     * @internal
     */
    public function addToHotCache(string $path, &$data)
    {
        $add = function (Google_Service_Drive_DriveFile $item) use ($path) {
            $this->hotMetaCacheId[$item->getId()] = $item;
            $this->hotMetaCachePath[$path . '/' . $item->getName()] = $item;
        };

        if (is_array($data)) {
            for ($i = 0; $i < count($data); $i++) {
                call_user_func($add, $data[$i]);
            }
        } else {
            call_user_func($add, $data);
        }
    }
}
