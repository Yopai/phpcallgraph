<?php
/**
 * Implementation of a call graph generation strategy wich renders output as a UML Graph Sequence Diagram input file
 *
 * PHP version 5
 *
 * This file is part of PHPCallGraph.
 *
 * PHPCallGraph is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHPCallGraph is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    PHPCallGraph
 * @author     Martin Cleaver <mrjc at users dot sourceforge dot net>
 * @copyright  2009 Martin Cleaver
 * @license    http://www.gnu.org/licenses/gpl.txt GNU General Public License
 */

// reduce warnings because of PEAR dependency
error_reporting(E_ALL ^ E_NOTICE);

require_once 'CallgraphDriver.php';

/**
 * implementation of a call graph generation strategy which renders output as a UML Graph Sequence Diagram input file
 * a later evolution may output as the graphic, for now we do the input file.
 *
 * A good variant for someone to build would be an XMI sequence diagram.
 *
 * TODO: add routines specific for messsage types, so instead of this:
 *        $this->addToMessageSequences('message(Incoming,'.$obj.',"'.$method.'");');
 *        $this->addToMessageSequencesMessage('Incoming',$obj,$method);
 */
class UmlGraphSequenceDiagramDriver implements CallgraphDriver {

    /** 
      objects already seen
    */
    protected $objects = array();
    /**
    sequence number of the last message
    */
    protected $sequenceNumber = 0;
    protected $useColor = true;
    protected $graphInput = '';
    protected $graph;
    protected $currentCaller = '';
    protected $internalFunctions;

    /**
	if true, then add a text box onto the page with the code from the current function in it.
    */
    protected $showMethodCodeInDiagram = false;

    protected $saveMethodCodeIntoStandaloneFile = true;

    protected $graphInit;
    protected $graphDefinitions;
    protected $graphSequence;
    protected $graphClosedown;

    /**
	Output format
    */
    protected $outputFormat = 'png';

    /**
     * @return CallgraphDriver
     */
    public function __construct($outputFormat = 'txt', $sequenceLibrary = '/usr/local/lib/sequence.pic') {
    	print "\n\n\n\n===================================\n";
        $this->setSequenceLibrary($sequenceLibrary);
        $this->setOutputFormat($outputFormat);
        $functions = get_defined_functions();
        $this->internalFunctions = $functions['internal'];
    }

    public function __destruct() {
    	  $this->closeObjects();

    	   print "===========Finishing: \n\n";
//	   print $this->graphInput;
    }

    /**
     * @return void
     */
    public function reset() {
        $this->initializeNewGraph();
    }

