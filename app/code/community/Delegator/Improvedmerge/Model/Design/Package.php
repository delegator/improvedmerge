<?php

class Delegator_Improvedmerge_Model_Design_Package extends Mage_Core_Model_Design_Package
{
    protected $compressBench;
    protected $hashBench;
    protected $writeBench;

    public function __construct()
    {
        parent::__construct();

        $this->compressBench = Mage::getModel('delegator_improvedmerge/bench');
        $this->hashBench = Mage::getModel('delegator_improvedmerge/bench');
        $this->writeBench = Mage::getModel('delegator_improvedmerge/bench');
    }

    /**
     * @ignore
     */
    public function writeFiles(
        $concatData,
        $targetFile,
        $extensionsFilter = array()
    )
    {
        // Write regular, concatenated file
        $this->writeBench->start();
        file_put_contents($targetFile, $concatData, LOCK_EX);
        $this->writeBench->end();
        $this->debugLog('Wrote regular file in ' . $this->compressBench->getTime());

        // Write pre-compressed (gzip) file
        $this->compressBench->start();
        file_put_contents($targetFile . '.gz', gzencode($concatData, 9), LOCK_EX);
        $this->compressBench->end();
        $this->debugLog('Wrote compressed file in ' . $this->compressBench->getTime());

        return true;
    }

    /**
     * Logs a debug message to the default log facility. This method will only
     * attempt to log the message if the environment variable
     * DG_IMPROVEDMERGE_DEBUG is present.
     */
    public function debugLog($message)
    {
        if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
            Mage::log($message);
        }
    }

    /**
     * Returns the extension for a given path.
     *
     * @param string $path
     * @return string Returns the portion of the filename in $path, starting from the last period.
     */
    public function getFileExtension($path)
    {
        return strrchr($path, '.');
    }

    /**
     * @ignore
     */
    public function getConcatContents($files, $callback = null)
    {
        $data = '';

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $contents = file_get_contents($file) . "\n";
            if ($callback && is_callable($callback)) {
                $contents = call_user_func($callback, $file, $contents);
            }

            $data .= $contents;

            $extension = $this->getFileExtension($file);
            if ($extension === '.js') {
                // Always terminate the final statement of a JavaScript file.
                $data .= ";\n";
            }
        }

        return $data;
    }

    /**
     * @ignore
     */
    public function getMergedFilesUrl($files, $mergeDir, $extensions, $callback = null)
    {
        // Assemble media URL
        $isSecure = Mage::app()->getRequest()->isSecure();
        $baseMediaUrl = Mage::getBaseUrl('media', $isSecure);

        // Determine target filename based on contents
        $this->hashBench->start();
        $concatData = $this->getConcatContents($files, $callback);
        $hash = hash('sha1', $concatData);
        $this->hashBench->end();
        $this->debugLog("Concat and hash for {$hash}.{$extensions} completed in " . $this->hashBench->getTime());

        // Use the sha1 hash in the asset filename for cachebusting
        // This generates something like aaf4c61ddcc5e8a2dabede0f3b482cd9aea9434d.js
        $targetFilename = $hash . '.' . $extensions;

        // Initialize merge directory
        $targetDir = $this->_initMergerDir($mergeDir);
        if (!$targetDir) {
            return '';
        }

        // Full path
        $fullPath = $targetDir . DS . $targetFilename;
        if (is_readable($fullPath)) {
            return $baseMediaUrl . $mergeDir . '/' . $targetFilename;
        }

        // Write concat and pre-gzip files to disk
        $this->writeFiles($concatData, $fullPath, $extensions);
        return $baseMediaUrl . $mergeDir . '/' . $targetFilename;
    }

    /**
     * Merges the contents of the provided JavaScript files.
     *
     * @param array $files List of JavaScript files (paths) to be merged.
     *
     * @return string When successful, returns the URL of the merged file.
     *                Otherwise, returns an empty string.
     */
    public function getMergedJsUrl($files)
    {
        return $this->getMergedFilesUrl($files, 'js', 'js');
    }

    /**
     * Merges the contents of the provided CSS files.
     *
     * @param array $files List of CSS files (paths) to be merged.
     *
     * @return string When successful, returns the URL of the merged file.
     *                Otherwise, returns an empty string.
     */
    public function getMergedCssUrl($files)
    {
        return $this->getMergedFilesUrl(
            $files,
            'css',
            'css',
            array($this, 'beforeMergeCss')
        );
    }
}
