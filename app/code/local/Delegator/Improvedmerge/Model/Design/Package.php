<?php

class Delegator_Improvedmerge_Model_Design_Package extends Mage_Core_Model_Design_Package
{
    /**
     * @ignore
     */
    public function filetimeReduce($acc, $item)
    {
        $currentItem = filemtime($item);
        if ($currentItem > $acc) {
            return $currentItem;
        }

        return $acc;
    }

    /**
     * @ignore
     */
    public function _mergeFiles(array $srcFiles, $targetFile = false,
                                $mustMerge = false, $beforeMergeCallback = null, $extensionsFilter = [])
    {
        try {
            // check whether merger is required
            $shouldMerge = $mustMerge || !$targetFile;
            if (!$shouldMerge) {
                if (!file_exists($targetFile)) {
                    $shouldMerge = true;
                } else {
                    $targetMtime = filemtime($targetFile);
                    foreach ($srcFiles as $file) {
                        if (!file_exists($file) || @filemtime($file) > $targetMtime) {
                            $shouldMerge = true;
                            break;
                        }
                    }
                }
            }
            // merge contents into the file
            if ($shouldMerge) {
                if ($targetFile && !is_writeable(dirname($targetFile))) {
                    // no translation intentionally
                    throw new Exception(sprintf('Path %s is not writeable.', dirname($targetFile)));
                }
                // filter by extensions
                if ($extensionsFilter) {
                    if (!is_array($extensionsFilter)) {
                        $extensionsFilter = array($extensionsFilter);
                    }
                    if (!empty($srcFiles)) {
                        foreach ($srcFiles as $key => $file) {
                            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $extensionsFilter)) {
                                unset($srcFiles[$key]);
                            }
                        }
                    }
                }
                if (empty($srcFiles)) {
                    // no translation intentionally
                    throw new Exception('No files to compile.');
                }
                $data = '';
                foreach ($srcFiles as $file) {
                    if (!file_exists($file)) {
                        continue;
                    }
                    $contents = file_get_contents($file) . "\n";
                    if ($beforeMergeCallback && is_callable($beforeMergeCallback)) {
                        $contents = call_user_func($beforeMergeCallback, $file, $contents);
                    }
                    $data .= $contents;
                }
                if (!$data) {
                    // no translation intentionally
                    throw new Exception(sprintf("No content found in files:\n%s", implode("\n", $srcFiles)));
                }

                if ($extensionsFilter === ['js']) {
                    $bench = new Ubench;
                    $bench->start();
                    $data = \JShrink\Minifier::minify($data, ['flaggedComments' => false]);
                    $bench->end();
                    if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
                        Mage::log('Minified JS in ' . $bench->getTime());
                    }
                } elseif ($extensionsFilter === ['css']) {
                    $bench = new Ubench;
                    $bench->start();
                    $compressor = new CSSmin();
                    $data = $compressor->run($data);
                    $bench->end();
                    if (getenv('DG_IMPROVEDMERGE_DEBUG') !== false) {
                        Mage::log('Minified CSS in ' . $bench->getTime());
                    }
                }

                if ($targetFile) {
                    file_put_contents($targetFile, $data, LOCK_EX);
                    file_put_contents($targetFile . '.gz', gzencode($data, 9), LOCK_EX);
                } else {
                    return $data; // no need to write to file, just return data
                }
            }

            return true; // no need in merger or merged into file successfully
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return false;
    }

    /**
     * @ignore
     */
    public function getMergedFilesUrl($files, $mergeDir, $extensions, $callbacks = null)
    {
        // Assemble media URL
        $isSecure = Mage::app()->getRequest()->isSecure();
        $baseMediaUrl = Mage::getBaseUrl('media', $isSecure);

        // Determine timestamp of most recently modified file
        $latestTime = array_reduce($files, [$this, 'filetimeReduce'], 0);
        $filesList = implode(',', $files);
        $hash = hash('sha256', $filesList . $latestTime);
        $targetFilename = $hash . '.' . $extensions;

        // Initialize merge directory
        $targetDir = $this->_initMergerDir($mergeDir);
        if (!$targetDir) {
            return '';
        }

        // Try to merge files
        $mergeFilesResult = $this->_mergeFiles(
            $files,
            $targetDir . DS . $targetFilename,
            false,
            $callbacks,
            $extensions
        );

        if ($mergeFilesResult) {
            return $baseMediaUrl . $mergeDir . '/' . $targetFilename;
        }

        return '';
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
            Mage::app()->getRequest()->isSecure() ? 'css_secure' : 'css',
            'css',
            [$this, 'beforeMergeCss']
        );
    }
}