    /**
     * @return void
     */
    protected function initializeNewGraph() {
     $this->graphInit = '';
     $this->graphDefinitions = '';
     $this->graphSequence = '';
     $this->graphClosedown = '';

     $sequenceNumber = 1;
    	      $this->addToInit( ".PS\n" .
			     'copy "/usr/local/lib/sequence.pic";');
	$this->addToInit("
# Measured in virtual inches
#Variable Name  Default Value   Operation
boxht    =0.3; #       Object box height
boxwid   =1.25; #       Object box width
awid     =0.25; # Active lifeline width
spacing  =0.25;  #Spacing between messages
movewid  =0.75; #Spacing between objects
dashwid  =0.05; #Interval for dashed lines
maxpswid =18;   #Maximum width of picture
maxpsht  =18;   #Maximum height of picture
	 ");

    }

    /**
     * Sets path to sequence library
     * @param string $sequenceLibrary  Path to sequence library, either relative or absolute
     * @return void
     */
    public function setSequenceLibrary($sequenceLibrary = 'sequence.pic') {
        $this->sequenceLibrary = $sequenceLibrary;
    }

    /**
     * Sets output format
     * @param string $outputFormat 'txt' for the raw chart, or one of the output formats supported by UMLGraph
     * @return void
     */
    public function setOutputFormat($outputFormat = 'txt') {
    	if ($outputFormat != 'txt') {
	   print "WARNING: $outputFormat not yet supported\n";
	}
//        $this->outputFormat = $outputFormat;

	print "WARNING: outputFormat setting ignored (needs reconciling with driver)";
    }

    /**
     * Enables or disables the use of color
     * @param boolean $boolean True if color should be used
     * @return void
     */
    public function setUseColor($boolean = true) {
        $this->useColor = $boolean;
    }


    /**
	Gets the source code for the current member being examined.
	Presently implemented through a hack global var.
	@global $memberCode Source code wanted
    */
    protected function getSourceCodeForClassMethod() {
    	    // Need to know the details about how to pick up the code - will we be passed them
	    // or can we pick up via an interface on the parent CallgraphDriver?
    	    
            // obtain source code
            //$memberCode = implode('', array_slice(file($file), $offset, $length));

	    global $memberCode;
	    return $memberCode;
    }

    /**
     * @param integer $line
     * @param string $file
     * @param string $name
     * @return void
     */
    public function startFunction($line, $file, $name) {
        $this->currentCaller = $name;
        $this->initializeNewGraph();

        print "startFunction:  $file:$line = $name\n";
	$this->commentToGraph("startFunction:  $file:$line = $name");
	$classAndMethod = $this->getClassAndMethod($name);
        $class = $classAndMethod['class'];
        $method = $classAndMethod['method'];

	$obj = $this->objForClass($class);	
	$this->registerObjectIfNew($obj);

	$this->addToInit('pobject(Incoming,"External Messages")');
	$this->addToMessageSequences('message(Incoming,'.$obj.',"'.$method.'");');
    }

    protected function objForCaller ($caller) {
       $caller = $this->removeAnyParameters($caller);
       $caller = $this->removeAnyMethod($caller);
       return $this->objForClass($caller);
    }

    protected function removeAnyParameters ($string) {
      $paramsStart = strpos($string, '(');
      if ($paramsStart) {
      	 $string = substr($string, 0, $paramsStart);
      }
      return $string;
    }

    protected function removeAnyMethod($string) {
//    print "removeAnyMETHOD $string\n";
      $methodStart = strpos($string, '::');
      if ($methodStart) {
         $string = substr($string, 0, $methodStart);
      }
//      print "ANS=$string\n";
      return $string;
    }


    protected function classForObj ($object) {
    
    	if (strpos($object,'Obj') === false) {
	   die("Object nameshould have contained Obj (was $object)\n");
	} else {
	   return substr($object, strlen('Obj'));
	}
    }

    protected function objForClass ($class) {
       if ($class == '') {
         die ("Class should not be empty! '$class'");
       }


    	$class = $this->removeAnyMethod($class);
     	$class = $this->removeAnyParameters($class);
	$obj = $class;

	// This is a hack, sadly, to eliminate stray 'Obj' prefixes.
    	if (strpos($class,'Obj') === false) {
	   print "Adding Obj to $class\n";
	   $obj = 'Obj'.$class;
	} else {
	   exit("$obj already contained Obj\n");
	}

    	return $obj;
    } 


    /**
     * @param integer $line
     * @param string $file
     * @param string $name
     * @return void
     */
    public function addCall($line, $file, $name) {
	$caller = $this->currentCaller;
	$classAndMethod = $this->getClassAndMethod($name);
	$destClass = $classAndMethod['class'];
	$method = $classAndMethod['method'];

        $this->commentToGraph("addCall from $caller:$line to $file = $destClass, $method\n"); # TODO: comment


//	$method = $this->removeAnyParameters($method);

	$fromObj = $this->objForCaller($caller);
	$destObj = $this->objForClass($destClass);


//        if ($destClass != 'ClassUnknown') {
		print "                   from caller=$caller:line to $file;\n";
		print "                    class=$destClass; method=$method obj=$destObj\n";
		print "$fromObj->$destObj\n";

		$this->registerObjectIfNew($fromObj);
		$this->registerObjectIfNew($destObj);
#		$this->addToMessageSequences('create_message('.$fromObj.','.$destObj.','.$name.');'); 
#		$this->addToMessageSequences('message('.$destObj.','.$fromObj.',"'.$this->sequenceNumber." ".$method.'");'); // can use $name instead of $method
		$this->addToMessageSequences('message('.$fromObj.','.$destObj.',"'.$this->sequenceNumber." ".$method.'");'); // can use $name instead of $method
		$this->addToMessageSequences('step();');
		$this->sequenceNumber++;
//        } else {
//		print "                   SKIPPED caller=$caller; class=$destClass; method=$method obj=$destObj\n";
//	}


    }

    /**
     * @return void
     */
    public function endFunction() {
	$this->commentToGraph("endFunction");
//    	$this->addToMessageSequences("return_message();");
	$this->addToMessageSequences("\n");
	$this->addToMessageSequences("\n");

	$this->closeObjects();

	$this->addToMessageSequences("step();");
	$this->addToMessageSequences("step();");
	$this->addToMessageSequences("step();");

	$obj = $this->objForCaller($this->currentCaller); 
        $this->addToObjectDefinitions('pobject(Filler1);');	

	$code = $this->getCodeForCurrentClassAndMethod();

	$filename = $this->filenameForFunctionSequenceGraph($this->currentCaller);

	if ($this->showMethodCodeInDiagram) {
	   $this->addCommentWithCodeIntoDiagram($code);
	}

	/* Save the UML Sequence Diagram */
	file_put_contents(
		$filename,
		 $this->__toString()
		 );

	if ($this->saveMethodCodeIntoStandaloneFile) {
	   $formattedCode=htmlspecialchars($code);
		file_put_contents(
		$filename.'.html',
		 $formattedCode
		 );
	}


	$this->convertSequenceGraphFileToSequenceGraph($filename);

    }


    protected function addCommentWithCodeIntoDiagram($codeForFunction) {
	
	$commentAndLineCount = $this->reformatForComment($codeForFunction);
	$boxHeight = $commentAndLineCount['lineCount'] / 6 + 0.25;
	
// theoretically ljust would left justify rather than center. Oh well.
// also could use a native box rather than a comment with it's folded comment.
	$this->addToMessageSequences('comment('.$obj.',C, right down, wid 3 height '.$boxHeight.' '.$commentAndLineCount['code'].' );');
    }


   protected function getCodeForCurrentClassAndMethod() {

        $classAndMethod = $this->getClassAndMethod($this->currentCaller);
	$class = $classAndMethod['class'];
        $method = $classAndMethod['method'];
    	print "CODE FOR $class $method";

	if ($class != 'ClassUnknown') { // SMELL - why would we get this?
	  $method = $this->removeAnyParameters($method);
	  $codeForFunction = $this->getSourceCodeForClassMethod();
	} else {
	  $codeForFunction = 'ClassUnknown';
	}	
	return $codeForFunction;
    }


    /**
	plot4pic wants double quotes around each line, retardedly
	Do any escaping needed
    */

    protected function reformatForComment($input) {
        $input = explode("\n", $input);
	$output = '';
//	$output = '"';
	foreach ($input as $line_num => $line) {
	// Escape all double quotes, as pic2plot interprets them as start of escape sequence.
            $line = str_replace('"','\"', $line); 
	    $output .= '"'. "#{$line_num}: " . $line . '"';
//	    $output .= "#{$line_num}: " . $line . '\CR';
	}
//	$output .= '"';
	print "$line_num lines\n";
	return array(code=>$output, lineCount=>$line_num);
   }


    protected $pic2plot = 'pic2plot';


    protected function convertSequenceGraphFileToSequenceGraph($filename) {
        $outfile = $filename.'.'.$this->outputFormat;
    	$cmd = implode(' ',array($this->pic2plot,$filename,'-T'.$this->outputFormat,'>',$outfile));
	print "CMD:". $cmd. "\n";
	exec($cmd);
    }

    protected function filenameForFunctionSequenceGraph($function) {
    	 $filename = $function;
	 $filename = str_replace('::', '..', $filename);
	 $filename = $this->removeAnyParameters($filename);
	 $filename = $filename . '.umlgraphSeq';
    	 print "SAVING FOR $function as $filename:\n";
	 return 'output/'.$filename;
    }

    /** 
    * Close the objects currently held open
    */
    protected function closeObjects() {
    	foreach ($this->objects as $object => $dummy) {
	    print "Closing $object\n";
	    $this->addToClosedown("inactive(".$object.");");
	    $this->addToClosedown("complete(".$object.");");
	}
	$this->objects = array();
    } 

    protected function getClassAndMethod($name) {
        $nameParts = explode('::', $name);
        $class = 'ClassUnknown'; // SMELL was 'default'
        $method = $name;
        if (count($nameParts) == 2) { // method call
            if (empty($nameParts[0])) {
                $class = 'ClassUnknown';
            } else {
                $class = $nameParts[0];
            }
            // obtain method name
            $method = $nameParts[1];
        }
	return array('class' => $class, 'method' => $method);
    }

    protected function registerObjectIfNew($object) {
        
//        if ($object == 'ObjClassUnknown') {
//	   return;
//	}
    	print "REGISTERING $object\n";
    	if (! $this->objects[$object]) {
	   $this->objects[$object] = 1;

	   $this->_registerObject($object);
	}
    }   

    protected function _registerObject ($object) {
    
	if (	
	   ($object == '') || (strpos($object, 'Obj') === false)
	   ) {
	   print "Trying to register non-object :".$object;
	   exit ("DIE");
	}
	$class = $this->classForObj($object);
	$this->addToObjectDefinitions('object('
                   . $object.''
                   . ','
                   . '":'.$class.'"'
                   . ');'
		 );
        $this->addToObjectDefinitions('step();');
	$this->addToObjectDefinitions('active('.$object.');');
        $this->addToObjectDefinitions('step();');

        $this->addToObjectDefinitions('pobject(FillerO);');
        return true;
    }

    protected function debug($section, $string) {
        print "|$section | ".$string."\n";
    }

    protected function addToInit($string) {
        $this->graphInit .= $string."\n";
	$this->debug('init', $string );
    }
    

    protected function addToObjectDefinitions($string) {
        $this->graphDefinitions .= $string."\n";
	$this->debug('def', $string );
    }
   

    protected function addToMessageSequences($string) {
        $this->graphSequence .= $string."\n";
	$this->debug('seq', $string );
    }
    

    protected function addToClosedown($string) {
        $this->graphClosedown .= $string."\n";
	$this->debug('closedown', $string );
    }
    

    protected function commentToGraph($string) {
        $this->addToMessageSequences('# '.$string);
    }   

    /**
     * @return string
     */
    public function __toString() {
	$this->addToClosedown("\n.PE"); // SMELL: move

        return $this->graphInit . 
	       $this->graphDefinitions .
	       $this->graphSequence .
	       $this->graphClosedown;
    }

}
?>
