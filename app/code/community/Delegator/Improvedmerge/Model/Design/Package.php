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
    ) {
        $data = $concatData;

        try {
            if ($extensionsFilter === 'js') {
                $jsbench = new Ubench;
                $jsbench->start();
                $data = \JShrink\Minifier::minify($concatData);
                $jsbench->end();
                if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
                    Mage::log('Minified JS in ' . $jsbench->getTime());
                }
            } elseif ($extensionsFilter === 'css') {
                $cssbench = new Ubench;
                $cssbench->start();
                $compressor = new CSSmin();
                $data = $compressor->run($concatData);
                $cssbench->end();
                if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
                    Mage::log('Minified CSS in ' . $cssbench->getTime());
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        file_put_contents($targetFile, $data, LOCK_EX);
        file_put_contents($targetFile . '.gz', gzencode($data, 9), LOCK_EX);

        return true;
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
        if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
            Mage::log("Concat and hash for {$hash}.{$extensions} completed in " . $hashbench->getTime());
        }

        // Comment here
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
