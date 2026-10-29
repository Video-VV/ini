<?php
/*
┌────────────────────────────────────────────────────────────────┐
│ Projectname:   PHP INI Class                                   │
│ Version:       1.3.2 (PHP 8.4 ready)                           │
│ Author:        Radovan Janjic <rade@it-radionica.com>          │
│ Update:        Vlasenko Vitaly                                 │
│ Last modified: 2026                                            │
│ Copyright (C): 2020 IT-radionica.com, All Rights Reserved      │
│                                                                │
├─┤ Description ├────────────────────────────────────────────────┤
│                                                                │
│ Simple PHP Class to manage INI files (read and write).         │
│ Adapted for PHP 8.4 with PSR-4 namespace and exceptions.       │
│                                                                │
└────────────────────────────────────────────────────────────────┘
*/

namespace videovv\Ini;

if(!defined('LC_ENCOD')) define('LC_ENCOD','UTF-8'); 

class INI
{
	/** INI file path
	 * @public String
	 */
	public $file = NULL;
	
	/** INI data
	 * @public Array
	 */
	public $data = array();
	
	/** Process sections
	 * @public Boolean
	 */
	public $sections = TRUE;
	
  /** INI top data (no section)
	 * @public Array['@']
	 */
  public $topData = array( '@'=>array() );

  /** INI comments 
	 * @public Array
	 */
  public $comments = array();

  /** Временное хранилище данных при чтении (используется в read)
	 * @public Array
	 */
  public $check = array();

  /** INI constant
	 * @const BEFORE
   * @const BEHIND
   * @const AFTER 
	 */
  const BEFORE = 'before';
  const BEHIND = 'behind';
  const AFTER = 'after';
  const _TOP = '@';
  const _LINE = 'line';
  const _VAR = 'var';
  const _SEP = ';';
  const _SEP_BASE64 = 'Ow==';
  
	/** Parse INI file
	 * @param 	String		$file 		- INI file path
	 * @param 	Boolean		$sections 	- Process sections
	 */
  public function __construct() {
		if (func_num_args()) {
			$args = func_get_args();
			call_user_func_array(array($this, 'read'), $args);
		}
	}
  /** Check variable in INI file on JSON encoding
	 * @param 	String		$string 		- value in INI variable
	 */
	private function isJSON($string = NULL) {
    $string = ($string) ? $string : '';
    try {  
      json_decode( $string, true, 512, JSON_THROW_ON_ERROR | JSON_HEX_TAG | 
                                       JSON_UNESCAPED_SLASHES |  JSON_UNESCAPED_UNICODE |
                                       JSON_HEX_QUOT | JSON_HEX_AMP );
     }  
      catch (\JsonException $exception) {  
       return false;
     }
     return true;
   }
  /** Save array in INI file on JSON encoding
	 * @param 	Array		$array 		- value in INI variable
	 */
   private function toJSON( $array = [] ) {
     try {  
       return json_encode( $array, JSON_THROW_ON_ERROR | JSON_HEX_TAG | 
                                   JSON_UNESCAPED_SLASHES |
                                   JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE |
                                   JSON_PRESERVE_ZERO_FRACTION );
     }  
      catch (\JsonException $exception) {  
       return 'Error JSON ('.$exception->getCode().') encoding: '.$exception->getMessage();
     }
   }

  /** Экранирование спецсимволов в строковом значении INI
   * @param  String  $value
   * @return String
   */
  private function escape_value( $value ) {
    return str_replace(
      array( '\\', '"' ),
      array( '\\\\', '\\"' ),
      (string)$value
    );
  }

  /** Разэкранирование спецсимволов в строковом значении INI
   * @param  String  $value
   * @return String
   */
  private function unescape_value( $value ) {
    return str_replace(
      array( '\\"', '\\\\' ),
      array( '"',   '\\' ),
      (string)$value
    );
  }
  
