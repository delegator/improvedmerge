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
     * @return string When successful, returns the URL of the merged file.
     * Otherwise, returns an empty string.
     */
    public function getMergedJsUrl($files)
    {
        return $this->getMergedFilesUrl($files, 'js', 'js');
    }

    /**
     * Merges the contents of the provided CSS files.
     *
     * @param array $files List of CSS files (paths) to be merged.
     * @return string When successful, returns the URL of the merged file.
     * Otherwise, returns an empty string.
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
