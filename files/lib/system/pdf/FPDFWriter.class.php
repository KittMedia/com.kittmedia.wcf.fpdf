<?php
namespace wcf\system\pdf;
use \wcf\system\exception\SystemException;
use \wcf\system\io\File;
use \wcf\system\style\StyleHandler;
use \wcf\system\WCF;
use \wcf\util\StringUtil;

/**
 * Creates a PDF file with FPDF.
 * 
 * @author	Dennis Kraffczyk
 * @copyright	2011-2014 KittMedia Productions
 * @license	Commercial <https://kittblog.com/board/licenses/commercial.html>
 * @package	com.kittmedia.wcf.fpdf
 * @category	Community Framework
 */
class FPDFWriter {
	/**
	 * Font families shipped with FPDF
	 * @var		array<string>
	 */
	protected static $coreFontFamilies = array('arial', 'courier', 'helvetica', 'symbol', 'times', 'zapfDingbats');
	
	/**
	 * Default RGB background color (used for filled cells for example)
	 * @var		array<integer>
	 */
	public static $defaultRGBBackgroundColor = array('r' => 255, 'g' => 255, 'b' => 255);
	
	/**
	 * Default RGB text color (used for text in cells for example)
	 * @var		array<integer>
	 */
	public static $defaultRGBTextColor = array('r' => 0, 'g' => 0, 'b' => 0);
	
	/**
	 * Font directory
	 * Leave empty if the 'fonts/fpdf' - directory of the WCF should be used
	 * @var		string
	 */
	public static $fontDirectory = '';
	
	/**
	 * Margin for left side
	 * @var		float
	 */
	public static $leftMargin = 1;
	
	/**
	 * PDF (FPDF instance)
	 * @var		\wcf\system\api\fpdf\FPDF
	 */
	protected $pdf = null;
	
	/**
	 * PDF orientation (default 'Portrait')
	 * Valid orientations are:
	 * 	- L|Landscape
	 * 	- P|Portrait
	 * 
	 * @var		string
	 */
	public static $orientation = 'Portrait';
	
	/**
	 * Margin for right side
	 * @var		float
	 */
	public static $rightMargin = 1;
	
	/**
	 * PDF size (default 'A4')
	 * Valid sizes are:
	 * 	- A3
	 * 	- A4
	 * 	- A5
	 * 	- Legal
	 * 	- Letter
	 * 
	 * @var		string
	 */
	public static $size = 'A4';
	
	/**
	 * PDF unit (default 'mm')
	 * Valid units are:
	 * 	- cm (centimeter)
	 * 	- in (inch)
	 * 	- mm (millimeter)
	 * 	- pt (point)
	 * 
	 * @var		string
	 */
	public static $unit = 'mm';
	
	/**
	 * @see		\wcf\system\Singleton::init()
	 */
	public final function __construct() {
		// handle font directory
		if (empty(static::$fontDirectory)) {
			static::$fontDirectory = WCF_DIR.'font/fpdf';
		}
		define('FPDF_FONTPATH', static::$fontDirectory);
		
		require_once(WCF_DIR.'lib/system/api/fpdf/fpdf.php');
		$this->pdf = new \FPDF(static::$orientation, static::$unit, static::$size);
		$this->pdf->setLeftMargin(static::$leftMargin);
		$this->pdf->setRightMargin(static::$rightMargin);
		$this->pdf->addPage();
	}
	
	/**
	 * Adds given image to given coordinates.
	 * If '$imagePath' is empty, the page logo will be used. 
	 * The other values are handled as described in the FPDF-API.
	 * 
	 * @param		string		$imagePath (FQDN address or URL)
	 * @param		float		$x
	 * @param		float		$y
	 * @param		float		$height
	 * @param		float		$width
	 * @param		mixed		$link
	 */
	public function addImage($imagePath = '', $x = null, $y = null, $height = 0, $width = 0, $link = '') {
		if (empty($imagePath)) {
			$imagePath = StyleHandler::getInstance()->getStyle()->getPageLogo();
			
			if (empty($imagePath)) {
				throw new SystemException('Please specify an "$imagePath", as no page logo is defined for the active style.');
			}
		}
		
		$this->pdf->Image($imagePath, $x, $y, $width, $height, $link);
	}
	