  /** Detect type variable 
	 * @param 	Mixed		$val 		- value in INI variable
	 */
   private function setValue( $var = NULL, $val = '') {
     $content = '';
     if( $var ) {
   		if (is_array( $val ) || is_object( $val ) ) {
          $content = $var.' = ' . $this->toJSON( $this->replace_com_data( $val ) ) . PHP_EOL;
 			} elseif ( is_string($val)) {
        if( empty( $val )) {
 				  $content = $var . ' = ' . PHP_EOL;
 			  } else {
          $content = $var . ' = "' . $this->escape_value( $this->replace_com_data( $val ) ) . '"' . PHP_EOL;
          }
        } elseif ( is_int( $val ) || is_float( $val )) {
           $content = $var . ' = ' . (string)$val . PHP_EOL;
        } elseif ( is_bool( $val )) {
           $content = $var . ' = ' . ($val ? 'true' : 'false' ) . PHP_EOL;
        } elseif ( is_null( $val )) {
           $content = $var . ' = ' . PHP_EOL;
        } else {
           $content = $var . ' = "' . $this->escape_value( $this->replace_com_data( $val ) ) . '"' . PHP_EOL;
 		   }
     }
     return $content;
   }

  /** Parse INI file (comments)
	 * @param 	Array		$aLine 	- content INI file
	 */
	 private function selectComm( $aLine = NULL ) {
    $aComment = array( self::_TOP => array() ); $afterVariable = ''; $section = '';
    for( $l=0; $l<count($aLine); $l++ ) { 
      $line= trim( $aLine[ $l ] );
      if( mb_substr( $line, 0, 1, LC_ENCOD ) == self::_SEP ) { 
        $section = ( isset( $section ) ? $section : '' );
        if( $section==$afterVariable ) $afterVariable = '';
        if( isset( $aComment[( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ] ) )  {
          $aComment[( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ][] = array( self::AFTER=> $line.PHP_EOL );
        } else {
          $aComment[( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ][] = array( self::AFTER=> $line.PHP_EOL );
        }
        continue;
      }
      $line = trim( preg_replace('/(?|"(?:[^"\\\\]|\\\\.)*"|"([\s\S]*?)")/u', '0', $line ) );
      
      if( empty( $line ) ) {
        if( isset( $aComment[( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ] ) )  {
          $aComment[( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ][] = array( self::AFTER=> PHP_EOL );
        } else {
          $aComment[( empty($afterVariable) ? self::_TOP : $section.$afterVariable) ][] = array( self::AFTER=> PHP_EOL );
        }
        continue;
      } elseif( mb_substr( $line, 0, 1, LC_ENCOD ) == '[' ) {
        preg_match( '/(?|(?:\[)([^\r\n]*)\]|\]([\s\S]*?)\])/u', $line, $matches_dep );
        if( isset( $matches_dep[0] ) ) {
          $afterVariable = trim( $matches_dep[0] );
          $section = $afterVariable;
          preg_match_all('/((?=;(.*))(.*)) | (^\"([^ ]);(.*)^\")/u', $line, $matches_com, PREG_SET_ORDER);
          foreach ($matches_com as $val) {
            if( isset( $val[0] ) ) {
              $com = trim( $val[0] ); if( mb_substr( $com, 0, 1, LC_ENCOD ) != self::_SEP ) { $com = self::_SEP.' '.$com; }
              $aComment[ ( empty($afterVariable) ? self::_TOP : $afterVariable) ][] = array( self::BEHIND=> $com.PHP_EOL );
              break;
            }
          }
        }
      } else {
        preg_match( '/^(.*?)=/u', $line, $matches_var );
        if( isset( $matches_var[1] ) && !empty( $matches_var[1] ) ) {
          $afterVariable = trim( $matches_var[1] );
        }
        preg_match_all('/(;(?:[^"\r\n]|"(?:[^"\\\\]|\\\\.)*")*)/u', $line, $matches_com, PREG_SET_ORDER);
        foreach ($matches_com as $val) {
          if( isset( $val[0] ) ) {
            $com = trim( $val[0] ); 
            if( mb_substr( $com, 0, 1, LC_ENCOD ) != self::_SEP ) { $com = self::_SEP.' '.$com; }
            $aComment[ ( empty($section.$afterVariable) ? self::_TOP : $section.$afterVariable) ][] = array( self::BEHIND=> $com.PHP_EOL );
            break;
          }
        }
      }
    }
    return $aComment;
   }

	/** Parse INI file
	 * @param 	String		$file 		- INI file path
	 * @param 	Boolean		$sections 	- Process sections
	 */
   function read($file = NULL, $sections = TRUE) {
     if( empty( $file ) ) { throw new \Exception('INI file path is not specified.'); }
     $this->file = ($file) ? $file : $this->file;
     if( !file_exists( $this->file )) { throw new \Exception('INI file not found: ' . $this->file); } 		 
     if( is_dir( $this->file ) ) { throw new \Exception('The specified path is a directory, not a file: ' . $this->file); }
     if( !is_readable( $this->file ) ) { throw new \Exception('Permission denied. Cannot read file: ' . $this->file); }

     $this->sections = $sections;
     $this->data = parse_ini_file( realpath($this->file), $this->sections, INI_SCANNER_RAW );
     $this->comments = $this->selectComm( explode( PHP_EOL, file_get_contents( realpath($this->file) ) ) );
     
     $rawLines = explode( PHP_EOL, file_get_contents( realpath($this->file) ) );
     $rawValues = array();
     $currentSection = self::_TOP;
     
     foreach ($rawLines as $line) {
         $line = trim($line);
         if (empty($line) || $line[0] === ';') continue;
         
         if ($line[0] === '[') {
             if (preg_match('/^\[(.*?)\]/', $line, $m)) {
                 $currentSection = trim($m[1]);
             }
         } elseif (preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
             $key = trim($m[1]);
             $rawVal = trim($m[2]);
             
             if (strpos($rawVal, ';') !== false) {
                 if (preg_match('/^"(?:[^"\\\\]|\\\\.)*"/u', $rawVal, $matchQuote)) {
                     $rawVal = $matchQuote[0];
                 } else {
                     $pos = strpos($rawVal, ';');
                     $rawVal = trim(substr($rawVal, 0, $pos));
                 }
             }
             
             if (strlen($rawVal) >= 2 && $rawVal[0] === '"' && substr($rawVal, -1) === '"') {
                 $rawVal = substr($rawVal, 1, -1);
             }
             
             $rawValues[$currentSection][$key] = $rawVal;
         }
     }

     $this->check = $this->data;

     foreach( $this->check as $section_var => $section_val ) {
       if( is_array( $section_val ) ) {
         foreach( $section_val as $var => $val) {
           if (isset($rawValues[$section_var][$var])) {
               $val = $rawValues[$section_var][$var];
           }

           if( $this->isJSON( $val ) ) {
             try {  
               $this->data[$section_var][$var] = json_decode( str_replace( self::_SEP_BASE64, self::_SEP, $val ), 
                                                               true, 512, JSON_THROW_ON_ERROR |
                                                               JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP );
             } catch (\JsonException $exception) {  
               $this->data[$section_var][$var] = 'Error JSON ('.$exception->getCode().') encoding: '.$exception->getMessage();
             }
           } else {
             $this->data[$section_var][$var] = $this->unescape_value( str_replace( self::_SEP_BASE64, self::_SEP, $val ) );
           }
         }
       } else {
         if (isset($rawValues[self::_TOP][$section_var])) {
             $section_val = $rawValues[self::_TOP][$section_var];
         }
         
         if( $this->isJSON( $section_val ) ) {
             try {  
               $this->topData[self::_TOP][$section_var] = json_decode( $section_val, true, 512, 
                                                                        JSON_THROW_ON_ERROR | JSON_HEX_TAG | 
                                                                        JSON_HEX_QUOT | JSON_HEX_AMP );      
             } catch (\JsonException $exception) {  
               $this->topData[self::_TOP][$section_var] = 'Error JSON ('.$exception->getCode().') encoding: '.$exception->getMessage();
             }
         } else {
           $this->topData[self::_TOP][$section_var] = $this->unescape_value( $section_val );
         }
         unset( $this->data[$section_var] );
       }
     }
     if( count( $this->topData[self::_TOP] ) > 0 ) {
       $this->data = array_merge( $this->topData, $this->data );
     }
     return $this->data;
 	 }

  /** before insert comments in INI file
	 */
   private function countCom( $aComment = NULL, $process = NULL) {
    $aComment = ( $aComment ) ? $aComment : array();
    $process = ( $process ) ? $process : self::BEHIND;
    $count = 0;
    foreach( $aComment as $сommKey ) {
      if( isset( $сommKey[ $process ] ) ) { 
        $count++;
      }
    }
    return $count;
  }

  /** addComment in INI file
	 */
   function addComment( $comment = '', $var = NULL, $insert  = NULL ) {
     static $oldvarcom = '';
     $this->comments = $this->comments;
     $this->data = $this->data;
     $varkey = ($var) ? explode(':', $var) : array( self::_TOP, '');
     $insert = ($insert) ? $insert : self::BEHIND;
     if( !isset( $varkey[1] ) ) { $varkey[1] = $varkey[0]; $varkey[0] = self::_TOP; }

     if( !isset( $this->comments[self::_TOP] ) ) { $this->comments[self::_TOP] = array(); }

     if( empty( $comment ) && $varkey[0] === self::_TOP ) {
       $this->comments[self::_TOP][] = array( self::AFTER=> PHP_EOL );
       return TRUE;  
     } else {
       if( $varkey[0] === self::_TOP) {
         $this->comments[self::_TOP][] = array( self::AFTER => (empty( $comment ) ? '' : self::_SEP.' '.$comment).PHP_EOL);
         return TRUE;  
       } else {
         if( isset( $this->data[ $varkey[0] ][ $varkey[1] ] ) ) { 
           $varcom = '['.trim( $varkey[0] ).']'.trim( $varkey[1] );
           if( $insert == self::BEHIND ) {
             $onebehind = !( isset( $this->comments[$varcom] ) && is_array( $this->comments[$varcom] ) &&
                             $this->countCom( $this->comments[$varcom] ) > 0 );
           }
           $this->comments[$varcom][] = array( $insert => (empty( $comment ) 
                                                           ? '' 
                                                           : ( $insert != self::BEHIND 
                                                               ? self::_SEP.' ' 
                                                               : ($onebehind ? self::_SEP.' ': '' ) ).$comment).PHP_EOL
                                             );
           return TRUE;  
         }
       }
     }
     return FALSE;  
   }

  /** delComment in INI file
	 */
   function delComment( $var = NULL, $insert  = NULL ) {
     $this->comments = $this->comments;
     if( $var ) { 
       $varkey = explode(':', $var);
       if( !isset( $varkey[1] ) ) { $varkey[1] = trim( $varkey[0] ); $varkey[0] = self::_TOP; }
       
       if( $varkey[0] === self::_TOP) {
         if( empty( $varkey[1] ) ) {
           $varcom = self::_TOP;
         } else {
           $varcom = trim( $varkey[1] );
         }
       } else {
         $varcom = '['.trim( $varkey[0] ).']'.trim( $varkey[1] );
       }
       if( isset( $this->comments[ $varcom ] ) ) {
         if( $insert ) {
           unset( $this->comments[ $varcom ] ); 
           return TRUE;  
         } else {
           $nCnt = count( $this->comments[ $varcom ] );
           for($c=0; $c<$nCnt; $c++) {
             if( isset( $this->comments[ $varcom ][$c][ $insert ] ) ) {
               unset( $this->comments[ $varcom ][$c] );
             }
           }
         }
       }
     } else {
       $this->comments = array();
       return TRUE;  
     }
     return FALSE;  
   }

  /** before insert comments in INI file
	 */
   private function before( $comments, $content = '') {
    foreach( $comments as $сommKey ) {
      if( isset( $сommKey[ self::BEFORE ] ) ) { 
        $content.= $сommKey[ self::BEFORE ]; 
      }
    }
    return $content;
  }

  /** behind insert comments in INI file
	 */
   private function behind( $comments, $content = '') {
    foreach( $comments as $сommKey ) {
      if( isset( $сommKey[ self::BEHIND ] ) ) { 
        $content = ( mb_substr( $content,-1, 1, LC_ENCOD ) == ']' 
                     ? $content 
                     : mb_substr( $content, 0, -1, LC_ENCOD ) 
                   ).' '.$сommKey[ self::BEHIND ];
      }
    }
    return $content;
  }

  /** after insert comments in INI file
	 */
   private function after( $comments, $content = '') {
    foreach( $comments as $сommKey ) {
      if( isset( $сommKey[ self::AFTER ] ) ) { 
        $content.= $сommKey[ self::AFTER ]; 
      }
    }
    return $content;
  }

  /** Check the variable in the INI file for the content of the character ;
	 */
  private function replace_com_data( $json_array, $search = self::_SEP, $replace = self::_SEP_BASE64 ) {
    if( is_array( $json_array ) ) {
      foreach ($json_array as $key => $value) {
          if (is_array($value)) {
              $json_array[$key] = $this->replace_com_data($value, $search, $replace );
          } elseif (is_string( $value )) {
              $json_array[$key] = str_replace( $search, $replace, $json_array[$key] );
          }
      }
      return $json_array;
    } else {
      return str_replace( $search, $replace, $json_array);
    }
  }

	/** Write INI file
	 */
	function write($file = NULL, $data = array(), $sections = TRUE, $comments = array() ) {
		$this->data = (!empty($data)) ? $data : $this->data;
		$this->file = ($file) ? $file : $this->file;
		$this->sections = $sections;
    $this->comments = (!empty($comments)) ? $comments : $this->comments;
		$content = NULL;

		if ($this->sections) {
			foreach ($this->data as $section => $data) {
  			$line = ( $section!=self::_TOP ? '[' . $section . ']' : '' );
        $content.= $line; $keyCom = ($section!=self::_TOP ? $line : self::_TOP );
        if( isset( $this->comments[ $keyCom ] ) ) {
          $content = $this->after( $this->comments[ $keyCom ], $this->behind( $this->comments[ $keyCom ], $content ) );
        } else {
          $content.= PHP_EOL;
        }
  			foreach ($data as $key => $val) {
          $keyCom = $line.$key;
          if( isset( $this->comments[ $keyCom ] ) ) $content = $this->before( $this->comments[ $keyCom ], $content );
          $content .= $this->setValue( $key, $val ); 
          if( isset( $this->comments[ $keyCom ] ) ) { 
            $content = $this->after( $this->comments[ $keyCom ], $this->behind( $this->comments[ $keyCom ], $content ) );
          }
  			}
			}
		} else {
			foreach ($this->data as $key => $val) {
        $keyCom = $line.$key;
        if(isset( $this->comments[ $keyCom ] )) $content = $this->before( $this->comments[ $keyCom ], $content );
        $content .= $this->setValue( $key, $val ); 
        if( isset( $this->comments[ $keyCom ] ) ) { 
          $content = $this->after( $this->comments[ $keyCom ], $this->behind( $this->comments[ $keyCom ], $content ) );
        } else {
          $content.= PHP_EOL;
        }
			}
		}
    if (!$handle = @fopen($this->file, 'w')) {
        throw new \Exception('Cannot open file for writing: ' . $this->file);
    }
    if (fwrite($handle, trim($content)) === false) {
        throw new \Exception('Cannot write data to file: ' . $this->file);
    }
    fclose($handle);
    return true;
	}
}