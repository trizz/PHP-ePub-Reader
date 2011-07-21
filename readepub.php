<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePub Reader class
 *
 * @package	CodeIgniter
 * @subpackage	Libraries
 * @author      Tristan Siebers
 * @link	n/a
 * @license     LGPL
 * @version     0.1.0
 */
class Readepub {

    /**
     * Contains the path to the dir with the ePub files
     * @var string Path to the extracted ePub files
     */
    var $ebookDir;
    /**
     * Holds the (relative to $ebookDir) path to the OPF file
     * @var string Location + name of OPF file
     */
    var $opfFile;
    /**
     * Relative (to $ebookDir) OPF (ePub files) dir
     * @var type Files dir
     */
    var $opfDir;
    /**
     * Holds all the found DC elements in the OPF file
     * @var array All found DC elements in the OPF file
     */
    var $dcElements;
    /**
     * Holds all the manifest items of the OPF file
     * @var array All manifest items
     */
    var $manifest;
    /**
     * Holds all the spin data
     * @var array Spine data
     */
    var $spine;
    /**
     * Holds the ToC data
     * @var array Array with ToC items
     */
    var $toc;

    public function init($ebookDir) {
        $this->ebookDir = $ebookDir;
        
        $this->_getOPF();

        $this->_getDcData();
        $this->_getManifest();
        $this->_getSpine();
        $this->_getTOC();
        
        //$this->debug();
    }
    
    /**
     * Get the specified DC item
     * @param string $item The DC Item key
     * @return string/boolean String when DC item exists, otherwise false
     */
    public function getDcItem($item) {
        if(key_exists($item, $this->dcElements)) {
            return $this->dcElements[$item];
        } else {
            return false;
        }
    }
    
    /**
     * Get the specified manifest item
     * @param string $item The manifest ID
     * @return string/boolean String when manifest item exists, otherwise false
     */
    public function getManifest($item) {
        if(key_exists($item, $this->manifest)) {
            return $this->manifest[$item];
        } else {
            return false;
        }
    }
    
    /**
     * Get the specified manifest by type
     * @param string $type The manifest type
     * @return string/boolean String when manifest item exists, otherwise false
     */
    public function getManifestByType($type) {
        foreach($this->manifest AS $manifestID => $manifest) {
            if($manifest['media-type'] == $type) {
                $return[$manifestID]['href'] = $manifest['href'];
                $return[$manifestID]['media-type'] = $manifest['media-type'];
            }
        }
        
        return (count($return) == 0) ? false : $return;
    }
    
    /**
     * Retrieve the ToC
     * @return array Array with ToC Data
     */
    public function getTOC() {
        return $this->toc;
    }
    
    /**
     * Returns the OPF/Data dir
     * @return string The OPF/data dir
     */
    public function getOPFDir() {
        return $this->opfDir;
    }

    /**
     * Prints all contents of the class directly to the screen
     */
    public function debug() {
        echo sprintf('<pre>%s</pre>', print_r($this, true));
    }

    // Private functions

    /**
     * Get the path to the OPF file from the META-INF/container.xml file
     * @return string Relative path to the OPF file
     */
    private function _getOPF() {
        $opfContents = simplexml_load_file($this->ebookDir . '/META-INF/container.xml');
        $opfAttributes = $opfContents->rootfiles->rootfile->attributes();
        $this->opfFile = (string) $opfAttributes[0]; // Typecasting to string to get rid of the XML object
        
        // Set also the dir to the OPF (and ePub files)
        $opfDirParts = explode('/',$this->opfFile);
        unset($opfDirParts[(count($opfDirParts)-1)]); // remove the last part (it's the .opf file itself)
        $this->opfDir = implode('/',$opfDirParts);
        
        return $this->opfFile;
    }

    /**
     * Read the metadata DC details (title, author, etc.) from the OPF file
     */
    private function _getDcData() {
        $opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);
        $this->dcElements = (array) $opfContents->metadata->children('dc', true);
    }

    /**
     * Gets the manifest data from the OPF file
     */
    private function _getManifest() {
        $opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);

        $iManifest = 0;
        foreach ($opfContents->manifest->item AS $item) {
            $attr = $item->attributes();
            $id = (string) $attr->id;
            $this->manifest[$id]['href'] = (string) $attr->href;
            $this->manifest[$id]['media-type'] = (string) $attr->{'media-type'};
            $iManifest++;
        }
    }

    /**
     * Get the spine data from the OPF file
     */
    private function _getSpine() {
        $opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);

        foreach ($opfContents->spine->itemref AS $item) {
            $attr = $item->attributes();
            $this->spine[] = (string) $attr->idref;
        }
    }
    
    
    /**
     * Build an array with the TOC
     */
    private function _getTOC() {
        $tocFile = $this->getManifest('ncx');
        $tocContents = simplexml_load_file($this->ebookDir.'/'.$this->opfDir.'/'.$tocFile['href']);
        
        $toc = array();
        foreach($tocContents->navMap->navPoint AS $navPoint) {
            $navPointData = $navPoint->attributes();
            $toc[(string)$navPointData['playOrder']]['id'] = (string)$navPointData['id'];
            $toc[(string)$navPointData['playOrder']]['naam'] = (string)$navPoint->navLabel->text;
            $toc[(string)$navPointData['playOrder']]['src'] = (string)$navPoint->content->attributes();
        }
       
        $this->toc = $toc;
    }
}