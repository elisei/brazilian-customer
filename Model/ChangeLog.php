<?php
/**
 * O2TI Brazilian Customer.
 *
 * Copyright © 2024 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\BrazilianCustomer\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File as DriverFile;

class ChangeLog
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $fileIo;

    /**
     * @var DriverFile
     */
    protected $driver;

    /**
     * Construct.
     *
     * @param Filesystem $filesystem
     * @param File $fileIo
     * @param DriverFile $driver
     */
    public function __construct(
        Filesystem $filesystem,
        File $fileIo,
        DriverFile $driver
    ) {
        $this->filesystem = $filesystem;
        $this->fileIo = $fileIo;
        $this->driver = $driver;
    }

    /**
     * Write To CSV.
     *
     * @param bool $isSuccess
     * @param array $data
     */
    public function writeToCsv(bool $isSuccess, array $data)
    {
        $header = "Customer Id,Email,Obs\n";
        $fileName = 'customer-errors.csv';

        if ($isSuccess) {
            $header = "Customer Id,Email,VAT ID,Phone\n";
            $fileName = 'customer-changes.csv';
        }

        $dirPath = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_EXPORT)->getAbsolutePath('O2TI');

        if (!$this->fileIo->checkAndCreateFolder($dirPath)) {
            return false;
        }

        $filePath = $dirPath . '/' . $fileName;

        $csvLine = implode(',', $data);

        try {
            if (!$this->driver->isExists($filePath)) {
                $this->driver->filePutContents($filePath, $header, FILE_APPEND | LOCK_EX);
            }

            $this->driver->filePutContents($filePath, $csvLine . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (FileSystemException $exe) {
            return false;
        }

        return true;
    }
}