	/**
	 * Adds a table with given data.
	 * @param		array<mixed>		$tableHeader
	 * @param		array<mixed>		$tableData
	 */
	public function addTable($tableHeader, $tableData = array(), $subTableData = array()) {
		if (!empty($tableData)) {
			$columnCount = count($tableHeader);
			foreach ($tableData as $index => $columnData) {
				if (count($columnData) !== $columnCount) {
					throw new SystemException('Data of table data index "'.$index.'" does not match amount of table heads.');
				}
				
				if (isset($subTableData[$index])) {
					foreach ($subTableData[$index] as $subIndex => $subColumnData) {
						if (count($subColumnData) !== $columnCount) {
							throw new SystemException('Sub data of table sub data index "'.$subIndex.'" does not match amount of table heads.');
						}
					}
				}
			}
		}
		
		// navigate to table beginning
		$this->pdf->Cell(1);
		
		// generate table head
		foreach ($tableHeader as $index => &$tableHeaderData) {
			if (!isset($tableHeaderData['height'])) {
				throw new SystemException('Height value is missing for table header "'.$index.'"');
			}
			
			if (isset($tableHeaderData['backgroundColor'])) {
				$rgbBackgroundColor = explode(',', $tableHeaderData['backgroundColor']);
				if (count($rgbBackgroundColor) !== 3) {
					throw new SystemException('Background color is invalid for table header "'.$index.'"');
				}
				else {
					foreach ($rgbBackgroundColor as $value) {
						if (intval($value) < 0 || intval($value) > 2550) {
							throw new SystemException('Background color is invalid for table header "'.$index.'"');
						}
					}
				}
				
				if (!isset($tableHeaderData['textColor'])) {
					throw new SystemException('Text color is missing for background coloured table header "'.$index.'"');
				}
				else {
					$rgbTextColor = explode(',', $tableHeaderData['textColor']);
					if (count($rgbTextColor) !== 3) {
						throw new SystemException('Text color is invalid for table header "'.$index.'"');
					}
					else {
						foreach ($rgbTextColor as $value) {
							if (intval($value) < 0 || intval($value) > 2550) {
								throw new SystemException('Text color is invalid for table header "'.$index.'"');
							}
						}
					}
				}
				
				$tableHeaderData['fill'] = 1;
			}
			else {
				$tableHeaderData['fill'] = 0;
			}
			
			if (!isset($tableHeaderData['alignment'])) {
				$tableHeaderData['alignment'] = '';
			}
			else {
				$tableHeaderData['alignment'] = substr(strtoupper($tableHeaderData['alignment']), 0, 1);
				if (!in_array($tableHeaderData['alignment'], array('C', 'L', 'R'))) {
					$tableHeaderData['alignment'] = '';
				}
			}
			
			if (!isset($tableHeaderData['border'])) {
				$tableHeaderData['border'] = 0;
			}
			else {
				if (is_int($tableHeaderData['border'])) {
					$tableHeaderData['border'] = intval($tableHeaderData['border']);
				}
				else {
					$tableHeaderData['border'] = substr(strtolower($tableHeaderData['border']), 0, 1);
					if (!in_array($tableHeaderData['border'], array('b', 'l', 'r', 't'))) {
						$tableHeaderData['border'] = 1;
					}
				}
			}
			
			if (!isset($tableHeaderData['value'])) {
				$tableHeaderData['value'] = '';
			}
			$tableHeaderData['value'] = $this->formatText($tableHeaderData['value']);
			
			if (!isset($tableHeaderData['width'])) {
				$tableHeaderData['width'] = 0;
			}
			
			if (isset($tableHeaderData['backgroundColor'])) {
				list($r, $g, $b) = explode(',', $tableHeaderData['backgroundColor']);
				$this->pdf->SetFillColor($r, $g, $b);
				
				list($r, $g, $b) = explode(',', $tableHeaderData['textColor']);
				$this->pdf->SetTextColor($r, $g, $b);
			}
			else {
				$this->pdf->SetFillColor(static::$defaultRGBBackgroundColor['r'],static::$defaultRGBBackgroundColor['g'], static::$defaultRGBBackgroundColor['b']);
				$this->pdf->SetTextColor(static::$defaultRGBTextColor['r'], static::$defaultRGBTextColor['g'], static::$defaultRGBTextColor['b']);
			}
			$this->pdf->Cell($tableHeaderData['width'], $tableHeaderData['height'], $tableHeaderData['value'], $tableHeaderData['border'], 0, $tableHeaderData['alignment'], $tableHeaderData['fill']);
		}
		
		// jump to the beginning of the table body
		$this->pdf->Ln();
		$this->pdf->Cell(1);
		
		// generate table body
		foreach ($tableData as $rowIndex => $rows) {
			$hasSubData = null;
			$i = 0;
			$rowCount = count($rows);
			foreach ($rows as $index => $row) {
				if ($hasSubData === null) {
					$hasSubData = isset($subTableData[$rowIndex]);
				}
				
				$this->pdf->Cell(
					$tableHeader[$index]['width'],
					$tableHeader[$index]['height'],
					$this->formatText($row),
					$tableHeader[$index]['border'],
					0,
					$tableHeader[$index]['alignment']
				);
				
				$i++;
				
				if ($i === $rowCount) {
					if ($hasSubData) {
						$this->pdf->Ln();
						$this->pdf->Cell(1);
						
						for ($y = 0, $z = count($subTableData[$rowIndex]); $y < $z; $y++) {
							foreach ($subTableData[$rowIndex][$y] as $index2 => $row2) {
								$this->pdf->Cell(
									$tableHeader[$index2]['width'],
									$tableHeader[$index2]['height'],
									$row2,
									$tableHeader[$index2]['border'],
									0,
									$tableHeader[$index2]['alignment']
								);
							}
							
							$this->pdf->Ln();
							$this->pdf->Cell(1);
						}
					}
				}
			}
			
			$this->pdf->Ln();
			$this->pdf->Cell(1);
		}
	}
	
