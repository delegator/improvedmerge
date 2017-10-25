<?php

class Delegator_Improvedmerge_Model_Design_Package extends Mage_Core_Model_Design_Package
{
    /**
     * @ignore
     */
    public function minifyAndWriteContents(
        $concatData,
        $targetFile,
        $extensionsFilter = array()
    )
    {
        $data = $concatData;

        try {
            if ($extensionsFilter === 'js') {
                $jsbench = new Ubench;
                $jsbench->start();
                $data = \JShrink\Minifier::minify($concatData, array('flaggedComments' => false));
                $jsbench->end();
                $this->debugLog('Minified JS in ' . $jsbench->getTime());
            } elseif ($extensionsFilter === 'css') {
                $cssbench = new Ubench;
                $cssbench->start();
                $compressor = new tubalmartin\CssMin\Minifier();
                $compressor->removeImportantComments(true);
                $data = $compressor->run($concatData);
                $cssbench->end();
                $this->debugLog('Minified CSS in ' . $cssbench->getTime());
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        // Write regular file
        file_put_contents($targetFile, $data, LOCK_EX);

        // Write pre-compressed (gzip) file
        $compressBench = new Ubench;
        $compressBench->start();
        file_put_contents($targetFile . '.gz', gzencode($data, 9), LOCK_EX);
        $compressBench->end();
        $this->debugLog('Wrote compressed file in ' . $compressBench->getTime());

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
        $hashbench = new Ubench;
        $hashbench->start();
        $concatData = $this->getConcatContents($files, $callback);
        $hash = hash('sha1', $concatData);
        $hashbench->end();
        $this->debugLog("Concat and hash for {$hash}.{$extensions} completed in " . $hashbench->getTime());

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

        // Try to minify files, always write contents
        $this->minifyAndWriteContents($concatData, $fullPath, $extensions);
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
