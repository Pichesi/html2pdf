<?php
/**
 * HTML2PDF Librairy - main class
 *
 * HTML => PDF convertor
 * distributed under the LGPL License
 *
 * @author  Laurent MINGUET <webmaster@html2pdf.fr>
 * @version 4.02
 */

if (!defined('__CLASS_HTML2PDF__')) {

define('__CLASS_HTML2PDF__', '4.02');

require_once(dirname(__FILE__).'/_mypdf/mypdf.class.php');
require_once(dirname(__FILE__).'/_mypdf/parsingHTML.class.php');
require_once(dirname(__FILE__).'/_mypdf/styleHTML.class.php');

    class HTML2PDF
    {
        /**
         * MyPDF object, extends from TCPDF
         * @var MyPDF
         */
        public $pdf = null;

        /**
         * CSS parsing
         * @var styleHTML
         */
        public $style = null;

        /**
         * HTML parsing
         * @var parsingHTML
         */
        public $parsing = null;

        protected $_langue           = 'fr';        // locale of the messages
        protected $_orientation      = 'P';         // page orientation : Portrait ou Landscape
        protected $_format           = 'A4';        // page format : A4, A3, ...
        protected $_encoding         = '';          // charset encoding
        protected $_unicode          = true;        // means that the input text is unicode (default = true)

        protected $_testTdInOnepage  = true;        // test of TD that can not take more than one page
        protected $_testIsImage      = true;        // test if the images exist or not
        protected $_testIsDeprecated = false;       // test the deprecated functions

        protected $_parsePos         = 0;           // position in the parsing
        protected $_tempPos          = 0;           // temporary position for complex table
        protected $_page             = 0;           // current page number

        protected $_subHtml          = null;        // sub html
        protected $_subPart          = false;       // sub HTML2PDF
        protected $_subHEADER        = array();     // sub action to make the header
        protected $_subFOOTER        = array();     // sub action to make the footer
        protected $_subSTATES        = array();     // array to save some parameters

        protected $_isSubPart        = false;       // flag : in a sub html2pdf
        protected $_isInThead        = false;       // flag : in a thead
        protected $_isInTfoot        = false;       // flag : in a tfoot
        protected $_isInOverflow     = false;       // flag : in a overflow
        protected $_isInFooter       = false;       // flag : in a footer
        protected $_isInDraw         = null;        // flag : in a draw (svg)
        protected $_isAfterFloat     = false;       // flag : is just after a float
        protected $_isInForm         = false;       // flag : is in a float. false / action of the form
        protected $_isInLink         = '';          // flag : is in a link. empty / href of the link
        protected $_isForOneLine     = false;       // flag : in a specific sub html2pdf to have the height of the next line

        protected $_maxX             = 0;           // maximum X of the current zone
        protected $_maxY             = 0;           // maximum Y of the current zone
        protected $_maxE             = 0;           // number of elements in the current zone
        protected $_maxH             = 0;           // maximum height of the line in the current zone
        protected $_maxSave          = array();     // save the maximums of the current zone
        protected $_currentH         = 0;           // height of the current line

        protected $_defaultLeft      = 0;           // default marges of the page
        protected $_defaultTop       = 0;
        protected $_defaultRight     = 0;
        protected $_defaultBottom    = 0;
        protected $_defaultFont      = null;        // default font to use, is the asked font does not exist

        protected $_margeLeft        = 0;           // current marges of the page
        protected $_margeTop         = 0;
        protected $_margeRight       = 0;
        protected $_margeBottom      = 0;
        protected $_marges           = array();     // save the different marges of the current page
        protected $_pageMarges       = array();     // float marges of the current page
        protected $_background       = array();     // background informations


        protected $_firstPage        = true;        // flag : first page
        protected $_defList          = array();     // table to save the stats of the tags UL and OL

        protected $_lstAnchor        = array();     // list of the anchors
        protected $_lstField         = array();     // list of the fields
        protected $_lstSelect        = array();     // list of the options of the current select
        protected $_previousCall     = null;        // last action called

        protected $_debugActif       = false;       // flag : mode debug is active
        protected $_debugOkUsage     = false;       // flag : the function memory_get_usage exist
        protected $_debugOkPeak      = false;       // flag : the function memory_get_peak_usage exist
        protected $_debugLevel       = 0;           // level in the debug
        protected $_debugStartTime   = 0;           // debug start time
        protected $_debugLastTime    = 0;           // debug stop time

        static protected $_subobj    = null;        // object html2pdf prepared in order to accelerate the creation of sub html2pdf
        static protected $_tables    = array();     // static table to prepare the nested html tables
        static protected $_textes    = array();     // static table with locales to use

        /**
         * class constructor
         *
         * @access public
         * @param  string   $orientation page orientation, same as TCPDF
         * @param  mixed    $format      The format used for pages, same as TCPDF
         * @param  $tring   $langue      Langue : fr, en, it...
         * @param  boolean  $unicode     TRUE means that the input text is unicode (default = true)
         * @param  String   $encoding    charset encoding; default is UTF-8
         * @param  array    $marges      Default marges (left, top, right, bottom)
         * @return HTML2PDF $this
         */
        public function __construct($orientation = 'P', $format = 'A4', $langue='fr', $unicode=true, $encoding='UTF-8', $marges = array(5, 5, 5, 8))
        {
            // init the page number
            $this->_page         = 0;
            $this->_firstPage    = true;

            // save the parameters
            $this->_orientation  = $orientation;
            $this->_format       = $format;
            $this->_langue       = strtolower($langue);
            $this->_unicode      = $unicode;
            $this->_encoding     = $encoding;


            // load the Local
            HTML2PDF::textLOAD($this->_langue);

            // create the  MyPDF object
            $this->pdf = new MyPDF($orientation, 'mm', $format, $unicode, $encoding);

            // init the CSS parsing object
            $this->style = new styleHTML($this->pdf);
            $this->style->FontSet();
            $this->_defList = array();

            // init some tests
            $this->setTestTdInOnePage(true);
            $this->setTestIsImage(true);
            $this->setTestIsDeprecated(true);

            // init the default font
            $this->setDefaultFont(null);

            // init the HTML parsing object
            $this->parsing = new parsingHTML($this->_encoding);
            $this->_subHtml = null;
            $this->_subPart = false;

            // init the marges of the page
            if (!is_array($marges)) $marges = array($marges, $marges, $marges, $marges);
            $this->_setDefaultMargins($marges[0], $marges[1], $marges[2], $marges[3]);
            $this->_setMargins();
            $this->_marges = array();

            // init the form's fields
            $this->_lstField = array();

            return $this;
        }

        /**
         * Destructor
         *
         * @access public
         * @return null
         */
        public function __destruct()
        {

        }

        /**
         * Clone to create a sub HTML2PDF from HTML2PDF::$_subobj
         *
         * @access public
         * @return    null
         */
        public function __clone()
        {
            $this->pdf      = clone $this->pdf;
            $this->parsing  = clone $this->parsing;
            $this->style    = clone $this->style;
            $this->style->setPdfParent($this->pdf);
        }

        /**
         * set the debug mode to On
         *
         * @access public
         * @return HTML2PDF $this
         */
        public function setModeDebug()
        {
            $time = microtime(true);

            $this->_debugActif     = true;
            $this->_debugOkUsage   = function_exists('memory_get_usage');
            $this->_debugOkPeak    = function_exists('memory_get_peak_usage');
            $this->_debugStartTime = $time;
            $this->_debugLastTime  = $time;

            $this->_DEBUG_stepline('step', 'time', 'delta', 'memory', 'peak');
            $this->_DEBUG_add('Init debug');

            return $this;
        }

        /**
         * Set the test of TD thdat can not take more than one page
         *
         * @access public
         * @param  boolean  $mode
         * @return HTML2PDF $this
         */
        public function setTestTdInOnePage($mode = true)
        {
            $this->_testTdInOnepage = $mode ? true : false;

            return $this;
        }

        /**
         * Set the test if the images exist or not
         *
         * @access public
         * @param  boolean  $mode
         * @return HTML2PDF $this
         */
        public function setTestIsImage($mode = true)
        {
            $this->_testIsImage = $mode ? true : false;

            return $this;
        }

        /**
         * Set the test on deprecated functions
         *
         * @access public
         * @param  boolean  $mode
         * @return HTML2PDF $this
         */
        public function setTestIsDeprecated($mode = true)
        {
            $this->_testIsDeprecated = $mode ? true : false;

            return $this;
        }

        /**
         * Set the default font to use, if no font is specify, or if the asked font does not exist
         *
         * @access public
         * @param  string   $default name of the default font to use. If null : Arial is no font is specify, and error if the asked font does not exist
         * @return HTML2PDF $this
         */
        public function setDefaultFont($default = null)
        {
            $this->_defaultFont = $default;
            $this->style->setDefaultFont($default);

            return $this;
        }

        /**
         * add a font, see TCPDF function addFont
         *
         * @access public
         * @param string $family Font family. The name can be chosen arbitrarily. If it is a standard family name, it will override the corresponding font.
         * @param string $style Font style. Possible values are (case insensitive):<ul><li>empty string: regular (default)</li><li>B: bold</li><li>I: italic</li><li>BI or IB: bold italic</li></ul>
         * @param string $fontfile The font definition file. By default, the name is built from the family and style, in lower case with no spaces.
         * @return HTML2PDF $this
         * @see TCPDF::addFont
         */
        public function addFont($family, $style='', $file='')
        {
            $this->pdf->AddFont($family, $style, $file);

            return $this;
        }

        /**
         * display a automatic index, from the bookmarks
         *
         * @access public
         * @param  string  $titre         index title
         * @param  int     $sizeTitle     font size of the index title, in mm
         * @param  int     $sizeBookmark  font size of the index, in mm
         * @param  boolean $bookmarkTitle add a bookmark for the index, at his beginning
         * @param  boolean $displayPage   display the page numbers
         * @param  int     $onPage        if null : at the end of the document on a new page, else on the $onPage page
         * @param  string  $fontName      font name to use
         * @return null
         */
        public function createIndex($titre = 'Index', $sizeTitle = 20, $sizeBookmark = 15, $bookmarkTitle = true, $displayPage = true, $onPage = null, $fontName = 'helvetica')
        {
            $oldPage = $this->INDEX_NewPage($onPage);
            $this->pdf->createIndex($this, $titre, $sizeTitle, $sizeBookmark, $bookmarkTitle, $displayPage, $onPage, $fontName);
            if ($oldPage) $this->pdf->setPage($oldPage);
        }

        /**
         * Send the document to a given destination: string, local file or browser.
         * Dest can be :
         *  I : send the file inline to the browser (default). The plug-in is used if available. The name given by name is used when one selects the "Save as" option on the link generating the PDF.
         *  D : send to the browser and force a file download with the name given by name.
         *  F : save to a local server file with the name given by name.
         *  S : return the document as a string. name is ignored.
         *  FI: equivalent to F + I option
         *  FD: equivalent to F + D option
         *  true  => I
         *  false => S
         *
         * @param  string $name The name of the file when saved.
         * @param  string $dest Destination where to send the document.
         * @return string content of the PDF, if $dest=S
         * @see TCPDF::close
         * @access public

         */
        public function Output($name = '', $dest = false)
        {
            // clean up
            HTML2PDF::$_subobj = null;
            HTML2PDF::$_tables = array();
            HTML2PDF::$_textes = array();

            // id on debug mode
            if ($this->_debugActif) {
                $this->_DEBUG_add('Before output');
                $this->pdf->Close();
                exit;
            }

            // complete parameters
            if ($dest===false) $dest = 'I';
            if ($dest===true)  $dest = 'S';
            if ($dest==='')    $dest = 'I';
            if ($name=='')     $name='document.pdf';

            // clean up the destination
            $dest = strtoupper($dest);
            if (!in_array($dest, array('I', 'D', 'F', 'S', 'FI','FD'))) $dest = 'I';

            // the name must be a PDF name
            if (strtolower(substr($name, -4))!='.pdf') {
                throw new HTML2PDF_exception(0, 'The output document name "'.$name.'" is not a PDF name');
            }

            // call the output of TCPDF
            return $this->pdf->Output($name, $dest);
        }

        /**
         * convert HTML to PDF
         *
         * @access public
         * @param  string   $html
         * @param  boolean  $debugVue  enable the HTML debug vue
         * @return null
         */
        public function writeHTML($html, $debugVue = false)
        {
            // if it is a real html page, we have to convert it
            if (preg_match('/<body/isU', $html))
                $html = $this->getHtmlFromPage($html);

            $html = str_replace('[[page_nb]]', '{nb}', $html);

            $html = str_replace('[[date_y]]', date('Y'), $html);
            $html = str_replace('[[date_m]]', date('m'), $html);
            $html = str_replace('[[date_d]]', date('d'), $html);

            $html = str_replace('[[date_h]]', date('H'), $html);
            $html = str_replace('[[date_i]]', date('i'), $html);
            $html = str_replace('[[date_s]]', date('s'), $html);

            // If we are in HTML debug vue : display the HTML
            if ($debugVue) {
                return $this->_vueHTML($html);
            }

            // convert HTMl to PDF
            $this->style->readStyle($html);
            $this->parsing->setHTML($html);
            $this->parsing->parse();
            $this->_makeHTMLcode();
        }

        /**
         * convert the HTML of a real page, to a code adapted to HTML2PDF
         *
         * @access public
         * @param  string HTML of a real page
         * @return string HTML adapted to HTML2PDF
         */
        public function getHtmlFromPage($html)
        {
            $html = str_replace('<BODY', '<body', $html);
            $html = str_replace('</BODY', '</body', $html);

            // extract the content
            $res = explode('<body', $html);
            if (count($res)<2) return $html;
            $content = '<page'.$res[1];
            $content = explode('</body', $content);
            $content = $content[0].'</page>';

            // extract the link tags
            preg_match_all('/<link([^>]*)>/isU', $html, $match);
            foreach ($match[0] as $src)
                $content = $src.'</link>'.$content;

            // extract the css style tags
            preg_match_all('/<style[^>]*>(.*)<\/style[^>]*>/isU', $html, $match);
            foreach ($match[0] as $src)
                $content = $src.$content;

            return $content;
        }

        /**
         * init a sub HTML2PDF. does not use it directly. Only the method createSubHTML must use it
         *
         * @access public
         * @param  string  $format
         * @param  string  $orientation
         * @param  array   $marge
         * @param  integer $page
         * @param  array   $defLIST
         */
        public function initSubHtml($format, $orientation, $marge, $page, $defLIST)
        {
            $this->_isSubPart = true;

            $this->style->setOnlyLeft();

            $this->_setNewPage($format, $orientation);

            $this->_saveMargin(0, 0, $marge);
            $this->_defList = $defLIST;

            $this->_page = $page;
            $this->pdf->setXY(0, 0);
            $this->style->FontSet();
        }

        /**
         * display the content in HTML moden for debug
         *
         * @access protected
         * @param  string $contenu
         */
        protected function _vueHTML($content)
        {
            $content = preg_replace('/<page_header([^>]*)>/isU', '<hr>'.HTML2PDF::textGET('vue01').' : $1<hr><div$1>', $content);
            $content = preg_replace('/<page_footer([^>]*)>/isU', '<hr>'.HTML2PDF::textGET('vue02').' : $1<hr><div$1>', $content);
            $content = preg_replace('/<page([^>]*)>/isU', '<hr>'.HTML2PDF::textGET('vue03').' : $1<hr><div$1>', $content);
            $content = preg_replace('/<\/page([^>]*)>/isU', '</div><hr>', $content);
            $content = preg_replace('/<bookmark([^>]*)>/isU', '<hr>bookmark : $1<hr>', $content);
            $content = preg_replace('/<\/bookmark([^>]*)>/isU', '', $content);
            $content = preg_replace('/<barcode([^>]*)>/isU', '<hr>barcode : $1<hr>', $content);
            $content = preg_replace('/<\/barcode([^>]*)>/isU', '', $content);
            $content = preg_replace('/<qrcode([^>]*)>/isU', '<hr>qrcode : $1<hr>', $content);
            $content = preg_replace('/<\/qrcode([^>]*)>/isU', '', $content);

            echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <title>'.HTML2PDF::textGET('vue04').' HTML</title>
        <meta http-equiv="Content-Type" content="text/html; charset='.$this->_encoding.'" >
    </head>
    <body style="padding: 10px; font-size: 10pt;font-family:    Verdana;">
'.$content.'
    </body>
</html>';
            exit;
        }

        /**
         * set the default margins of the page
         *
         * @access protected
         * @param  int $left   (mm, left margin)
         * @param  int $top    (mm, top margin)
         * @param  int $right  (mm, right margin, if null => left=right)
         * @param  int $bottom (mm, bottom margin, if null => bottom=8mm)
         */
        protected function _setDefaultMargins($left, $top, $right = null, $bottom = null)
        {
            if ($right===null)  $right = $left;
            if ($bottom===null) $bottom = 8;

            $this->_defaultLeft   = $this->style->ConvertToMM($left.'mm');
            $this->_defaultTop    = $this->style->ConvertToMM($top.'mm');
            $this->_defaultRight  = $this->style->ConvertToMM($right.'mm');
            $this->_defaultBottom = $this->style->ConvertToMM($bottom.'mm');
        }

        /**
         * create a new page
         *
         * @access protected
         * @param  mixed   $format
         * @param  string  $orientation
         * @param  array   $background background information
         * @param  integer $curr real position in the html parseur (if break line in the write of a text)
         */
        protected function _setNewPage($format = null, $orientation = '', $background = null, $curr = null)
        {
            $this->_firstPage = false;

            $this->_format = $format ? $format : $this->_format;
            $this->_orientation = $orientation ? $orientation : $this->_orientation;
            $this->_background = $background!==null ? $background : $this->_background;
            $this->_maxY = 0;
            $this->_maxX = 0;
            $this->_maxH = 0;

            $this->pdf->SetMargins($this->_defaultLeft, $this->_defaultTop, $this->_defaultRight);
            $this->pdf->AddPage($this->_orientation, $this->_format);
            $this->_page++;

            if (!$this->_subPart && !$this->_isSubPart) {
                if (is_array($this->_background)) {
                    if (isset($this->_background['color']) && $this->_background['color']) {
                        $this->pdf->setFillColorArray($this->_background['color']);
                        $this->pdf->Rect(0, 0, $this->pdf->getW(), $this->pdf->getH(), 'F');
                    }

                    if (isset($this->_background['img']) && $this->_background['img'])
                        $this->pdf->Image($this->_background['img'], $this->_background['posX'], $this->_background['posY'], $this->_background['width']);
                }

                $this->_setPageHeader();
                $this->_setPageFooter();
            }

            $this->_setMargins();
            $this->pdf->setY($this->_margeTop);

            $this->_setNewPositionForNewLine($curr);
            $this->_maxH = 0;
        }

        /**
         * set the real margin, using the default margins and the page margins
         *
         * @access protected
         */
        protected function _setMargins()
        {
            // prepare the margins
            $this->_margeLeft   = $this->_defaultLeft   + (isset($this->_background['left'])   ? $this->_background['left']   : 0);
            $this->_margeRight  = $this->_defaultRight  + (isset($this->_background['right'])  ? $this->_background['right']  : 0);
            $this->_margeTop    = $this->_defaultTop    + (isset($this->_background['top'])    ? $this->_background['top']    : 0);
            $this->_margeBottom = $this->_defaultBottom + (isset($this->_background['bottom']) ? $this->_background['bottom'] : 0);

            // set the PDF margins
            $this->pdf->SetMargins($this->_margeLeft, $this->_margeTop, $this->_margeRight);
            $this->pdf->setcMargin(0);
            $this->pdf->SetAutoPageBreak(false, $this->_margeBottom);

            // set the float Margins
            $this->_pageMarges = array();
            $this->_pageMarges[floor($this->_margeTop*100)] = array($this->_margeLeft, $this->pdf->getW()-$this->_margeRight);
        }

        /**
         * add a debug step
         *
         * @access protected
         * @param  string  $name step name
         * @param  boolean $level (true=up, false=down, null=nothing to do)
         * @return $this
         */
        protected function _DEBUG_add($name, $level=null)
        {
            // if true : UP
            if ($level===true) $this->_debugLevel++;

            $name   = str_repeat('  ', $this->_debugLevel). $name.($level===true ? ' Begin' : ($level===false ? ' End' : ''));
            $time  = microtime(true);
            $usage = ($this->_debugOkUsage ? memory_get_usage() : 0);
            $peak  = ($this->_debugOkPeak ? memory_get_peak_usage() : 0);

            $this->_DEBUG_stepline(
                $name,
                number_format(($time - $this->_debugStartTime)*1000, 1, '.', ' ').' ms',
                number_format(($time - $this->_debugLastTime)*1000, 1, '.', ' ').' ms',
                number_format($usage/1024, 1, '.', ' ').' Ko',
                number_format($peak/1024, 1, '.', ' ').' Ko'
            );

            $this->_debugLastTime = $time;

            // it false : DOWN
            if ($level===false) $this->_debugLevel--;

            return $this;
        }

        /**
         * display a debug line
         *
         *
         * @access protected
         * @param  string $name
         * @param  string $timeTotal
         * @param  string $timeStep
         * @param  string $memoryUsage
         * @param  string $memoryPeak
         */
        protected function _DEBUG_stepline($name, $timeTotal, $timeStep, $memoryUsage, $memoryPeak)
        {
            $txt = str_pad($name, 30, ' ', STR_PAD_RIGHT).
                    str_pad($timeTotal, 12, ' ', STR_PAD_LEFT).
                    str_pad($timeStep, 12, ' ', STR_PAD_LEFT).
                    str_pad($memoryUsage, 15, ' ', STR_PAD_LEFT).
                    str_pad($memoryPeak, 15, ' ', STR_PAD_LEFT);

            echo '<pre style="padding:0; margin:0">'.$txt.'</pre>';
        }

        /**
         * get the Min and Max X, for Y (use the float margins)
         *
         * @access protected
         * @param  float $y
         * @return array(float, float)
         */
        protected function _getMargins($y)
        {
            $y = floor($y*100);
            $x = array($this->pdf->getlMargin(), $this->pdf->getW()-$this->pdf->getrMargin());

            foreach ($this->_pageMarges as $mY => $mX)
                if ($mY<=$y) $x = $mX;

            return $x;
        }

        /**
         * Add margins, for a float
         *
         * @access protected
         * @param  string $float (left / right)
         * @param  float  $xLeft
         * @param  float  $yTop
         * @param  float  $xRight
         * @param  float  $yBottom
         */
        protected function _addMargins($float, $xLeft, $yTop, $xRight, $yBottom)
        {
            // get the current float margins, for top and bottom
            $oldTop    = $this->_getMargins($yTop);
            $oldBottom = $this->_getMargins($yBottom);

            // update the top float margin
            if ($float=='left'  && $oldTop[0]<$xRight) $oldTop[0] = $xRight;
            if ($float=='right' && $oldTop[1]>$xLeft)  $oldTop[1] = $xLeft;

            $yTop = floor($yTop*100);
            $yBottom = floor($yBottom*100);

            // erase all the float margins that are smaller than the new one
            foreach ($this->_pageMarges as $mY => $mX) {
                if ($mY<$yTop) continue;
                if ($mY>$yBottom) break;
                if ($float=='left' && $this->_pageMarges[$mY][0]<$xRight)  unset($this->_pageMarges[$mY]);
                if ($float=='right' && $this->_pageMarges[$mY][1]>$xLeft) unset($this->_pageMarges[$mY]);
            }

            // save the new Top and Bottom margins
            $this->_pageMarges[$yTop] = $oldTop;
            $this->_pageMarges[$yBottom] = $oldBottom;

            // sort the margins
            ksort($this->_pageMarges);

            // we are just after float
            $this->_isAfterFloat = true;
        }

        /**
         * Save old margins (push), and set new ones
         *
         * @access protected
         * @param  float  $ml left margin
         * @param  float  $mt top margin
         * @param  float  $mr right margin
         */
        protected function _saveMargin($ml, $mt, $mr)
        {
            // save old margins
            $this->_marges[] = array('l' => $this->pdf->getlMargin(), 't' => $this->pdf->gettMargin(), 'r' => $this->pdf->getrMargin(), 'page' => $this->_pageMarges);

            // set new ones
            $this->pdf->SetMargins($ml, $mt, $mr);

            // prepare for float margins
            $this->_pageMarges = array();
            $this->_pageMarges[floor($mt*100)] = array($ml, $this->pdf->getW()-$mr);
        }

        /**
         * load the last saved margins (pop)
         *
         * @access protected
         */
        protected function _loadMargin()
        {
            $old = array_pop($this->_marges);
            if ($old) {
                $ml = $old['l'];
                $mt = $old['t'];
                $mr = $old['r'];
                $mP = $old['page'];
            } else {
                $ml = $this->_margeLeft;
                $mt = 0;
                $mr = $this->_margeRight;
                $mP = array($mt => array($ml, $this->pdf->getW()-$mr));
            }

            $this->pdf->SetMargins($ml, $mt, $mr);
            $this->_pageMarges = $mP;
        }

        /**
         * save the current maxs (push)
         *
         * @access protected
         */
        protected function _saveMax()
        {
            $this->_maxSave[] = array($this->_maxX, $this->_maxY, $this->_maxH, $this->_maxE);
        }

        /**
         * load the last saved current maxs (pop)
         *
         * @access protected
         */
        protected function _loadMax()
        {
            $old = array_pop($this->_maxSave);

            if ($old) {
                $this->_maxX = $old[0];
                $this->_maxY = $old[1];
                $this->_maxH = $old[2];
                $this->_maxE = $old[3];
            } else {
                $this->_maxX = 0;
                $this->_maxY = 0;
                $this->_maxH = 0;
                $this->_maxE = 0;
            }
        }

        /**
         * draw the PDF header with the HTML in page_header
         *
         * @access protected
         */
        protected function _setPageHeader()
        {
            if (!count($this->_subHEADER)) return false;

            $oldParsePos = $this->_parsePos;
            $oldParseCode = $this->parsing->code;

            $this->_parsePos = 0;
            $this->parsing->code = $this->_subHEADER;
            $this->_makeHTMLcode();

            $this->_parsePos = $oldParsePos;
            $this->parsing->code = $oldParseCode;
        }

        /**
         * draw the PDF footer with the HTML in page_footer
         *
         * @access protected
         */
        protected function _setPageFooter()
        {
            if (!count($this->_subFOOTER)) return false;

            $oldParsePos = $this->_parsePos;
            $oldParseCode = $this->parsing->code;

            $this->_parsePos = 0;
            $this->parsing->code = $this->_subFOOTER;
            $this->_isInFooter = true;
            $this->_makeHTMLcode();
            $this->_isInFooter = false;

            $this->_parsePos = $oldParsePos;
            $this->parsing->code = $oldParseCode;
        }

        /**
         * new line, with a specific height
         *
         * @access protected
         * @param float   $h
         * @param integer $curr real current position in the text, if new line in the write of a text
         */
        protected function _setNewLine($h, $curr = null)
        {
            $this->pdf->Ln($h);
            $this->_setNewPositionForNewLine($curr);
        }

        /**
         * calculate the start position of the next line,  depending on the text-align
         *
         * @todo   use Word Spacing for text-align=justify
         * @access protected
         * @param  integer $curr real current position in the text, if new line in the write of a text
         */
        protected function _setNewPositionForNewLine($curr = null)
        {
            // get the margins for the current line
            list($lx, $rx) = $this->_getMargins($this->pdf->getY());
            $this->pdf->setX($lx);
            $wMax = $rx-$lx;
            $this->_currentH = 0;

            // if subPart => return because align left
            if ($this->_subPart || $this->_isSubPart || $this->_isForOneLine) {
//                $this->pdf->setWordSpacing(0);
                return null;
            }

            // create the sub object
            $sub = null;
            $this->_createSubHTML($sub);
            $sub->_saveMargin(0, 0, $sub->pdf->getW()-$wMax);
            $sub->_isForOneLine = true;
            $sub->_parsePos = $this->_parsePos;
            $sub->parsing->code = $this->parsing->code;

            // if $curr => adapt the current position of the parsing
            if ($curr!==null && $sub->parsing->code[$this->_parsePos]['name']=='write') {
                $txt = $sub->parsing->code[$this->_parsePos]['param']['txt'];
                $txt = str_replace('[[page_cu]]', $sub->_page, $txt);
                $sub->parsing->code[$this->_parsePos]['param']['txt'] = substr($txt, $curr);
            } else
                $sub->_parsePos++;

            // for each element of the parsing => load the action
            $res = null;
            for ($sub->_parsePos; $sub->_parsePos<count($sub->parsing->code); $sub->_parsePos++) {
                $action = $sub->parsing->code[$sub->_parsePos];
                $res = $sub->_executeAction($action);
                if (!$res) break;
            }

            $w = $sub->_maxX; // max width
            $h = $sub->_maxH; // max height
            $e = ($res===null ? $sub->_maxE : 0); // maxnumber of elemets on the line

            // destroy the sub HTML
            $this->_destroySubHTML($sub);

            // adapt the start of the line, depending on the text-align
            if ($this->style->value['text-align']=='center')
                $this->pdf->setX(($rx+$this->pdf->getX()-$w)*0.5-0.01);
            else if ($this->style->value['text-align']=='right')
                $this->pdf->setX($rx-$w-0.01);
            else
                $this->pdf->setX($lx);

            // set the height of the line
            $this->_currentH = $h;

            // if justify => set the word spacing
            if ($this->style->value['text-align']=='justify' && $e>1) {
//                $this->pdf->setWordSpacing(($wMax-$w)/($e-1));
            } else {
//                $this->pdf->setWordSpacing(0);
            }
        }

        /**
         * prepare HTML2PDF::$_subobj (used for create the sub HTML2PDF objects
         *
         * @access protected
         */
        protected function _prepareSubObj()
        {
            $pdf = null;

            // create the sub object
            HTML2PDF::$_subobj = new HTML2PDF(
                                        $this->_orientation,
                                        $this->_format,
                                        $this->_langue,
                                        $this->_unicode,
                                        $this->_encoding,
                                        array($this->_defaultLeft,$this->_defaultTop,$this->_defaultRight,$this->_defaultBottom)
                                    );

            // init
            HTML2PDF::$_subobj->setTestTdInOnePage($this->_testTdInOnepage);
            HTML2PDF::$_subobj->setTestIsImage($this->_testIsImage);
            HTML2PDF::$_subobj->setTestIsDeprecated($this->_testIsDeprecated);
            HTML2PDF::$_subobj->setDefaultFont($this->_defaultFont);
            HTML2PDF::$_subobj->style->css            = &$this->style->css;
            HTML2PDF::$_subobj->style->cssKeys        = &$this->style->cssKeys;

            // clone font from the original PDF
            HTML2PDF::$_subobj->pdf->cloneFontFrom($this->pdf);

            // remove the link to the parent
            HTML2PDF::$_subobj->style->setPdfParent($pdf);
        }

        /**
         * create a sub HTML2PDF, to calculate the multi-tables
         *
         * @access protected
         * @param  &HTML2PDF $subHtml sub HTML2PDF to create
         * @param  integer   $cellmargin if in a TD : cellmargin of this td
         */
        protected function _createSubHTML(&$subHtml, $cellmargin=0)
        {
            // prepare the subObject, if never prepare before
            if (HTML2PDF::$_subobj===null) {
                $this->_prepareSubObj();
            }

            // calculate the width to use
            if ($this->style->value['width']) {
                $marge = $cellmargin*2;
                $marge+= $this->style->value['padding']['l'] + $this->style->value['padding']['r'];
                $marge+= $this->style->value['border']['l']['width'] + $this->style->value['border']['r']['width'];
                $marge = $this->pdf->getW() - $this->style->value['width'] + $marge;
            } else {
                $marge = $this->_margeLeft+$this->_margeRight;
            }

            // BUGFIX : we have to call the method, because of a bug in php 5.1.6
            HTML2PDF::$_subobj->pdf->getPage();

            // clone the sub oject
            $subHtml = clone HTML2PDF::$_subobj;
            $subHtml->style->table = $this->style->table;
            $subHtml->style->value = $this->style->value;
            $subHtml->initSubHtml($this->_format, $this->_orientation, $marge, $this->_page, $this->_defList);
        }

        /**
         * destroy a subHTML2PDF
         *
         * @access protected
         */
        protected function _destroySubHTML(&$subHtml)
        {
            unset($subHtml);
            $subHtml = null;
        }

        /**
         * Convert a arabic number in roman number
         *
         * @access protected
         * @param  integer $nbArabic
         * @return string  $nbRoman
         */
        protected function _listeArab2Rom($nbArabic)
        {
            $nbBaseTen    = array('I','X','C','M');
            $nbBaseFive    = array('V','L','D');
            $nbRoman    = '';

            if ($nbArabic<1)    return $nbArabic;
            if ($nbArabic>3999) return $nbArabic;

            for ($i=3; $i>=0 ; $i--) {
                $chiffre=floor($nbArabic/pow(10, $i));
                if ($chiffre>=1) {
                    $nbArabic=$nbArabic-$chiffre*pow(10, $i);
                    if ($chiffre<=3) {
                        for ($j=$chiffre; $j>=1; $j--) {
                            $nbRoman=$nbRoman.$nbBaseTen[$i];
                        }
                    } else if ($chiffre==9) {
                        $nbRoman=$nbRoman.$nbBaseTen[$i].$nbBaseTen[$i+1];
                    } else if ($chiffre==4) {
                    $nbRoman=$nbRoman.$nbBaseTen[$i].$nbBaseFive[$i];
                    } else {
                        $nbRoman=$nbRoman.$nbBaseFive[$i];
                        for ($j=$chiffre-5; $j>=1; $j--) {
                            $nbRoman=$nbRoman.$nbBaseTen[$i];
                        }
                    }
                }
            }
            return $nbRoman;
        }

        /**
         * add a LI to the current level
         *
         * @access protected
         */
        protected function _listeAddLi()
        {
            $this->_defList[count($this->_defList)-1]['nb']++;
        }

        /**
         * get the width to use for the column of the list
         *
         * @access protected
         * @return string $width
         */
        protected function _listeGetWidth()
        {
            return '7mm';
        }

        /**
         * get the padding to use for the column of the list
         *
         * @access protected
         * @return string $padding
         */
        protected function _listeGetPadding()
        {
            return '1mm';
        }

        /**
         * get the information of the li on the current level
         *
         * @access protected
         * @return array(fontName, small size, string)
         */
        protected function _listeGetLi()
        {
            $im = $this->_defList[count($this->_defList)-1]['img'];
            $st = $this->_defList[count($this->_defList)-1]['style'];
            $nb = $this->_defList[count($this->_defList)-1]['nb'];
            $up = (substr($st, 0, 6)=='upper-');

            if ($im) return array(false, false, $im);

            switch($st)
            {
                case 'none':
                    return array('helvetica', true, ' ');

                case 'upper-alpha':
                case 'lower-alpha':
                    $str = '';
                    while ($nb>26) {
                        $str = chr(96+$nb%26).$str;
                        $nb = floor($nb/26);
                    }
                    $str = chr(96+$nb).$str;

                    return array('helvetica', false, ($up ? strtoupper($str) : $str).'.');

                case 'upper-roman':
                case 'lower-roman':
                    $str = $this->_listeArab2Rom($nb);

                    return array('helvetica', false, ($up ? strtoupper($str) : $str).'.');

                case 'decimal':
                    return array('helvetica', false, $nb.'.');

                case 'square':
                    return array('zapfdingbats', true, chr(110));

                case 'circle':
                    return array('zapfdingbats', true, chr(109));

                case 'disc':
                default:
                    return array('zapfdingbats', true, chr(108));
            }
        }

        /**
         * add a level to the list
         *
         * @access protected
         * @param  string $type  : ul, ol
         * @param  string $style : lower-alpha, ...
         * @param  string $img
         */
        protected function _listeAddLevel($type = 'ul', $style = '', $img = null)
        {
            // get the url of the image, if we want to use a image
            if ($img) {
                if (preg_match('/^url\(([^)]+)\)$/isU', trim($img), $match)) {
                    $img = $match[1];
                } else {
                    $img = null;
                }
            } else {
                $img = null;
            }

            // prepare the datas
            if (!in_array($type, array('ul', 'ol'))) $type = 'ul';
            if (!in_array($style, array('lower-alpha', 'upper-alpha', 'upper-roman', 'lower-roman', 'decimal', 'square', 'circle', 'disc', 'none'))) $style = '';

            if (!$style) {
                if ($type=='ul')    $style = 'disc';
                else                $style = 'decimal';
            }

            // add the new level
            $this->_defList[count($this->_defList)] = array('style' => $style, 'nb' => 0, 'img' => $img);
        }

        /**
         * remove a level to the list
         *
         * @access protected
         */
        protected function _listeDelLevel()
        {
            if (count($this->_defList)) {
                unset($this->_defList[count($this->_defList)-1]);
                $this->_defList = array_values($this->_defList);
            }
        }

        /**
         * execute the actions to convert the html
         *
         * @access protected
         */
        protected function _makeHTMLcode()
        {
            // foreach elements of the parsing
            for ($this->_parsePos=0; $this->_parsePos<count($this->parsing->code); $this->_parsePos++) {

                // get the action to do
                $action = $this->parsing->code[$this->_parsePos];

                // if it is a opening of table / ul / ol
                if (in_array($action['name'], array('table', 'ul', 'ol')) && !$action['close']) {

                    //  we will work as a sub HTML to calculate the size of the element
                    $this->_subPart = true;

                    // get the name of the opening tag
                    $tagOpen = $action['name'];

                    // save the actual pos on the parsing
                    $this->_tempPos = $this->_parsePos;

                    // foreach elements, while we are in the opened tag
                    while (isset($this->parsing->code[$this->_tempPos]) && !($this->parsing->code[$this->_tempPos]['name']==$tagOpen && $this->parsing->code[$this->_tempPos]['close'])) {
                        // make the action
                        $this->_executeAction($this->parsing->code[$this->_tempPos]);
                        $this->_tempPos++;
                    }

                    // execute the closure of the tag
                    if (isset($this->parsing->code[$this->_tempPos])) {
                        $this->_executeAction($this->parsing->code[$this->_tempPos]);
                    }

                    // end of the sub part
                    $this->_subPart = false;
                }

                // execute the action
                $this->_executeAction($action);
            }
        }

        /**
         * execute the action from the parsing
         *
         * @access protected
         * @param  array $action
         */
        protected function _executeAction($action)
        {
            // name of the action
            $fnc = ($action['close'] ? '_tag_close_' : '_tag_open_').strtoupper($action['name']);

            // parameters of the action
            $param = $action['param'];

            // if it the first action of the first page, and if it is not a open tag of PAGE => create the new page
            if ($fnc!='_tag_open_PAGE' && $this->_firstPage) {
                $this->_setNewPage();
            }

            // the action must exist
            if (!is_callable(array(&$this, $fnc))) {
                throw new HTML2PDF_exception(1, strtoupper($action['name']), $this->parsing->getHtmlErrorCode($action['html_pos']));
            }

            // lauch the action
            $res = $this->{$fnc}($param);

            // save the name of the action
            $this->_previousCall = $fnc;

            // return the result
            return $res;
        }

        /**
         * get the position of the element on the current line, depending on his height
         *
         * @access protected
         * @param  float $h
         * @return float
         */
        protected function _getElementY($h)
        {
            if ($this->_subPart || $this->_isSubPart || !$this->_currentH || $this->_currentH<$h)
                return 0;

            return ($this->_currentH-$h)*0.8;
        }

        /**
         * make a break line
         *
         * @access protected
         * @param  float $h current line height
         * @param  integer $curr real current position in the text, if new line in the write of a text
         */
        protected function _makeBreakLine($h, $curr = null)
        {
            if ($h) {
                if (($this->pdf->getY()+$h<$this->pdf->getH() - $this->pdf->getbMargin()) || $this->_isInOverflow || $this->_isInFooter)
                    $this->_setNewLine($h, $curr);
                else
                    $this->_setNewPage(null, '', null, $curr);
            } else {
                $this->_setNewPositionForNewLine($curr);
            }

            $this->_maxH = 0;
        }

        /**
         * display a image
         *
         * @access protected
         * @param  string $src
         * @param  boolean $subLi if true=image of a list
         * @return boolean depending on "isForOneLine"
         */
        protected function _drawImage($src, $subLi=false)
        {
            // get the size of the image
            // WARNING : if URL, "allow_url_fopen" must turned to "on" in php.ini
            $infos=@getimagesize($src);

            // if the image does not exist, or can not be loaded
            if (count($infos)<2) {
                // if the test is activ => exception
                if ($this->_testIsImage) {
                    throw new HTML2PDF_exception(6, $src);
                }

                // else, display a gray rectangle
                $src = null;
                $infos = array(16, 16);
            }

            // convert the size of the image in the unit of the PDF
            $imageWidth = $infos[0]/$this->pdf->getK();
            $imageHeight = $infos[1]/$this->pdf->getK();

            // calculate the size from the css style
            if ($this->style->value['width'] && $this->style->value['height']) {
                $w = $this->style->value['width'];
                $h = $this->style->value['height'];
            } else if ($this->style->value['width']) {
                $w = $this->style->value['width'];
                $h = $imageHeight*$w/$imageWidth;
            } else if ($this->style->value['height']) {
                $h = $this->style->value['height'];
                $w = $imageWidth*$h/$imageHeight;
            } else {
                // convert px to pt
                $w = 72./96.*$imageWidth;
                $h = 72./96.*$imageHeight;
            }

            // are we in a float
            $float = $this->style->getFloat();

            // if we are in a float, but if something else if on the line => Break Line
            if ($float && $this->_maxH) {
                // make the break line (false if we are in "_isForOneLine" mode)
                if (!$this->_tag_open_BR(array())) {
                    return false;
                }
            }

            // position of the image
            $x = $this->pdf->getX();
            $y = $this->pdf->getY();

            // if the image can not be put on the current line => new line
            if (!$float && ($x + $w>$this->pdf->getW() - $this->pdf->getrMargin()) && $this->_maxH) {
                if ($this->_isForOneLine) {
                    return false;
                }

                // set the new line
                $hnl = max($this->_maxH, $this->style->getLineHeight());
                $this->_setNewLine($hnl);

                // get the new position
                $x = $this->pdf->getX();
                $y = $this->pdf->getY();
            }

            // if the image can not be put on the current page
            if (($y + $h>$this->pdf->getH() - $this->pdf->getbMargin()) && !$this->_isInOverflow) {
                // new page
                $this->_setNewPage();

                // get the new position
                $x = $this->pdf->getX();
                $y = $this->pdf->getY();
            }

            // correction for display the image of a list
            $hT = 0.80*$this->style->value['font-size'];
            if ($subLi && $h<$hT) {
                $y+=($hT-$h);
            }

            // add the margin top
            $yc = $y-$this->style->value['margin']['t'];

            // get the width and the position of the parent
            $old = $this->style->getOldValues();
            if ( $old['width']) {
                $parentWidth = $old['width'];
                $parentX = $x;
            } else {
                $parentWidth = $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();
                $parentX = $this->pdf->getlMargin();
            }

            // if we are in a gloat => adapt the parent position and width
            if ($float) {
                list($lx, $rx) = $this->_getMargins($yc);
                $parentX = $lx;
                $parentWidth = $rx-$lx;
            }

            // calculate the position of the image, if align to the right
            if ($parentWidth>$w && $float!='left') {
                if ($float=='right' || $this->style->value['text-align']=='li_right')    $x = $parentX + $parentWidth - $w-$this->style->value['margin']['r']-$this->style->value['margin']['l'];
            }

            // display the image
            if (!$this->_subPart && !$this->_isSubPart) {
                if ($src) {
                    $this->pdf->Image($src, $x, $y, $w, $h, '', $this->_isInLink);
                } else {
                    // rectangle if the image can not be loaded
                    $this->pdf->setFillColorArray(array(240, 220, 220));
                    $this->pdf->Rect($x, $y, $w, $h, 'F');
                }
            }

            // apply the margins
            $x-= $this->style->value['margin']['l'];
            $y-= $this->style->value['margin']['t'];
            $w+= $this->style->value['margin']['l'] + $this->style->value['margin']['r'];
            $h+= $this->style->value['margin']['t'] + $this->style->value['margin']['b'];

            if ($float=='left') {
                // save the current max
                $this->_maxX = max($this->_maxX, $x+$w);
                $this->_maxY = max($this->_maxY, $y+$h);

                // add the image to the margins
                $this->_addMargins($float, $x, $y, $x+$w, $y+$h);

                // get the new position
                list($lx, $rx) = $this->_getMargins($yc);
                $this->pdf->setXY($lx, $yc);
            } else if ($float=='right') {
                // save the current max. We don't save the X because it is not the real max of the line
                $this->_maxY = max($this->_maxY, $y+$h);

                // add the image to the margins
                $this->_addMargins($float, $x, $y, $x+$w, $y+$h);

                // get the new position
                list($lx, $rx) = $this->_getMargins($yc);
                $this->pdf->setXY($lx, $yc);
            } else {
                // set the new position at the end of the image
                $this->pdf->setX($x+$w);

                // save the current max
                $this->_maxX = max($this->_maxX, $x+$w);
                $this->_maxY = max($this->_maxY, $y+$h);
                $this->_maxH = max($this->_maxH, $h);
            }

            return true;
        }

        /**
         * draw a rectangle
         *
         * @access protected
         * @param  float $x
         * @param  float $y
         * @param  float $w
         * @param  float $h
         * @param  array $border
         * @param  float $padding - internal marge of the rectanble => not used, but...
         * @param  float $margin  - external marge of the rectanble
         * @param  array $background
         * @return boolean
         */
        protected function _drawRectangle($x, $y, $w, $h, $border, $padding, $margin, $background)
        {
            // if we are in a subpart or if height is null => return false
            if ($this->_subPart || $this->_isSubPart || $h===null) return false;

            // add the margin
            $x+= $margin;
            $y+= $margin;
            $w-= $margin*2;
            $h-= $margin*2;

            // get the radius of the border
            $outTL = $border['radius']['tl'];
            $outTR = $border['radius']['tr'];
            $outBR = $border['radius']['br'];
            $outBL = $border['radius']['bl'];

            // prepare the out radius
            $outTL = ($outTL[0] && $outTL[1]) ? $outTL : null;
            $outTR = ($outTR[0] && $outTR[1]) ? $outTR : null;
            $outBR = ($outBR[0] && $outBR[1]) ? $outBR : null;
            $outBL = ($outBL[0] && $outBL[1]) ? $outBL : null;

            // prepare the in radius
            $inTL = $outTL;
            $inTR = $outTR;
            $inBR = $outBR;
            $inBL = $outBL;

            if (is_array($inTL)) {
                $inTL[0]-= $border['l']['width'];
                $inTL[1]-= $border['t']['width'];
            }
            if (is_array($inTR)) {
                $inTR[0]-= $border['r']['width'];
                $inTR[1]-= $border['t']['width'];
            }
            if (is_array($inBR)) {
                $inBR[0]-= $border['r']['width'];
                $inBR[1]-= $border['b']['width'];
            }
            if (is_array($inBL)) {
                $inBL[0]-= $border['l']['width'];
                $inBL[1]-= $border['b']['width'];
            }

            if ($inTL[0]<=0 || $inTL[1]<=0) $inTL = null;
            if ($inTR[0]<=0 || $inTR[1]<=0) $inTR = null;
            if ($inBR[0]<=0 || $inBR[1]<=0) $inBR = null;
            if ($inBL[0]<=0 || $inBL[1]<=0) $inBL = null;

            // prepare the background color
            $pdfStyle = '';
            if ($background['color']) {
                $this->pdf->setFillColorArray($background['color']);
                $pdfStyle.= 'F';
            }

            // if we have a background to fill => fill it with a path (because of the radius)
            if ($pdfStyle) {
                $this->pdf->clippingPathStart($x, $y, $w, $h, $outTL, $outTR, $outBL, $outBR);
                $this->pdf->Rect($x, $y, $w, $h, $pdfStyle);
                $this->pdf->clippingPathStop();
            }

            // prepare the background image
            if ($background['image']) {
                $iName      = $background['image'];
                $iPosition  = $background['position']!==null ? $background['position'] : array(0, 0);
                $iRepeat    = $background['repeat']!==null   ? $background['repeat']   : array(true, true);

                // size of the background without the borders
                $bX = $x;
                $bY = $y;
                $bW = $w;
                $bH = $h;

                if ($border['b']['width']) {
                    $bH-= $border['b']['width'];
                }
                if ($border['l']['width']) {
                    $bW-= $border['l']['width'];
                    $bX+= $border['l']['width'];
                }
                if ($border['t']['width']) {
                    $bH-= $border['t']['width'];
                    $bY+= $border['t']['width'];
                }
                if ($border['r']['width']) {
                    $bW-= $border['r']['width'];
                }

                // get the size of the image
                // WARNING : if URL, "allow_url_fopen" must turned to "on" in php.ini
                $imageInfos=@getimagesize($iName);

                // if the image can not be loaded
                if (count($imageInfos)<2) {
                    if ($this->_testIsImage) {
                        throw new HTML2PDF_exception(6, $iName);
                    }
                } else {
                    // convert the size of the image from pixel to the unit of the PDF
                    $imageWidth    = 72./96.*$imageInfos[0]/$this->pdf->getK();
                    $imageHeight    = 72./96.*$imageInfos[1]/$this->pdf->getK();

                    // prepare the position of the backgroung
                    if ($iRepeat[0]) $iPosition[0] = $bX;
                    else if (preg_match('/^([-]?[0-9\.]+)%/isU', $iPosition[0], $match)) $iPosition[0] = $bX + $match[1]*($bW-$imageWidth)/100;
                    else $iPosition[0] = $bX+$iPosition[0];

                    if ($iRepeat[1]) $iPosition[1] = $bY;
                    else if (preg_match('/^([-]?[0-9\.]+)%/isU', $iPosition[1], $match)) $iPosition[1] = $bY + $match[1]*($bH-$imageHeight)/100;
                    else $iPosition[1] = $bY+$iPosition[1];

                    $imageXmin = $bX;
                    $imageXmax = $bX+$bW;
                    $imageYmin = $bY;
                    $imageYmax = $bY+$bH;

                    if (!$iRepeat[0] && !$iRepeat[1]) {
                        $imageXmin =     $iPosition[0]; $imageXmax =     $iPosition[0]+$imageWidth;
                        $imageYmin =     $iPosition[1]; $imageYmax =     $iPosition[1]+$imageHeight;
                    } else if ($iRepeat[0] && !$iRepeat[1]) {
                        $imageYmin =     $iPosition[1]; $imageYmax =     $iPosition[1]+$imageHeight;
                    } else if (!$iRepeat[0] && $iRepeat[1]) {
                        $imageXmin =     $iPosition[0]; $imageXmax =     $iPosition[0]+$imageWidth;
                    }

                    // build the path to display the image (because of radius)
                    $this->pdf->clippingPathStart($bX, $bY, $bW, $bH, $inTL, $inTR, $inBL, $inBR);

                    // repeat the image
                    for ($iY=$imageYmin; $iY<$imageYmax; $iY+=$imageHeight) {
                        for ($iX=$imageXmin; $iX<$imageXmax; $iX+=$imageWidth) {
                            $cX = null;
                            $cY = null;
                            $cW = $imageWidth;
                            $cH = $imageHeight;
                            if ($imageYmax-$iY<$imageHeight) {
                                $cX = $iX;
                                $cY = $iY;
                                $cH = $imageYmax-$iY;
                            }
                            if ($imageXmax-$iX<$imageWidth) {
                                $cX = $iX;
                                $cY = $iY;
                                $cW = $imageXmax-$iX;
                            }

                            $this->pdf->Image($iName, $iX, $iY, $imageWidth, $imageHeight, '', '');
                        }
                    }

                    // end of the path
                    $this->pdf->clippingPathStop();
                }
            }

            // adding some loose (0.01mm)
            $loose = 0.01;
            $x-= $loose;
            $y-= $loose;
            $w+= 2.*$loose;
            $h+= 2.*$loose;
            if ($border['l']['width']) $border['l']['width']+= 2.*$loose;
            if ($border['t']['width']) $border['t']['width']+= 2.*$loose;
            if ($border['r']['width']) $border['r']['width']+= 2.*$loose;
            if ($border['b']['width']) $border['b']['width']+= 2.*$loose;

            // prepare the test on borders
            $testBl = ($border['l']['width'] && $border['l']['color'][0]!==null);
            $testBt = ($border['t']['width'] && $border['t']['color'][0]!==null);
            $testBr = ($border['r']['width'] && $border['r']['color'][0]!==null);
            $testBb = ($border['b']['width'] && $border['b']['color'][0]!==null);

            // draw the radius bottom-left
            if (is_array($outBL) && ($testBb || $testBl)) {
                if ($inBL) {
                    $courbe = array();
                    $courbe[] = $x+$outBL[0];              $courbe[] = $y+$h;
                    $courbe[] = $x;                        $courbe[] = $y+$h-$outBL[1];
                    $courbe[] = $x+$outBL[0];              $courbe[] = $y+$h-$border['b']['width'];
                    $courbe[] = $x+$border['l']['width'];  $courbe[] = $y+$h-$outBL[1];
                    $courbe[] = $x+$outBL[0];              $courbe[] = $y+$h-$outBL[1];
                } else {
                    $courbe = array();
                    $courbe[] = $x+$outBL[0];              $courbe[] = $y+$h;
                    $courbe[] = $x;                        $courbe[] = $y+$h-$outBL[1];
                    $courbe[] = $x+$border['l']['width'];  $courbe[] = $y+$h-$border['b']['width'];
                    $courbe[] = $x+$outBL[0];              $courbe[] = $y+$h-$outBL[1];
                }
                $this->_drawCurve($courbe, $border['l']['color']);
            }

            // draw the radius left-top
            if (is_array($outTL) && ($testBt || $testBl)) {
                if ($inTL) {
                    $courbe = array();
                    $courbe[] = $x;                        $courbe[] = $y+$outTL[1];
                    $courbe[] = $x+$outTL[0];              $courbe[] = $y;
                    $courbe[] = $x+$border['l']['width'];  $courbe[] = $y+$outTL[1];
                    $courbe[] = $x+$outTL[0];              $courbe[] = $y+$border['t']['width'];
                    $courbe[] = $x+$outTL[0];              $courbe[] = $y+$outTL[1];
                } else {
                    $courbe = array();
                    $courbe[] = $x;                        $courbe[] = $y+$outTL[1];
                    $courbe[] = $x+$outTL[0];              $courbe[] = $y;
                    $courbe[] = $x+$border['l']['width'];  $courbe[] = $y+$border['t']['width'];
                    $courbe[] = $x+$outTL[0];              $courbe[] = $y+$outTL[1];
                }
                $this->_drawCurve($courbe, $border['t']['color']);
            }

            // draw the radius top-right
            if (is_array($outTR) && ($testBt || $testBr)) {
                if ($inTR) {
                    $courbe = array();
                    $courbe[] = $x+$w-$outTR[0];             $courbe[] = $y;
                    $courbe[] = $x+$w;                       $courbe[] = $y+$outTR[1];
                    $courbe[] = $x+$w-$outTR[0];             $courbe[] = $y+$border['t']['width'];
                    $courbe[] = $x+$w-$border['r']['width']; $courbe[] = $y+$outTR[1];
                    $courbe[] = $x+$w-$outTR[0];             $courbe[] = $y+$outTR[1];
                } else {
                    $courbe = array();
                    $courbe[] = $x+$w-$outTR[0];             $courbe[] = $y;
                    $courbe[] = $x+$w;                       $courbe[] = $y+$outTR[1];
                    $courbe[] = $x+$w-$border['r']['width']; $courbe[] = $y+$border['t']['width'];
                    $courbe[] = $x+$w-$outTR[0];             $courbe[] = $y+$outTR[1];
                }
                $this->_drawCurve($courbe, $border['r']['color']);
            }

            // draw the radius right-bottom
            if (is_array($outBR) && ($testBb || $testBr)) {
                if ($inBR) {
                    $courbe = array();
                    $courbe[] = $x+$w;                       $courbe[] = $y+$h-$outBR[1];
                    $courbe[] = $x+$w-$outBR[0];             $courbe[] = $y+$h;
                    $courbe[] = $x+$w-$border['r']['width']; $courbe[] = $y+$h-$outBR[1];
                    $courbe[] = $x+$w-$outBR[0];             $courbe[] = $y+$h-$border['b']['width'];
                    $courbe[] = $x+$w-$outBR[0];             $courbe[] = $y+$h-$outBR[1];
                } else {
                    $courbe = array();
                    $courbe[] = $x+$w;                       $courbe[] = $y+$h-$outBR[1];
                    $courbe[] = $x+$w-$outBR[0];             $courbe[] = $y+$h;
                    $courbe[] = $x+$w-$border['r']['width']; $courbe[] = $y+$h-$border['b']['width'];
                    $courbe[] = $x+$w-$outBR[0];             $courbe[] = $y+$h-$outBR[1];
                }
                $this->_drawCurve($courbe, $border['b']['color']);
            }

            // draw the left border
            if ($testBl) {
                $pt = array();
                $pt[] = $x;                       $pt[] = $y+$h;
                $pt[] = $x;                       $pt[] = $y+$h-$border['b']['width'];
                $pt[] = $x;                       $pt[] = $y+$border['t']['width'];
                $pt[] = $x;                       $pt[] = $y;
                $pt[] = $x+$border['l']['width']; $pt[] = $y+$border['t']['width'];
                $pt[] = $x+$border['l']['width']; $pt[] = $y+$h-$border['b']['width'];

                $bord = 3;
                if (is_array($outBL)) {
                    $bord-=1;
                    $pt[3] -= $outBL[1] - $border['b']['width'];
                    if ($inBL) $pt[11]-= $inBL[1];
                    unset($pt[0]);unset($pt[1]);
                }
                if (is_array($outTL)) {
                    $bord-=2;
                    $pt[5] += $outTL[1]-$border['t']['width'];
                    if ($inTL) $pt[9] += $inTL[1];
                    unset($pt[6]);unset($pt[7]);
                }

                $pt = array_values($pt);
                $this->_drawLine($pt, $border['l']['color'], $border['l']['type'], $border['l']['width'], $bord);
            }

            // draw the top border
            if ($testBt) {
                $pt = array();
                $pt[] = $x;                          $pt[] = $y;
                $pt[] = $x+$border['l']['width'];    $pt[] = $y;
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y;
                $pt[] = $x+$w;                       $pt[] = $y;
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y+$border['t']['width'];
                $pt[] = $x+$border['l']['width'];    $pt[] = $y+$border['t']['width'];

                $bord = 3;
                if (is_array($outTL)) {
                    $bord-=1;
                    $pt[2] += $outTL[0] - $border['l']['width'];
                    if ($inTL) $pt[10]+= $inTL[0];
                    unset($pt[0]);unset($pt[1]);
                }
                if (is_array($outTR)) {
                    $bord-=2;
                    $pt[4] -= $outTR[0] - $border['r']['width'];
                    if ($inTR) $pt[8] -= $inTR[0];
                    unset($pt[6]);unset($pt[7]);
                }

                $pt = array_values($pt);
                $this->_drawLine($pt, $border['t']['color'], $border['t']['type'], $border['t']['width'], $bord);
            }

            // draw the right border
            if ($testBr) {
                $pt = array();
                $pt[] = $x+$w;                       $pt[] = $y;
                $pt[] = $x+$w;                       $pt[] = $y+$border['t']['width'];
                $pt[] = $x+$w;                       $pt[] = $y+$h-$border['b']['width'];
                $pt[] = $x+$w;                       $pt[] = $y+$h;
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y+$h-$border['b']['width'];
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y+$border['t']['width'];

                $bord = 3;
                if (is_array($outTR)) {
                    $bord-=1;
                    $pt[3] += $outTR[1] - $border['t']['width'];
                    if ($inTR) $pt[11]+= $inTR[1];
                    unset($pt[0]);unset($pt[1]);
                }
                if (is_array($outBR)) {
                    $bord-=2;
                    $pt[5] -= $outBR[1] - $border['b']['width'];
                    if ($inBR) $pt[9] -= $inBR[1];
                    unset($pt[6]);unset($pt[7]);
                }

                $pt = array_values($pt);
                $this->_drawLine($pt, $border['r']['color'], $border['r']['type'], $border['r']['width'], $bord);
            }

            // draw the bottom border
            if ($testBb) {
                $pt = array();
                $pt[] = $x+$w;                       $pt[] = $y+$h;
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y+$h;
                $pt[] = $x+$border['l']['width'];    $pt[] = $y+$h;
                $pt[] = $x;                          $pt[] = $y+$h;
                $pt[] = $x+$border['l']['width'];    $pt[] = $y+$h-$border['b']['width'];
                $pt[] = $x+$w-$border['r']['width']; $pt[] = $y+$h-$border['b']['width'];

                $bord = 3;
                if (is_array($outBL)) {
                    $bord-=2;
                    $pt[4] += $outBL[0] - $border['l']['width'];
                    if ($inBL) $pt[8] += $inBL[0];
                    unset($pt[6]);unset($pt[7]);
                }
                if (is_array($outBR)) {
                    $bord-=1;
                    $pt[2] -= $outBR[0] - $border['r']['width'];
                    if ($inBR) $pt[10]-= $inBR[0];
                    unset($pt[0]);unset($pt[1]);

                }

                $pt = array_values($pt);
                $this->_drawLine($pt, $border['b']['color'], $border['b']['type'], $border['b']['width'], $bord);
            }

            if ($background['color']) {
                $this->pdf->setFillColorArray($background['color']);
            }

            return true;
        }

        /**
         * draw a curve (for border radius)
         *
         * @access protected
         * @param  array $pt
         * @param  array $color
         */
        protected function _drawCurve($pt, $color)
        {
            $this->pdf->setFillColorArray($color);

            if (count($pt)==10)
                $this->pdf->drawCurve($pt[0], $pt[1], $pt[2], $pt[3], $pt[4], $pt[5], $pt[6], $pt[7], $pt[8], $pt[9]);
            else
                $this->pdf->drawCorner($pt[0], $pt[1], $pt[2], $pt[3], $pt[4], $pt[5], $pt[6], $pt[7]);
        }

        /**
         * draw a ligne with a specific type, and specific start and end for radius
         *
         * @access protected
         * @param  array   $pt
         * @param  float   $color
         * @param  string  $type (dashed, dotted, double, solid)
         * @param  float   $width
         * @param  integer $radius (binary from 0 to 3 with 1=>start with a radius, 2=>end with a radius)
         */
        protected function _drawLine($pt, $color, $type, $width, $radius=3)
        {
            // set the fill color
            $this->pdf->setFillColorArray($color);

            // if dashed or dotted
            if ($type=='dashed' || $type=='dotted') {

                // clean the end of the line, if radius
                if ($radius==1) {
                    $tmp = array(); $tmp[]=$pt[0]; $tmp[]=$pt[1]; $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[8]; $tmp[]=$pt[9];
                    $this->pdf->Polygon($tmp, 'F');

                    $tmp = array(); $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[4]; $tmp[]=$pt[5]; $tmp[]=$pt[6]; $tmp[]=$pt[7]; $tmp[]=$pt[8]; $tmp[]=$pt[9];
                    $pt = $tmp;
                } else if ($radius==2) {
                    $tmp = array(); $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[4]; $tmp[]=$pt[5]; $tmp[]=$pt[6]; $tmp[]=$pt[7];
                    $this->pdf->Polygon($tmp, 'F');

                    $tmp = array(); $tmp[]=$pt[0]; $tmp[]=$pt[1]; $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[6]; $tmp[]=$pt[7]; $tmp[]=$pt[8]; $tmp[]=$pt[9];
                    $pt = $tmp;
                } else if ($radius==3) {
                    $tmp = array(); $tmp[]=$pt[0]; $tmp[]=$pt[1]; $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[10]; $tmp[]=$pt[11];
                    $this->pdf->Polygon($tmp, 'F');

                    $tmp = array(); $tmp[]=$pt[4]; $tmp[]=$pt[5]; $tmp[]=$pt[6]; $tmp[]=$pt[7]; $tmp[]=$pt[8]; $tmp[]=$pt[9];
                    $this->pdf->Polygon($tmp, 'F');

                    $tmp = array(); $tmp[]=$pt[2]; $tmp[]=$pt[3]; $tmp[]=$pt[4]; $tmp[]=$pt[5]; $tmp[]=$pt[8]; $tmp[]=$pt[9]; $tmp[]=$pt[10]; $tmp[]=$pt[11];
                    $pt = $tmp;
                }

                // horisontal or vertical line
                if ($pt[2]==$pt[0]) {
                    $l = abs(($pt[3]-$pt[1])*0.5);
                    $px = 0;
                    $py = $width;
                    $x1 = $pt[0]; $y1 = ($pt[3]+$pt[1])*0.5;
                    $x2 = $pt[6]; $y2 = ($pt[7]+$pt[5])*0.5;
                } else {
                    $l = abs(($pt[2]-$pt[0])*0.5);
                    $px = $width;
                    $py = 0;
                    $x1 = ($pt[2]+$pt[0])*0.5; $y1 = $pt[1];
                    $x2 = ($pt[6]+$pt[4])*0.5; $y2 = $pt[7];
                }

                // if dashed : 3x bigger than dotted
                if ($type=='dashed') {
                    $px = $px*3.;
                    $py = $py*3.;
                }
                $mode = ($l/($px+$py)<.5);

                // display the dotted/dashed line
                for ($i=0; $l-($px+$py)*($i-0.5)>0; $i++) {
                    if (($i%2)==$mode) {
                        $j = $i-0.5;
                        $lx1 = $px*($j);   if ($lx1<-$l) $lx1 =-$l;
                        $ly1 = $py*($j);   if ($ly1<-$l) $ly1 =-$l;
                        $lx2 = $px*($j+1); if ($lx2>$l)  $lx2 = $l;
                        $ly2 = $py*($j+1); if ($ly2>$l)  $ly2 = $l;

                        $tmp = array();
                        $tmp[] = $x1+$lx1; $tmp[] = $y1+$ly1;
                        $tmp[] = $x1+$lx2; $tmp[] = $y1+$ly2;
                        $tmp[] = $x2+$lx2; $tmp[] = $y2+$ly2;
                        $tmp[] = $x2+$lx1; $tmp[] = $y2+$ly1;
                        $this->pdf->Polygon($tmp, 'F');

                        if ($j>0) {
                            $tmp = array();
                            $tmp[] = $x1-$lx1; $tmp[] = $y1-$ly1;
                            $tmp[] = $x1-$lx2; $tmp[] = $y1-$ly2;
                            $tmp[] = $x2-$lx2; $tmp[] = $y2-$ly2;
                            $tmp[] = $x2-$lx1; $tmp[] = $y2-$ly1;
                            $this->pdf->Polygon($tmp, 'F');
                        }
                    }
                }
            } else if ($type=='double') {

                // if double, 2 lines : 0=>1/3 and 2/3=>1
                $pt1 = $pt;
                $pt2 = $pt;

                if (count($pt)==12) {
                    // line 1
                    $pt1[0] = ($pt[0]-$pt[10])*0.33 + $pt[10];
                    $pt1[1] = ($pt[1]-$pt[11])*0.33 + $pt[11];
                    $pt1[2] = ($pt[2]-$pt[10])*0.33 + $pt[10];
                    $pt1[3] = ($pt[3]-$pt[11])*0.33 + $pt[11];
                    $pt1[4] = ($pt[4]-$pt[8])*0.33 + $pt[8];
                    $pt1[5] = ($pt[5]-$pt[9])*0.33 + $pt[9];
                    $pt1[6] = ($pt[6]-$pt[8])*0.33 + $pt[8];
                    $pt1[7] = ($pt[7]-$pt[9])*0.33 + $pt[9];
                    $pt2[10]= ($pt[10]-$pt[0])*0.33 + $pt[0];
                    $pt2[11]= ($pt[11]-$pt[1])*0.33 + $pt[1];

                    // line 2
                    $pt2[2] = ($pt[2] -$pt[0])*0.33 + $pt[0];
                    $pt2[3] = ($pt[3] -$pt[1])*0.33 + $pt[1];
                    $pt2[4] = ($pt[4] -$pt[6])*0.33 + $pt[6];
                    $pt2[5] = ($pt[5] -$pt[7])*0.33 + $pt[7];
                    $pt2[8] = ($pt[8] -$pt[6])*0.33 + $pt[6];
                    $pt2[9] = ($pt[9] -$pt[7])*0.33 + $pt[7];
                } else {
                    // line 1
                    $pt1[0] = ($pt[0]-$pt[6])*0.33 + $pt[6];
                    $pt1[1] = ($pt[1]-$pt[7])*0.33 + $pt[7];
                    $pt1[2] = ($pt[2]-$pt[4])*0.33 + $pt[4];
                    $pt1[3] = ($pt[3]-$pt[5])*0.33 + $pt[5];

                    // line 2
                    $pt2[6] = ($pt[6]-$pt[0])*0.33 + $pt[0];
                    $pt2[7] = ($pt[7]-$pt[1])*0.33 + $pt[1];
                    $pt2[4] = ($pt[4]-$pt[2])*0.33 + $pt[2];
                    $pt2[5] = ($pt[5]-$pt[3])*0.33 + $pt[3];
                }
                $this->pdf->Polygon($pt1, 'F');
                $this->pdf->Polygon($pt2, 'F');
            } else if ($type=='solid') {
                // solid line : draw directly the polygon
                $this->pdf->Polygon($pt, 'F');
            }
        }

        /**
         * prepare a transform matrix, only for drawing a SVG graphic
         *
         * @access protected
         * @param  string $transform
         * @return array  $matrix
         */
        protected function _prepareTransform($transform)
        {
            // it can not be  empty
            if (!$transform) return null;

            // sctions must be like scale(...)
            if (!preg_match_all('/([a-z]+)\(([^\)]*)\)/isU', $transform, $match)) return null;

            // prepare the list of the actions
            $actions = array();

            // for actions
            for ($k=0; $k<count($match[0]); $k++) {

                // get the name of the action
                $name = strtolower($match[1][$k]);

                // get the parameters of the action
                $val = explode(',', trim($match[2][$k]));
                foreach ($val as $i => $j) {
                    $val[$i] = trim($j);
                }

                // prepare the matrix, depending on the action
                switch($name)
                {
                    case 'scale':
                        if (!isset($val[0])) $val[0] = 1.;      else $val[0] = 1.*$val[0];
                        if (!isset($val[1])) $val[1] = $val[0]; else $val[1] = 1.*$val[1];
                        $actions[] = array($val[0],0,0,$val[1],0,0);
                        break;

                    case 'translate':
                        if (!isset($val[0])) $val[0] = 0.; else $val[0] = $this->style->ConvertToMM($val[0], $this->_isInDraw['w']);
                        if (!isset($val[1])) $val[1] = 0.; else $val[1] = $this->style->ConvertToMM($val[1], $this->_isInDraw['h']);
                        $actions[] = array(1,0,0,1,$val[0],$val[1]);
                        break;

                    case 'rotate':
                        if (!isset($val[0])) $val[0] = 0.; else $val[0] = $val[0]*M_PI/180.;
                        if (!isset($val[1])) $val[1] = 0.; else $val[1] = $this->style->ConvertToMM($val[1], $this->_isInDraw['w']);
                        if (!isset($val[2])) $val[2] = 0.; else $val[2] = $this->style->ConvertToMM($val[2], $this->_isInDraw['h']);
                        if ($val[1] || $val[2]) $actions[] = array(1,0,0,1,-$val[1],-$val[2]);
                        $actions[] = array(cos($val[0]),sin($val[0]),-sin($val[0]),cos($val[0]),0,0);
                        if ($val[1] || $val[2]) $actions[] = array(1,0,0,1,$val[1],$val[2]);
                        break;

                    case 'skewx':
                        if (!isset($val[0])) $val[0] = 0.; else $val[0] = $val[0]*M_PI/180.;
                        $actions[] = array(1,0,tan($val[0]),1,0,0);
                        break;

                    case 'skewy':
                        if (!isset($val[0])) $val[0] = 0.; else $val[0] = $val[0]*M_PI/180.;
                        $actions[] = array(1,tan($val[0]),0,1,0,0);
                        break;
                    case 'matrix':
                        if (!isset($val[0])) $val[0] = 0.; else $val[0] = $val[0]*1.;
                        if (!isset($val[1])) $val[1] = 0.; else $val[1] = $val[1]*1.;
                        if (!isset($val[2])) $val[2] = 0.; else $val[2] = $val[2]*1.;
                        if (!isset($val[3])) $val[3] = 0.; else $val[3] = $val[3]*1.;
                        if (!isset($val[4])) $val[4] = 0.; else $val[4] = $this->style->ConvertToMM($val[4], $this->_isInDraw['w']);
                        if (!isset($val[5])) $val[5] = 0.; else $val[5] = $this->style->ConvertToMM($val[5], $this->_isInDraw['h']);
                        $actions[] =$val;
                        break;
                }
            }

            // if ther is no actions => return
            if (!$actions) return null;

            // get the first matrix
            $m = $actions[0]; unset($actions[0]);

            // foreach matrix => multiply to the last matrix
            foreach ($actions as $n) {
                $m = array(
                    $m[0]*$n[0]+$m[2]*$n[1],
                    $m[1]*$n[0]+$m[3]*$n[1],
                    $m[0]*$n[2]+$m[2]*$n[3],
                    $m[1]*$n[2]+$m[3]*$n[3],
                    $m[0]*$n[4]+$m[2]*$n[5]+$m[4],
                    $m[1]*$n[4]+$m[3]*$n[5]+$m[5]
                );
            }

            // return the matrix
            return $m;
        }

        protected function XXX_getDrawNumber(&$lst, $key, $n=1, $correct=false)
        {
            $res = array_fill(0, $n, 0);
            $tmp = isset($lst[$key]) ? $lst[$key] : null;
            if (!$tmp) return $res;
            $tmp = explode(' ', trim(preg_replace('/[\s]+/', ' ', $tmp)));
            foreach ($tmp as $k => $v) {
                $v = trim($v);
                if (!$correct) {
                    $res[$k] = $this->style->ConvertToMM($v);
                } else {
                    $res[$k] = $this->style->ConvertToMM($v, ($k%2) ? $this->_isInDraw['h'] : $this->_isInDraw['w']);
                }
            }
            return $res;
        }

        /**
         * tag : PAGE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_PAGE($param)
        {
            if ($this->_isForOneLine) return false;
            if ($this->_debugActif) $this->_DEBUG_add('PAGE n�'.($this->_page+1), true);

            $newPageSet= (!isset($param['pageset']) || $param['pageset']!='old');

            $this->_maxH = 0;
            if ($newPageSet) {
                $this->_subHEADER = array();
                $this->_subFOOTER = array();

                // identification de l'orientation demand�e
                $orientation = '';
                if (isset($param['orientation'])) {
                    $param['orientation'] = strtolower($param['orientation']);
                    if ($param['orientation']=='p')            $orientation = 'P';
                    if ($param['orientation']=='portrait')    $orientation = 'P';

                    if ($param['orientation']=='l')            $orientation = 'L';
                    if ($param['orientation']=='paysage')    $orientation = 'L';
                    if ($param['orientation']=='landscape')    $orientation = 'L';
                }

                // identification de l'orientation demand�e
                $format = null;
                if (isset($param['format'])) {
                    $format = strtolower($param['format']);
                    if (preg_match('/^([0-9]+)x([0-9]+)$/isU', $format, $match)) {
                        $format = array(intval($match[1]), intval($match[2]));
                    }
                }

                // identification des propri�t�s du background
                $background = array();
                if (isset($param['backimg'])) {
                    $background['img']        = isset($param['backimg'])    ? $param['backimg']        : '';        // nom de l'image
                    $background['posX']        = isset($param['backimgx'])    ? $param['backimgx']    : 'center'; // position horizontale de l'image
                    $background['posY']        = isset($param['backimgy'])    ? $param['backimgy']    : 'middle'; // position verticale de l'image
                    $background['width']    = isset($param['backimgw'])    ? $param['backimgw']    : '100%';    // taille de l'image (100% = largueur de la feuille)

                    // conversion du nom de l'image, en cas de param�tres en _GET
                    $background['img'] = str_replace('&amp;', '&', $background['img']);
                    // conversion des positions
                    if ($background['posX']=='left')    $background['posX'] = '0%';
                    if ($background['posX']=='center')    $background['posX'] = '50%';
                    if ($background['posX']=='right')    $background['posX'] = '100%';
                    if ($background['posY']=='top')        $background['posY'] = '0%';
                    if ($background['posY']=='middle')    $background['posY'] = '50%';
                    if ($background['posY']=='bottom')    $background['posY'] = '100%';


                    // si il y a une image de pr�cis�
                    if ($background['img']) {
                        // get the size of the image
                        // WARNING : if URL, "allow_url_fopen" must turned to "on" in php.ini
                        $infos=@getimagesize($background['img']);
                        if (count($infos)>1) {
                            // taille de l'image, en fonction de la taille sp�cifi�e.
                            $imageWidth = $this->style->ConvertToMM($background['width'], $this->pdf->getW());
                            $imageHeight = $imageWidth*$infos[1]/$infos[0];

                            // r�cup�ration des dimensions et positions de l'image
                            $background['width']    = $imageWidth;
                            $background['posX']        = $this->style->ConvertToMM($background['posX'], $this->pdf->getW() - $imageWidth);
                            $background['posY']        = $this->style->ConvertToMM($background['posY'], $this->pdf->getH() - $imageHeight);
                        } else
                            $background = array();
                    } else
                        $background = array();
                }

                // marges TOP et BOTTOM pour le texte.
                $background['top']    = isset($param['backtop'])    ? $param['backtop']    : '0';
                $background['bottom'] = isset($param['backbottom']) ? $param['backbottom'] : '0';
                $background['left']   = isset($param['backleft'])   ? $param['backleft']   : '0';
                $background['right']  = isset($param['backright'])  ? $param['backright']  : '0';

                if (preg_match('/^([0-9]*)$/isU', $background['top']))    $background['top']    .= 'mm';
                if (preg_match('/^([0-9]*)$/isU', $background['bottom'])) $background['bottom'] .= 'mm';
                if (preg_match('/^([0-9]*)$/isU', $background['left']))   $background['left']   .= 'mm';
                if (preg_match('/^([0-9]*)$/isU', $background['right']))  $background['right']  .= 'mm';

                $background['top']    = $this->style->ConvertToMM($background['top'], $this->pdf->getH());
                $background['bottom'] = $this->style->ConvertToMM($background['bottom'], $this->pdf->getH());
                $background['left']   = $this->style->ConvertToMM($background['left'], $this->pdf->getW());
                $background['right']  = $this->style->ConvertToMM($background['right'], $this->pdf->getW());

                $res = false;
                $background['color']    = isset($param['backcolor'])    ? $this->style->ConvertToColor($param['backcolor'], $res) : null;
                if (!$res) $background['color'] = null;

                $this->style->save();
                $this->style->analyse('PAGE', $param);
                $this->style->setPosition();
                $this->style->FontSet();

                // nouvelle page
                $this->_setNewPage($format, $orientation, $background);

                // footer automatique
                if (isset($param['footer'])) {
                    $lst = explode(';', $param['footer']);
                    foreach ($lst as $key => $val) $lst[$key] = trim(strtolower($val));
                    $page    = in_array('page', $lst);
                    $date    = in_array('date', $lst);
                    $heure    = in_array('heure', $lst);
                    $form    = in_array('form', $lst);
                } else {
                    $page    = null;
                    $date    = null;
                    $heure    = null;
                    $form    = null;
                }
                $this->pdf->SetMyFooter($page, $date, $heure, $form);
            } else {
                $this->style->save();
                $this->style->analyse('PAGE', $param);
                $this->style->setPosition();
                $this->style->FontSet();

                $this->_setNewPage();
            }

            return true;
        }

        /**
         * tag : PAGE
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_PAGE($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            $this->style->load();
            $this->style->FontSet();

            if ($this->_debugActif) $this->_DEBUG_add('PAGE n�'.$this->_page, false);

            return true;
        }


        protected function _tag_open_PAGE_HEADER($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_subHEADER = array();
            for ($this->_parsePos; $this->_parsePos<count($this->parsing->code); $this->_parsePos++) {
                $action = $this->parsing->code[$this->_parsePos];
                if ($action['name']=='page_header') $action['name']='page_header_sub';
                $this->_subHEADER[] = $action;
                if (strtolower($action['name'])=='page_header_sub' && $action['close']) break;
            }

            $this->_setPageHeader();

            return true;
        }

        protected function _tag_open_PAGE_FOOTER($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_subFOOTER = array();
            for ($this->_parsePos; $this->_parsePos<count($this->parsing->code); $this->_parsePos++) {
                $action = $this->parsing->code[$this->_parsePos];
                if ($action['name']=='page_footer') $action['name']='page_footer_sub';
                $this->_subFOOTER[] = $action;
                if (strtolower($action['name'])=='page_footer_sub' && $action['close']) break;
            }

            $this->_setPageFooter();

            return true;
        }

        protected function _tag_open_PAGE_HEADER_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            // sauvegarde de l'�tat
            $this->_subSTATES = array();
            $this->_subSTATES['x']    = $this->pdf->getX();
            $this->_subSTATES['y']    = $this->pdf->getY();
            $this->_subSTATES['s']    = $this->style->value;
            $this->_subSTATES['t']    = $this->style->table;
            $this->_subSTATES['ml']    = $this->_margeLeft;
            $this->_subSTATES['mr']    = $this->_margeRight;
            $this->_subSTATES['mt']    = $this->_margeTop;
            $this->_subSTATES['mb']    = $this->_margeBottom;
            $this->_subSTATES['mp']    = $this->_pageMarges;

            // nouvel etat pour le footer
            $this->_pageMarges = array();
            $this->_margeLeft    = $this->_defaultLeft;
            $this->_margeRight    = $this->_defaultRight;
            $this->_margeTop        = $this->_defaultTop;
            $this->_margeBottom    = $this->_defaultBottom;
            $this->pdf->SetMargins($this->_margeLeft, $this->_margeTop, $this->_margeRight);
            $this->pdf->SetAutoPageBreak(false, $this->_margeBottom);
            $this->pdf->setXY($this->_defaultLeft, $this->_defaultTop);

            $this->style->initStyle();
            $this->style->resetStyle();
            $this->style->value['width']    = $this->pdf->getW() - $this->_defaultLeft - $this->_defaultRight;
            $this->style->table                = array();

            $this->style->save();
            $this->style->analyse('page_header_sub', $param);
            $this->style->setPosition();
            $this->style->FontSet();
            $this->_setNewPositionForNewLine();
            return true;
        }

        protected function _tag_close_PAGE_HEADER_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();

            // retablissement de l'etat
            $this->style->value                = $this->_subSTATES['s'];
            $this->style->table                = $this->_subSTATES['t'];
            $this->_pageMarges                = $this->_subSTATES['mp'];
            $this->_margeLeft                = $this->_subSTATES['ml'];
            $this->_margeRight                = $this->_subSTATES['mr'];
            $this->_margeTop                    = $this->_subSTATES['mt'];
            $this->_margeBottom                = $this->_subSTATES['mb'];
            $this->pdf->SetMargins($this->_margeLeft, $this->_margeTop, $this->_margeRight);
            $this->pdf->setbMargin($this->_margeBottom);
            $this->pdf->SetAutoPageBreak(false, $this->_margeBottom);
            $this->pdf->setXY($this->_subSTATES['x'], $this->_subSTATES['y']);

            $this->style->FontSet();
            $this->_maxH = 0;

            return true;
        }

        protected function _tag_open_PAGE_FOOTER_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_subSTATES = array();
            $this->_subSTATES['x']    = $this->pdf->getX();
            $this->_subSTATES['y']    = $this->pdf->getY();
            $this->_subSTATES['s']    = $this->style->value;
            $this->_subSTATES['t']    = $this->style->table;
            $this->_subSTATES['ml']    = $this->_margeLeft;
            $this->_subSTATES['mr']    = $this->_margeRight;
            $this->_subSTATES['mt']    = $this->_margeTop;
            $this->_subSTATES['mb']    = $this->_margeBottom;
            $this->_subSTATES['mp']    = $this->_pageMarges;

            // nouvel etat pour le footer
            $this->_pageMarges = array();
            $this->_margeLeft    = $this->_defaultLeft;
            $this->_margeRight    = $this->_defaultRight;
            $this->_margeTop        = $this->_defaultTop;
            $this->_margeBottom    = $this->_defaultBottom;
            $this->pdf->SetMargins($this->_margeLeft, $this->_margeTop, $this->_margeRight);
            $this->pdf->SetAutoPageBreak(false, $this->_margeBottom);
            $this->pdf->setXY($this->_defaultLeft, $this->_defaultTop);


            $this->style->initStyle();
            $this->style->resetStyle();
            $this->style->value['width']    = $this->pdf->getW() - $this->_defaultLeft - $this->_defaultRight;
            $this->style->table                = array();

            // on en cr�� un sous HTML que l'on transforme en PDF
            // pour r�cup�rer la hauteur
            // on extrait tout ce qui est contenu dans le FOOTER
            $sub = null;
            $this->_createSubHTML($sub);
            $sub->parsing->code = $this->parsing->getLevel($this->_parsePos);
            $sub->_makeHTMLcode();
            $this->pdf->setY($this->pdf->getH() - $sub->_maxY - $this->_defaultBottom - 0.01);
            $this->_destroySubHTML($sub);

            $this->style->save();
            $this->style->analyse('page_footer_sub', $param);
            $this->style->setPosition();
            $this->style->FontSet();
            $this->_setNewPositionForNewLine();

            return true;
        }

        protected function _tag_close_PAGE_FOOTER_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();

            $this->style->value                = $this->_subSTATES['s'];
            $this->style->table                = $this->_subSTATES['t'];
            $this->_pageMarges                 = $this->_subSTATES['mp'];
            $this->_margeLeft                = $this->_subSTATES['ml'];
            $this->_margeRight                = $this->_subSTATES['mr'];
            $this->_margeTop                    = $this->_subSTATES['mt'];
            $this->_margeBottom                = $this->_subSTATES['mb'];
            $this->pdf->SetMargins($this->_margeLeft, $this->_margeTop, $this->_margeRight);
            $this->pdf->SetAutoPageBreak(false, $this->_margeBottom);
            $this->pdf->setXY($this->_subSTATES['x'], $this->_subSTATES['y']);

            $this->style->FontSet();
            $this->_maxH = 0;

            return true;
        }

        /**
         * tag : NOBREAK
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_NOBREAK($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            // on en cr�� un sous HTML que l'on transforme en PDF
            // pour analyse les dimensions
            // et voir si ca rentre
            $sub = null;
            $this->_createSubHTML($sub);
            $sub->parsing->code = $this->parsing->getLevel($this->_parsePos);
            $sub->_makeHTMLcode();

            $y = $this->pdf->getY();
            if (
                $sub->_maxY < ($this->pdf->getH() - $this->pdf->gettMargin()-$this->pdf->getbMargin()) &&
                $y + $sub->_maxY>=($this->pdf->getH() - $this->pdf->getbMargin())
            ) {
                $this->_setNewPage();
            }
            $this->_destroySubHTML($sub);

            return true;
        }


        /**
         * tag : NOBREAK
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_NOBREAK($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            return true;
        }

        /**
         * tag : DIV
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_DIV($param, $other = 'div')
        {
            if ($this->_isForOneLine) return false;
            if ($this->_debugActif) $this->_DEBUG_add(strtoupper($other), true);

            $this->style->save();
            $this->style->analyse($other, $param);
            $this->style->FontSet();

            // gestion specifique a la tag legend pour l'afficher au bon endroit
            if (in_array($other, array('fieldset', 'legend'))) {
                if (isset($param['moveTop']))    $this->style->value['margin']['t']    += $param['moveTop'];
                if (isset($param['moveLeft']))    $this->style->value['margin']['l']    += $param['moveLeft'];
                if (isset($param['moveDown']))    $this->style->value['margin']['b']    += $param['moveDown'];
            }

            $alignObject = null;
            if ($this->style->value['margin-auto']) $alignObject = 'center';

            $marge = array();
            $marge['l'] = $this->style->value['border']['l']['width'] + $this->style->value['padding']['l']+0.03;
            $marge['r'] = $this->style->value['border']['r']['width'] + $this->style->value['padding']['r']+0.03;
            $marge['t'] = $this->style->value['border']['t']['width'] + $this->style->value['padding']['t']+0.03;
            $marge['b'] = $this->style->value['border']['b']['width'] + $this->style->value['padding']['b']+0.03;

            // on extrait tout ce qui est contenu dans la DIV
            $level = $this->parsing->getLevel($this->_parsePos);

            // on en cr�� un sous HTML que l'on transforme en PDF
            // pour analyse les dimensions
            $w = 0; $h = 0;
            if (count($level)) {
                $sub = null;
                $this->_createSubHTML($sub);
                $sub->parsing->code = $level;
                $sub->_makeHTMLcode();
                $w = $sub->_maxX;
                $h = $sub->_maxY;
                $this->_destroySubHTML($sub);
            }
            $wReel = $w;
            $hReel = $h;

//            if (($w==0 && $this->style->value['width']==0) || ($w>$this->style->value['width']) || $this->style->value['position']=='absolute')
                $w+= $marge['l']+$marge['r']+0.001;

            $h+= $marge['t']+$marge['b']+0.001;

            if ($this->style->value['overflow']=='hidden') {
                $overW = max($w, $this->style->value['width']);
                $overH = max($h, $this->style->value['height']);
                $overflow = true;
                $this->style->value['old_maxX'] = $this->_maxX;
                $this->style->value['old_maxY'] = $this->_maxY;
                $this->style->value['old_maxH'] = $this->_maxH;
                $this->style->value['old_overflow'] = $this->_isInOverflow;
                $this->_isInOverflow = true;
            } else {
                $overW = null;
                $overH = null;
                $overflow = false;
                $this->style->value['width']    = max($w, $this->style->value['width']);
                $this->style->value['height']    = max($h, $this->style->value['height']);
            }

            switch($this->style->value['rotate'])
            {
                case 90:
                    $tmp = $overH; $overH = $overW; $overW = $tmp;
                    $tmp = $hReel; $hReel = $wReel; $wReel = $tmp;
                    unset($tmp);
                    $w = $this->style->value['height'];
                    $h = $this->style->value['width'];
                    $tX =-$h;
                    $tY = 0;
                    break;

                case 180:
                    $w = $this->style->value['width'];
                    $h = $this->style->value['height'];
                    $tX = -$w;
                    $tY = -$h;
                    break;

                case 270:
                    $tmp = $overH; $overH = $overW; $overW = $tmp;
                    $tmp = $hReel; $hReel = $wReel; $wReel = $tmp;
                    unset($tmp);
                    $w = $this->style->value['height'];
                    $h = $this->style->value['width'];
                    $tX = 0;
                    $tY =-$w;
                    break;

                default:
                    $w = $this->style->value['width'];
                    $h = $this->style->value['height'];
                    $tX = 0;
                    $tY = 0;
                    break;
            }

            if (!$this->style->value['position']) {
                if (
                    $w < ($this->pdf->getW() - $this->pdf->getlMargin()-$this->pdf->getrMargin()) &&
                    $this->pdf->getX() + $w>=($this->pdf->getW() - $this->pdf->getrMargin())
                    )
                    $this->_tag_open_BR(array());

                if (
                        ($h < ($this->pdf->getH() - $this->pdf->gettMargin()-$this->pdf->getbMargin())) &&
                        ($this->pdf->getY() + $h>=($this->pdf->getH() - $this->pdf->getbMargin())) &&
                        !$this->_isInOverflow
                    )
                    $this->_setNewPage();

                // en cas d'alignement => correction
                $old = $this->style->getOldValues();
                $parentWidth = $old['width'] ? $old['width'] : $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();

                if ($parentWidth>$w) {
                    if ($alignObject=='center')        $this->pdf->setX($this->pdf->getX() + ($parentWidth-$w)*0.5);
                    else if ($alignObject=='right')    $this->pdf->setX($this->pdf->getX() + $parentWidth-$w);
                }

                $this->style->setPosition();
            } else {
                // en cas d'alignement => correction
                $old = $this->style->getOldValues();
                $parentWidth = $old['width'] ? $old['width'] : $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();

                if ($parentWidth>$w) {
                    if ($alignObject=='center')        $this->pdf->setX($this->pdf->getX() + ($parentWidth-$w)*0.5);
                    else if ($alignObject=='right')    $this->pdf->setX($this->pdf->getX() + $parentWidth-$w);
                }

                $this->style->setPosition();
                $this->_saveMax();
                $this->_maxX = 0;
                $this->_maxY = 0;
                $this->_maxH = 0;
                $this->_maxE = 0;
            }

            if ($this->style->value['rotate']) {
                $this->pdf->startTransform();
                $this->pdf->setRotation($this->style->value['rotate']);
                $this->pdf->setTranslate($tX, $tY);
            }

            // initialisation du style des bordures de la div
            $this->_drawRectangle(
                $this->style->value['x'],
                $this->style->value['y'],
                $this->style->value['width'],
                $this->style->value['height'],
                $this->style->value['border'],
                $this->style->value['padding'],
                0,
                $this->style->value['background']
            );

            $marge = array();
            $marge['l'] = $this->style->value['border']['l']['width'] + $this->style->value['padding']['l']+0.03;
            $marge['r'] = $this->style->value['border']['r']['width'] + $this->style->value['padding']['r']+0.03;
            $marge['t'] = $this->style->value['border']['t']['width'] + $this->style->value['padding']['t']+0.03;
            $marge['b'] = $this->style->value['border']['b']['width'] + $this->style->value['padding']['b']+0.03;

            $this->style->value['width'] -= $marge['l']+$marge['r'];
            $this->style->value['height']-= $marge['t']+$marge['b'];

            // positionnement en fonction des alignements
            $xCorr = 0;
            $yCorr = 0;
            if (!$this->_subPart && !$this->_isSubPart) {
                switch($this->style->value['text-align'])
                {
                    case 'right':
                        $xCorr = ($this->style->value['width']-$wReel);
                        break;
                    case 'center':
                        $xCorr = ($this->style->value['width']-$wReel)*0.5;
                        break;
                }
                if ($xCorr>0) $xCorr=0;
                switch($this->style->value['vertical-align'])
                {
                    case 'bottom':
                        $yCorr = ($this->style->value['height']-$hReel);
                        break;
                    case 'middle':
                        $yCorr = ($this->style->value['height']-$hReel)*0.5;
                        break;
                }
            }

            if ($overflow) {
                $overW-= $marge['l']+$marge['r'];
                $overH-= $marge['t']+$marge['b'];
                $this->pdf->clippingPathStart(
                    $this->style->value['x']+$marge['l'],
                    $this->style->value['y']+$marge['t'],
                    $this->style->value['width'],
                    $this->style->value['height']
                );

                $this->style->value['x']+= $xCorr;
                // limitation des marges aux dimensions du contenu
                $mL = $this->style->value['x']+$marge['l'];
                $mR = $this->pdf->getW() - $mL - $overW;
            } else {
                // limitation des marges aux dimensions de la div
                $mL = $this->style->value['x']+$marge['l'];
                $mR = $this->pdf->getW() - $mL - $this->style->value['width'];
            }

            $x = $this->style->value['x']+$marge['l'];
            $y = $this->style->value['y']+$marge['t']+$yCorr;
            $this->_saveMargin($mL, 0, $mR);
            $this->pdf->setXY($x, $y);

            $this->_setNewPositionForNewLine();

            return true;
        }
        protected function _tag_open_BLOCKQUOTE($param)
        {
            return $this->_tag_open_DIV($param, 'blockquote');
        }
        protected function _tag_open_LEGEND($param)
        {
            return $this->_tag_open_DIV($param, 'legend');
        }

        /**
         * tag : FIELDSET
         * mode : OPEN
         * ecrite par Pavel Kochman
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_FIELDSET($param)
        {

            $this->style->save();
            $this->style->analyse('fieldset', $param);

            // get height of LEGEND element and make fieldset corrections
            for ($tempPos = $this->_parsePos + 1; $tempPos<count($this->parsing->code); $tempPos++) {
                $action = $this->parsing->code[$tempPos];
                if ($action['name'] == 'fieldset') break;
                if ($action['name'] == 'legend' && !$action['close']) {
                    $legendOpenPos = $tempPos;

                    $sub = null;
                    $this->_createSubHTML($sub);
                    $sub->parsing->code = $this->parsing->getLevel($tempPos - 1);

                    // pour chaque element identifi� par le parsing
                    $res = null;
                    for ($sub->_parsePos = 0; $sub->_parsePos<count($sub->parsing->code); $sub->_parsePos++) {
                        $action = $sub->parsing->code[$sub->_parsePos];
                        $sub->_executeAction($action);

                        if ($action['name'] == 'legend' && $action['close'])
                            break;
                    }

                    $legendH = $sub->_maxY;
                    $this->_destroySubHTML($sub);

                    $move = $this->style->value['padding']['t'] + $this->style->value['border']['t']['width'] + 0.03;

                    $param['moveTop'] = $legendH / 2;

                    $this->parsing->code[$legendOpenPos]['param']['moveTop'] = - ($legendH / 2 + $move);
                    $this->parsing->code[$legendOpenPos]['param']['moveLeft'] = 2 - $this->style->value['border']['l']['width'] - $this->style->value['padding']['l'];
                    $this->parsing->code[$legendOpenPos]['param']['moveDown'] = $move;
                    break;
                }
            }
            $this->style->load();

            return $this->_tag_open_DIV($param, 'fieldset');
        }

        /**
         * tag : DIV
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_DIV($param, $other='div')
        {
            if ($this->_isForOneLine) return false;

            if ($this->style->value['overflow']=='hidden') {
                $this->_maxX = $this->style->value['old_maxX'];
                $this->_maxY = $this->style->value['old_maxY'];
                $this->_maxH = $this->style->value['old_maxH'];
                $this->_isInOverflow = $this->style->value['old_overflow'];
                $this->pdf->clippingPathStop();
            }

            if ($this->style->value['rotate'])
                $this->pdf->stopTransform();

            $marge = array();
            $marge['l'] = $this->style->value['border']['l']['width'] + $this->style->value['padding']['l']+0.03;
            $marge['r'] = $this->style->value['border']['r']['width'] + $this->style->value['padding']['r']+0.03;
            $marge['t'] = $this->style->value['border']['t']['width'] + $this->style->value['padding']['t']+0.03;
            $marge['b'] = $this->style->value['border']['b']['width'] + $this->style->value['padding']['b']+0.03;

            $x = $this->style->value['x'];
            $y = $this->style->value['y'];
            $w = $this->style->value['width']+$marge['l']+$marge['r']+$this->style->value['margin']['r'];
            $h = $this->style->value['height']+$marge['t']+$marge['b']+$this->style->value['margin']['b'];

            switch($this->style->value['rotate'])
            {
                case 90:
                    $t = $w; $w = $h; $h = $t;
                    break;

                case 270:
                    $t = $w; $w = $h; $h = $t;
                    break;

                default:
                    break;
            }


            if ($this->style->value['position']!='absolute') {
                // position
                $this->pdf->setXY($x+$w, $y);

                // position MAX
                $this->_maxX = max($this->_maxX, $x+$w);
                $this->_maxY = max($this->_maxY, $y+$h);
                 $this->_maxH = max($this->_maxH, $h);
            } else {
                // position
                $this->pdf->setXY($this->style->value['xc'], $this->style->value['yc']);

                $this->_loadMax();
            }

             $block = ($this->style->value['display']!='inline' && $this->style->value['position']!='absolute');

             $this->style->load();
            $this->style->FontSet();
            $this->_loadMargin();

            if ($block) $this->_tag_open_BR(array());
            if ($this->_debugActif) $this->_DEBUG_add(strtoupper($other), false);

            return true;
        }
        protected function _tag_close_BLOCKQUOTE($param)
        {
            return $this->_tag_close_DIV($param, 'blockquote');
        }
        protected function _tag_close_FIELDSET($param)
        {
            return $this->_tag_close_DIV($param, 'fieldset');
        }
        protected function _tag_close_LEGEND($param)
        {
            return $this->_tag_close_DIV($param, 'legend');
        }

        /**
         * tag : BARCODE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_BARCODE($param)
        {
            // pour compatibilit� < 3.29
            $lstBarcode = array();
            $lstBarcode['UPC_A']  =    'UPCA';
            $lstBarcode['CODE39'] =    'C39';

            if (!isset($param['type']))     $param['type'] = 'C39';
            if (!isset($param['value']))    $param['value']    = 0;
            if (!isset($param['label']))    $param['label']    = 'label';
            if (!isset($param['style']['color'])) $param['style']['color'] = '#000000';

            if ($this->_testIsDeprecated && (isset($param['bar_h']) || isset($param['bar_w'])))
                throw new HTML2PDF_exception(9, array('BARCODE', 'bar_h, bar_w'));

            $param['type'] = strtoupper($param['type']);
            if (isset($lstBarcode[$param['type']])) $param['type'] = $lstBarcode[$param['type']];

            $this->style->save();
            $this->style->analyse('barcode', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $x = $this->pdf->getX();
            $y = $this->pdf->getY();
            $w = $this->style->value['width'];    if (!$w) $w = $this->style->ConvertToMM('50mm');
            $h = $this->style->value['height'];    if (!$h) $h = $this->style->ConvertToMM('10mm');
            $txt = ($param['label']!=='none' ? $this->style->value['font-size'] : false);
            $c = $this->style->value['color'];
            $infos = $this->pdf->myBarcode($param['value'], $param['type'], $x, $y, $w, $h, $txt, $c);

            // position maximale globale
            $this->_maxX = max($this->_maxX, $x+$infos[0]);
            $this->_maxY = max($this->_maxY, $y+$infos[1]);
            $this->_maxH = max($this->_maxH, $infos[1]);
            $this->_maxE++;

            $this->pdf->setXY($x+$infos[0], $y);

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : BARCODE
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_BARCODE($param)
        {
            // completement inutile

            return true;
        }

        /**
         * tag : QRCODE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_QRCODE($param)
        {
            if ($this->_testIsDeprecated && (isset($param['size']) || isset($param['noborder'])))
                throw new HTML2PDF_exception(9, array('QRCODE', 'size, noborder'));

            if ($this->_debugActif) $this->_DEBUG_add('QRCODE', true);

            if (!isset($param['value']))                        $param['value']    = '';
            if (!isset($param['ec']))                            $param['ec']    = 'H';
            if (!isset($param['style']['color']))                $param['style']['color'] = '#000000';
            if (!isset($param['style']['background-color']))    $param['style']['background-color'] = '#FFFFFF';
            if (isset($param['style']['border'])) {
                $borders = $param['style']['border']!='none';
                unset($param['style']['border']);
            } else {
                $borders = true;
            }

            if ($param['value']==='') return true;
            if (!in_array($param['ec'], array('L', 'M', 'Q', 'H'))) $param['ec'] = 'H';

            $this->style->save();
            $this->style->analyse('qrcode', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $x = $this->pdf->getX();
            $y = $this->pdf->getY();
            $w = $this->style->value['width'];
            $h = $this->style->value['height'];
            $size = max($w, $h); if (!$size) $size = $this->style->ConvertToMM('50mm');

            $style = array(
                    'fgcolor' => $this->style->value['color'],
                    'bgcolor' => $this->style->value['background']['color'],
                );

            if ($borders) {
                $style['border'] = true;
                $style['padding'] = 'auto';
            } else {
                $style['border'] = false;
                $style['padding'] = 0;
            }

            if (!$this->_subPart && !$this->_isSubPart) {
                $this->pdf->write2DBarcode($param['value'], 'QRCODE,'.$param['ec'], $x, $y, $size, $size, $style);
            }

            // position maximale globale
            $this->_maxX = max($this->_maxX, $x+$size);
            $this->_maxY = max($this->_maxY, $y+$size);
             $this->_maxH = max($this->_maxH, $size);

             $this->pdf->setX($x+$size);

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : QRCODE
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_QRCODE($param)
        {
            if ($this->_debugActif) $this->_DEBUG_add('QRCODE', false);
            // completement inutile
            return true;
        }

        /**
         * tag : BOOKMARK
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_BOOKMARK($param)
        {
            $titre = isset($param['title']) ? trim($param['title']) : '';
            $level = isset($param['level']) ? floor($param['level']) : 0;

            if ($level<0) $level = 0;
            if ($titre) $this->pdf->Bookmark($titre, $level, -1);

            return true;
        }

        /**
         * tag : BOOKMARK
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_BOOKMARK($param)
        {
            // completement inutile

            return true;
        }

        /**
         * tag : WRITE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_WRITE($param)
        {
            $fill = ($this->style->value['background']['color']!==null && $this->style->value['background']['image']===null);
            if (in_array($this->style->value['id_tag'], array('fieldset', 'legend', 'div', 'table', 'tr', 'td', 'th')))
                $fill = false;

            // r�cup�ration du texte � �crire, et conversion
            $txt = $param['txt'];

            if ($this->_isAfterFloat) {
                $txt = ltrim($txt);
                $this->_isAfterFloat = false;
            }

            $txt = str_replace('[[page_cu]]', $this->_page, $txt);

            if ($this->style->value['text-transform']!='none') {
                if ($this->style->value['text-transform']=='capitalize')
                    $txt = ucwords($txt);
                else if ($this->style->value['text-transform']=='uppercase')
                    $txt = strtoupper($txt);
                else if ($this->style->value['text-transform']=='lowercase')
                    $txt = strtolower($txt);
            }

            // tailles du texte
            $h    = 1.08*$this->style->value['font-size'];
            $dh    = $h*$this->style->value['mini-decal'];
            $lh = $this->style->getLineHeight();

            // identification de l'alignement
            $align = 'L';
            if ($this->style->value['text-align']=='li_right') {
                $w = $this->style->value['width'];
                $align = 'R';
            }

            // pr� calcul de la taille de chaque mot et de la phrase complete
            $w = 0;
            $words = explode(' ', $txt);
            foreach ($words as $k => $word) {
                $words[$k] = array($word, $this->pdf->GetStringWidth($word));
                $w+= $words[$k][1];
            }
            $space = $this->pdf->GetStringWidth(' ');
            $w+= $space*(count($words)-1);

            $currPos = 0;                                   // position dans le texte
            $maxX = 0;                                      // plus grande largeur du texte apres retour � la ligne
            $x = $this->pdf->getX();                        // position du texte
            $y = $this->pdf->getY();
            $dy = $this->_getElementY($lh);

            list($left, $right) = $this->_getMargins($y);    // marges autorisees
            $nb = 0;                                        // nbr de lignes d�coup�es

            // tant que ca ne rentre pas sur la ligne et qu'on a du texte => on d�coupe
            while ($x+$w>$right && $x<$right && count($words)) {
                // trouver une phrase qui rentre dans la largeur, en ajoutant les mots 1 � 1
                $i=0;
                $old = array('', 0);
                $str = $words[0];
                $add = false;
                while (($x+$str[1])<$right) {
                    $i++;
                    $add = true;

                    array_shift($words);
                    $old = $str;

                    if (!count($words)) break;
                    $str[0].= ' '.$words[0][0];
                    $str[1]+= $space+$words[0][1];
                }
                $str = $old;

                // si rien de rentre, et que le premier mot ne rentre de toute facon pas dans une ligne, on le force...
                if ($i==0 && (($left+$words[0][1])>=$right)) {
                    $str = $words[0];
                    array_shift($words);
                    $i++;
                    $add = true;
                }
                $currPos+= ($currPos ? 1 : 0)+strlen($str[0]);

                // ecriture du bout de phrase extrait et qui rentre
                $wc = ($align=='L' ? $str[1] : $this->style->value['width']);
                if ($right - $left<$wc) $wc = $right - $left;
/*
                if ($this->pdf->ws) {
                    $oldSpace = $this->pdf->CurrentFont['cw'][' '];
                    $this->pdf->CurrentFont['cw'][' ']*=(1.+$this->pdf->ws);
                    $wc = $str[1];
                    $this->pdf->CurrentFont['cw'][' '] = $oldSpace;
                }
*/
                if (strlen($str[0])) {
                    $this->pdf->setXY($this->pdf->getX(), $y+$dh+$dy);
                    $this->pdf->Cell($wc, $h, $str[0], 0, 0, $align, $fill, $this->_isInLink);
                    $this->pdf->setXY($this->pdf->getX(), $y);
                }
                $this->_maxH = max($this->_maxH, $lh);

                // d�termination de la largeur max
                $maxX = max($maxX, $this->pdf->getX());

                // nouvelle position et nouvelle largeur pour la boucle
                $w-= $str[1];
                $y = $this->pdf->getY();
                $x = $this->pdf->getX();
                $dy = $this->_getElementY($lh);

                // si il reste du text � afficher
                if (count($words)) {
                    if ($add) $w-= $space;
                    if ($this->_isForOneLine) {
                        $this->_maxE+= $i+1;
                        $this->_maxX = max($this->_maxX, $maxX);
                        return null;
                    }

                    // retour � la ligne
                    $this->_tag_open_BR(array('style' => ''), $currPos);

                    $y = $this->pdf->getY();
                    $x = $this->pdf->getX();
                    $dy = $this->_getElementY($lh);

                    // si la prochaine ligne ne rentre pas dans la page => nouvelle page
                    if ($y + $h>=$this->pdf->getH() - $this->pdf->getbMargin()) {
                        if (!$this->_isInOverflow && !$this->_isInFooter) {
                            $this->_setNewPage(null, '', null, $currPos);
                            $y = $this->pdf->getY();
                            $x = $this->pdf->getX();
                            $dy = $this->_getElementY($lh);
                        }
                    }

                    // ligne supl�mentaire. au bout de 1000 : trop long => erreur
                    $nb++;
                    if ($nb>1000) {
                        $txt = ''; foreach ($words as $k => $word) $txt.= ($k ? ' ' : '').$word[0];
                        throw new HTML2PDF_exception(2, array($txt, $right-$left, $w));
                    }

                    list($left, $right) = $this->_getMargins($y);    // marges autorisees
                }
            }

            // si il reste du text apres d�coupe, c'est qu'il rentre direct => on l'affiche
            if (count($words)) {
                $txt = ''; foreach ($words as $k => $word) $txt.= ($k ? ' ' : '').$word[0];
/*
                if ($this->pdf->ws) {
                    $oldSpace = $this->pdf->CurrentFont['cw'][' '];
                    $this->pdf->CurrentFont['cw'][' ']*=(1.+$this->pdf->ws);
                    $w = $this->pdf->GetStringWidth($txt);
                    $this->pdf->CurrentFont['cw'][' '] = $oldSpace;
                }
*/
                $this->pdf->setXY($this->pdf->getX(), $y+$dh+$dy);
                $this->pdf->Cell(($align=='L' ? $w : $this->style->value['width']), $h, $txt, 0, 0, $align, $fill, $this->_isInLink);
                $this->pdf->setXY($this->pdf->getX(), $y);
                $this->_maxH = max($this->_maxH, $lh);
                $this->_maxE+= count($words);
            }

            // d�termination des positions MAX
            $maxX = max($maxX, $this->pdf->getX());
            $maxY = $this->pdf->getY()+$h;

            // position maximale globale
            $this->_maxX = max($this->_maxX, $maxX);
            $this->_maxY = max($this->_maxY, $maxY);

            return true;
        }

        /**
         * tag : BR
         * mode : OPEN
         *
         * @param    array $param
         * @param    integer    position reelle courante si saut de ligne pendant l'ecriture d'un texte
         * @return    null
         */
        protected function _tag_open_BR($param, $curr = null)
        {
            if ($this->_isForOneLine) return false;

            $h = max($this->_maxH, $this->style->getLineHeight());

            // si la ligne est vide, la position maximale n'a pas �t� mise � jour => on la met � jour
            if ($this->_maxH==0) $this->_maxY = max($this->_maxY, $this->pdf->getY()+$h);

            $this->_makeBreakLine($h, $curr);

            $this->_maxH = 0;

            return true;
        }

        /**
         * tag : HR
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_HR($param)
        {
            if ($this->_isForOneLine) return false;
            $oldAlign = $this->style->value['text-align'];
            $this->style->value['text-align'] = 'left';

            if ($this->_maxH) $this->_tag_open_BR($param);

            $fontSize = $this->style->value['font-size'];
            $this->style->value['font-size']=$fontSize*0.5; $this->_tag_open_BR($param);
            $this->style->value['font-size']=0;

            $param['style']['width'] = '100%';

            $this->style->save();
            $this->style->value['height']=$this->style->ConvertToMM('1mm');

            $this->style->analyse('hr', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $h = $this->style->value['height'];
            if ($h)        $h-= $this->style->value['border']['t']['width']+$this->style->value['border']['b']['width'];
            if ($h<=0)    $h = $this->style->value['border']['t']['width']+$this->style->value['border']['b']['width'];

            $this->_drawRectangle($this->pdf->getX(), $this->pdf->getY(), $this->style->value['width'], $h, $this->style->value['border'], 0, 0, $this->style->value['background']);
            $this->_maxH = $h;

            $this->style->load();
            $this->style->FontSet();

            $this->_tag_open_BR($param);

            $this->style->value['font-size']=$fontSize*0.5; $this->_tag_open_BR($param);
            $this->style->value['font-size']=$fontSize;

            $this->style->value['text-align'] = $oldAlign;
            $this->_setNewPositionForNewLine();

            return true;
        }

        /**
         * tag : B
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_B($param, $other = 'b')
        {
            $this->style->save();
            $this->style->value['font-bold'] = true;
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_open_STRONG($param)
        {
            return $this->_tag_open_B($param, 'strong');
        }

        /**
         * tag : B
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_B($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_STRONG($param)
        {
            return $this->_tag_close_B($param);
        }

        /**
         * tag : I
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_I($param, $other = 'i')
        {
            $this->style->save();
            $this->style->value['font-italic'] = true;
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_open_ADDRESS($param)
        {
            return $this->_tag_open_I($param, 'address');
        }
        protected function _tag_open_CITE($param)
        {
            return $this->_tag_open_I($param, 'cite');
        }
        protected function _tag_open_EM($param)
        {
            return $this->_tag_open_I($param, 'em');
        }
        protected function _tag_open_SAMP($param)
        {
            return $this->_tag_open_I($param, 'samp');
        }

        /**
         * tag : I
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_I($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_ADDRESS($param)
        {
            return $this->_tag_close_I($param);
        }
        protected function _tag_close_CITE($param)
        {
            return $this->_tag_close_I($param);
        }
        protected function _tag_close_EM($param)
        {
            return $this->_tag_close_I($param);
        }
        protected function _tag_close_SAMP($param)
        {
            return $this->_tag_close_I($param);
        }

        /**
         * tag : S
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_S($param, $other = 's')
        {
            $this->style->save();
            $this->style->value['font-linethrough'] = true;
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_open_DEL($param)
        {
            return $this->_tag_open_S($param, 'del');
        }

        /**
         * tag : S
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_S($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_DEL($param)
        {
            return $this->_tag_close_S($param);
        }

        /**
         * tag : U
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_U($param, $other='u')
        {
            $this->style->save();
            $this->style->value['font-underline'] = true;
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_open_INS($param)
        {
            return $this->_tag_open_U($param, 'ins');
        }

        /**
         * tag : U
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_U($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_INS($param)
        {
            return $this->_tag_close_U($param);
        }

        /**
         * tag : A
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_A($param)
        {
            $this->_isInLink = str_replace('&amp;', '&', isset($param['href']) ? $param['href'] : '');

            if (isset($param['name'])) {
                $name =     $param['name'];
                if (!isset($this->_lstAnchor[$name])) $this->_lstAnchor[$name] = array($this->pdf->AddLink(), false);

                if (!$this->_lstAnchor[$name][1]) {
                    $this->_lstAnchor[$name][1] = true;
                    $this->pdf->SetLink($this->_lstAnchor[$name][0], -1, -1);
                }
            }

            if (preg_match('/^#([^#]+)$/isU', $this->_isInLink, $match)) {
                $name = $match[1];
                if (!isset($this->_lstAnchor[$name])) $this->_lstAnchor[$name] = array($this->pdf->AddLink(), false);

                $this->_isInLink = $this->_lstAnchor[$name][0];
            }

            $this->style->save();
            $this->style->value['font-underline'] = true;
            $this->style->value['color'] = array(20, 20, 250);
            $this->style->analyse('a', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : A
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_A($param)
        {
            $this->_isInLink    = '';
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : H1
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_H1($param, $other = 'h1')
        {
            if ($this->_isForOneLine) return false;

            if ($this->_maxH) $this->_tag_open_BR(array());
            $this->style->save();
            $this->style->value['font-bold'] = true;

            $size = array('h1' => '28px', 'h2' => '24px', 'h3' => '20px', 'h4' => '16px', 'h5' => '12px', 'h6' => '9px');
            $this->style->value['margin']['l'] = 0;
            $this->style->value['margin']['r'] = 0;
            $this->style->value['margin']['t'] = $this->style->ConvertToMM('16px');
            $this->style->value['margin']['b'] = $this->style->ConvertToMM('16px');
            $this->style->value['font-size'] = $this->style->ConvertToMM($size[$other]);

            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();
            $this->_setNewPositionForNewLine();

            return true;
        }
        protected function _tag_open_H2($param)
        {
            return $this->_tag_open_H1($param, 'h2');
        }
        protected function _tag_open_H3($param)
        {
            return $this->_tag_open_H1($param, 'h3');
        }
        protected function _tag_open_H4($param)
        {
            return $this->_tag_open_H1($param, 'h4');
        }
        protected function _tag_open_H5($param)
        {
            return $this->_tag_open_H1($param, 'h5');
        }
        protected function _tag_open_H6($param)
        {
            return $this->_tag_open_H1($param, 'h6');
        }


        /**
         * tag : H1
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_H1($param)
        {
            if ($this->_isForOneLine) return false;

            // hauteur du H1
            $this->_maxH+= $this->style->value['margin']['b'];
            $h = max($this->_maxH, $this->style->getLineHeight());

            $this->style->load();
            $this->style->FontSet();

            // saut de ligne et initialisation de la hauteur
            $this->_makeBreakLine($h);
            $this->_maxH = 0;

            return true;
        }
        protected function _tag_close_H2($param)
        {
            return $this->_tag_close_H1($param);
        }
        protected function _tag_close_H3($param)
        {
            return $this->_tag_close_H1($param);
        }
        protected function _tag_close_H4($param)
        {
            return $this->_tag_close_H1($param);
        }
        protected function _tag_close_H5($param)
        {
            return $this->_tag_close_H1($param);
        }
        protected function _tag_close_H6($param)
        {
            return $this->_tag_close_H1($param);
        }

        /**
         * tag : SPAN
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_SPAN($param, $other = 'span')
        {
            $this->style->save();
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_open_FONT($param)
        {
            return $this->_tag_open_SPAN($param, 'font');
        }

        protected function _tag_open_LABEL($param)
        {
            return $this->_tag_open_SPAN($param, 'label');
        }

        /**
         * tag : SPAN
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_SPAN($param)
        {
            $this->style->restorePosition();
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_FONT($param)
        {
            return $this->_tag_close_SPAN($param);
        }
        protected function _tag_close_LABEL($param)
        {
            return $this->_tag_close_SPAN($param);
        }


        /**
         * tag : P
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_P($param)
        {
            if ($this->_isForOneLine) return false;

            if (!in_array($this->_previousCall, array('_tag_close_P', '_tag_close_UL'))) {
                if ($this->_maxH) $this->_tag_open_BR(array());
            }

            $this->style->save();
            $this->style->analyse('p', $param);
            $this->style->setPosition();
            $this->style->FontSet();
             // annule les effets du setposition
            $this->pdf->setXY($this->pdf->getX()-$this->style->value['margin']['l'], $this->pdf->getY()-$this->style->value['margin']['t']);

            list($mL, $mR) = $this->_getMargins($this->pdf->getY());
            $mR = $this->pdf->getW()-$mR;
            $mL+= $this->style->value['margin']['l']+$this->style->value['padding']['l'];
            $mR+= $this->style->value['margin']['r']+$this->style->value['padding']['r'];
            $this->_saveMargin($mL, 0, $mR);

            if ($this->style->value['text-indent']>0) {
                $y = $this->pdf->getY()+$this->style->value['margin']['t']+$this->style->value['padding']['t'];
                $this->_pageMarges[floor($y*100)] = array($mL+$this->style->value['text-indent'], $this->pdf->getW()-$mR);
                $y+= $this->style->getLineHeight()*0.1;
                $this->_pageMarges[floor($y*100)] = array($mL, $this->pdf->getW()-$mR);
            }
            $this->_makeBreakLine($this->style->value['margin']['t']+$this->style->value['padding']['t']);
            return true;
        }

        /**
         * tag : P
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_P($param)
        {
            if ($this->_isForOneLine) return false;

            if ($this->_maxH) $this->_tag_open_BR(array());
            $this->_loadMargin();
            $h = $this->style->value['margin']['b']+$this->style->value['padding']['b'];

            $this->style->load();
            $this->style->FontSet();
            $this->_makeBreakLine($h);

            return true;
        }

        /**
         * tag : PRE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_PRE($param, $other = 'pre')
        {
            if ($other=='pre' && $this->_maxH) $this->_tag_open_BR(array());

            $this->style->save();
            $this->style->value['font-family']    = 'courier';
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            if ($other=='pre') return $this->_tag_open_DIV($param, $other);

            return true;
        }
        protected function _tag_open_CODE($param)
        {
            return $this->_tag_open_PRE($param, 'code');
        }

        /**
         * tag : PRE
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_PRE($param, $other = 'pre')
        {
            if ($other=='pre') {
                if ($this->_isForOneLine) return false;

                $this->_tag_close_DIV($param);
                $this->_tag_open_BR(array());
            }
            $this->style->load();
            $this->style->FontSet();

            return true;
        }
        protected function _tag_close_CODE($param)
        {
            return $this->_tag_close_PRE($param, 'code');
        }

        /**
         * tag : BIG
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_BIG($param)
        {
            $this->style->save();
            $this->style->value['mini-decal']-= $this->style->value['mini-size']*0.12;
            $this->style->value['mini-size'] *= 1.2;
            $this->style->analyse('big', $param);
            $this->style->setPosition();
            $this->style->FontSet();
            return true;
        }

        /**
         * tag : BIG
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_BIG($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : SMALL
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_SMALL($param)
        {
            $this->style->save();
            $this->style->value['mini-decal']+= $this->style->value['mini-size']*0.05;
            $this->style->value['mini-size'] *= 0.82;
            $this->style->analyse('small', $param);
            $this->style->setPosition();
            $this->style->FontSet();
            return true;
        }

        /**
         * tag : SMALL
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_SMALL($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }


        /**
         * tag : SUP
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_SUP($param)
        {
            $this->style->save();
            $this->style->value['mini-decal']-= $this->style->value['mini-size']*0.15;
            $this->style->value['mini-size'] *= 0.75;
            $this->style->analyse('sup', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : SUP
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_SUP($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : SUB
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_SUB($param)
        {
            $this->style->save();
            $this->style->value['mini-decal']+= $this->style->value['mini-size']*0.15;
            $this->style->value['mini-size'] *= 0.75;
            $this->style->analyse('sub', $param);
            $this->style->setPosition();
            $this->style->FontSet();
            return true;
        }

        /**
         * tag : SUB
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_SUB($param)
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : UL
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_UL($param, $other = 'ul')
        {
            if ($this->_isForOneLine) return false;

            if (!in_array($this->_previousCall, array('_tag_close_P', '_tag_close_UL'))) {
                if ($this->_maxH) $this->_tag_open_BR(array());
                if (!count($this->_defList)) $this->_tag_open_BR(array());
            }

            if (!isset($param['style']['width'])) $param['allwidth'] = true;
            $param['cellspacing'] = 0;

            // une liste est trait�e comme un tableau
            $this->_tag_open_TABLE($param, $other);

            // ajouter un niveau de liste
            $this->_listeAddLevel($other, $this->style->value['list-style-type'], $this->style->value['list-style-image']);

            return true;
        }
        protected function _tag_open_OL($param)
        {
            return $this->_tag_open_UL($param, 'ol');
        }

        /**
         * tag : UL
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_UL($param)
        {
            if ($this->_isForOneLine) return false;

            // fin du tableau
            $this->_tag_close_TABLE($param);

            // enlever un niveau de liste
            $this->_listeDelLevel();

            if (!$this->_subPart) {
                if (!count($this->_defList)) $this->_tag_open_BR(array());
            }

            return true;
        }
        protected function _tag_close_OL($param)
        {
            return $this->_tag_close_UL($param);
        }

        /**
         * tag : LI
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_LI($param)
        {
            if ($this->_isForOneLine) return false;

            // ajouter une puce au niveau actuel
            $this->_listeAddLi();

            if (!isset($param['style']['width'])) $param['style']['width'] = '100%';

            // preparation du style de la puce
            $paramPUCE = $param;

            $inf = $this->_listeGetLi();
            if ($inf[0]) {
                $paramPUCE['style']['font-family']        = $inf[0];
                $paramPUCE['style']['text-align']        = 'li_right';
                $paramPUCE['style']['vertical-align']    = 'top';
                $paramPUCE['style']['width']            = $this->_listeGetWidth();
                $paramPUCE['style']['padding-right']    = $this->_listeGetPadding();
                $paramPUCE['txt'] = $inf[2];
            } else {
                $paramPUCE['style']['text-align']        = 'li_right';
                $paramPUCE['style']['vertical-align']    = 'top';
                $paramPUCE['style']['width']            = $this->_listeGetWidth();
                $paramPUCE['style']['padding-right']    = $this->_listeGetPadding();
                $paramPUCE['src'] = $inf[2];
                $paramPUCE['sub_li'] = true;
            }

            // nouvelle ligne
            $this->_tag_open_TR($param, 'li');

            $this->style->save();

            // small
            if ($inf[1]) {
                $this->style->value['mini-decal']+= $this->style->value['mini-size']*0.045;
                $this->style->value['mini-size'] *= 0.75;
            }

            // si on est dans un sub_html => preparation, sinon affichage classique
            if ($this->_subPart) {
                // TD pour la puce
                $tmpPos = $this->_tempPos;
                $tmpLst1 = $this->parsing->code[$tmpPos+1];
                $tmpLst2 = $this->parsing->code[$tmpPos+2];
                $this->parsing->code[$tmpPos+1] = array();
                $this->parsing->code[$tmpPos+1]['name']    = (isset($paramPUCE['src'])) ? 'img' : 'write';
                $this->parsing->code[$tmpPos+1]['param']    = $paramPUCE; unset($this->parsing->code[$tmpPos+1]['param']['style']['width']);
                $this->parsing->code[$tmpPos+1]['close']    = 0;
                $this->parsing->code[$tmpPos+2] = array();
                $this->parsing->code[$tmpPos+2]['name']    = 'li';
                $this->parsing->code[$tmpPos+2]['param']    = $paramPUCE;
                $this->parsing->code[$tmpPos+2]['close']    = 1;
                $this->_tag_open_TD($paramPUCE, 'li_sub');
                $this->_tag_close_TD($param);
                $this->_tempPos = $tmpPos;
                $this->parsing->code[$tmpPos+1] = $tmpLst1;
                $this->parsing->code[$tmpPos+2] = $tmpLst2;
            } else {
                // TD pour la puce
                $this->_tag_open_TD($paramPUCE, 'li_sub');
                unset($paramPUCE['style']['width']);
                if (isset($paramPUCE['src']))    $this->_tag_open_IMG($paramPUCE);
                else                            $this->_tag_open_WRITE($paramPUCE);
                $this->_tag_close_TD($paramPUCE);
            }
            $this->style->load();


            // td pour le contenu
            $this->_tag_open_TD($param, 'li');

            return true;
        }

        /**
         * tag : LI
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_LI($param)
        {
            if ($this->_isForOneLine) return false;

            // fin du contenu
            $this->_tag_close_TD($param);

            // fin de la ligne
            $this->_tag_close_TR($param);

            return true;
        }

        /**
         * tag : TBODY
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TBODY($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->save();
            $this->style->analyse('tbody', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : TBODY
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TBODY($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : THEAD
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_THEAD($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->save();
            $this->style->analyse('thead', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            // si on est en mode sub_html : sauvegarde du num�ro du TR
            if ($this->_subPart) {
                HTML2PDF::$_tables[$param['num']]['thead']['tr'][0] = HTML2PDF::$_tables[$param['num']]['tr_curr'];
                HTML2PDF::$_tables[$param['num']]['thead']['code'] = array();
                for ($pos=$this->_tempPos; $pos<count($this->parsing->code); $pos++) {
                    $action = $this->parsing->code[$pos];
                    if (strtolower($action['name'])=='thead') $action['name'] = 'thead_sub';
                    HTML2PDF::$_tables[$param['num']]['thead']['code'][] = $action;
                    if (strtolower($action['name'])=='thead_sub' && $action['close']) break;
                }
            } else {
                $level = $this->parsing->getLevel($this->_parsePos);
                $this->_parsePos+= count($level);
                HTML2PDF::$_tables[$param['num']]['tr_curr']+= count(HTML2PDF::$_tables[$param['num']]['thead']['tr']);
            }

            return true;
        }

        /**
         * tag : THEAD
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_THEAD($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();
            $this->style->FontSet();

            // si on est en mode sub_html : sauvegarde du num�ro du TR
            if ($this->_subPart) {
                $min = HTML2PDF::$_tables[$param['num']]['thead']['tr'][0];
                $max = HTML2PDF::$_tables[$param['num']]['tr_curr']-1;
                HTML2PDF::$_tables[$param['num']]['thead']['tr'] = range($min, $max);
            }

            return true;
        }

        /**
         * tag : TFOOT
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TFOOT($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->save();
            $this->style->analyse('tfoot', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            // si on est en mode sub_html : sauvegarde du num�ro du TR
            if ($this->_subPart) {
                HTML2PDF::$_tables[$param['num']]['tfoot']['tr'][0] = HTML2PDF::$_tables[$param['num']]['tr_curr'];
                HTML2PDF::$_tables[$param['num']]['tfoot']['code'] = array();
                for ($pos=$this->_tempPos; $pos<count($this->parsing->code); $pos++) {
                    $action = $this->parsing->code[$pos];
                    if (strtolower($action['name'])=='tfoot') $action['name'] = 'tfoot_sub';
                    HTML2PDF::$_tables[$param['num']]['tfoot']['code'][] = $action;
                    if (strtolower($action['name'])=='tfoot_sub' && $action['close']) break;
                }
            } else {
                $level = $this->parsing->getLevel($this->_parsePos);
                $this->_parsePos+= count($level);
                HTML2PDF::$_tables[$param['num']]['tr_curr']+= count(HTML2PDF::$_tables[$param['num']]['tfoot']['tr']);
            }

            return true;
        }

        /**
         * tag : TFOOT
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TFOOT($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();
            $this->style->FontSet();

            // si on est en mode sub_html : sauvegarde du num�ro du TR
            if ($this->_subPart) {
                $min = HTML2PDF::$_tables[$param['num']]['tfoot']['tr'][0];
                $max = HTML2PDF::$_tables[$param['num']]['tr_curr']-1;
                HTML2PDF::$_tables[$param['num']]['tfoot']['tr'] = range($min, $max);
            }

            return true;
        }

        /**
         * tag : THEAD_SUB
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_THEAD_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->save();
            $this->style->analyse('thead', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : THEAD_SUB
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_THEAD_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : TFOOT_SUB
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TFOOT_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->save();
            $this->style->analyse('tfoot', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : TFOOT_SUB
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TFOOT_SUB($param)
        {
            if ($this->_isForOneLine) return false;

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : FORM
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_FORM($param)
        {
            $this->style->save();
            $this->style->analyse('form', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $this->pdf->setFormDefaultProp(
                array(
                    'lineWidth'=>1,
                    'borderStyle'=>'solid',
                    'fillColor'=>array(220, 220, 255),
                    'strokeColor'=>array(128, 128, 200)
                )
            );

            $this->_isInForm = isset($param['action']) ? $param['action'] : '';

            return true;
        }

        /**
         * tag : FORM
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_FORM($param)
        {
            $this->_isInForm = false;
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : TABLE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TABLE($param, $other = 'table')
        {
            if ($this->_maxH) {
                if ($this->_isForOneLine) return false;
                $this->_tag_open_BR(array());
            }

            if ($this->_isForOneLine) {
                $this->_maxE++;
                $this->_maxX = $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();
                return false;
            }

            $this->_maxH = 0;

            $alignObject = isset($param['align']) ? strtolower($param['align']) : 'left';
            if (isset($param['align'])) unset($param['align']);
            if (!in_array($alignObject, array('left', 'center', 'right'))) $alignObject = 'left';

            // lecture et initialisation du style
            $this->style->save();
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            if ($this->style->value['margin-auto']) $alignObject = 'center';

            // est-on en collapse
            $collapse = false;
            if ($other=='table')
                $collapse = isset($this->style->value['border']['collapse']) ? $this->style->value['border']['collapse'] : false;

            // si oui il faut adapt� les borders
            if ($collapse) {
                $param['style']['border'] = 'none';
                $param['cellspacing'] = 0;
                $none = $this->style->readBorder('none');
                $this->style->value['border']['t'] = $none;
                $this->style->value['border']['r'] = $none;
                $this->style->value['border']['b'] = $none;
                $this->style->value['border']['l'] = $none;
            }

            // si on est en mode sub_html : initialisation des dimensions et autres
            if ($this->_subPart) {
                if ($this->_debugActif) $this->_DEBUG_add('Table n�'.$param['num'], true);
                HTML2PDF::$_tables[$param['num']] = array();
                HTML2PDF::$_tables[$param['num']]['border']        = isset($param['border']) ? $this->style->readBorder($param['border']) : null; // border sp�cifique si border precis� en param�tre
                HTML2PDF::$_tables[$param['num']]['cellpadding']    = $this->style->ConvertToMM(isset($param['cellpadding']) ? $param['cellpadding'] : '1px'); // cellpadding du tableau
                HTML2PDF::$_tables[$param['num']]['cellspacing']    = $this->style->ConvertToMM(isset($param['cellspacing']) ? $param['cellspacing'] : '2px'); // cellspacing du tableau
                HTML2PDF::$_tables[$param['num']]['cases']        = array();                // liste des propri�t�s des cases
                HTML2PDF::$_tables[$param['num']]['corr']        = array();                // tableau de correlation pour les colspan et rowspan
                HTML2PDF::$_tables[$param['num']]['corr_x']        = 0;                    // position dans le tableau de correlation
                HTML2PDF::$_tables[$param['num']]['corr_y']        = 0;                    // position dans le tableau de correlation
                HTML2PDF::$_tables[$param['num']]['td_curr']        = 0;                    // colonne courante
                HTML2PDF::$_tables[$param['num']]['tr_curr']        = 0;                    // ligne courante
                HTML2PDF::$_tables[$param['num']]['curr_x']        = $this->pdf->getX();    // position courante X
                HTML2PDF::$_tables[$param['num']]['curr_y']        = $this->pdf->getY();    // position courante Y
                HTML2PDF::$_tables[$param['num']]['width']        = 0;                    // largeur globale
                HTML2PDF::$_tables[$param['num']]['height']        = 0;                    // hauteur globale
                HTML2PDF::$_tables[$param['num']]['align']        = $alignObject;
                HTML2PDF::$_tables[$param['num']]['marge']        = array();
                HTML2PDF::$_tables[$param['num']]['marge']['t']    = $this->style->value['padding']['t']+$this->style->value['border']['t']['width']+HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5;
                HTML2PDF::$_tables[$param['num']]['marge']['r']    = $this->style->value['padding']['r']+$this->style->value['border']['r']['width']+HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5;
                HTML2PDF::$_tables[$param['num']]['marge']['b']    = $this->style->value['padding']['b']+$this->style->value['border']['b']['width']+HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5;
                HTML2PDF::$_tables[$param['num']]['marge']['l']    = $this->style->value['padding']['l']+$this->style->value['border']['l']['width']+HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5;
                HTML2PDF::$_tables[$param['num']]['page']        = 0;                    // nombre de pages
                HTML2PDF::$_tables[$param['num']]['new_page']    = true;                    // nouvelle page pour le TR courant
                HTML2PDF::$_tables[$param['num']]['style_value'] = null;                    // style du tableau
                HTML2PDF::$_tables[$param['num']]['thead']        = array();                // infos sur le thead
                HTML2PDF::$_tables[$param['num']]['tfoot']        = array();                // infos sur le tfoot
                HTML2PDF::$_tables[$param['num']]['thead']['tr']    = array();                // tr du thead
                HTML2PDF::$_tables[$param['num']]['tfoot']['tr']    = array();                // tr du tfoot
                HTML2PDF::$_tables[$param['num']]['thead']['height']    = 0;                // hauteur du thead
                HTML2PDF::$_tables[$param['num']]['tfoot']['height']    = 0;                // hauteur du tfoot
                HTML2PDF::$_tables[$param['num']]['thead']['code'] = array();            // contenu HTML du thead
                HTML2PDF::$_tables[$param['num']]['tfoot']['code'] = array();            // contenu HTML du tfoot
                HTML2PDF::$_tables[$param['num']]['cols']        = array();                // definition via les tags col
                $this->_saveMargin($this->pdf->getlMargin(), $this->pdf->gettMargin(), $this->pdf->getrMargin());

                // adaptation de la largeur en fonction des marges du tableau
                $this->style->value['width']-= HTML2PDF::$_tables[$param['num']]['marge']['l'] + HTML2PDF::$_tables[$param['num']]['marge']['r'];
            } else {
                // on repart � la premiere page du tableau et � la premiere case
                HTML2PDF::$_tables[$param['num']]['page'] = 0;
                HTML2PDF::$_tables[$param['num']]['td_curr']    = 0;
                HTML2PDF::$_tables[$param['num']]['tr_curr']    = 0;
                HTML2PDF::$_tables[$param['num']]['td_x']        = HTML2PDF::$_tables[$param['num']]['marge']['l']+HTML2PDF::$_tables[$param['num']]['curr_x'];
                HTML2PDF::$_tables[$param['num']]['td_y']        = HTML2PDF::$_tables[$param['num']]['marge']['t']+HTML2PDF::$_tables[$param['num']]['curr_y'];

                // initialisation du style des bordures de la premiere partie de tableau
                $this->_drawRectangle(
                    HTML2PDF::$_tables[$param['num']]['curr_x'],
                    HTML2PDF::$_tables[$param['num']]['curr_y'],
                    HTML2PDF::$_tables[$param['num']]['width'],
                    isset(HTML2PDF::$_tables[$param['num']]['height'][0]) ? HTML2PDF::$_tables[$param['num']]['height'][0] : null,
                    $this->style->value['border'],
                    $this->style->value['padding'],
                    0,
                    $this->style->value['background']
                );

                HTML2PDF::$_tables[$param['num']]['style_value'] = $this->style->value;
            }

            return true;
        }

        /**
         * tag : TABLE
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TABLE($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            // restauration du style
            $this->style->load();
            $this->style->FontSet();

            // si on est en mode sub_html : initialisation des dimensions et autres
            if ($this->_subPart) {
                // ajustement de la taille des cases
                $this->calculTailleCases(HTML2PDF::$_tables[$param['num']]['cases'], HTML2PDF::$_tables[$param['num']]['corr']);

                // calcul de la hauteur du THEAD et du TFOOT
                $lst = array('thead', 'tfoot');
                foreach ($lst as $mode) {
                    HTML2PDF::$_tables[$param['num']][$mode]['height'] = 0;
                    foreach (HTML2PDF::$_tables[$param['num']][$mode]['tr'] as $tr) {
                        // hauteur de la ligne tr
                        $h = 0;
                        for ($i=0; $i<count(HTML2PDF::$_tables[$param['num']]['cases'][$tr]); $i++)
                            if (HTML2PDF::$_tables[$param['num']]['cases'][$tr][$i]['rowspan']==1)
                                $h = max($h, HTML2PDF::$_tables[$param['num']]['cases'][$tr][$i]['h']);
                        HTML2PDF::$_tables[$param['num']][$mode]['height']+= $h;
                    }
                }

                // calcul des dimensions du tableau - Largeur
                HTML2PDF::$_tables[$param['num']]['width'] = HTML2PDF::$_tables[$param['num']]['marge']['l'] + HTML2PDF::$_tables[$param['num']]['marge']['r'];
                if (isset(HTML2PDF::$_tables[$param['num']]['cases'][0]))
                    foreach (HTML2PDF::$_tables[$param['num']]['cases'][0] as $case)
                        HTML2PDF::$_tables[$param['num']]['width']+= $case['w'];

                // positionnement du tableau horizontalement;
                $old = $this->style->getOldValues();
                $parentWidth = $old['width'] ? $old['width'] : $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();
                $x = HTML2PDF::$_tables[$param['num']]['curr_x'];
                $w = HTML2PDF::$_tables[$param['num']]['width'];
                if ($parentWidth>$w) {
                    if (HTML2PDF::$_tables[$param['num']]['align']=='center')
                        $x = $x + ($parentWidth-$w)*0.5;
                    else if (HTML2PDF::$_tables[$param['num']]['align']=='right')
                        $x = $x + $parentWidth-$w;

                    HTML2PDF::$_tables[$param['num']]['curr_x'] = $x;
                }


                // calcul des dimensions du tableau - hauteur du tableau sur chaque page
                HTML2PDF::$_tables[$param['num']]['height'] = array();

                $h0 = HTML2PDF::$_tables[$param['num']]['marge']['t'] + HTML2PDF::$_tables[$param['num']]['marge']['b'];    // minimum de hauteur � cause des marges
                $h0+= HTML2PDF::$_tables[$param['num']]['thead']['height'] + HTML2PDF::$_tables[$param['num']]['tfoot']['height']; // et du tfoot et thead
                $max = $this->pdf->getH() - $this->pdf->getbMargin();            // max de hauteur par page
                $y = HTML2PDF::$_tables[$param['num']]['curr_y'];    // position Y actuelle
                $height = $h0;

                // on va lire les hauteurs de chaque ligne, une � une, et voir si ca rentre sur la page.
                for ($k=0; $k<count(HTML2PDF::$_tables[$param['num']]['cases']); $k++) {
                    // si c'est des lignes du thead ou du tfoot : on passe
                    if (in_array($k, HTML2PDF::$_tables[$param['num']]['thead']['tr'])) continue;
                    if (in_array($k, HTML2PDF::$_tables[$param['num']]['tfoot']['tr'])) continue;

                    // hauteur de la ligne $k
                    $th = 0;
                    $h = 0;
                    for ($i=0; $i<count(HTML2PDF::$_tables[$param['num']]['cases'][$k]); $i++) {
                        $h = max($h, HTML2PDF::$_tables[$param['num']]['cases'][$k][$i]['h']);

                        if (HTML2PDF::$_tables[$param['num']]['cases'][$k][$i]['rowspan']==1)
                            $th = max($th, HTML2PDF::$_tables[$param['num']]['cases'][$k][$i]['h']);
                    }

                    // si la ligne ne rentre pas dans la page
                    // => la hauteur sur cette page est trouv�e, et on passe � la page d'apres
                    if ($y+$h+$height>$max) {
                        if ($height==$h0) $height = null;
                        HTML2PDF::$_tables[$param['num']]['height'][] = $height;
                        $height = $h0;
                        $y = $this->_margeTop;
                    }
                    $height+= $th;
                }
                // rajout du reste de tableau (si il existe) � la derniere page
                if ($height!=$h0 || $k==0) HTML2PDF::$_tables[$param['num']]['height'][] = $height;
            } else {
                if (count(HTML2PDF::$_tables[$param['num']]['tfoot']['code'])) {
                    $tmpTR = HTML2PDF::$_tables[$param['num']]['tr_curr'];
                    $tmpTD = HTML2PDF::$_tables[$param['num']]['td_curr'];
                    $oldParsePos = $this->_parsePos;
                    $oldParseCode = $this->parsing->code;

                    HTML2PDF::$_tables[$param['num']]['tr_curr'] = HTML2PDF::$_tables[$param['num']]['tfoot']['tr'][0];
                    HTML2PDF::$_tables[$param['num']]['td_curr'] = 0;
                    $this->_parsePos = 0;
                    $this->parsing->code = HTML2PDF::$_tables[$param['num']]['tfoot']['code'];
                    $this->_makeHTMLcode();

                    $this->_parsePos =     $oldParsePos;
                    $this->parsing->code = $oldParseCode;
                    HTML2PDF::$_tables[$param['num']]['tr_curr'] = $tmpTR;
                    HTML2PDF::$_tables[$param['num']]['td_curr'] = $tmpTD;
                }

                // determination des coordonn�es de sortie du tableau
                $x = HTML2PDF::$_tables[$param['num']]['curr_x'] + HTML2PDF::$_tables[$param['num']]['width'];
                if (count(HTML2PDF::$_tables[$param['num']]['height'])>1)
                    $y = $this->_margeTop+HTML2PDF::$_tables[$param['num']]['height'][count(HTML2PDF::$_tables[$param['num']]['height'])-1];
                else if (count(HTML2PDF::$_tables[$param['num']]['height'])==1)
                    $y = HTML2PDF::$_tables[$param['num']]['curr_y']+HTML2PDF::$_tables[$param['num']]['height'][count(HTML2PDF::$_tables[$param['num']]['height'])-1];
                else
                    $y = HTML2PDF::$_tables[$param['num']]['curr_y'];

                // taille du tableau
                $this->_maxX = max($this->_maxX, $x);
                $this->_maxY = max($this->_maxY, $y);


                // nouvelle position apres le tableau
                $this->pdf->setXY($this->pdf->getlMargin(), $y);

                // restauration des marges
                $this->_loadMargin();

                if ($this->_debugActif) $this->_DEBUG_add('Table n�'.$param['num'], false);
            }

            return true;
        }


        /**
         * tag : COL
         * mode : OPEN (pas de CLOSE)
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_COL($param)
        {
            $span = isset($param['span']) ? $param['span'] : 1;
            for ($k=0; $k<$span; $k++)
                HTML2PDF::$_tables[$param['num']]['cols'][] = $param;
        }

        /**
         * tag : TR
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TR($param, $other = 'tr')
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            // analyse du style
            $this->style->save();
            $this->style->analyse($other, $param);
            $this->style->setPosition();
            $this->style->FontSet();

            // positionnement dans le tableau
            HTML2PDF::$_tables[$param['num']]['tr_curr']++;
            HTML2PDF::$_tables[$param['num']]['td_curr']= 0;

            // si on est pas dans un sub_html
            if (!$this->_subPart) {
                // Y courant apres la ligne
                $ty=null;
                for ($ii=0; $ii<count(HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1]); $ii++)
                    $ty = max($ty, HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][$ii]['h']);

                $hfoot = HTML2PDF::$_tables[$param['num']]['tfoot']['height'];

                // si la ligne ne rentre pas dans la page => nouvelle page
                if (!$this->_isInTfoot && HTML2PDF::$_tables[$param['num']]['td_y'] + HTML2PDF::$_tables[$param['num']]['marge']['b'] + $ty +$hfoot> $this->pdf->getH() - $this->pdf->getbMargin()) {
                    if (count(HTML2PDF::$_tables[$param['num']]['tfoot']['code'])) {
                        $tmpTR = HTML2PDF::$_tables[$param['num']]['tr_curr'];
                        $tmpTD = HTML2PDF::$_tables[$param['num']]['td_curr'];
                        $oldParsePos = $this->_parsePos;
                        $oldParseCode = $this->parsing->code;

                        HTML2PDF::$_tables[$param['num']]['tr_curr'] = HTML2PDF::$_tables[$param['num']]['tfoot']['tr'][0];
                        HTML2PDF::$_tables[$param['num']]['td_curr'] = 0;
                        $this->_parsePos = 0;
                        $this->parsing->code = HTML2PDF::$_tables[$param['num']]['tfoot']['code'];
                        $this->_isInTfoot = true;
                        $this->_makeHTMLcode();
                        $this->_isInTfoot = false;

                        $this->_parsePos =     $oldParsePos;
                        $this->parsing->code = $oldParseCode;
                        HTML2PDF::$_tables[$param['num']]['tr_curr'] = $tmpTR;
                        HTML2PDF::$_tables[$param['num']]['td_curr'] = $tmpTD;
                    }

                    HTML2PDF::$_tables[$param['num']]['new_page'] = true;
                    $this->_setNewPage();

                    HTML2PDF::$_tables[$param['num']]['page']++;
                    HTML2PDF::$_tables[$param['num']]['curr_y'] = $this->pdf->getY();
                    HTML2PDF::$_tables[$param['num']]['td_y'] = HTML2PDF::$_tables[$param['num']]['curr_y']+HTML2PDF::$_tables[$param['num']]['marge']['t'];

                    // si la hauteur de cette partie a bien �t� calcul�e, on trace le cadre
                    if (isset(HTML2PDF::$_tables[$param['num']]['height'][HTML2PDF::$_tables[$param['num']]['page']])) {
                        $old = $this->style->value;
                        $this->style->value = HTML2PDF::$_tables[$param['num']]['style_value'];

                        // initialisation du style des bordures de la premiere partie de tableau
                        $this->_drawRectangle(
                            HTML2PDF::$_tables[$param['num']]['curr_x'],
                            HTML2PDF::$_tables[$param['num']]['curr_y'],
                            HTML2PDF::$_tables[$param['num']]['width'],
                            HTML2PDF::$_tables[$param['num']]['height'][HTML2PDF::$_tables[$param['num']]['page']],
                            $this->style->value['border'],
                            $this->style->value['padding'],
                            HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5,
                            $this->style->value['background']
                        );

                        $this->style->value = $old;
                    }
                }

                if (HTML2PDF::$_tables[$param['num']]['new_page'] && count(HTML2PDF::$_tables[$param['num']]['thead']['code'])) {
                    HTML2PDF::$_tables[$param['num']]['new_page'] = false;
                    $tmpTR = HTML2PDF::$_tables[$param['num']]['tr_curr'];
                    $tmpTD = HTML2PDF::$_tables[$param['num']]['td_curr'];
                    $oldParsePos = $this->_parsePos;
                    $oldParseCode = $this->parsing->code;

                    HTML2PDF::$_tables[$param['num']]['tr_curr'] = HTML2PDF::$_tables[$param['num']]['thead']['tr'][0];
                    HTML2PDF::$_tables[$param['num']]['td_curr'] = 0;
                    $this->_parsePos = 0;
                    $this->parsing->code = HTML2PDF::$_tables[$param['num']]['thead']['code'];
                    $this->_isInThead = true;
                    $this->_makeHTMLcode();
                    $this->_isInThead = false;

                    $this->_parsePos =     $oldParsePos;
                    $this->parsing->code = $oldParseCode;
                    HTML2PDF::$_tables[$param['num']]['tr_curr'] = $tmpTR;
                    HTML2PDF::$_tables[$param['num']]['td_curr'] = $tmpTD;
                    HTML2PDF::$_tables[$param['num']]['new_page'] = true;
                }
            } else {
                HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1] = array();
                if (!isset(HTML2PDF::$_tables[$param['num']]['corr'][HTML2PDF::$_tables[$param['num']]['corr_y']]))
                    HTML2PDF::$_tables[$param['num']]['corr'][HTML2PDF::$_tables[$param['num']]['corr_y']] = array();

                HTML2PDF::$_tables[$param['num']]['corr_x']=0;
                while(isset(HTML2PDF::$_tables[$param['num']]['corr'][HTML2PDF::$_tables[$param['num']]['corr_y']][HTML2PDF::$_tables[$param['num']]['corr_x']]))
                    HTML2PDF::$_tables[$param['num']]['corr_x']++;
            }

            return true;
        }

        /**
         * tag : TR
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TR($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            // restauration du style
            $this->style->load();
            $this->style->FontSet();

            // si on est pas dans un sub_html
            if (!$this->_subPart) {
                // Y courant apres la ligne
                $ty=null;
                for ($ii=0; $ii<count(HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1]); $ii++) {
                    if (HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][$ii]['rowspan']==1) {
                        $ty = HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][$ii]['h'];
                    }
                }

                // mise � jour des coordonn�es courantes
                HTML2PDF::$_tables[$param['num']]['td_x'] = HTML2PDF::$_tables[$param['num']]['curr_x']+HTML2PDF::$_tables[$param['num']]['marge']['l'];
                HTML2PDF::$_tables[$param['num']]['td_y']+= $ty;
                HTML2PDF::$_tables[$param['num']]['new_page'] = false;
            } else {
                HTML2PDF::$_tables[$param['num']]['corr_y']++;
            }

            return true;
        }

        /**
         * tag : TD
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TD($param, $other = 'td')
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            $param['cellpadding'] = HTML2PDF::$_tables[$param['num']]['cellpadding'].'mm';
            $param['cellspacing'] = HTML2PDF::$_tables[$param['num']]['cellspacing'].'mm';

            if ($other=='li') {
                $specialLi = true;
            } else {
                $specialLi = false;
                if ($other=='li_sub') {
                    $param['style']['border'] = 'none';
                    $param['style']['background-color']        = 'transparent';
                    $param['style']['background-image']        = 'none';
                    $param['style']['background-position']    = '';
                    $param['style']['background-repeat']    = '';
                    $other = 'li';
                }
            }

            // est-on en collapse, et egalement y-a-t'il des definitions de colonne
            $x = HTML2PDF::$_tables[$param['num']]['td_curr'];
            $y = HTML2PDF::$_tables[$param['num']]['tr_curr']-1;
            $colspan = isset($param['colspan']) ? $param['colspan'] : 1;
            $rowspan = isset($param['rowspan']) ? $param['rowspan'] : 1;
            $collapse = false;
            if (in_array($other, array('td', 'th'))) {
                $numCol = isset(HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['Xr']) ? HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['Xr'] : HTML2PDF::$_tables[$param['num']]['corr_x'];

                // si une definition de colonne est presente
                if (isset(HTML2PDF::$_tables[$param['num']]['cols'][$numCol])) {
                    // on la recupere
                    $colParam = HTML2PDF::$_tables[$param['num']]['cols'][$numCol];

                    // pour les colspan, on recupere toutes les largeurs
                    $colParam['style']['width'] = array();
                    for ($k=0; $k<$colspan; $k++) {
                        if (isset(HTML2PDF::$_tables[$param['num']]['cols'][$numCol+$k]['style']['width'])) {
                            $colParam['style']['width'][] = HTML2PDF::$_tables[$param['num']]['cols'][$numCol+$k]['style']['width'];
                        }
                    }

                    // on les somme
                    $total = '';
                    $last = $this->style->getLastWidth();
                    if (count($colParam['style']['width'])) {
                        $total = $colParam['style']['width'][0]; unset($colParam['style']['width'][0]);
                        foreach ($colParam['style']['width'] as $width) {
                            if (substr($total, -1)=='%' && substr($width, -1)=='%')
                                $total = (str_replace('%', '', $total)+str_replace('%', '', $width)).'%';
                            else
                                $total = ($this->style->ConvertToMM($total, $last) + $this->style->ConvertToMM($width, $last)).'mm';
                        }
                    }

                    // et on recupere la largeur finale
                    if ($total)
                        $colParam['style']['width'] = $total;
                    else
                        unset($colParam['style']['width']);


                    // on merge les 2 styles (col + td)
                    $param['style'] = array_merge($colParam['style'], $param['style']);

                    // si une classe est d�finie, on la merge egalement
                    if (isset($colParam['class']))
                        $param['class'] = $colParam['class'].(isset($param['class']) ? ' '.$param['class'] : '');
                }

                $collapse = isset($this->style->value['border']['collapse']) ? $this->style->value['border']['collapse'] : false;
            }


            // analyse du style
            $this->style->save();
            $legacy = null;
            if (in_array($other, array('td', 'th'))) {
                $legacy = array();

                $old = $this->style->getLastValue('background');
                if ($old && ($old['color'] || $old['image']))
                    $legacy['background'] = $old;

                if (HTML2PDF::$_tables[$param['num']]['border']) {
                    $legacy['border'] = array();
                    $legacy['border']['l'] = HTML2PDF::$_tables[$param['num']]['border'];
                    $legacy['border']['t'] = HTML2PDF::$_tables[$param['num']]['border'];
                    $legacy['border']['r'] = HTML2PDF::$_tables[$param['num']]['border'];
                    $legacy['border']['b'] = HTML2PDF::$_tables[$param['num']]['border'];
                }
            }
            $return = $this->style->analyse($other, $param, $legacy);

            if ($specialLi) {
                $this->style->value['width']-= $this->style->ConvertToMM($this->_listeGetWidth());
                $this->style->value['width']-= $this->style->ConvertToMM($this->_listeGetPadding());
            }
            $this->style->setPosition();
            $this->style->FontSet();

            // si on est en collapse : modification du style
            if ($collapse) {
                if (!$this->_subPart) {
                    if (
                        (HTML2PDF::$_tables[$param['num']]['tr_curr']>1 && !HTML2PDF::$_tables[$param['num']]['new_page']) ||
                        (!$this->_isInThead && count(HTML2PDF::$_tables[$param['num']]['thead']['code']))
                    ) {
                        $this->style->value['border']['t'] = $this->style->readBorder('none');
                    }
                }

                if (HTML2PDF::$_tables[$param['num']]['td_curr']>0) {
                    if (!$return) $this->style->value['width']+= $this->style->value['border']['l']['width'];
                    $this->style->value['border']['l'] = $this->style->readBorder('none');
                }
            }

            $marge = array();
            $marge['t'] = $this->style->value['padding']['t']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['t']['width'];
            $marge['r'] = $this->style->value['padding']['r']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['r']['width'];
            $marge['b'] = $this->style->value['padding']['b']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['b']['width'];
            $marge['l'] = $this->style->value['padding']['l']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['l']['width'];

            // si on est dans un sub_html
            if ($this->_subPart) {
                // on se positionne dans le tableau
                HTML2PDF::$_tables[$param['num']]['td_curr']++;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x] = array();
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['w'] = 0;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['h'] = 0;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['dw'] = 0;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['colspan'] = $colspan;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['rowspan'] = $rowspan;
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['Xr'] = HTML2PDF::$_tables[$param['num']]['corr_x'];
                HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['Yr'] = HTML2PDF::$_tables[$param['num']]['corr_y'];

                for ($j=0; $j<$rowspan; $j++) {
                    for ($i=0; $i<$colspan; $i++) {
                        HTML2PDF::$_tables[$param['num']]['corr']
                            [HTML2PDF::$_tables[$param['num']]['corr_y']+$j]
                            [HTML2PDF::$_tables[$param['num']]['corr_x']+$i] = ($i+$j>0) ? '' : array($x,$y,$colspan,$rowspan);
                    }
                }
                HTML2PDF::$_tables[$param['num']]['corr_x']+= $colspan;
                while(isset(HTML2PDF::$_tables[$param['num']]['corr'][HTML2PDF::$_tables[$param['num']]['corr_y']][HTML2PDF::$_tables[$param['num']]['corr_x']]))
                    HTML2PDF::$_tables[$param['num']]['corr_x']++;

                // on extrait tout ce qui est contenu dans le TD
                // on en cr�� un sous HTML que l'on transforme en PDF
                // pour analyse les dimensions
                // et les r�cup�rer dans le tableau global.
                $level = $this->parsing->getLevel($this->_tempPos);
                $this->_createSubHTML($this->_subHtml);
                $this->_subHtml->parsing->code = $level;
                $this->_subHtml->_makeHTMLcode();
                $this->_tempPos+= count($level);
            } else {
                // on se positionne dans le tableau
                HTML2PDF::$_tables[$param['num']]['td_curr']++;
                HTML2PDF::$_tables[$param['num']]['td_x']+= HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['dw'];

                // initialisation du style des bordures de la premiere partie de tableau
                $this->_drawRectangle(
                    HTML2PDF::$_tables[$param['num']]['td_x'],
                    HTML2PDF::$_tables[$param['num']]['td_y'],
                    HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['w'],
                    HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['h'],
                    $this->style->value['border'],
                    $this->style->value['padding'],
                    HTML2PDF::$_tables[$param['num']]['cellspacing']*0.5,
                    $this->style->value['background']
                );


                $this->style->value['width'] = HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['w'] - $marge['l'] - $marge['r'];

                // limitation des marges aux dimensions de la case
                $mL = HTML2PDF::$_tables[$param['num']]['td_x']+$marge['l'];
                $mR = $this->pdf->getW() - $mL - $this->style->value['width'];
                $this->_saveMargin($mL, 0, $mR);

                // positionnement en fonction
                $hCorr = HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['h'];
                $hReel = HTML2PDF::$_tables[$param['num']]['cases'][$y][$x]['real_h'];
                switch($this->style->value['vertical-align'])
                {
                    case 'bottom':
                        $yCorr = $hCorr-$hReel;
                        break;

                    case 'middle':
                        $yCorr = ($hCorr-$hReel)*0.5;
                        break;

                    case 'top':
                    default:
                        $yCorr = 0;
                        break;
                }

                $x = HTML2PDF::$_tables[$param['num']]['td_x']+$marge['l'];
                $y = HTML2PDF::$_tables[$param['num']]['td_y']+$marge['t']+$yCorr;
                $this->pdf->setXY($x, $y);
                $this->_setNewPositionForNewLine();
            }

            return true;
        }

        /**
         * tag : TD
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TD($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;

            // r�cup�ration de la marge
            $marge = array();
            $marge['t'] = $this->style->value['padding']['t']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['t']['width'];
            $marge['r'] = $this->style->value['padding']['r']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['r']['width'];
            $marge['b'] = $this->style->value['padding']['b']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['b']['width'];
            $marge['l'] = $this->style->value['padding']['l']+0.5*HTML2PDF::$_tables[$param['num']]['cellspacing']+$this->style->value['border']['l']['width'];
            $marge['t']+= 0.001;
            $marge['r']+= 0.001;
            $marge['b']+= 0.001;
            $marge['l']+= 0.001;

            // si on est dans un sub_html
            if ($this->_subPart) {
                if ($this->_testTdInOnepage && $this->_subHtml->pdf->getPage()>1)
                    throw new HTML2PDF_exception(7);

                // dimentions de cette case
                $w0 = $this->_subHtml->_maxX + $marge['l'] + $marge['r'];
                $h0 = $this->_subHtml->_maxY + $marge['t'] + $marge['b'];

                // dimensions impos�es par le style
                $w2 = $this->style->value['width'] + $marge['l'] + $marge['r'];
                $h2 = $this->style->value['height'] + $marge['t'] + $marge['b'];

                // dimension finale de la case = max des 2 ci-dessus
                HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][HTML2PDF::$_tables[$param['num']]['td_curr']-1]['w'] = max(array($w0, $w2));
                HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][HTML2PDF::$_tables[$param['num']]['td_curr']-1]['h'] = max(array($h0, $h2));

                HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][HTML2PDF::$_tables[$param['num']]['td_curr']-1]['real_w'] = $w0;
                HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][HTML2PDF::$_tables[$param['num']]['td_curr']-1]['real_h'] = $h0;

                // suppresion du sous_html
                $this->_destroySubHTML($this->_subHtml);
            } else {
                $this->_loadMargin();
                //positionnement
                HTML2PDF::$_tables[$param['num']]['td_x']+= HTML2PDF::$_tables[$param['num']]['cases'][HTML2PDF::$_tables[$param['num']]['tr_curr']-1][HTML2PDF::$_tables[$param['num']]['td_curr']-1]['w'];
            }

            // restauration du style
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        protected function calculTailleCases(&$cases, &$corr)
        {
/*            // construction d'un tableau de correlation
            $corr = array();

            // on fait correspondre chaque case d'un tableau norm� aux cases r�elles, en prennant en compte les colspan et rowspan
            $Yr=0;
            for ($y=0; $y<count($cases); $y++)
            {
                $Xr=0;     while(isset($corr[$Yr][$Xr])) $Xr++;

                for ($x=0; $x<count($cases[$y]); $x++)
                {
                    for ($j=0; $j<$cases[$y][$x]['rowspan']; $j++) {
                        for ($i=0; $i<$cases[$y][$x]['colspan']; $i++) {
                            $corr[$Yr+$j][$Xr+$i] = ($i+$j>0) ? '' : array($x, $y, $cases[$y][$x]['colspan'], $cases[$y][$x]['rowspan']);
                        }
                    }
                    $Xr+= $cases[$y][$x]['colspan'];
                    while(isset($corr[$Yr][$Xr])) $Xr++;
                }
                $Yr++;
            }
*/
            if (!isset($corr[0])) return true;

            // on d�termine, pour les cases sans colspan, la largeur maximale de chaque colone
            $sw = array();
            for ($x=0; $x<count($corr[0]); $x++) {
                $m=0;
                for ($y=0; $y<count($corr); $y++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x]) && $corr[$y][$x][2]==1) {
                        $m = max($m, $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w']);
                    }
                }
                $sw[$x] = $m;
            }

            // on v�rifie que cette taille est valide avec les colones en colspan
            for ($x=0; $x<count($corr[0]); $x++) {
                for ($y=0; $y<count($corr); $y++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x]) && $corr[$y][$x][2]>1) {
                        // somme des colonnes correspondant au colspan
                        $s = 0; for ($i=0; $i<$corr[$y][$x][2]; $i++) $s+= $sw[$x+$i];

                        // si la somme est inf�rieure � la taille necessaire => r�gle de 3 pour adapter
                        if ($s>0 && $s<$cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w']) {
                            for ($i=0; $i<$corr[$y][$x][2]; $i++) {
                                $sw[$x+$i] = $sw[$x+$i]/$s*$cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w'];
                            }
                        }
                    }
                }
            }

            // on applique les nouvelles largeurs
            for ($x=0; $x<count($corr[0]); $x++) {
                for ($y=0; $y<count($corr); $y++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x])) {
                        if ($corr[$y][$x][2]==1) {
                            $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w'] = $sw[$x];
                        } else {
                            // somme des colonnes correspondant au colspan
                            $s = 0; for ($i=0; $i<$corr[$y][$x][2]; $i++) $s+= $sw[$x+$i];
                            $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w'] = $s;
                        }
                    }
                }
            }

            // on d�termine, pour les cases sans rowspan, la hauteur maximale de chaque colone
            $sh = array();
            for ($y=0; $y<count($corr); $y++) {
                $m=0;
                for ($x=0; $x<count($corr[0]); $x++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x]) && $corr[$y][$x][3]==1) {
                        $m = max($m, $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['h']);
                    }
                }
                $sh[$y] = $m;
            }


            // on v�rifie que cette taille est valide avec les lignes en rowspan
            for ($y=0; $y<count($corr); $y++) {
                for ($x=0; $x<count($corr[0]); $x++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x]) && $corr[$y][$x][3]>1) {
                        // somme des colonnes correspondant au colspan
                        $s = 0; for ($i=0; $i<$corr[$y][$x][3]; $i++) $s+= $sh[$y+$i];

                        // si la somme est inf�rieure � la taille necessaire => r�gle de 3 pour adapter
                        if ($s>0 && $s<$cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['h']) {
                            for ($i=0; $i<$corr[$y][$x][3]; $i++) {
                                $sh[$y+$i] = $sh[$y+$i]/$s*$cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['h'];
                            }
                        }
                    }
                }
            }


            // on applique les nouvelles hauteurs
            for ($y=0; $y<count($corr); $y++) {
                for ($x=0; $x<count($corr[0]); $x++) {
                    if (isset($corr[$y][$x]) && is_array($corr[$y][$x])) {
                        if ($corr[$y][$x][3]==1) {
                            $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['h'] = $sh[$y];
                        } else {
                            // somme des lignes correspondant au rowspan
                            $s = 0; for ($i=0; $i<$corr[$y][$x][3]; $i++) $s+= $sh[$y+$i];
                            $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['h'] = $s;

                            for ($j=1; $j<$corr[$y][$x][3]; $j++) {
                                $tx = $x+1;
                                $ty = $y+$j;
                                for (true; isset($corr[$ty][$tx]) && !is_array($corr[$ty][$tx]); $tx++);
                                if (isset($corr[$ty][$tx])) {
                                    $cases[$corr[$ty][$tx][1]][$corr[$ty][$tx][0]]['dw']+= $cases[$corr[$y][$x][1]][$corr[$y][$x][0]]['w'];
                                }
                            }
                        }
                    }
                }
            }
        }

        /**
         * tag : TH
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TH($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;
            // identique � TD mais en gras
            if (!isset($param['style']['font-weight'])) $param['style']['font-weight'] = 'bold';
            $this->_tag_open_TD($param, 'th');

            return true;
        }

        /**
         * tag : TH
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TH($param)
        {
            if ($this->_isForOneLine) return false;

            $this->_maxH = 0;
            // identique � TD
            $this->_tag_close_TD($param);

            return true;
        }

        /**
         * tag : IMG
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_IMG($param)
        {
            // analyse du style
            $src    = str_replace('&amp;', '&', $param['src']);

            $this->style->save();
            $this->style->value['width']    = 0;
            $this->style->value['height']    = 0;
            $this->style->value['border']    = array(
                                                    'type'    => 'none',
                                                    'width'    => 0,
                                                    'color'    => array(0, 0, 0),
                                                );
            $this->style->value['background'] = array(
                                                    'color'        => null,
                                                    'image'        => null,
                                                    'position'    => null,
                                                    'repeat'    => null
                                                );
            $this->style->analyse('img', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            // affichage de l'image
            $res = $this_drawImage($src, isset($param['sub_li']));
            if (!$res) return $res;

            // restauration du style
            $this->style->load();
            $this->style->FontSet();
            $this->_maxE++;

            return true;
        }

        /**
         * tag : SELECT
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_SELECT($param)
        {
            // preparation du champs
            if (!isset($param['name']))        $param['name']    = 'champs_pdf_'.(count($this->_lstField)+1);

            $param['name'] = strtolower($param['name']);

            if (isset($this->_lstField[$param['name']]))
                $this->_lstField[$param['name']]++;
            else
                $this->_lstField[$param['name']] = 1;

            $this->style->save();
            $this->style->analyse('select', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $this->_lstSelect = array();
            $this->_lstSelect['name']    = $param['name'];
            $this->_lstSelect['multi']    = isset($param['multiple']) ? true : false;
            $this->_lstSelect['size']    = isset($param['size']) ? $param['size'] : 1;
            $this->_lstSelect['options']    = array();

            if ($this->_lstSelect['multi'] && $this->_lstSelect['size']<3) $this->_lstSelect['size'] = 3;

            return true;
        }

        /**
         * tag : OPTION
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_OPTION($param)
        {
            // on extrait tout ce qui est contenu dans l'option
            $level = $this->parsing->getLevel($this->_parsePos);
            $this->_parsePos+= count($level);
            $value = isset($param['value']) ? $param['value'] : 'aut_tag_open_opt_'.(count($this->_lstSelect)+1);

            $this->_lstSelect['options'][$value] = isset($level[0]['param']['txt']) ? $level[0]['param']['txt'] : '';

            return true;
        }

        /**
         * tag : OPTION
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_OPTION($param)
        {
            return true;
        }

        /**
         * tag : SELECT
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_SELECT()
        {
            // position d'affichage
            $x = $this->pdf->getX();
            $y = $this->pdf->getY();
            $f = 1.08*$this->style->value['font-size'];

            $w = $this->style->value['width']; if (!$w) $w = 50;
            $h = ($f*1.07*$this->_lstSelect['size'] + 1);
            $opts = array();
            if ($this->_lstSelect['multi']) $opts['multipleSelection'] = 'true';

            if ($this->_lstSelect['size']>1)
                $this->pdf->ListBox($this->_lstSelect['name'], $w, $h, $this->_lstSelect['options'], $opts);
            else
                $this->pdf->ComboBox($this->_lstSelect['name'], $w, $h, $this->_lstSelect['options']);

            $this->_maxX = max($this->_maxX, $x+$w);
            $this->_maxY = max($this->_maxY, $y+$h);
             $this->_maxH = max($this->_maxH, $h);
            $this->pdf->setX($x+$w);

            $this->style->load();
            $this->style->FontSet();

            $this->_lstSelect = array();

            return true;
        }

        /**
         * tag : TEXTAREA
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_TEXTAREA($param)
        {
            // preparation du champs
            if (!isset($param['name']))        $param['name']    = 'champs_pdf_'.(count($this->_lstField)+1);

            $param['name'] = strtolower($param['name']);

            if (isset($this->_lstField[$param['name']]))
                $this->_lstField[$param['name']]++;
            else
                $this->_lstField[$param['name']] = 1;

            $this->style->save();
            $this->style->analyse('textarea', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            // position d'affichage
            $x = $this->pdf->getX();
            $y = $this->pdf->getY();
            $fx = 0.65*$this->style->value['font-size'];
            $fy = 1.08*$this->style->value['font-size'];

            // on extrait tout ce qui est contenu dans le textarea
            $level = $this->parsing->getLevel($this->_parsePos);
            $this->_parsePos+= count($level);

            $w = $fx*(isset($param['cols']) ? $param['cols'] : 22)+1;
            $h = $fy*1.07*(isset($param['rows']) ? $param['rows'] : 3)+3;

//            if ($this->style->value['width']) $w = $this->style->value['width'];
//            if ($this->style->value['height']) $h = $this->style->value['height'];

            $prop = array();
            $prop['multiline'] = true;
            $prop['value'] = isset($level[0]['param']['txt']) ? $level[0]['param']['txt'] : '';

            $this->pdf->TextField($param['name'], $w, $h, $prop, array(), $x, $y);

            $this->_maxX = max($this->_maxX, $x+$w);
            $this->_maxY = max($this->_maxY, $y+$h);
             $this->_maxH = max($this->_maxH, $h);
            $this->pdf->setX($x+$w);

            return true;
        }

        /**
         * tag : TEXTAREA
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_TEXTAREA()
        {
            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : INPUT
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_INPUT($param)
        {
            // preparation du champs
            if (!isset($param['name']))        $param['name']    = 'champs_pdf_'.(count($this->_lstField)+1);
            if (!isset($param['value']))    $param['value']    = '';
            if (!isset($param['type']))        $param['type']    = 'text';

            $param['name'] = strtolower($param['name']);
            $param['type'] = strtolower($param['type']);

            if (!in_array($param['type'], array('text', 'checkbox', 'radio', 'hidden', 'submit', 'reset', 'button'))) $param['type'] = 'text';

            if (isset($this->_lstField[$param['name']]))
                $this->_lstField[$param['name']]++;
            else
                $this->_lstField[$param['name']] = 1;

            $this->style->save();
            $this->style->analyse('input', $param);
            $this->style->setPosition();
            $this->style->FontSet();

            $name = $param['name'];

            // position d'affichage
            $x = $this->pdf->getX();
            $y = $this->pdf->getY();
            $f = 1.08*$this->style->value['font-size'];

            switch($param['type'])
            {
                case 'checkbox':
                    $w = 3;
                    $h = $w;
                    if ($h<$f) $y+= ($f-$h)*0.5;
                    $this->pdf->CheckBox($name, $w, isset($param['checked']), array(), array(), ($param['value'] ? $param['value'] : 'Yes'), $x, $y);
                    break;

                case 'radio':
                    $w = 3;
                    $h = $w;
                    if ($h<$f) $y+= ($f-$h)*0.5;
                    $this->pdf->RadioButton($name, $w, array(), array(), ($param['value'] ? $param['value'] : 'On'), isset($param['selected']), $x, $y);
                    break;

                case 'hidden':
                    $w = 0;
                    $h = 0;
                    $prop = array();
                    $prop['value'] = $param['value'];
                    $this->pdf->TextField($name, $w, $h, $prop, array(), $x, $y);
                    break;

                case 'text':
                    $w = $this->style->value['width']; if (!$w) $w = 40;
                    $h = $f*1.3;
                    $prop = array();
                    $prop['value'] = $param['value'];
                    $this->pdf->TextField($name, $w, $h, $prop, array(), $x, $y);
                    break;

                case 'submit':
                    $w = $this->style->value['width'];    if (!$w) $w = 40;
                    $h = $this->style->value['height'];    if (!$h) $h = $f*1.3;
                    $action = array('S'=>'SubmitForm', 'F'=>$this->_isInForm, 'Flags'=>array('ExportFormat'));
                    $this->pdf->Button($name, $w, $h, $param['value'], $action, array(), array(), $x, $y);
                    break;

                case 'reset':
                    $w = $this->style->value['width'];    if (!$w) $w = 40;
                    $h = $this->style->value['height'];    if (!$h) $h = $f*1.3;
                    $action = array('S'=>'ResetForm');
                    $this->pdf->Button($name, $w, $h, $param['value'], $action, array(), array(), $x, $y);
                    break;

                case 'button':
                    $w = $this->style->value['width'];    if (!$w) $w = 40;
                    $h = $this->style->value['height'];    if (!$h) $h = $f*1.3;
                    $action = isset($param['onclick']) ? $param['onclick'] : '';
                    $this->pdf->Button($name, $w, $h, $param['value'], $action, array(), array(), $x, $y);
                    break;

                default:
                    $w = 0;
                    $h = 0;
                    break;
            }

            $this->_maxX = max($this->_maxX, $x+$w);
            $this->_maxY = max($this->_maxY, $y+$h);
             $this->_maxH = max($this->_maxH, $h);
            $this->pdf->setX($x+$w);

            $this->style->load();
            $this->style->FontSet();

            return true;
        }

        /**
         * tag : DRAW
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_DRAW($param)
        {
            if ($this->_isForOneLine) return false;
            if ($this->_debugActif) $this->_DEBUG_add('DRAW', true);

            $this->style->save();
            $this->style->analyse('draw', $param);
            $this->style->FontSet();

            $alignObject = null;
            if ($this->style->value['margin-auto']) $alignObject = 'center';

            $overW = $this->style->value['width'];
            $overH = $this->style->value['height'];
            $this->style->value['old_maxX'] = $this->_maxX;
            $this->style->value['old_maxY'] = $this->_maxY;
            $this->style->value['old_maxH'] = $this->_maxH;

            $w = $this->style->value['width'];
            $h = $this->style->value['height'];

            if (!$this->style->value['position']) {
                if (
                    $w < ($this->pdf->getW() - $this->pdf->getlMargin()-$this->pdf->getrMargin()) &&
                    $this->pdf->getX() + $w>=($this->pdf->getW() - $this->pdf->getrMargin())
                    )
                    $this->_tag_open_BR(array());

                if (
                        ($h < ($this->pdf->getH() - $this->pdf->gettMargin()-$this->pdf->getbMargin())) &&
                        ($this->pdf->getY() + $h>=($this->pdf->getH() - $this->pdf->getbMargin())) &&
                        !$this->_isInOverflow
                    )
                    $this->_setNewPage();

                // en cas d'alignement => correction
                $old = $this->style->getOldValues();
                $parentWidth = $old['width'] ? $old['width'] : $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();

                if ($parentWidth>$w) {
                    if ($alignObject=='center')        $this->pdf->setX($this->pdf->getX() + ($parentWidth-$w)*0.5);
                    else if ($alignObject=='right')    $this->pdf->setX($this->pdf->getX() + $parentWidth-$w);
                }

                $this->style->setPosition();
            } else {
                // en cas d'alignement => correction
                $old = $this->style->getOldValues();
                $parentWidth = $old['width'] ? $old['width'] : $this->pdf->getW() - $this->pdf->getlMargin() - $this->pdf->getrMargin();

                if ($parentWidth>$w) {
                    if ($alignObject=='center')        $this->pdf->setX($this->pdf->getX() + ($parentWidth-$w)*0.5);
                    else if ($alignObject=='right')    $this->pdf->setX($this->pdf->getX() + $parentWidth-$w);
                }

                $this->style->setPosition();
                $this->_saveMax();
                $this->_maxX = 0;
                $this->_maxY = 0;
                $this->_maxH = 0;
                $this->_maxE = 0;
            }

            // initialisation du style des bordures de la div
            $this->_drawRectangle(
                $this->style->value['x'],
                $this->style->value['y'],
                $this->style->value['width'],
                $this->style->value['height'],
                $this->style->value['border'],
                $this->style->value['padding'],
                0,
                $this->style->value['background']
            );

            $marge = array();
            $marge['l'] = $this->style->value['border']['l']['width'];
            $marge['r'] = $this->style->value['border']['r']['width'];
            $marge['t'] = $this->style->value['border']['t']['width'];
            $marge['b'] = $this->style->value['border']['b']['width'];

            $this->style->value['width'] -= $marge['l']+$marge['r'];
            $this->style->value['height']-= $marge['t']+$marge['b'];

            $overW-= $marge['l']+$marge['r'];
            $overH-= $marge['t']+$marge['b'];
            $this->pdf->clippingPathStart(
                $this->style->value['x']+$marge['l'],
                $this->style->value['y']+$marge['t'],
                $this->style->value['width'],
                $this->style->value['height']
            );

            // limitation des marges aux dimensions du contenu
            $mL = $this->style->value['x']+$marge['l'];
            $mR = $this->pdf->getW() - $mL - $overW;

            $x = $this->style->value['x']+$marge['l'];
            $y = $this->style->value['y']+$marge['t'];
            $this->_saveMargin($mL, 0, $mR);
            $this->pdf->setXY($x, $y);

            $this->_isInDraw = array(
                'x' => $x,
                'y' => $y,
                'w' => $overW,
                'h' => $overH,
            );
            $this->pdf->doTransform(array(1,0,0,1,$x,$y));
            $this->pdf->SetAlpha(1.);
            return true;
        }

        /**
         * tag : DRAW
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_DRAW($param)
        {
            if ($this->_isForOneLine) return false;

            $this->pdf->SetAlpha(1.);
            $this->pdf->undoTransform();
            $this->pdf->clippingPathStop();

            $this->_maxX = $this->style->value['old_maxX'];
            $this->_maxY = $this->style->value['old_maxY'];
            $this->_maxH = $this->style->value['old_maxH'];

            $marge = array();
            $marge['l'] = $this->style->value['border']['l']['width'];
            $marge['r'] = $this->style->value['border']['r']['width'];
            $marge['t'] = $this->style->value['border']['t']['width'];
            $marge['b'] = $this->style->value['border']['b']['width'];

            $x = $this->style->value['x'];
            $y = $this->style->value['y'];
            $w = $this->style->value['width']+$marge['l']+$marge['r'];
            $h = $this->style->value['height']+$marge['t']+$marge['b'];

            if ($this->style->value['position']!='absolute') {
                // position
                $this->pdf->setXY($x+$w, $y);

                // position MAX
                $this->_maxX = max($this->_maxX, $x+$w);
                $this->_maxY = max($this->_maxY, $y+$h);
                $this->_maxH = max($this->_maxH, $h);
            } else {
                // position
                $this->pdf->setXY($this->style->value['xc'], $this->style->value['yc']);

                $this->_loadMax();
            }

            $block = ($this->style->value['display']!='inline' && $this->style->value['position']!='absolute');

            $this->style->load();
            $this->style->FontSet();
            $this->_loadMargin();

            if ($block) $this->_tag_open_BR(array());
            if ($this->_debugActif) $this->_DEBUG_add('DRAW', false);

            $this->_isInDraw = null;

            return true;
        }

        /**
         * tag : LINE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_LINE($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'LINE');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
            $styles = $this->style->getSvgStyle('path', $param);
            $styles['fill'] = null;
            $style = $this->pdf->svgSetStyle($styles);

            $x1 = isset($param['x1']) ? $this->style->ConvertToMM($param['x1'], $this->_isInDraw['w']) : 0.;
            $y1 = isset($param['y1']) ? $this->style->ConvertToMM($param['y1'], $this->_isInDraw['h']) : 0.;
            $x2 = isset($param['x2']) ? $this->style->ConvertToMM($param['x2'], $this->_isInDraw['w']) : 0.;
            $y2 = isset($param['y2']) ? $this->style->ConvertToMM($param['y2'], $this->_isInDraw['h']) : 0.;
            $this->pdf->svgLine($x1, $y1, $x2, $y2);

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : RECT
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_RECT($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'RECT');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $x = isset($param['x']) ? $this->style->ConvertToMM($param['x'], $this->_isInDraw['w']) : 0.;
            $y = isset($param['y']) ? $this->style->ConvertToMM($param['y'], $this->_isInDraw['h']) : 0.;
            $w = isset($param['w']) ? $this->style->ConvertToMM($param['w'], $this->_isInDraw['w']) : 0.;
            $h = isset($param['h']) ? $this->style->ConvertToMM($param['h'], $this->_isInDraw['h']) : 0.;

            $this->pdf->svgRect($x, $y, $w, $h, $style);

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : CIRCLE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_CIRCLE($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'CIRCLE');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $cx = isset($param['cx']) ? $this->style->ConvertToMM($param['cx'], $this->_isInDraw['w']) : 0.;
            $cy = isset($param['cy']) ? $this->style->ConvertToMM($param['cy'], $this->_isInDraw['h']) : 0.;
            $r = isset($param['r']) ? $this->style->ConvertToMM($param['r'], $this->_isInDraw['w']) : 0.;
            $this->pdf->svgEllipse($cx, $cy, $r, $r, $style);

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : ELLIPSE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_ELLIPSE($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'ELLIPSE');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $cx = isset($param['cx']) ? $this->style->ConvertToMM($param['cx'], $this->_isInDraw['w']) : 0.;
            $cy = isset($param['cy']) ? $this->style->ConvertToMM($param['cy'], $this->_isInDraw['h']) : 0.;
            $rx = isset($param['ry']) ? $this->style->ConvertToMM($param['rx'], $this->_isInDraw['w']) : 0.;
            $ry = isset($param['rx']) ? $this->style->ConvertToMM($param['ry'], $this->_isInDraw['h']) : 0.;
            $this->pdf->svgEllipse($cx, $cy, $rx, $ry, $style);

            $this->pdf->undoTransform();
            $this->style->load();
        }


            /**
         * tag : POLYLINE
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_POLYLINE($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'POLYGON');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $path = isset($param['points']) ? $param['points'] : null;
            if ($path) {
                $path = str_replace(',', ' ', $path);
                $path = preg_replace('/[\s]+/', ' ', trim($path));

                // decoupage et nettoyage
                $path = explode(' ', $path);
                foreach ($path as $k => $v) {
                    $path[$k] = trim($v);
                    if ($path[$k]==='') unset($path[$k]);
                }
                $path = array_values($path);

                $actions = array();
                for ($k=0; $k<count($path); $k+=2) {
                    $actions[] = array(($k ? 'L' : 'M') ,
                                        $this->style->ConvertToMM($path[$k+0], $this->_isInDraw['w']),
                                        $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['h']));
                }

                // on trace
                $this->pdf->svgPolygone($actions, $style);
            }

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : POLYGON
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_POLYGON($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'POLYGON');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $path = isset($param['points']) ? $param['points'] : null;
            if ($path) {
                $path = str_replace(',', ' ', $path);
                $path = preg_replace('/[\s]+/', ' ', trim($path));

                // decoupage et nettoyage
                $path = explode(' ', $path);
                foreach ($path as $k => $v) {
                    $path[$k] = trim($v);
                    if ($path[$k]==='') unset($path[$k]);
                }
                $path = array_values($path);

                $actions = array();
                for ($k=0; $k<count($path); $k+=2) {
                    $actions[] = array(($k ? 'L' : 'M') ,
                                        $this->style->ConvertToMM($path[$k+0], $this->_isInDraw['w']),
                                        $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['h']));
                }
                $actions[] = array('z');
                // on trace
                $this->pdf->svgPolygone($actions, $style);
            }

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : PATH
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_PATH($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'PATH');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);

            $path = isset($param['d']) ? $param['d'] : null;
            if ($path) {
                // preparation
                $path = str_replace(',', ' ', $path);
                $path = preg_replace('/([a-zA-Z])([0-9\.\-])/', '$1 $2', $path);
                $path = preg_replace('/([0-9\.])([a-zA-Z])/', '$1 $2', $path);
                $path = preg_replace('/[\s]+/', ' ', trim($path));
                $path = preg_replace('/ ([a-z]{2})/', '$1', $path);

                // decoupage et nettoyage
                $path = explode(' ', $path);
                foreach ($path as $k => $v) {
                    $path[$k] = trim($v);
                    if ($path[$k]==='') unset($path[$k]);
                }
                $path = array_values($path);

                // conversion des unites
                $actions = array();
                $action = array();
                for ($k=0; $k<count($path); $k+=count($action)) {
                    $action = array();
                    $action[] = $path[$k];
                    switch($path[$k])
                    {
                        case 'C':
                        case 'c':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['w']);    // x1
                            $action[] = $this->style->ConvertToMM($path[$k+2], $this->_isInDraw['h']);    // y1
                            $action[] = $this->style->ConvertToMM($path[$k+3], $this->_isInDraw['w']);    // x2
                            $action[] = $this->style->ConvertToMM($path[$k+4], $this->_isInDraw['h']);    // y2
                            $action[] = $this->style->ConvertToMM($path[$k+5], $this->_isInDraw['w']);    // x
                            $action[] = $this->style->ConvertToMM($path[$k+6], $this->_isInDraw['h']);    // y
                            break;

                        case 'Q':
                        case 'S':
                        case 'q':
                        case 's':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['w']);    // x2
                            $action[] = $this->style->ConvertToMM($path[$k+2], $this->_isInDraw['h']);    // y2
                            $action[] = $this->style->ConvertToMM($path[$k+3], $this->_isInDraw['w']);    // x
                            $action[] = $this->style->ConvertToMM($path[$k+4], $this->_isInDraw['h']);    // y
                            break;

                        case 'A':
                        case 'a':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['w']);    // rx
                            $action[] = $this->style->ConvertToMM($path[$k+2], $this->_isInDraw['h']);    // ry
                            $action[] = 1.*$path[$k+3];                                                    // angle de deviation de l'axe X
                            $action[] = ($path[$k+4]=='1') ? 1 : 0;                                        // large-arc-flag
                            $action[] = ($path[$k+5]=='1') ? 1 : 0;                                     // sweep-flag
                            $action[] = $this->style->ConvertToMM($path[$k+6], $this->_isInDraw['w']);    // x
                            $action[] = $this->style->ConvertToMM($path[$k+7], $this->_isInDraw['h']);    // y
                            break;

                        case 'M':
                        case 'L':
                        case 'T':
                        case 'm':
                        case 'l':
                        case 't':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['w']);    // x
                            $action[] = $this->style->ConvertToMM($path[$k+2], $this->_isInDraw['h']);    // y
                            break;

                        case 'H':
                        case 'h':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['w']);    // x
                            break;

                        case 'V':
                        case 'v':
                            $action[] = $this->style->ConvertToMM($path[$k+1], $this->_isInDraw['h']);    // y
                            break;

                        case 'z':
                        case 'Z':
                        default:
                            break;
                    }
                    $actions[] = $action;
                }

                // on trace
                $this->pdf->svgPolygone($actions, $style);
            }

            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * tag : G
         * mode : OPEN
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_open_G($param)
        {
            if (!$this->_isInDraw) throw new HTML2PDF_exception(8, 'LINE');
            $this->pdf->doTransform(isset($param['transform']) ? $this->_prepareTransform($param['transform']) : null);
            $this->style->save();
             $styles = $this->style->getSvgStyle('path', $param);
            $style = $this->pdf->svgSetStyle($styles);
        }

        /**
         * tag : G
         * mode : CLOSE
         *
         * @param    array $param
         * @return    null
         */
        protected function _tag_close_G($param)
        {
            $this->pdf->undoTransform();
            $this->style->load();
        }

        /**
         * nouvelle page pour l'index. ne pas utiliser directement. seul MyPDF doit l'utiliser !!!!
         *
         * @param    int        page courante
         * @return    null
         */
        public function INDEX_NewPage(&$page)
        {
            if ($page) {
                $oldPage = $this->pdf->getPage();
                $this->pdf->setPage($page);
                $this->pdf->setXY($this->_margeLeft, $this->_margeTop);
                $this->_maxH = 0;
                $page++;
                return $oldPage;
            } else {
                $this->_setNewPage();
                return null;
            }
        }

        /**
         * chargement du fichier de langue
         *
         * @param    string langue
         * @return    null
         */
        static protected function textLOAD($langue)
        {
            if (count(HTML2PDF::$_textes)) return true;

            if (!preg_match('/^([a-z0-9]+)$/isU', $langue)) {
                echo 'ERROR : language code <b>'.$langue.'</b> incorrect.';
                exit;
            }

            $file = dirname(__FILE__).'/locale/'.strtolower($langue).'.csv';
            if (!is_file($file)) {
                echo 'ERROR : language code <b>'.$langue.'</b> unknown.<br>';
                echo 'You can create the translation file <b>'.$file.'</b> and send it to me in order to integrate it into a future version.';
                exit;
            }

            HTML2PDF::$_textes = array();
            $handle = fopen($file, 'r');
            while (!feof($handle)) {
                $line = fgetcsv($handle);
                if (count($line)!=2) continue;

                HTML2PDF::$_textes[trim($line[0])] = trim($line[1]);
            }
            fclose($handle);

            return true;
        }

        /**
         * recuperer un texte precis
         *
         * @param    string code du texte
         * @return    null
         */
        static public function textGET($key)
        {
            if (!isset(HTML2PDF::$_textes[$key])) return '######';

            return HTML2PDF::$_textes[$key];
        }
    }

    class HTML2PDF_exception extends exception
    {
        protected $_tag = null;
        protected $_html = null;
        protected $_image = null;
        protected $_messageHtml = '';

        /**
         * generer une erreur HTML2PDF
         *
         * @param    int        numero de l'erreur
         * @param    mixed    indications suplementaires sur l'erreur
         * @return    string    code HTML eventuel associ� � l'erreur
         */
        final public function __construct($err = 0, $other = null, $html = '')
        {
            // creation du message d'erreur
            $msg = '';

            switch($err)
            {
                case 1:
                    $msg = (HTML2PDF::textGET('err01'));
                    $msg = str_replace('[[OTHER]]', $other, $msg);
                    $this->_tag = $other;
                    break;

                case 2:
                    $msg = (HTML2PDF::textGET('err02'));
                    $msg = str_replace('[[OTHER_0]]', $other[0], $msg);
                    $msg = str_replace('[[OTHER_1]]', $other[1], $msg);
                    $msg = str_replace('[[OTHER_2]]', $other[2], $msg);
                    break;

                case 3:
                    $msg = (HTML2PDF::textGET('err03'));
                    $msg = str_replace('[[OTHER]]', $other, $msg);
                    $this->_tag = $other;
                    break;

                case 4:
                    $msg = (HTML2PDF::textGET('err04'));
                    $msg = str_replace('[[OTHER]]', print_r($other, true), $msg);
                    break;

                case 5:
                    $msg = (HTML2PDF::textGET('err05'));
                    $msg = str_replace('[[OTHER]]', print_r($other, true), $msg);
                    break;

                case 6:
                    $msg = (HTML2PDF::textGET('err06'));
                    $msg = str_replace('[[OTHER]]', $other, $msg);
                    $this->_image = $other;
                    break;

                case 7:
                    $msg = (HTML2PDF::textGET('err07'));
                    break;

                case 8:
                    $msg = (HTML2PDF::textGET('err08'));
                    $msg = str_replace('[[OTHER]]', $other, $msg);
                    $this->_tag = $other;
                    break;

                case 9:
                    $msg = (HTML2PDF::textGET('err09'));
                    $msg = str_replace('[[OTHER_0]]', $other[0], $msg);
                    $msg = str_replace('[[OTHER_1]]', $other[1], $msg);
                    $this->_tag = $other[0];
                    break;
                case 0:
                    $msg = $other;
                    break;
            }

            // creation du message HTML
            $this->_messageHtml = '<span style="color: #AA0000; font-weight: bold;">'.(HTML2PDF::textGET('txt01')).$err.'</span><br>';
            $this->_messageHtml.= (HTML2PDF::textGET('txt02')).' '.$this->file.'<br>';
            $this->_messageHtml.= (HTML2PDF::textGET('txt03')).' '.$this->line.'<br>';
            $this->_messageHtml.= '<br>';
            $this->_messageHtml.= $msg;

            // creation du message classique
            $msg = HTML2PDF::textGET('txt01').$err.' : '.strip_tags($msg);

            if ($html) {
                $this->_messageHtml.= "<br><br>HTML : ...".trim(htmlentities($html)).'...';
                $this->_html = $html;
                $msg.= ' HTML : ...'.trim($html).'...';
            }

            parent::__construct($msg, $err);
        }

        public function __toString()
        {
            return $this->_messageHtml;
        }
        public function getTAG()
        {
            return $this->_tag;
        }
        public function getHTML()
        {
            return $this->_html;
        }
        public function getIMAGE()
        {
            return $this->_image;
        }
    }
}