	/**
	 * Adds the given text to the given coordinates.
	 * @param	string		$text
	 * @param	integer		$x
	 * @param	integer		$y
	 * @param	array<mixed>	$variables
	 */
	public function addText($text, $x, $y, $variables = array()) {
		$this->pdf->Text($x, $y, $this->formatText($text, $variables));
	}
	
	/**
	 * Formats a text for using it within a pdf.
	 * You can passe an language variable as '$text' and variables.
	 * @param	string		$text
	 * @param	array<mixed>	$variables
	 * @return	string
	 */
	protected function formatText($text, $variables = array()) {
		$text = WCF::getLanguage()->getDynamicVariable($text, $variables);
		
		if (StringUtil::isUTF8($text)) {
			$text = StringUtil::convertEncoding('UTF-8', 'ISO-8859-1', $text);
		}
		
		return $text;
	}
	
	/**
	 * Returns a correct name for downloading the pdf.
	 * If '$name' is given, the value will be checked for '.pdf'-ending.
	 * Otherwise a random name will be set used as name.
	 * @param	string		$name
	 * @return	string
	 */
	protected function getDownloadName($name = '') {
		if (empty($name)) {
			$name = mb_substr(StringUtil::getRandomID(), 0, 8).'.pdf';
		}
		else if (!StringUtil::endsWith($name, '.pdf')) {
			$name .= '.pdf';
		}
		
		return $name;
	}
	
	/**
	 * Returns the source code of the pdf document.
	 * @return	string
	 */
	public function getSourceCode() {
		return $this->pdf->Output('', 'S');
	}
	
	/**
	 * Saves the pdf on hdd with given path.
	 * @param	string		$path
	 */
	public function saveOnDisk($path) {
		$file = new File($path, 'w');
		$file->write($this->getSourceCode());
		$file->close();
	}
	
	/**
	 * Sets the font used for writing the next text.
	 * @param	string		$fontFamily
	 * @param	integer		$size
	 * @param	boolean		$bold
	 * @param	boolean		$italic
	 * @param	boolean		$underline
	 */
	public function setFont($fontFamily, $size = 8, $bold = false, $italic = false, $underline = false) {
		$fontFamily = mb_strtolower($fontFamily);
		if (!in_array($fontFamily, static::$coreFontFamilies)) {
			$fontFamily = 'arial';
		}
		
		$styleString = '';
		if ($bold) $styleString .= 'B';
		if ($italic) $styleString .= 'I';
		if ($underline) $styleString .= 'U';
		
		$this->pdf->setFont($fontFamily, $styleString, $size);
	}
	
	/**
	 * Shows the 'Save as'-dialog and forces the download.
	 * @param	string		$name
	 */
	public function showDownloadDialog($name = '') {
		$this->pdf->Output($this->getDownloadName($name), 'D');
	}
	
	/**
	 * Shows the pdf within the browser, if pdf-plugin is available.
	 * If pdf-plugin is not available the viewer will be forced to download
	 * the pdf within the given name.
	 * @param	string		$name
	 */
	public function showInBrowser($name = '') {
		$this->pdf->Output($this->getDownloadName($name), 'I');
	}
}
