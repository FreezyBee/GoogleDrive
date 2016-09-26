<?php

namespace FreezyBee\GoogleDrive;

/**
 * Class GoogleDriveException
 * @package FreezyBee\GoogleDrive
 */
class GoogleDriveException extends \Exception
{
}

/**
 * Class NodeNotExistsException
 * @package FreezyBee\GoogleDrive
 */
class NodeNotExistsException extends GoogleDriveException
{
}

/**
 * Class NodeDuplicatedException
 * @package FreezyBee\GoogleDrive
 */
class NodeDuplicatedException extends GoogleDriveException
{
}

/**
 * Class FolderDuplicatedException
 * @package FreezyBee\GoogleDrive
 */
class FolderDuplicatedException extends NodeDuplicatedException
{
}

/**
 * Class FileDuplicatedException
 * @package FreezyBee\GoogleDrive
 */
class FileDuplicatedException extends NodeDuplicatedException
{
}

/**
 * Class FileNotExistsException
 * @package FreezyBee\GoogleDrive
 */
class FileNotExistsException extends NodeNotExistsException
{
}

/**
 * Class FolderNotExistsException
 * @package FreezyBee\GoogleDrive
 */
class FolderNotExistsException extends NodeNotExistsException
{
}
